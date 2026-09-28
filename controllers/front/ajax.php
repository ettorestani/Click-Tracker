<?php
/**
 * Click Tracker Module - AJAX Controller
 *
 * Handles incoming click tracking data from frontend
 *
 * @author    Ettore Stani
 * @copyright 2024 Ettore Stani
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class ClickTrackerAjaxModuleFrontController extends ModuleFrontController
{
    /** @var bool Serve the endpoint over HTTPS when the shop uses SSL */
    public $ssl = true;

    /** @var bool AJAX controller */
    public $ajax = true;

    /** @var ClickTracker */
    public $module;

    /** @var array|null Cached shop hosts */
    protected $shopHosts = null;

    /** @var int Rate limit: maximum requests (clicks + product views) per IP and time window */
    const RATE_LIMIT_MAX = 60;

    /** @var int Rate limit: time window in seconds */
    const RATE_LIMIT_WINDOW = 60;

    /**
     * Initialize controller
     */
    public function init()
    {
        // Disable error display - we want JSON responses only
        @ini_set('display_errors', 'off');

        parent::init();
    }

    /**
     * Process AJAX request
     */
    public function postProcess()
    {
        // Set JSON header early
        header('Content-Type: application/json; charset=utf-8');

        try {
            // Only allow POST requests
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->ajaxResponse(false, 'Invalid request method');
                return;
            }

            // Requests must come from a page of this shop
            if (!$this->isAllowedOrigin()) {
                $this->ajaxResponse(false, 'Invalid origin');
                return;
            }

            // Check rate limiting
            if (!$this->checkRateLimit()) {
                $this->ajaxResponse(false, 'Rate limit exceeded');
                return;
            }

            require_once _PS_MODULE_DIR_ . 'clicktracker/classes/ClickTrackerLog.php';
            require_once _PS_MODULE_DIR_ . 'clicktracker/classes/ClickTrackerSource.php';

            // Get and validate token
            $token = Tools::getValue('token');
            if (empty($token) || !$this->validateToken($token)) {
                $this->ajaxResponse(false, 'Invalid token');
                return;
            }

            if (Tools::getValue('action') === 'view') {
                $this->processProductView();
                return;
            }

            // Get POST data
            $context = Tools::getValue('context');
            $pageType = Tools::getValue('page_type');
            $clickedClass = (string) Tools::getValue('clicked_class');
            $elementType = Tools::getValue('element_type');
            $pageUrl = trim((string) Tools::getValue('page_url'));

            // Validate required fields
            if (empty($context) || $clickedClass === '' || empty($elementType) || $pageUrl === '') {
                $this->ajaxResponse(false, 'Missing required fields');
                return;
            }

            // Only selectors currently configured in the back office are accepted
            $selectorConfig = $this->module->getSelectorConfig();
            if (!isset($selectorConfig[$clickedClass])) {
                $this->ajaxResponse(false, 'Unknown selector');
                return;
            }

            // Validate context type
            if (!ClickTrackerLog::isValidContextType($context)) {
                $context = ClickTrackerLog::CONTEXT_OTHER;
            }

            // Element type forced in the configuration wins over client-side detection
            if (!empty($selectorConfig[$clickedClass]['type'])) {
                $elementType = $selectorConfig[$clickedClass]['type'];
            } elseif (!ClickTrackerLog::isValidElementType($elementType)) {
                $elementType = ClickTrackerLog::ELEMENT_OTHER;
            }

            // Validate URL: absolute http(s) URL on one of the shop domains
            if (Tools::strlen($pageUrl) > 2000 || !$this->isShopUrl($pageUrl)) {
                $this->ajaxResponse(false, 'Invalid URL');
                return;
            }

            $log = array(
                'id_shop' => (int) $this->context->shop->id,
                'context_type' => $context,
                'page_type' => preg_match('/^[a-zA-Z0-9_\-]{1,64}$/', (string) $pageType) ? $pageType : null,
                'clicked_class' => $this->cleanInputString($clickedClass, 255),
                'element_type' => $elementType,
                'page_url' => $this->truncate($pageUrl, 500),
                'page_path' => $this->truncate(ClickTrackerLog::normalizeUrl($pageUrl), 500),
                'device' => $this->getDevice(),
                'date_add' => date('Y-m-d H:i:s'),
            );

            if ($context === ClickTrackerLog::CONTEXT_PRODUCT) {
                // Product data is read from the catalog (default language), never trusted from the client
                $product = new Product((int) Tools::getValue('id_product'), false, (int) Configuration::get('PS_LANG_DEFAULT'), (int) $this->context->shop->id);
                if (Validate::isLoadedObject($product)) {
                    $log['id_product'] = (int) $product->id;
                    $log['product_name'] = $this->cleanInputString($product->name, 255);
                    $log['id_category'] = (int) $product->id_category_default ?: null;
                    $log['id_manufacturer'] = (int) $product->id_manufacturer ?: null;
                } else {
                    // Unknown product (e.g. deleted meanwhile): keep the click without product data
                    $log['context_type'] = ClickTrackerLog::CONTEXT_OTHER;
                }
            }

            if ($log['context_type'] !== ClickTrackerLog::CONTEXT_PRODUCT) {
                $pageTitle = Tools::getValue('page_title');
                if (!empty($pageTitle)) {
                    $log['page_title'] = $this->cleanInputString($pageTitle, 255);
                }
            }

            $log = array_merge($log, $this->getTrafficSource($pageUrl));

            if (ClickTrackerLog::insertLog($log)) {
                $this->ajaxResponse(true, 'Click tracked');
            } else {
                $this->ajaxResponse(false, 'Failed to save');
            }
        } catch (Exception $e) {
            $this->handleError($e);
        } catch (Throwable $e) {
            // PHP 7+ errors (TypeError...): never break the page, always answer JSON
            $this->handleError($e);
        }
    }

    /**
     * Log an unexpected error (debug mode only) and answer with a generic message
     *
     * @param Exception|Throwable $e
     */
    protected function handleError($e)
    {
        if (Configuration::get('CLICKTRACKER_DEBUG')) {
            PrestaShopLogger::addLog('ClickTracker error: ' . $e->getMessage(), 3, null, 'ClickTrackerLog', null, true);
        }
        $this->ajaxResponse(false, 'Server error');
    }

    /**
     * Count a product page view (used for the click-through rate)
     */
    protected function processProductView()
    {
        if (!Configuration::get('CLICKTRACKER_TRACK_VIEWS')) {
            $this->ajaxResponse(false, 'View tracking disabled');
            return;
        }

        $pageUrl = trim((string) Tools::getValue('page_url'));
        if (Tools::strlen($pageUrl) > 2000 || !$this->isShopUrl($pageUrl)) {
            $this->ajaxResponse(false, 'Invalid URL');
            return;
        }

        $idProduct = (int) Tools::getValue('id_product');
        if ($idProduct <= 0 || !Product::existsInDatabase($idProduct, 'product')) {
            $this->ajaxResponse(false, 'Invalid product');
            return;
        }

        if (ClickTrackerLog::addProductView((int) $this->context->shop->id, $idProduct)) {
            $this->ajaxResponse(true, 'View tracked');
        } else {
            $this->ajaxResponse(false, 'Failed to save');
        }
    }

    /**
     * Traffic source of the click: from the current page, or from the landing page
     * of the session when first-touch attribution is enabled
     *
     * @param string $pageUrl Validated page URL
     * @return array traffic_source, utm_*, referrer_host
     */
    protected function getTrafficSource($pageUrl)
    {
        $url = $pageUrl;
        // Requests from a cached pre-1.2.0 script carry no referrer: source is then unknown, not direct
        $referrer = Tools::getIsset('referrer') ? (string) Tools::getValue('referrer') : null;

        if (Configuration::get('CLICKTRACKER_FIRST_TOUCH')) {
            $landingUrl = trim((string) Tools::getValue('landing_url'));
            if ($landingUrl !== '' && Tools::strlen($landingUrl) <= 2000 && $this->isShopUrl($landingUrl)) {
                $url = $landingUrl;
                $referrer = (string) Tools::getValue('landing_referrer');
            }
        }

        if ($referrer !== null && Tools::strlen($referrer) > 2000) {
            $referrer = null;
        }

        return ClickTrackerSource::classify($url, $referrer, $this->getShopHosts());
    }

    /**
     * Device type from the user agent (PrestaShop Mobile_Detect)
     *
     * @return string
     */
    protected function getDevice()
    {
        switch ($this->context->getDevice()) {
            case Context::DEVICE_MOBILE:
                return ClickTrackerLog::DEVICE_MOBILE;
            case Context::DEVICE_TABLET:
                return ClickTrackerLog::DEVICE_TABLET;
            default:
                return ClickTrackerLog::DEVICE_DESKTOP;
        }
    }

    /**
     * Check rate limiting per client IP (stored hashed, never in clear)
     *
     * @return bool True if within rate limit (or if the limit cannot be checked), false if exceeded
     */
    protected function checkRateLimit()
    {
        // Fail open: a missing table (files deployed before the upgrade) or a database hiccup
        // must never stop click tracking
        try {
            return $this->incrementRateCounter() <= self::RATE_LIMIT_MAX;
        } catch (Exception $e) {
            return true;
        }
    }

    /**
     * Increment and return the request counter of the client IP for the current window
     *
     * @return int Requests in the current window (0 if the counter could not be read)
     */
    protected function incrementRateCounter()
    {
        $db = Db::getInstance();
        $table = _DB_PREFIX_ . 'clicktracker_rate';
        $now = time();
        $windowStart = $now - self::RATE_LIMIT_WINDOW;
        $ipHash = sha1(Tools::getRemoteAddr() . _COOKIE_KEY_);

        // hits is assigned before window_start, so both IF() read the previous window
        $db->execute('INSERT INTO `' . $table . '` (`ip_hash`, `window_start`, `hits`)
            VALUES ("' . pSQL($ipHash) . '", ' . (int) $now . ', 1)
            ON DUPLICATE KEY UPDATE
                `hits` = IF(`window_start` <= ' . (int) $windowStart . ', 1, `hits` + 1),
                `window_start` = IF(`window_start` <= ' . (int) $windowStart . ', ' . (int) $now . ', `window_start`)');

        // Occasional cleanup of expired rows
        if (mt_rand(1, 100) === 1) {
            $db->execute('DELETE FROM `' . $table . '` WHERE `window_start` < ' . (int) ($now - 3600));
        }

        return (int) $db->getValue('SELECT `hits` FROM `' . $table . '` WHERE `ip_hash` = "' . pSQL($ipHash) . '"', false);
    }

    /**
     * Validate security token
     *
     * @param string $token Token to validate
     * @return bool
     */
    protected function validateToken($token)
    {
        return Tools::getToken(false) === $token;
    }

    /**
     * Check Origin (or Referer as fallback) header against the shop domains
     *
     * @return bool
     */
    protected function isAllowedOrigin()
    {
        if (!empty($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] !== 'null') {
            return $this->isShopUrl($_SERVER['HTTP_ORIGIN']);
        }

        if (!empty($_SERVER['HTTP_REFERER'])) {
            return $this->isShopUrl($_SERVER['HTTP_REFERER']);
        }

        // Some privacy tools strip both headers: other checks still apply
        return true;
    }

    /**
     * Check that a URL is an absolute http(s) URL on one of the shop domains
     *
     * @param string $url URL to validate
     * @return bool
     */
    protected function isShopUrl($url)
    {
        $parts = parse_url($url);
        if (empty($parts['scheme']) || empty($parts['host'])
            || !in_array(Tools::strtolower($parts['scheme']), array('http', 'https'), true)) {
            return false;
        }

        return in_array(Tools::strtolower($parts['host']), $this->getShopHosts(), true);
    }

    /**
     * Hosts configured for the current shop
     *
     * @return array
     */
    protected function getShopHosts()
    {
        if ($this->shopHosts !== null) {
            return $this->shopHosts;
        }

        $hosts = array();
        $rows = Db::getInstance()->executeS('SELECT `domain`, `domain_ssl` FROM `' . _DB_PREFIX_ . 'shop_url`
            WHERE `id_shop` = ' . (int) $this->context->shop->id);

        $domains = array($this->context->shop->domain, $this->context->shop->domain_ssl);
        foreach ((array) $rows as $row) {
            $domains[] = $row['domain'];
            $domains[] = $row['domain_ssl'];
        }

        foreach ($domains as $domain) {
            if (!empty($domain)) {
                // Strip an optional port
                $hosts[] = Tools::strtolower(preg_replace('/:\d+$/', '', $domain));
            }
        }

        $this->shopHosts = array_values(array_unique($hosts));

        return $this->shopHosts;
    }

    /**
     * Clean string input (stored raw, escaped on output)
     *
     * @param string $string Input string
     * @param int $maxLength Maximum length
     * @return string Cleaned string
     */
    protected function cleanInputString($string, $maxLength = 255)
    {
        $string = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $string)));

        return $this->truncate($string, $maxLength);
    }

    /**
     * Truncate a string to a maximum length (multibyte safe)
     *
     * @param string $string Input string
     * @param int $maxLength Maximum length
     * @return string
     */
    protected function truncate($string, $maxLength)
    {
        if (function_exists('mb_substr')) {
            return mb_substr($string, 0, $maxLength, 'UTF-8');
        }

        return substr($string, 0, $maxLength);
    }

    /**
     * Send JSON response and exit
     *
     * @param bool $success Success status
     * @param string $message Response message
     */
    protected function ajaxResponse($success, $message = '')
    {
        $response = array(
            'success' => $success,
        );

        if (!$success && !empty($message)) {
            $response['error'] = $message;
        } elseif ($success && !empty($message)) {
            $response['message'] = $message;
        }

        die(json_encode($response));
    }
}

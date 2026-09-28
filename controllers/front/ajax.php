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

    /** @var int Rate limit: maximum requests per time window */
    const RATE_LIMIT_MAX = 30;

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

            // Get and validate token
            $token = Tools::getValue('token');
            if (empty($token) || !$this->validateToken($token)) {
                $this->ajaxResponse(false, 'Invalid token');
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
            if (!in_array($clickedClass, $this->module->getTrackedSelectorLabels(), true)) {
                $this->ajaxResponse(false, 'Unknown selector');
                return;
            }

            // Validate context type
            if (!ClickTrackerLog::isValidContextType($context)) {
                $context = ClickTrackerLog::CONTEXT_OTHER;
            }

            // Validate element type
            if (!ClickTrackerLog::isValidElementType($elementType)) {
                $elementType = ClickTrackerLog::ELEMENT_OTHER;
            }

            // Validate URL: absolute http(s) URL on one of the shop domains
            if (Tools::strlen($pageUrl) > 2000 || !$this->isShopUrl($pageUrl)) {
                $this->ajaxResponse(false, 'Invalid URL');
                return;
            }

            // Create log entry
            $log = new ClickTrackerLog();
            $log->id_shop = (int) $this->context->shop->id;
            $log->context_type = $context;
            $log->page_type = preg_match('/^[a-zA-Z0-9_\-]{1,64}$/', (string) $pageType) ? $pageType : null;
            $log->clicked_class = $this->cleanInputString($clickedClass, 255);
            $log->element_type = $elementType;
            $log->page_url = $this->truncate($pageUrl, 500);
            $log->page_path = $this->truncate(ClickTrackerLog::normalizeUrl($pageUrl), 500);
            $log->date_add = date('Y-m-d H:i:s');

            if ($context === ClickTrackerLog::CONTEXT_PRODUCT) {
                // Product data is read from the catalog (default language), never trusted from the client
                $product = new Product((int) Tools::getValue('id_product'), false, (int) Configuration::get('PS_LANG_DEFAULT'), (int) $this->context->shop->id);
                if (!Validate::isLoadedObject($product)) {
                    $this->ajaxResponse(false, 'Invalid product');
                    return;
                }

                $log->id_product = (int) $product->id;
                $log->product_name = $this->cleanInputString($product->name, 255);
                $log->id_category = (int) $product->id_category_default ?: null;
            } else {
                $pageTitle = Tools::getValue('page_title');
                if (!empty($pageTitle)) {
                    $log->page_title = $this->cleanInputString($pageTitle, 255);
                }
            }

            // Save to database
            if ($log->save()) {
                $this->ajaxResponse(true, 'Click tracked');
            } else {
                $this->ajaxResponse(false, 'Failed to save');
            }
        } catch (Exception $e) {
            // Log error silently in debug mode
            if (Configuration::get('CLICKTRACKER_DEBUG')) {
                PrestaShopLogger::addLog(
                    'ClickTracker error: ' . $e->getMessage(),
                    3,
                    null,
                    'ClickTrackerLog',
                    null,
                    true
                );
            }
            $this->ajaxResponse(false, 'Server error');
        }
    }

    /**
     * Check rate limiting per client IP (stored hashed, never in clear)
     *
     * @return bool True if within rate limit, false if exceeded
     */
    protected function checkRateLimit()
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

        $hits = (int) $db->getValue('SELECT `hits` FROM `' . $table . '` WHERE `ip_hash` = "' . pSQL($ipHash) . '"', false);

        return $hits <= self::RATE_LIMIT_MAX;
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

        return array_unique($hosts);
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

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
    /** @var bool Disable SSL requirement for AJAX */
    public $ssl = true;

    /** @var bool AJAX controller */
    public $ajax = true;

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

            // Check rate limiting
            if (!$this->checkRateLimit()) {
                $this->ajaxResponse(false, 'Rate limit exceeded');
                return;
            }

            // Load the ClickTrackerLog class
            require_once _PS_MODULE_DIR_ . 'clicktracker/classes/ClickTrackerLog.php';

            // Get and validate token
            $token = Tools::getValue('token');
            if (empty($token) || !$this->validateToken($token)) {
                $this->ajaxResponse(false, 'Invalid token');
                return;
            }

            // Get POST data
            $context = Tools::getValue('context');
            $clickedClass = Tools::getValue('clicked_class');
            $elementType = Tools::getValue('element_type');
            $pageUrl = Tools::getValue('page_url');

            // Validate required fields
            if (empty($context) || empty($clickedClass) || empty($elementType) || empty($pageUrl)) {
                $this->ajaxResponse(false, 'Missing required fields');
                return;
            }

            // Validate context type
            if (!ClickTrackerLog::isValidContextType($context)) {
                $context = ClickTrackerLog::CONTEXT_CMS; // Fallback
            }

            // Validate element type
            if (!ClickTrackerLog::isValidElementType($elementType)) {
                $elementType = ClickTrackerLog::ELEMENT_OTHER; // Fallback
            }

            // Validate URL
            if (!$this->isValidUrl($pageUrl)) {
                $this->ajaxResponse(false, 'Invalid URL');
                return;
            }

            // Sanitize inputs
            $clickedClass = $this->cleanInputString($clickedClass, 100);
            $pageUrl = $this->cleanInputUrl($pageUrl, 500);

            // Create log entry
            $log = new ClickTrackerLog();
            $log->context_type = $context;
            $log->clicked_class = $clickedClass;
            $log->element_type = $elementType;
            $log->page_url = $pageUrl;
            $log->date_add = date('Y-m-d H:i:s');

            // Add context-specific data
            if ($context === ClickTrackerLog::CONTEXT_PRODUCT) {
                $idProduct = (int) Tools::getValue('id_product');
                $productName = Tools::getValue('product_name');
                $idCategory = (int) Tools::getValue('id_category');

                if ($idProduct > 0) {
                    $log->id_product = $idProduct;
                }

                if (!empty($productName)) {
                    $log->product_name = $this->cleanInputString($productName, 255);
                }

                if ($idCategory > 0) {
                    $log->id_category = $idCategory;
                }
            } else {
                // CMS context
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
     * Check rate limiting using session
     *
     * @return bool True if within rate limit, false if exceeded
     */
    protected function checkRateLimit()
    {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $currentTime = time();
        $sessionKey = 'et_clicktracker_rate_limit';

        // Initialize or get rate limit data from session
        if (!isset($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = array(
                'count' => 0,
                'window_start' => $currentTime,
            );
        }

        $rateData = $_SESSION[$sessionKey];

        // Reset if time window has passed
        if ($currentTime - $rateData['window_start'] >= self::RATE_LIMIT_WINDOW) {
            $_SESSION[$sessionKey] = array(
                'count' => 1,
                'window_start' => $currentTime,
            );
            return true;
        }

        // Check if limit exceeded
        if ($rateData['count'] >= self::RATE_LIMIT_MAX) {
            return false;
        }

        // Increment counter
        $_SESSION[$sessionKey]['count']++;

        return true;
    }

    /**
     * Validate security token
     *
     * @param string $token Token to validate
     * @return bool
     */
    protected function validateToken($token)
    {
        // PrestaShop token validation
        $expectedToken = Tools::getToken(false);
        return $expectedToken === $token;
    }

    /**
     * Validate URL
     *
     * @param string $url URL to validate
     * @return bool
     */
    protected function isValidUrl($url)
    {
        // Allow relative URLs starting with /
        if (strpos($url, '/') === 0) {
            return true;
        }

        // Validate full URLs
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Clean string input
     *
     * @param string $string Input string
     * @param int $maxLength Maximum length
     * @return string Cleaned string
     */
    protected function cleanInputString($string, $maxLength = 255)
    {
        $string = strip_tags($string);
        $string = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');

        if (function_exists('mb_substr')) {
            $string = mb_substr($string, 0, $maxLength, 'UTF-8');
        } else {
            $string = substr($string, 0, $maxLength);
        }

        return $string;
    }

    /**
     * Clean URL input
     *
     * @param string $url URL to clean
     * @param int $maxLength Maximum length
     * @return string Cleaned URL
     */
    protected function cleanInputUrl($url, $maxLength = 500)
    {
        $url = filter_var($url, FILTER_SANITIZE_URL);

        if (function_exists('mb_substr')) {
            $url = mb_substr($url, 0, $maxLength, 'UTF-8');
        } else {
            $url = substr($url, 0, $maxLength);
        }

        return $url;
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

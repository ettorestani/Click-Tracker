<?php
/**
 * Click Tracker Module for PrestaShop
 *
 * Advanced click tracking system to monitor user interactions with specific page elements
 * (WhatsApp buttons, phone buttons, CTAs, Maps links)
 *
 * @author    Ettore Stani
 * @copyright 2024 Ettore Stani
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * @version   1.0.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/ClickTrackerLog.php';

class ClickTracker extends Module
{
    /** @var string Configuration key prefix */
    const CONFIG_PREFIX = 'CLICKTRACKER_';

    /** @var array Configuration keys */
    protected $configKeys = array(
        'CSS_CLASSES',
        'BODY_CLASSES',
        'TRACK_PRODUCT',
        'TRACK_CMS',
        'EXTERNAL_ONLY',
        'DEBUG',
    );

    /**
     * Module constructor
     */
    public function __construct()
    {
        $this->name = 'clicktracker';
        $this->tab = 'analytics_stats';
        $this->version = '1.0.0';
        $this->author = 'Ettore Stani';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array(
            'min' => '1.7.0.0',
            'max' => '8.99.99',
        );
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Click Tracker');
        $this->description = $this->l('Advanced click tracking system to monitor user interactions with specific page elements (WhatsApp, phone buttons, CTAs)');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall? All tracking data will be permanently deleted.');
    }

    /**
     * Module installation
     *
     * @return bool
     */
    public function install()
    {
        // Include SQL install script
        include dirname(__FILE__) . '/sql/install.php';

        return parent::install()
            && $this->registerHook('displayHeader')
            && $this->setDefaultConfiguration();
    }

    /**
     * Module uninstallation
     *
     * @return bool
     */
    public function uninstall()
    {
        // Include SQL uninstall script
        include dirname(__FILE__) . '/sql/uninstall.php';

        // Remove all configuration values
        foreach ($this->configKeys as $key) {
            Configuration::deleteByName(self::CONFIG_PREFIX . $key);
        }

        return $this->unregisterHook('displayHeader')
            && parent::uninstall();
    }

    /**
     * Set default configuration values
     *
     * @return bool
     */
    protected function setDefaultConfiguration()
    {
        return Configuration::updateValue(self::CONFIG_PREFIX . 'CSS_CLASSES', '')
            && Configuration::updateValue(self::CONFIG_PREFIX . 'BODY_CLASSES', '')
            && Configuration::updateValue(self::CONFIG_PREFIX . 'TRACK_PRODUCT', 1)
            && Configuration::updateValue(self::CONFIG_PREFIX . 'TRACK_CMS', 1)
            && Configuration::updateValue(self::CONFIG_PREFIX . 'EXTERNAL_ONLY', 0)
            && Configuration::updateValue(self::CONFIG_PREFIX . 'DEBUG', 0);
    }

    /**
     * Module configuration page
     *
     * @return string
     */
    public function getContent()
    {
        // Check employee permission
        if (!$this->context->employee->hasAuthOnShop($this->context->shop->id)) {
            return $this->displayError($this->l('You do not have permission to access this module.'));
        }

        $output = '';

        // Handle form submissions (POST only for security)
        if (Tools::isSubmit('submitClickTrackerConfig') && Tools::getIsset('submitClickTrackerConfig')) {
            $output .= $this->processConfigForm();
        }

        // Handle delete action (POST only for security)
        if (Tools::isSubmit('submitDeleteLog') && Tools::getValue('id_log')) {
            $output .= $this->processDeleteLog((int) Tools::getValue('id_log'));
        }

        // Handle bulk delete (POST only)
        if (Tools::isSubmit('submitBulkDelete') && Tools::getValue('logBox')) {
            $output .= $this->processBulkDelete(Tools::getValue('logBox'));
        }

        // Handle CSV export
        if (Tools::isSubmit('exportCsv')) {
            $this->processExportCsv();
        }

        // Load CSS and JS for backend
        $this->context->controller->addCSS($this->_path . 'views/css/back.css');
        $this->context->controller->addJS($this->_path . 'views/js/back.js');

        // Define JS translations
        Media::addJsDef(array(
            'et_clickTrackerTranslations' => array(
                'noItemsSelected' => $this->l('Please select at least one item.'),
                'confirmDelete' => $this->l('Are you sure you want to delete this item?'),
                'confirmBulkDelete' => $this->l('Are you sure you want to delete the selected items?'),
            ),
        ));

        // Render the page content based on active section (using 'section' to avoid conflict with PS 'tab' parameter)
        $activeTab = Tools::getValue('section', 'config');

        $output .= $this->renderTabs($activeTab);

        switch ($activeTab) {
            case 'logs':
                $output .= $this->renderLogsPage();
                break;
            case 'stats':
                $output .= $this->renderStatsPage();
                break;
            default:
                $output .= $this->renderConfigForm();
                $output .= $this->renderDonationBox();
        }

        return $output;
    }

    /**
     * Render navigation tabs
     *
     * @param string $activeTab Currently active tab
     * @return string HTML
     */
    protected function renderTabs($activeTab)
    {
        $tabs = array(
            'config' => array(
                'title' => $this->l('Configuration'),
                'icon' => 'icon-cog',
            ),
            'logs' => array(
                'title' => $this->l('Logs'),
                'icon' => 'icon-list',
            ),
            'stats' => array(
                'title' => $this->l('Statistics'),
                'icon' => 'icon-bar-chart',
            ),
        );

        $this->context->smarty->assign(array(
            'tabs' => $tabs,
            'activeTab' => $activeTab,
            'moduleLink' => $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name,
        ));

        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/tabs.tpl');
    }

    /**
     * Render configuration form
     *
     * @return string HTML
     */
    protected function renderConfigForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitClickTrackerConfig';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Get configuration form structure
     *
     * @return array Form structure
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'textarea',
                        'label' => $this->l('CSS Classes to Track'),
                        'name' => self::CONFIG_PREFIX . 'CSS_CLASSES',
                        'desc' => $this->l('Enter CSS classes to track, one per line. Examples: .btn-wa, .btn-phone, .btn-maps. Only elements with these classes will be tracked.'),
                        'cols' => 60,
                        'rows' => 6,
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->l('Page Selectors (Optional)'),
                        'name' => self::CONFIG_PREFIX . 'BODY_CLASSES',
                        'desc' => $this->l('Enter body classes or IDs to activate tracking on specific pages, one per line. Use dot for classes (.module-smartblog-details) or hash for IDs (#checkout). Leave empty to use only product/CMS detection.'),
                        'cols' => 60,
                        'rows' => 4,
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Track Product Pages'),
                        'name' => self::CONFIG_PREFIX . 'TRACK_PRODUCT',
                        'desc' => $this->l('Enable click tracking on product pages.'),
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'track_product_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ),
                            array(
                                'id' => 'track_product_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ),
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Track CMS/Blog Pages'),
                        'name' => self::CONFIG_PREFIX . 'TRACK_CMS',
                        'desc' => $this->l('Enable click tracking on CMS and blog pages.'),
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'track_cms_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ),
                            array(
                                'id' => 'track_cms_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ),
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Track External Links Only'),
                        'name' => self::CONFIG_PREFIX . 'EXTERNAL_ONLY',
                        'desc' => $this->l('Track only links with http/https in the href attribute.'),
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'external_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ),
                            array(
                                'id' => 'external_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ),
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Debug Mode'),
                        'name' => self::CONFIG_PREFIX . 'DEBUG',
                        'desc' => $this->l('Enable debug mode to log JavaScript errors to browser console.'),
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'debug_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ),
                            array(
                                'id' => 'debug_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ),
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Get configuration form values
     *
     * @return array Form values
     */
    protected function getConfigFormValues()
    {
        return array(
            self::CONFIG_PREFIX . 'CSS_CLASSES' => Configuration::get(self::CONFIG_PREFIX . 'CSS_CLASSES'),
            self::CONFIG_PREFIX . 'BODY_CLASSES' => Configuration::get(self::CONFIG_PREFIX . 'BODY_CLASSES'),
            self::CONFIG_PREFIX . 'TRACK_PRODUCT' => Configuration::get(self::CONFIG_PREFIX . 'TRACK_PRODUCT'),
            self::CONFIG_PREFIX . 'TRACK_CMS' => Configuration::get(self::CONFIG_PREFIX . 'TRACK_CMS'),
            self::CONFIG_PREFIX . 'EXTERNAL_ONLY' => Configuration::get(self::CONFIG_PREFIX . 'EXTERNAL_ONLY'),
            self::CONFIG_PREFIX . 'DEBUG' => Configuration::get(self::CONFIG_PREFIX . 'DEBUG'),
        );
    }

    /**
     * Process configuration form submission
     *
     * @return string Success/error message
     */
    protected function processConfigForm()
    {
        $cssClasses = Tools::getValue(self::CONFIG_PREFIX . 'CSS_CLASSES', '');
        $bodyClasses = Tools::getValue(self::CONFIG_PREFIX . 'BODY_CLASSES', '');

        // Validate and clean CSS classes
        $cssClasses = $this->validateCssClasses($cssClasses);
        $bodyClasses = $this->validateBodyClasses($bodyClasses);

        Configuration::updateValue(self::CONFIG_PREFIX . 'CSS_CLASSES', $cssClasses);
        Configuration::updateValue(self::CONFIG_PREFIX . 'BODY_CLASSES', $bodyClasses);
        Configuration::updateValue(self::CONFIG_PREFIX . 'TRACK_PRODUCT', (int) Tools::getValue(self::CONFIG_PREFIX . 'TRACK_PRODUCT'));
        Configuration::updateValue(self::CONFIG_PREFIX . 'TRACK_CMS', (int) Tools::getValue(self::CONFIG_PREFIX . 'TRACK_CMS'));
        Configuration::updateValue(self::CONFIG_PREFIX . 'EXTERNAL_ONLY', (int) Tools::getValue(self::CONFIG_PREFIX . 'EXTERNAL_ONLY'));
        Configuration::updateValue(self::CONFIG_PREFIX . 'DEBUG', (int) Tools::getValue(self::CONFIG_PREFIX . 'DEBUG'));

        return $this->displayConfirmation($this->l('Settings saved successfully.'));
    }

    /**
     * Validate and clean CSS classes input
     *
     * @param string $input Raw input
     * @return string Cleaned CSS classes
     */
    protected function validateCssClasses($input)
    {
        $lines = explode("\n", $input);
        $cleaned = array();

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Ensure class starts with . if it doesn't
            if (strpos($line, '.') !== 0 && strpos($line, '#') !== 0) {
                $line = '.' . $line;
            }

            // Basic validation - only allow valid CSS selector characters
            if (preg_match('/^[.#][a-zA-Z0-9_\-]+$/', $line)) {
                $cleaned[] = $line;
            }
        }

        return implode("\n", $cleaned);
    }

    /**
     * Validate and clean body selectors input (classes and IDs)
     *
     * @param string $input Raw input
     * @return string Cleaned body selectors
     */
    protected function validateBodyClasses($input)
    {
        $lines = explode("\n", $input);
        $cleaned = array();

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Ensure selector starts with . or #
            if (strpos($line, '.') !== 0 && strpos($line, '#') !== 0) {
                // Default to class if no prefix
                $line = '.' . $line;
            }

            // Basic validation - only allow valid CSS selector characters
            if (preg_match('/^[.#][a-zA-Z][a-zA-Z0-9_\-]*$/', $line)) {
                $cleaned[] = $line;
            }
        }

        return implode("\n", $cleaned);
    }

    /**
     * Render logs page
     *
     * @return string HTML
     */
    protected function renderLogsPage()
    {
        // Get filters from request
        $filters = array(
            'context_type' => Tools::getValue('filter_context', ''),
            'element_type' => Tools::getValue('filter_element', ''),
            'date_from' => Tools::getValue('filter_date_from', ''),
            'date_to' => Tools::getValue('filter_date_to', ''),
            'search' => Tools::getValue('filter_search', ''),
        );

        // Remove empty filters
        $filters = array_filter($filters, function ($value) {
            return $value !== '';
        });

        $page = max(1, (int) Tools::getValue('page', 1));
        $perPage = (int) Tools::getValue('per_page', 20);
        $orderBy = Tools::getValue('order_by', 'date_add');
        $orderDir = Tools::getValue('order_dir', 'DESC');

        // Get paginated logs
        $result = ClickTrackerLog::getLogsWithPagination($page, $perPage, $filters, $orderBy, $orderDir);

        // Element type labels
        $elementTypes = array(
            'whatsapp' => $this->l('WhatsApp'),
            'phone' => $this->l('Phone'),
            'maps' => $this->l('Maps'),
            'other' => $this->l('Other'),
        );

        // Context type labels
        $contextTypes = array(
            'product' => $this->l('Product'),
            'cms' => $this->l('CMS'),
        );

        $this->context->smarty->assign(array(
            'logs' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'per_page' => $result['per_page'],
            'total_pages' => $result['total_pages'],
            'filters' => $filters + array(
                'context_type' => '',
                'element_type' => '',
                'date_from' => '',
                'date_to' => '',
                'search' => '',
            ),
            'order_by' => $orderBy,
            'order_dir' => $orderDir,
            'element_types' => $elementTypes,
            'context_types' => $contextTypes,
            'moduleLink' => $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name . '&section=logs',
            'token' => Tools::getAdminTokenLite('AdminModules'),
        ));

        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/logs.tpl');
    }

    /**
     * Render statistics page
     *
     * @return string HTML
     */
    protected function renderStatsPage()
    {
        // Date range for filters (default: last 30 days)
        $dateFrom = Tools::getValue('stats_date_from', date('Y-m-d', strtotime('-30 days')));
        $dateTo = Tools::getValue('stats_date_to', date('Y-m-d'));

        // Overview cards data
        $totalClicks = ClickTrackerLog::getTotalClicks();
        $thisMonthClicks = ClickTrackerLog::getThisMonthClicks();
        $mostClickedType = ClickTrackerLog::getMostClickedElementType();
        $topProducts = ClickTrackerLog::getTopProducts(1, $dateFrom, $dateTo);
        $topProduct = !empty($topProducts) ? $topProducts[0] : null;

        // Chart data
        $clicksByDate = ClickTrackerLog::getClicksGroupedByDate($dateFrom, $dateTo, 'day');
        $clicksByElement = ClickTrackerLog::getClicksGroupedByElementType($dateFrom, $dateTo);
        $clicksByContext = ClickTrackerLog::getClicksGroupedByContext($dateFrom, $dateTo);
        $topProductsList = ClickTrackerLog::getTopProducts(10, $dateFrom, $dateTo);
        $topPagesList = ClickTrackerLog::getTopPages(10, $dateFrom, $dateTo);

        // Element type labels
        $elementTypeLabels = array(
            'whatsapp' => $this->l('WhatsApp'),
            'phone' => $this->l('Phone'),
            'maps' => $this->l('Maps'),
            'other' => $this->l('Other'),
        );

        // Prepare chart data arrays
        $chartDates = array();
        $chartCounts = array();
        foreach ($clicksByDate as $row) {
            $chartDates[] = $row['date_group'];
            $chartCounts[] = (int) $row['total'];
        }

        $pieLabels = array();
        $pieCounts = array();
        foreach ($clicksByElement as $row) {
            $pieLabels[] = isset($elementTypeLabels[$row['element_type']]) ? $elementTypeLabels[$row['element_type']] : $row['element_type'];
            $pieCounts[] = (int) $row['total'];
        }

        $this->context->smarty->assign(array(
            'total_clicks' => $totalClicks,
            'this_month_clicks' => $thisMonthClicks,
            'most_clicked_type' => $mostClickedType ? (isset($elementTypeLabels[$mostClickedType]) ? $elementTypeLabels[$mostClickedType] : $mostClickedType) : '-',
            'top_product' => $topProduct,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'chart_dates' => json_encode($chartDates),
            'chart_counts' => json_encode($chartCounts),
            'pie_labels' => json_encode($pieLabels),
            'pie_counts' => json_encode($pieCounts),
            'clicks_by_context' => $clicksByContext,
            'top_products' => $topProductsList,
            'top_pages' => $topPagesList,
            'moduleLink' => $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name . '&section=stats',
            'admin_token' => Tools::getAdminTokenLite('AdminModules'),
            'chart_js_path' => $this->_path . 'views/js/chart.min.js',
        ));

        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/stats.tpl');
    }

    /**
     * Process single log deletion
     *
     * @param int $id_log Log ID
     * @return string Message
     */
    protected function processDeleteLog($id_log)
    {
        $log = new ClickTrackerLog($id_log);
        if (Validate::isLoadedObject($log)) {
            if ($log->delete()) {
                return $this->displayConfirmation($this->l('Log entry deleted successfully.'));
            }
        }
        return $this->displayError($this->l('Error deleting log entry.'));
    }

    /**
     * Process bulk delete
     *
     * @param array $ids Log IDs
     * @return string Message
     */
    protected function processBulkDelete($ids)
    {
        $deleted = 0;
        foreach ($ids as $id) {
            $log = new ClickTrackerLog((int) $id);
            if (Validate::isLoadedObject($log) && $log->delete()) {
                $deleted++;
            }
        }

        if ($deleted > 0) {
            return $this->displayConfirmation(sprintf($this->l('%d log entries deleted successfully.'), $deleted));
        }

        return $this->displayError($this->l('Error deleting log entries.'));
    }

    /**
     * Process CSV export
     */
    protected function processExportCsv()
    {
        // Get current filters
        $filters = array(
            'context_type' => Tools::getValue('filter_context', ''),
            'element_type' => Tools::getValue('filter_element', ''),
            'date_from' => Tools::getValue('filter_date_from', ''),
            'date_to' => Tools::getValue('filter_date_to', ''),
            'search' => Tools::getValue('filter_search', ''),
        );

        $filters = array_filter($filters, function ($value) {
            return $value !== '';
        });

        // Get all logs matching filters
        $logs = ClickTrackerLog::getLogsForExport($filters);

        // Generate filename
        $filename = 'clicktracker_export_' . date('Y-m-d') . '.csv';

        // Set headers for download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Output UTF-8 BOM for Excel compatibility
        echo "\xEF\xBB\xBF";

        // Open output stream
        $output = fopen('php://output', 'w');

        // Write header row
        fputcsv($output, array(
            $this->l('Date/Time'),
            $this->l('Context Type'),
            $this->l('Element Type'),
            $this->l('Clicked Class'),
            $this->l('Product ID'),
            $this->l('Product Name'),
            $this->l('Category ID'),
            $this->l('Page URL'),
            $this->l('Page Title'),
        ), ';');

        // Write data rows
        foreach ($logs as $log) {
            fputcsv($output, array(
                $log['date_add'],
                $log['context_type'],
                $log['element_type'],
                $log['clicked_class'],
                $log['id_product'] ?: '',
                $log['product_name'] ?: '',
                $log['id_category'] ?: '',
                $log['page_url'],
                $log['page_title'] ?: '',
            ), ';');
        }

        fclose($output);
        exit;
    }

    /**
     * Hook: displayHeader
     * Injects tracking script into page header
     *
     * @param array $params Hook parameters
     * @return string HTML/JS to inject
     */
    public function hookDisplayHeader($params)
    {
        // Check if tracking is enabled for current page type
        $controller = $this->context->controller;
        $controllerName = get_class($controller);

        $isProductPage = ($controllerName === 'ProductController' || $controller->php_self === 'product');
        $isCmsPage = ($controllerName === 'CmsController' || $controller->php_self === 'cms'
            || strpos($controllerName, 'Blog') !== false || strpos($controllerName, 'blog') !== false);

        // Check configuration
        $trackProduct = (bool) Configuration::get(self::CONFIG_PREFIX . 'TRACK_PRODUCT');
        $trackCms = (bool) Configuration::get(self::CONFIG_PREFIX . 'TRACK_CMS');

        // Get body classes configuration for additional page matching
        $bodyClassesConfig = Configuration::get(self::CONFIG_PREFIX . 'BODY_CLASSES');
        $bodyClassesArray = array();
        if (!empty($bodyClassesConfig)) {
            $bodyClassesArray = array_filter(array_map('trim', explode("\n", $bodyClassesConfig)));
        }

        // Check if we should track based on standard page types
        $shouldTrackByPageType = false;
        if ($isProductPage && $trackProduct) {
            $shouldTrackByPageType = true;
        } elseif ($isCmsPage && $trackCms) {
            $shouldTrackByPageType = true;
        }

        // If body classes are configured, we'll let the JS handle the check
        // because body classes are only available client-side
        $hasBodyClassesConfig = !empty($bodyClassesArray);

        // Don't render if no tracking conditions apply
        if (!$shouldTrackByPageType && !$hasBodyClassesConfig) {
            return '';
        }

        // Get CSS classes to track
        $cssClasses = Configuration::get(self::CONFIG_PREFIX . 'CSS_CLASSES');
        if (empty($cssClasses)) {
            return '';
        }

        $classesArray = array_filter(array_map('trim', explode("\n", $cssClasses)));
        if (empty($classesArray)) {
            return '';
        }

        // Get other configuration
        $externalOnly = (bool) Configuration::get(self::CONFIG_PREFIX . 'EXTERNAL_ONLY');
        $debug = (bool) Configuration::get(self::CONFIG_PREFIX . 'DEBUG');

        // Generate security token
        $token = Tools::getToken(false);

        // Get AJAX endpoint URL
        $ajaxUrl = $this->context->link->getModuleLink($this->name, 'ajax', array(), true);

        // Prepare product data if on product page
        $productData = null;
        if ($isProductPage && isset($this->context->controller->getProduct()->id)) {
            $product = $this->context->controller->getProduct();
            $productData = array(
                'id_product' => (int) $product->id,
                'product_name' => $product->name,
                'id_category' => (int) $product->id_category_default,
            );
        }

        // Determine context type
        $contextType = 'cms';
        if ($isProductPage) {
            $contextType = 'product';
        }

        // Assign variables to template
        $this->context->smarty->assign(array(
            'et_clicktracker_classes' => json_encode($classesArray),
            'et_clicktracker_body_classes' => json_encode($bodyClassesArray),
            'et_clicktracker_external_only' => $externalOnly ? 'true' : 'false',
            'et_clicktracker_debug' => $debug ? 'true' : 'false',
            'et_clicktracker_token' => $token,
            'et_clicktracker_ajax_url' => $ajaxUrl,
            'et_clicktracker_context' => $contextType,
            'et_clicktracker_product_data' => $productData ? json_encode($productData) : 'null',
            'et_clicktracker_should_track' => $shouldTrackByPageType ? 'true' : 'false',
            'et_clicktracker_js_path' => $this->_path . 'views/js/tracker.js',
        ));

        return $this->display(__FILE__, 'views/templates/hook/tracking-script.tpl');
    }

    /**
     * Render donation/support box for configuration page
     *
     * @return string HTML
     */
    protected function renderDonationBox()
    {
        $html = '<div class="panel">';
        $html .= '<div class="panel-heading">';
        $html .= '<i class="icon-heart"></i> ' . $this->l('Support This Module');
        $html .= '</div>';
        $html .= '<div class="panel-body">';
        $html .= '<p>' . $this->l('This module is free and open-source. If you find it useful, consider supporting its development:') . '</p>';
        $html .= '<a href="https://www.paypal.com/paypalme/ettorestani" target="_blank" rel="noopener noreferrer" class="btn btn-primary">';
        $html .= '<i class="icon-heart"></i> ' . $this->l('SUPPORT VIA PAYPAL');
        $html .= '</a>';
        $html .= '<p style="margin-top: 15px;">';
        $html .= $this->l('Need professional help? Contact me at') . ' ';
        $html .= '<a href="mailto:info@ettorestani.it">info@ettorestani.it</a> ';
        $html .= $this->l('or visit') . ' ';
        $html .= '<a href="https://www.ettorestani.it" target="_blank" rel="noopener noreferrer">www.ettorestani.it</a>';
        $html .= '</p>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
}

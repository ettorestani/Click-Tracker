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
 * @version   1.1.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/ClickTrackerLog.php';

class ClickTracker extends Module
{
    /** @var string Configuration key prefix */
    const CONFIG_PREFIX = 'CLICKTRACKER_';

    /** @var int Above this number of days the time chart is grouped by month */
    const CHART_DAILY_MAX_DAYS = 92;

    /** @var array Configuration keys */
    protected $configKeys = array(
        'CSS_CLASSES',
        'BODY_CLASSES',
        'TRACK_PRODUCT',
        'TRACK_CMS',
        'TRACK_OTHER',
        'EXTERNAL_ONLY',
        'DEBUG',
        'DELETE_ON_UNINSTALL',
    );

    /** @var array Chart colors by element type */
    protected $elementColors = array(
        'whatsapp' => '#25D366',
        'phone' => '#007bff',
        'maps' => '#EA4335',
        'other' => '#6c757d',
    );

    /**
     * Module constructor
     */
    public function __construct()
    {
        $this->name = 'clicktracker';
        $this->tab = 'analytics_stats';
        $this->version = '1.1.0';
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

        if (Configuration::get(self::CONFIG_PREFIX . 'DELETE_ON_UNINSTALL')) {
            $this->confirmUninstall = $this->l('Are you sure you want to uninstall? All tracking data will be permanently deleted.');
        } else {
            $this->confirmUninstall = $this->l('Are you sure you want to uninstall? Tracking data will be kept in the database.');
        }
    }

    /**
     * Module installation
     *
     * @return bool
     */
    public function install()
    {
        if (!(include dirname(__FILE__) . '/sql/install.php')) {
            return false;
        }

        return parent::install()
            && $this->registerHook('actionFrontControllerSetMedia')
            && $this->setDefaultConfiguration();
    }

    /**
     * Module uninstallation
     *
     * @return bool
     */
    public function uninstall()
    {
        // Used by sql/uninstall.php
        $deleteLogs = (bool) Configuration::get(self::CONFIG_PREFIX . 'DELETE_ON_UNINSTALL');

        if (!(include dirname(__FILE__) . '/sql/uninstall.php')) {
            return false;
        }

        foreach ($this->configKeys as $key) {
            Configuration::deleteByName(self::CONFIG_PREFIX . $key);
        }

        return parent::uninstall();
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
            && Configuration::updateValue(self::CONFIG_PREFIX . 'TRACK_OTHER', 0)
            && Configuration::updateValue(self::CONFIG_PREFIX . 'EXTERNAL_ONLY', 0)
            && Configuration::updateValue(self::CONFIG_PREFIX . 'DEBUG', 0)
            && Configuration::updateValue(self::CONFIG_PREFIX . 'DELETE_ON_UNINSTALL', 0);
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
        if (Tools::isSubmit('submitClickTrackerConfig')) {
            $output .= $this->processConfigForm();
        }

        // Handle delete action (POST only for security)
        if (Tools::isSubmit('submitDeleteLog') && Tools::getValue('id_log')) {
            $output .= $this->processDeleteLog((int) Tools::getValue('id_log'));
        }

        // Handle bulk delete (POST only)
        if (Tools::isSubmit('submitBulkDelete') && is_array(Tools::getValue('logBox'))) {
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
            'moduleLink' => $this->getModuleAdminLink(),
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
     * Build a yes/no switch field for HelperForm
     *
     * @param string $key Configuration key (without prefix)
     * @param string $label Field label
     * @param string $desc Field description
     * @return array
     */
    protected function getSwitchField($key, $label, $desc)
    {
        $id = Tools::strtolower($key);

        return array(
            'type' => 'switch',
            'label' => $label,
            'name' => self::CONFIG_PREFIX . $key,
            'desc' => $desc,
            'is_bool' => true,
            'values' => array(
                array(
                    'id' => $id . '_on',
                    'value' => 1,
                    'label' => $this->l('Yes'),
                ),
                array(
                    'id' => $id . '_off',
                    'value' => 0,
                    'label' => $this->l('No'),
                ),
            ),
        );
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
                        'label' => $this->l('CSS Selectors to Track'),
                        'name' => self::CONFIG_PREFIX . 'CSS_CLASSES',
                        'desc' => $this->l('Enter CSS selectors to track, one per line. Examples: .btn-wa, #call-button, a[href^="tel:"], a[href*="wa.me"]. Only elements matching these selectors will be tracked.'),
                        'cols' => 60,
                        'rows' => 6,
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->l('Page Selectors (Optional)'),
                        'name' => self::CONFIG_PREFIX . 'BODY_CLASSES',
                        'desc' => $this->l('Enter body classes or IDs to activate tracking on specific pages, one per line. Use dot for classes (.module-smartblog-details) or hash for IDs (#checkout). Leave empty to use only page type detection.'),
                        'cols' => 60,
                        'rows' => 4,
                    ),
                    $this->getSwitchField('TRACK_PRODUCT', $this->l('Track Product Pages'), $this->l('Enable click tracking on product pages.')),
                    $this->getSwitchField('TRACK_CMS', $this->l('Track CMS/Blog Pages'), $this->l('Enable click tracking on CMS and blog pages.')),
                    $this->getSwitchField('TRACK_OTHER', $this->l('Track All Other Pages'), $this->l('Enable click tracking on every other page (home, categories, 404, contact...).')),
                    $this->getSwitchField('EXTERNAL_ONLY', $this->l('Track External Links Only'), $this->l('Track only links that leave the shop: links to other domains and tel:, mailto:, whatsapp: links. Internal links and buttons without href are ignored.')),
                    $this->getSwitchField('DEBUG', $this->l('Debug Mode'), $this->l('Enable debug mode to log JavaScript errors to browser console.')),
                    $this->getSwitchField('DELETE_ON_UNINSTALL', $this->l('Delete Data on Uninstall'), $this->l('If enabled, all click logs are permanently deleted when the module is uninstalled or reset.')),
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
        $values = array();
        foreach ($this->configKeys as $key) {
            $values[self::CONFIG_PREFIX . $key] = Configuration::get(self::CONFIG_PREFIX . $key);
        }

        return $values;
    }

    /**
     * Process configuration form submission
     *
     * @return string Success/error message
     */
    protected function processConfigForm()
    {
        $rejected = array();
        $cssClasses = $this->validateCssSelectors(Tools::getValue(self::CONFIG_PREFIX . 'CSS_CLASSES', ''), $rejected);
        $bodyClasses = $this->validateBodyClasses(Tools::getValue(self::CONFIG_PREFIX . 'BODY_CLASSES', ''), $rejected);

        Configuration::updateValue(self::CONFIG_PREFIX . 'CSS_CLASSES', $cssClasses);
        Configuration::updateValue(self::CONFIG_PREFIX . 'BODY_CLASSES', $bodyClasses);

        foreach (array('TRACK_PRODUCT', 'TRACK_CMS', 'TRACK_OTHER', 'EXTERNAL_ONLY', 'DEBUG', 'DELETE_ON_UNINSTALL') as $key) {
            Configuration::updateValue(self::CONFIG_PREFIX . $key, (int) Tools::getValue(self::CONFIG_PREFIX . $key));
        }

        $output = $this->displayConfirmation($this->l('Settings saved successfully.'));
        if (!empty($rejected)) {
            $output .= $this->displayWarning($this->l('The following selectors were ignored because they are not valid:') . ' ' . implode(', ', $rejected));
        }

        return $output;
    }

    /**
     * Validate and clean tracked CSS selectors
     *
     * Simple words are treated as classes; full CSS selectors (attributes, combinators) are allowed.
     *
     * @param string $input Raw input
     * @param array $rejected Collects rejected lines
     * @return string Cleaned selectors, one per line
     */
    protected function validateCssSelectors($input, array &$rejected)
    {
        $cleaned = array();

        foreach (preg_split('/\r\n|\r|\n/', (string) $input) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Bare word: treat as class name
            if (preg_match('/^[a-zA-Z_\-][a-zA-Z0-9_\-]*$/', $line)) {
                $line = '.' . $line;
            }

            // Allowed CSS selector characters only (no braces, angle brackets or semicolons)
            if (Tools::strlen($line) <= 255 && preg_match('/^[a-zA-Z0-9_\-.#\[\]=^$*~|"\' :(),>+]+$/', $line)) {
                $cleaned[] = $line;
            } else {
                $rejected[] = $line;
            }
        }

        return implode("\n", array_unique($cleaned));
    }

    /**
     * Validate and clean body selectors input (classes and IDs)
     *
     * @param string $input Raw input
     * @param array $rejected Collects rejected lines
     * @return string Cleaned body selectors
     */
    protected function validateBodyClasses($input, array &$rejected)
    {
        $cleaned = array();

        foreach (preg_split('/\r\n|\r|\n/', (string) $input) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Ensure selector starts with . or #
            if (strpos($line, '.') !== 0 && strpos($line, '#') !== 0) {
                $line = '.' . $line;
            }

            if (preg_match('/^[.#][a-zA-Z][a-zA-Z0-9_\-]*$/', $line)) {
                $cleaned[] = $line;
            } else {
                $rejected[] = $line;
            }
        }

        return implode("\n", array_unique($cleaned));
    }

    /**
     * Configured selectors to track
     *
     * @return array
     */
    public function getTrackedSelectors()
    {
        $config = (string) Configuration::get(self::CONFIG_PREFIX . 'CSS_CLASSES');

        return array_values(array_filter(array_map('trim', explode("\n", $config)), 'strlen'));
    }

    /**
     * Labels stored in clicked_class for each configured selector
     *
     * @return array
     */
    public function getTrackedSelectorLabels()
    {
        return array_map(array('ClickTracker', 'getSelectorLabel'), $this->getTrackedSelectors());
    }

    /**
     * Label stored for a selector: simple ".class" selectors are stored without the dot
     * (same rule as tracker.js)
     *
     * @param string $selector CSS selector
     * @return string
     */
    public static function getSelectorLabel($selector)
    {
        return preg_match('/^\.[a-zA-Z0-9_\-]+$/', $selector) ? Tools::substr($selector, 1) : $selector;
    }

    /**
     * Context type labels
     *
     * @return array
     */
    protected function getContextTypeLabels()
    {
        return array(
            'product' => $this->l('Product'),
            'cms' => $this->l('CMS'),
            'category' => $this->l('Category'),
            'home' => $this->l('Home'),
            'other' => $this->l('Other'),
        );
    }

    /**
     * Element type labels
     *
     * @return array
     */
    protected function getElementTypeLabels()
    {
        return array(
            'whatsapp' => $this->l('WhatsApp'),
            'phone' => $this->l('Phone'),
            'maps' => $this->l('Maps'),
            'other' => $this->l('Other'),
        );
    }

    /**
     * Module admin link
     *
     * @param array $params Additional query parameters
     * @return string
     */
    protected function getModuleAdminLink(array $params = array())
    {
        $link = $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name;

        return $params ? $link . '&' . http_build_query($params) : $link;
    }

    /**
     * Return a Y-m-d date or the default value
     *
     * @param mixed $value Raw value
     * @param string $default Default value
     * @return string
     */
    protected function sanitizeDate($value, $default = '')
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $parts = explode('-', $value);
            if (checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Read log filters from the request
     *
     * @return array Non-empty filters
     */
    protected function getLogFilters()
    {
        $context = Tools::getValue('filter_context', '');
        $element = Tools::getValue('filter_element', '');

        $filters = array(
            'context_type' => array_key_exists($context, $this->getContextTypeLabels()) ? $context : '',
            'element_type' => array_key_exists($element, $this->getElementTypeLabels()) ? $element : '',
            'date_from' => $this->sanitizeDate(Tools::getValue('filter_date_from', '')),
            'date_to' => $this->sanitizeDate(Tools::getValue('filter_date_to', '')),
            'search' => trim((string) Tools::getValue('filter_search', '')),
        );

        return array_filter($filters, function ($value) {
            return $value !== '';
        });
    }

    /**
     * Render logs page
     *
     * @return string HTML
     */
    protected function renderLogsPage()
    {
        $filters = $this->getLogFilters();

        $page = max(1, (int) Tools::getValue('page', 1));
        $perPage = (int) Tools::getValue('per_page', 20);
        $orderBy = Tools::getValue('order_by', 'date_add');
        $orderDir = Tools::getValue('order_dir', 'DESC');

        // Get paginated logs
        $result = ClickTrackerLog::getLogsWithPagination($page, $perPage, $filters, $orderBy, $orderDir);

        // Only http(s) URLs are rendered as links (old rows were not validated server side)
        foreach ($result['items'] as &$item) {
            $item['has_safe_url'] = (bool) preg_match('#^https?://#i', $item['page_url']);
        }
        unset($item);

        // Query string used by pagination links (URL-encoded)
        $filterQuery = http_build_query(array(
            'filter_context' => isset($filters['context_type']) ? $filters['context_type'] : '',
            'filter_element' => isset($filters['element_type']) ? $filters['element_type'] : '',
            'filter_date_from' => isset($filters['date_from']) ? $filters['date_from'] : '',
            'filter_date_to' => isset($filters['date_to']) ? $filters['date_to'] : '',
            'filter_search' => isset($filters['search']) ? $filters['search'] : '',
        ));

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
            'filter_query' => $filterQuery,
            'order_by' => $orderBy,
            'order_dir' => $orderDir,
            'element_types' => $this->getElementTypeLabels(),
            'context_types' => $this->getContextTypeLabels(),
            'moduleLink' => $this->getModuleAdminLink(array('section' => 'logs')),
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
        $dateFrom = $this->sanitizeDate(Tools::getValue('stats_date_from'), date('Y-m-d', strtotime('-30 days')));
        $dateTo = $this->sanitizeDate(Tools::getValue('stats_date_to'), date('Y-m-d'));
        if ($dateFrom > $dateTo) {
            list($dateFrom, $dateTo) = array($dateTo, $dateFrom);
        }

        $elementTypeLabels = $this->getElementTypeLabels();

        // Overview cards (all but "this month" follow the selected period)
        $periodClicks = ClickTrackerLog::getTotalClicks(array('date_from' => $dateFrom, 'date_to' => $dateTo));
        $thisMonthClicks = ClickTrackerLog::getThisMonthClicks();
        $mostClickedType = ClickTrackerLog::getMostClickedElementType($dateFrom, $dateTo);
        $topProductsList = ClickTrackerLog::getTopProducts(10, $dateFrom, $dateTo);
        $topProduct = !empty($topProductsList) ? $topProductsList[0] : null;

        // Chart data
        $days = (strtotime($dateTo) - strtotime($dateFrom)) / 86400;
        $groupBy = $days > self::CHART_DAILY_MAX_DAYS ? 'month' : 'day';
        $clicksByDate = ClickTrackerLog::getClicksGroupedByDate($dateFrom, $dateTo, $groupBy);
        $clicksByElement = ClickTrackerLog::getClicksGroupedByElementType($dateFrom, $dateTo);
        $clicksByContext = ClickTrackerLog::getClicksGroupedByContext($dateFrom, $dateTo);
        $topPagesList = ClickTrackerLog::getTopPages(10, $dateFrom, $dateTo);

        $pieLabels = array();
        $pieCounts = array();
        $pieColors = array();
        foreach ($clicksByElement as $row) {
            $type = $row['element_type'];
            $pieLabels[] = isset($elementTypeLabels[$type]) ? $elementTypeLabels[$type] : $type;
            $pieCounts[] = (int) $row['total'];
            $pieColors[] = isset($this->elementColors[$type]) ? $this->elementColors[$type] : $this->elementColors['other'];
        }

        $this->context->smarty->assign(array(
            'period_clicks' => $periodClicks,
            'this_month_clicks' => $thisMonthClicks,
            'most_clicked_type' => $mostClickedType ? (isset($elementTypeLabels[$mostClickedType]) ? $elementTypeLabels[$mostClickedType] : $mostClickedType) : '-',
            'top_product' => $topProduct,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'has_period_clicks' => $periodClicks > 0,
            'chart_dates' => json_encode(array_keys($clicksByDate)),
            'chart_counts' => json_encode(array_values($clicksByDate)),
            'pie_labels' => json_encode($pieLabels),
            'pie_counts' => json_encode($pieCounts),
            'pie_colors' => json_encode($pieColors),
            'clicks_by_context' => $clicksByContext,
            'context_types' => $this->getContextTypeLabels(),
            'top_products' => $topProductsList,
            'top_pages' => $topPagesList,
            'moduleLink' => $this->getModuleAdminLink(array('section' => 'stats')),
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
     * Neutralize values that spreadsheet software would interpret as formulas
     *
     * @param mixed $value Cell value
     * @return string
     */
    protected function csvCell($value)
    {
        $value = (string) $value;
        if ($value !== '' && strpos("=+-@\t\r", $value[0]) !== false) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Process CSV export (rows are streamed, not loaded in memory)
     */
    protected function processExportCsv()
    {
        $filters = $this->getLogFilters();

        // Discard any buffered back office output
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $filename = 'clicktracker_export_' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Output UTF-8 BOM for Excel compatibility
        echo "\xEF\xBB\xBF";

        $output = fopen('php://output', 'w');

        fputcsv($output, array(
            $this->l('Date/Time'),
            $this->l('Context Type'),
            $this->l('Page Type'),
            $this->l('Element Type'),
            $this->l('Clicked Class'),
            $this->l('Product ID'),
            $this->l('Product Name'),
            $this->l('Category ID'),
            $this->l('Page URL'),
            $this->l('Page Title'),
        ), ';');

        $db = Db::getInstance();
        $result = ClickTrackerLog::queryLogsForExport($filters);
        while ($result && ($log = $db->nextRow($result))) {
            fputcsv($output, array(
                $log['date_add'],
                $log['context_type'],
                $this->csvCell($log['page_type']),
                $log['element_type'],
                $this->csvCell($log['clicked_class']),
                $log['id_product'] ?: '',
                $this->csvCell($log['product_name']),
                $log['id_category'] ?: '',
                $this->csvCell($log['page_url']),
                $this->csvCell($log['page_title']),
            ), ';');
        }

        fclose($output);
        exit;
    }

    /**
     * Detect the current page type and tracking context
     *
     * @param FrontController $controller Current controller
     * @return array [context_type, page_type]
     */
    protected function getPageContext($controller)
    {
        $pageType = method_exists($controller, 'getPageName') ? (string) $controller->getPageName() : (string) $controller->php_self;
        $controllerName = get_class($controller);

        if ($controller instanceof ProductController || $pageType === 'product') {
            $context = ClickTrackerLog::CONTEXT_PRODUCT;
        } elseif ($controller instanceof CmsController || $pageType === 'cms' || stripos($controllerName, 'blog') !== false || stripos($pageType, 'blog') !== false) {
            $context = ClickTrackerLog::CONTEXT_CMS;
        } elseif ($pageType === 'category') {
            $context = ClickTrackerLog::CONTEXT_CATEGORY;
        } elseif ($pageType === 'index') {
            $context = ClickTrackerLog::CONTEXT_HOME;
        } else {
            $context = ClickTrackerLog::CONTEXT_OTHER;
        }

        return array($context, Tools::substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', $pageType), 0, 64));
    }

    /**
     * Hook: actionFrontControllerSetMedia
     * Registers the tracking script and its configuration
     *
     * @param array $params Hook parameters
     */
    public function hookActionFrontControllerSetMedia($params)
    {
        $controller = $this->context->controller;
        if (!($controller instanceof FrontController) || !empty($controller->ajax)) {
            return;
        }

        $selectors = $this->getTrackedSelectors();
        if (empty($selectors)) {
            return;
        }

        list($contextType, $pageType) = $this->getPageContext($controller);

        // Page type enabled server side?
        $trackByType = array(
            ClickTrackerLog::CONTEXT_PRODUCT => 'TRACK_PRODUCT',
            ClickTrackerLog::CONTEXT_CMS => 'TRACK_CMS',
        );
        $configKey = isset($trackByType[$contextType]) ? $trackByType[$contextType] : 'TRACK_OTHER';
        $shouldTrackByPageType = (bool) Configuration::get(self::CONFIG_PREFIX . $configKey);

        // Body selectors can only be checked client side
        $bodyClassesConfig = (string) Configuration::get(self::CONFIG_PREFIX . 'BODY_CLASSES');
        $bodyClasses = array_values(array_filter(array_map('trim', explode("\n", $bodyClassesConfig)), 'strlen'));

        if (!$shouldTrackByPageType && empty($bodyClasses)) {
            return;
        }

        $productData = null;
        if ($contextType === ClickTrackerLog::CONTEXT_PRODUCT && method_exists($controller, 'getProduct')) {
            $product = $controller->getProduct();
            if (Validate::isLoadedObject($product)) {
                $productData = array('id_product' => (int) $product->id);
            }
        }

        Media::addJsDef(array(
            'et_clickTrackerConfig' => array(
                'classes' => $selectors,
                'bodyClasses' => $bodyClasses,
                'externalOnly' => (bool) Configuration::get(self::CONFIG_PREFIX . 'EXTERNAL_ONLY'),
                'debug' => (bool) Configuration::get(self::CONFIG_PREFIX . 'DEBUG'),
                'token' => Tools::getToken(false),
                // Same protocol as the page: keeps the request same-origin
                'ajaxUrl' => $this->context->link->getModuleLink($this->name, 'ajax', array(), Tools::usingSecureMode()),
                'context' => $contextType,
                'pageType' => $pageType,
                'productData' => $productData,
                'shouldTrackByPageType' => $shouldTrackByPageType,
            ),
        ));

        $controller->registerJavascript(
            'module-clicktracker-tracker',
            'modules/' . $this->name . '/views/js/tracker.js',
            array('position' => 'bottom', 'priority' => 200)
        );
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

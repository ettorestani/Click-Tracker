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
 * @version   1.2.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/ClickTrackerLog.php';
require_once dirname(__FILE__) . '/classes/ClickTrackerSource.php';

class ClickTracker extends Module
{
    /** @var string Configuration key prefix */
    const CONFIG_PREFIX = 'CLICKTRACKER_';

    /** @var int Above this number of days the time chart is grouped by month */
    const CHART_DAILY_MAX_DAYS = 92;

    /** @var array Configuration keys editable in the form */
    protected $configKeys = array(
        'CSS_CLASSES',
        'BODY_CLASSES',
        'TRACK_PRODUCT',
        'TRACK_CMS',
        'TRACK_OTHER',
        'EXTERNAL_ONLY',
        'TRACK_VIEWS',
        'FIRST_TOUCH',
        'DEBUG',
        'DELETE_ON_UNINSTALL',
    );

    /** @var array Internal configuration keys (not in the form) */
    protected $internalConfigKeys = array(
        'SCHEMA_VERSION',
        'VIEWS_SINCE',
    );

    /** @var array Switch keys of the configuration form */
    protected $switchKeys = array(
        'TRACK_PRODUCT',
        'TRACK_CMS',
        'TRACK_OTHER',
        'EXTERNAL_ONLY',
        'TRACK_VIEWS',
        'FIRST_TOUCH',
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

    /** @var array|null Parsed selector configuration cache */
    protected $selectorConfig = null;

    /**
     * Module constructor
     */
    public function __construct()
    {
        $this->name = 'clicktracker';
        $this->tab = 'analytics_stats';
        $this->version = '1.2.0';
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
            && $this->migrateExistingSchema()
            && $this->setDefaultConfiguration();
    }

    /**
     * Bring a table kept from a previous version (uninstall without data deletion) to the
     * current schema. The upgrade scripts are idempotent and never delete data.
     *
     * @return bool
     */
    protected function migrateExistingSchema()
    {
        foreach (array('1.1.0', '1.2.0') as $version) {
            $function = 'upgrade_module_' . str_replace('.', '_', $version);
            if (!function_exists($function)) {
                require_once dirname(__FILE__) . '/upgrade/upgrade-' . $version . '.php';
            }
            if (!$function($this)) {
                return false;
            }
        }

        return true;
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

        // Kept with the data, so a reinstall does not count views from the wrong date
        if ($deleteLogs) {
            foreach ($this->internalConfigKeys as $key) {
                Configuration::deleteByName(self::CONFIG_PREFIX . $key);
            }
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
        $defaults = array(
            'CSS_CLASSES' => '',
            'BODY_CLASSES' => '',
            'TRACK_PRODUCT' => 1,
            'TRACK_CMS' => 1,
            'TRACK_OTHER' => 0,
            'EXTERNAL_ONLY' => 0,
            'TRACK_VIEWS' => 1,
            'FIRST_TOUCH' => 0,
            'DEBUG' => 0,
            'DELETE_ON_UNINSTALL' => 0,
        );

        foreach ($defaults as $key => $value) {
            if (!Configuration::updateValue(self::CONFIG_PREFIX . $key, $value)) {
                return false;
            }
        }

        if (Configuration::getGlobalValue(self::CONFIG_PREFIX . 'VIEWS_SINCE') === false) {
            Configuration::updateGlobalValue(self::CONFIG_PREFIX . 'VIEWS_SINCE', date('Y-m-d H:i:s'));
        }

        // Fresh install creates the full schema
        return Configuration::updateGlobalValue(self::CONFIG_PREFIX . 'SCHEMA_VERSION', ClickTrackerLog::SCHEMA_VERSION);
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

        // Logs, statistics and export need the current schema; clicks are tracked anyway
        $schemaReady = ClickTrackerLog::isSchemaReady();
        if (!$schemaReady) {
            $output .= $this->displayWarning($this->l('The module database has not been upgraded yet: logs and statistics are not available until the module upgrade is run from the Module Manager. Clicks are still being tracked.'));
        }

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
        if ($schemaReady && Tools::isSubmit('exportCsv')) {
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
                $output .= $schemaReady ? $this->renderLogsPage() : '';
                break;
            case 'stats':
                $output .= $schemaReady ? $this->renderStatsPage() : '';
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
                        'desc' => $this->l('One per line, in the form: selector | label | type. Label and type are optional; type can be whatsapp, phone, maps or other and overrides automatic detection. Examples: .btn-wa | WhatsApp, .btn-prenota-consulenza | Book a consultation, a[href^="tel:"] | Phone | phone'),
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
                    $this->getSwitchField('TRACK_VIEWS', $this->l('Count Product Views'), $this->l('Count product page views to compute the click-through rate (clicks / views) per product. Adds one lightweight request per product page view.')),
                    $this->getSwitchField('FIRST_TOUCH', $this->l('First-Touch Source Attribution'), $this->l('Attribute each click to the source of the first page of the visit (e.g. a Google Ads landing page) instead of the page where the click happened. Stores the landing page in the browser sessionStorage: enable it only if your cookie/consent policy covers it.')),
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
        $this->selectorConfig = null;

        foreach ($this->switchKeys as $key) {
            Configuration::updateValue(self::CONFIG_PREFIX . $key, (int) Tools::getValue(self::CONFIG_PREFIX . $key));
        }

        $output = $this->displayConfirmation($this->l('Settings saved successfully.'));
        if (!empty($rejected)) {
            $output .= $this->displayWarning($this->l('The following selectors were ignored because they are not valid:') . ' '
                . htmlspecialchars(implode(', ', $rejected), ENT_QUOTES, 'UTF-8'));
        }

        return $output;
    }

    /**
     * Parse one "selector | label | type" line
     *
     * @param string $line Raw line
     * @return array|null selector, label, type; null if invalid
     */
    protected function parseSelectorLine($line)
    {
        // "|=" is a valid CSS attribute operator, not a separator
        $parts = array_map('trim', preg_split('/\|(?!=)/', trim($line)));
        if (count($parts) > 3) {
            return null;
        }

        $selector = $parts[0];
        $label = isset($parts[1]) ? $parts[1] : '';
        $type = isset($parts[2]) ? Tools::strtolower($parts[2]) : '';

        // Bare word: treat as class name
        if (preg_match('/^[a-zA-Z_\-][a-zA-Z0-9_\-]*$/', $selector)) {
            $selector = '.' . $selector;
        }

        // Allowed CSS selector characters only (no braces, angle brackets or semicolons)
        if ($selector === '' || Tools::strlen($selector) > 200 || !preg_match('/^[a-zA-Z0-9_\-.#\[\]=^$*~|"\' :(),>+]+$/', $selector)) {
            return null;
        }
        if ($label !== '' && !preg_match('/^[^<>{}|\r\n]{1,64}$/u', $label)) {
            return null;
        }
        if ($type !== '' && !ClickTrackerLog::isValidElementType($type)) {
            return null;
        }

        return array('selector' => $selector, 'label' => $label, 'type' => $type);
    }

    /**
     * Validate and clean tracked CSS selectors
     *
     * @param string $input Raw input
     * @param array $rejected Collects rejected lines
     * @return string Cleaned lines "selector | label | type"
     */
    protected function validateCssSelectors($input, array &$rejected)
    {
        $cleaned = array();
        $seen = array();

        foreach (preg_split('/\r\n|\r|\n/', (string) $input) as $line) {
            if (trim($line) === '') {
                continue;
            }

            $entry = $this->parseSelectorLine($line);
            if ($entry === null) {
                $rejected[] = trim($line);
                continue;
            }

            if (isset($seen[$entry['selector']])) {
                continue;
            }
            $seen[$entry['selector']] = true;

            $normalized = $entry['selector'];
            if ($entry['label'] !== '' || $entry['type'] !== '') {
                $normalized .= ' | ' . $entry['label'];
            }
            if ($entry['type'] !== '') {
                $normalized .= ' | ' . $entry['type'];
            }
            $cleaned[] = $normalized;
        }

        return implode("\n", $cleaned);
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
     * Configured selectors, indexed by the value stored in clicked_class
     *
     * @return array clicked_class => [selector, label, type]
     */
    public function getSelectorConfig()
    {
        if ($this->selectorConfig !== null) {
            return $this->selectorConfig;
        }

        $this->selectorConfig = array();
        $config = (string) Configuration::get(self::CONFIG_PREFIX . 'CSS_CLASSES');

        foreach (preg_split('/\r\n|\r|\n/', $config) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $entry = $this->parseSelectorLine($line);
            if ($entry !== null) {
                $this->selectorConfig[self::getSelectorLabel($entry['selector'])] = $entry;
            }
        }

        return $this->selectorConfig;
    }

    /**
     * Configured selectors to track (CSS only, for the frontend script)
     *
     * @return array
     */
    public function getTrackedSelectors()
    {
        $selectors = array();
        foreach ($this->getSelectorConfig() as $entry) {
            $selectors[] = $entry['selector'];
        }

        return $selectors;
    }

    /**
     * Human readable name of a stored clicked_class value
     *
     * @param string $clickedClass Stored value
     * @return string Configured label, or the selector itself
     */
    protected function getActionLabel($clickedClass)
    {
        $config = $this->getSelectorConfig();

        return !empty($config[$clickedClass]['label']) ? $config[$clickedClass]['label'] : $clickedClass;
    }

    /**
     * Value stored in clicked_class for a selector: simple ".class" selectors are stored
     * without the dot (same rule as tracker.js)
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
     * Traffic source labels
     *
     * @return array
     */
    protected function getSourceLabels()
    {
        return array(
            ClickTrackerSource::GOOGLE_ADS => $this->l('Google Ads'),
            ClickTrackerSource::GOOGLE_ORGANIC => $this->l('Google (organic/Shopping)'),
            ClickTrackerSource::META => $this->l('Meta (Facebook/Instagram)'),
            ClickTrackerSource::SEARCH => $this->l('Other search engines'),
            ClickTrackerSource::SOCIAL => $this->l('Other social networks'),
            ClickTrackerSource::EMAIL => $this->l('Email/Newsletter'),
            ClickTrackerSource::CAMPAIGN => $this->l('Other campaigns (UTM)'),
            ClickTrackerSource::REFERRAL => $this->l('Other websites'),
            ClickTrackerSource::DIRECT => $this->l('Direct'),
            ClickTrackerSource::INTERNAL => $this->l('Internal navigation'),
            ClickTrackerSource::UNKNOWN => $this->l('Unknown'),
        );
    }

    /**
     * Device labels
     *
     * @return array
     */
    protected function getDeviceLabels()
    {
        return array(
            ClickTrackerLog::DEVICE_MOBILE => $this->l('Mobile'),
            ClickTrackerLog::DEVICE_TABLET => $this->l('Tablet'),
            ClickTrackerLog::DEVICE_DESKTOP => $this->l('Desktop'),
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
        $values = array(
            'context_type' => array((string) Tools::getValue('filter_context', ''), $this->getContextTypeLabels()),
            'element_type' => array((string) Tools::getValue('filter_element', ''), $this->getElementTypeLabels()),
            'traffic_source' => array((string) Tools::getValue('filter_source', ''), $this->getSourceLabels()),
            'device' => array((string) Tools::getValue('filter_device', ''), $this->getDeviceLabels()),
        );

        $filters = array();
        foreach ($values as $name => $value) {
            $filters[$name] = array_key_exists($value[0], $value[1]) ? $value[0] : '';
        }

        $filters['date_from'] = $this->sanitizeDate(Tools::getValue('filter_date_from', ''));
        $filters['date_to'] = $this->sanitizeDate(Tools::getValue('filter_date_to', ''));
        $filters['search'] = trim((string) Tools::getValue('filter_search', ''));

        return array_filter($filters, function ($value) {
            return $value !== '';
        });
    }

    /**
     * Request parameters that reproduce the given filters
     *
     * @param array $filters Filters
     * @return array
     */
    protected function getFilterParams(array $filters)
    {
        $map = array(
            'filter_context' => 'context_type',
            'filter_element' => 'element_type',
            'filter_source' => 'traffic_source',
            'filter_device' => 'device',
            'filter_date_from' => 'date_from',
            'filter_date_to' => 'date_to',
            'filter_search' => 'search',
        );

        $params = array();
        foreach ($map as $param => $filter) {
            $params[$param] = isset($filters[$filter]) ? $filters[$filter] : '';
        }

        return $params;
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

        $sourceLabels = $this->getSourceLabels();
        $deviceLabels = $this->getDeviceLabels();

        foreach ($result['items'] as &$item) {
            // Only http(s) URLs are rendered as links (old rows were not validated server side)
            $item['has_safe_url'] = (bool) preg_match('#^https?://#i', $item['page_url']);
            $item['action_label'] = $this->getActionLabel($item['clicked_class']);

            $source = isset($item['traffic_source']) ? $item['traffic_source'] : null;
            $item['source_label'] = $source !== null && isset($sourceLabels[$source]) ? $sourceLabels[$source] : '';
            $device = isset($item['device']) ? $item['device'] : null;
            $item['device_label'] = $device !== null && isset($deviceLabels[$device]) ? $deviceLabels[$device] : '';
        }
        unset($item);

        $filterParams = $this->getFilterParams($filters);

        $this->context->smarty->assign(array(
            'logs' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'per_page' => $result['per_page'],
            'total_pages' => $result['total_pages'],
            'filters' => $filters + array(
                'context_type' => '',
                'element_type' => '',
                'traffic_source' => '',
                'device' => '',
                'date_from' => '',
                'date_to' => '',
                'search' => '',
            ),
            'filter_params' => $filterParams,
            // Query string used by pagination links (URL-encoded)
            'filter_query' => http_build_query($filterParams),
            'order_by' => $orderBy,
            'order_dir' => $orderDir,
            'element_types' => $this->getElementTypeLabels(),
            'context_types' => $this->getContextTypeLabels(),
            'source_types' => $sourceLabels,
            'device_types' => $deviceLabels,
            'schema_ready' => ClickTrackerLog::isSchemaReady(),
            'moduleLink' => $this->getModuleAdminLink(array('section' => 'logs')),
            'token' => Tools::getAdminTokenLite('AdminModules'),
        ));

        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/logs.tpl');
    }

    /**
     * JSON safe to print inside an inline <script> block
     *
     * @param mixed $value
     * @return string
     */
    protected function jsonForScript($value)
    {
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    /**
     * Percentage change between two values
     *
     * @param int $current Current value
     * @param int $previous Previous value
     * @return array value, previous, pct (null when previous is 0), dir (up/down/flat)
     */
    protected function compareValues($current, $previous)
    {
        $current = (int) $current;
        $previous = (int) $previous;

        return array(
            'value' => $current,
            'previous' => $previous,
            'pct' => $previous > 0 ? (int) round(($current - $previous) / $previous * 100) : null,
            'dir' => $current > $previous ? 'up' : ($current < $previous ? 'down' : 'flat'),
        );
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

        // Previous period of the same length, ending the day before the selected one
        $days = (int) round((strtotime($dateTo) - strtotime($dateFrom)) / 86400) + 1;
        $prevTo = date('Y-m-d', strtotime($dateFrom . ' -1 day'));
        $prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($days - 1) . ' days'));

        // This month so far vs the same days of the previous month
        $monthStart = date('Y-m-01');
        $prevMonthStart = date('Y-m-01', strtotime($monthStart . ' -1 month'));
        $prevMonthEnd = date('Y-m-d', min(
            strtotime($prevMonthStart . ' +' . ((int) date('j') - 1) . ' days'),
            strtotime(date('Y-m-t', strtotime($prevMonthStart)))
        ));

        $elementTypeLabels = $this->getElementTypeLabels();
        $sourceLabels = $this->getSourceLabels();
        $deviceLabels = $this->getDeviceLabels();
        $idLang = (int) $this->context->language->id;

        // Overview cards
        $periodClicks = ClickTrackerLog::getTotalClicks(array('date_from' => $dateFrom, 'date_to' => $dateTo));
        $periodCompare = $this->compareValues(
            $periodClicks,
            ClickTrackerLog::getTotalClicks(array('date_from' => $prevFrom, 'date_to' => $prevTo))
        );
        $monthCompare = $this->compareValues(
            ClickTrackerLog::getTotalClicks(array('date_from' => $monthStart, 'date_to' => date('Y-m-d'))),
            ClickTrackerLog::getTotalClicks(array('date_from' => $prevMonthStart, 'date_to' => $prevMonthEnd))
        );
        $mostClickedType = ClickTrackerLog::getMostClickedElementType($dateFrom, $dateTo);
        $topProductsList = ClickTrackerLog::getTopProducts(10, $dateFrom, $dateTo);
        $topProduct = !empty($topProductsList) ? $topProductsList[0] : null;

        // Time chart
        $groupBy = $days > self::CHART_DAILY_MAX_DAYS ? 'month' : 'day';
        $clicksByDate = ClickTrackerLog::getClicksGroupedByDate($dateFrom, $dateTo, $groupBy);

        // Element types (doughnut, colors bound to the type)
        $pieLabels = array();
        $pieCounts = array();
        $pieColors = array();
        foreach (ClickTrackerLog::getClicksGroupedByElementType($dateFrom, $dateTo) as $row) {
            $type = $row['element_type'];
            $pieLabels[] = isset($elementTypeLabels[$type]) ? $elementTypeLabels[$type] : $type;
            $pieCounts[] = (int) $row['total'];
            $pieColors[] = isset($this->elementColors[$type]) ? $this->elementColors[$type] : $this->elementColors['other'];
        }

        // Traffic sources with comparison
        $previousSources = array();
        foreach (ClickTrackerLog::getClicksGroupedBySource($prevFrom, $prevTo) as $row) {
            $previousSources[(string) $row['traffic_source']] = (int) $row['total'];
        }
        $sources = array();
        foreach (ClickTrackerLog::getClicksGroupedBySource($dateFrom, $dateTo) as $row) {
            $key = (string) $row['traffic_source'];
            $code = $key === '' ? ClickTrackerSource::UNKNOWN : $key;
            $sources[] = array(
                'label' => isset($sourceLabels[$code]) ? $sourceLabels[$code] : $code,
                'share' => $periodClicks > 0 ? round($row['total'] / $periodClicks * 100, 1) : 0,
            ) + $this->compareValues($row['total'], isset($previousSources[$key]) ? $previousSources[$key] : 0);
        }

        // Actions (configured labels)
        $actions = array();
        foreach (ClickTrackerLog::getClicksGroupedBySelector($dateFrom, $dateTo) as $row) {
            $actions[] = array(
                'label' => $this->getActionLabel($row['clicked_class']),
                'selector' => $row['clicked_class'],
                'total' => (int) $row['total'],
            );
        }

        // Devices (only clicks recorded since 1.2.0 have one)
        $devices = array();
        $deviceRows = ClickTrackerLog::getClicksGroupedByDevice($dateFrom, $dateTo);
        $deviceTotal = 0;
        foreach ($deviceRows as $row) {
            $deviceTotal += (int) $row['total'];
        }
        foreach ($deviceRows as $row) {
            $devices[] = array(
                'label' => isset($deviceLabels[$row['device']]) ? $deviceLabels[$row['device']] : $row['device'],
                'total' => (int) $row['total'],
                'share' => $deviceTotal > 0 ? round($row['total'] / $deviceTotal * 100, 1) : 0,
            );
        }

        // Hours and weekdays
        $weekdayLabels = array(
            $this->l('Mon'), $this->l('Tue'), $this->l('Wed'), $this->l('Thu'),
            $this->l('Fri'), $this->l('Sat'), $this->l('Sun'),
        );
        $hourLabels = array();
        foreach (range(0, 23) as $hour) {
            $hourLabels[] = sprintf('%02d', $hour);
        }

        // Click-through rate
        $ctr = ClickTrackerLog::getProductCtr(15, $dateFrom, $dateTo, Configuration::get(self::CONFIG_PREFIX . 'VIEWS_SINCE'), $idLang);
        $ctr['rate'] = $ctr['views'] > 0 ? round($ctr['clicks'] / $ctr['views'] * 100, 1) : null;
        $ctr['since'] = Configuration::get(self::CONFIG_PREFIX . 'VIEWS_SINCE');

        $this->context->smarty->assign(array(
            'schema_ready' => ClickTrackerLog::isSchemaReady(),
            'period_compare' => $periodCompare,
            'month_compare' => $monthCompare,
            'prev_from' => $prevFrom,
            'prev_to' => $prevTo,
            'most_clicked_type' => $mostClickedType ? (isset($elementTypeLabels[$mostClickedType]) ? $elementTypeLabels[$mostClickedType] : $mostClickedType) : '-',
            'top_product' => $topProduct,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'has_period_clicks' => $periodClicks > 0,
            'chart_dates' => $this->jsonForScript(array_keys($clicksByDate)),
            'chart_counts' => $this->jsonForScript(array_values($clicksByDate)),
            'pie_labels' => $this->jsonForScript($pieLabels),
            'pie_counts' => $this->jsonForScript($pieCounts),
            'pie_colors' => $this->jsonForScript($pieColors),
            'hour_labels' => $this->jsonForScript($hourLabels),
            'hour_counts' => $this->jsonForScript(array_values(ClickTrackerLog::getClicksByHour($dateFrom, $dateTo))),
            'weekday_labels' => $this->jsonForScript($weekdayLabels),
            'weekday_counts' => $this->jsonForScript(array_values(ClickTrackerLog::getClicksByWeekday($dateFrom, $dateTo))),
            'sources' => $sources,
            'actions' => $actions,
            'devices' => $devices,
            'device_total' => $deviceTotal,
            'top_categories' => ClickTrackerLog::getTopCategories(10, $dateFrom, $dateTo, $idLang),
            'top_manufacturers' => ClickTrackerLog::getTopManufacturers(10, $dateFrom, $dateTo),
            'ctr' => $ctr,
            'ctr_enabled' => (bool) Configuration::get(self::CONFIG_PREFIX . 'TRACK_VIEWS'),
            'clicks_by_context' => ClickTrackerLog::getClicksGroupedByContext($dateFrom, $dateTo),
            'context_types' => $this->getContextTypeLabels(),
            'top_products' => $topProductsList,
            'top_pages' => ClickTrackerLog::getTopPages(10, $dateFrom, $dateTo),
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
        $sourceLabels = $this->getSourceLabels();
        $deviceLabels = $this->getDeviceLabels();

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
            $this->l('Action'),
            $this->l('Product ID'),
            $this->l('Product Name'),
            $this->l('Category ID'),
            $this->l('Brand'),
            $this->l('Page URL'),
            $this->l('Page Title'),
            $this->l('Traffic Source'),
            'utm_source',
            'utm_medium',
            'utm_campaign',
            $this->l('Referrer'),
            $this->l('Device'),
        ), ';');

        $db = Db::getInstance();
        $result = ClickTrackerLog::queryLogsForExport($filters);
        while ($result && ($log = $db->nextRow($result))) {
            $source = isset($log['traffic_source']) ? $log['traffic_source'] : null;
            $device = isset($log['device']) ? $log['device'] : null;

            fputcsv($output, array(
                $log['date_add'],
                $log['context_type'],
                $this->csvCell($log['page_type']),
                $log['element_type'],
                $this->csvCell($log['clicked_class']),
                $this->csvCell($this->getActionLabel($log['clicked_class'])),
                $log['id_product'] ?: '',
                $this->csvCell($log['product_name']),
                $log['id_category'] ?: '',
                $this->csvCell(isset($log['manufacturer_name']) ? $log['manufacturer_name'] : ''),
                $this->csvCell($log['page_url']),
                $this->csvCell($log['page_title']),
                $source !== null && isset($sourceLabels[$source]) ? $sourceLabels[$source] : '',
                $this->csvCell(isset($log['utm_source']) ? $log['utm_source'] : ''),
                $this->csvCell(isset($log['utm_medium']) ? $log['utm_medium'] : ''),
                $this->csvCell(isset($log['utm_campaign']) ? $log['utm_campaign'] : ''),
                $this->csvCell(isset($log['referrer_host']) ? $log['referrer_host'] : ''),
                $device !== null && isset($deviceLabels[$device]) ? $deviceLabels[$device] : '',
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
     * Hook: displayHeader (used up to 1.0.0)
     * Keeps tracking active if the files are updated before the upgrade has moved the module
     * to actionFrontControllerSetMedia. Assets registered here are still rendered by the theme.
     *
     * @param array $params Hook parameters
     * @return string
     */
    public function hookDisplayHeader($params)
    {
        $this->hookActionFrontControllerSetMedia($params);

        return '';
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

        $schemaReady = ClickTrackerLog::isSchemaReady();
        $firstTouch = $schemaReady && (bool) Configuration::get(self::CONFIG_PREFIX . 'FIRST_TOUCH');

        // First-touch attribution needs the script on every page to record the landing page
        if (!$shouldTrackByPageType && empty($bodyClasses) && !$firstTouch) {
            return;
        }

        $productData = null;
        if ($contextType === ClickTrackerLog::CONTEXT_PRODUCT && method_exists($controller, 'getProduct')) {
            $product = $controller->getProduct();
            if (Validate::isLoadedObject($product)) {
                $productData = array('id_product' => (int) $product->id);
            }
        }

        // Views are counted only where product clicks are tracked, so the rate is consistent
        $trackViews = $schemaReady && $productData !== null && $shouldTrackByPageType
            && (bool) Configuration::get(self::CONFIG_PREFIX . 'TRACK_VIEWS');

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
                'trackViews' => $trackViews,
                'firstTouch' => $firstTouch,
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

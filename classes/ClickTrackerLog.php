<?php
/**
 * Click Tracker Module - ClickTrackerLog ObjectModel Class
 *
 * @author    Ettore Stani
 * @copyright 2024 Ettore Stani
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Class ClickTrackerLog
 *
 * ObjectModel for click tracking data (used for loading/deleting in the back office).
 * New rows are written with insertLog(), which only writes the columns of the current schema.
 * Text values are stored raw and must be escaped on output.
 */
class ClickTrackerLog extends ObjectModel
{
    /** @var string Schema version required by the 1.2.0 columns */
    const SCHEMA_VERSION = '1.2.0';

    /** @var int Log ID */
    public $id_clicktracker_log;

    /** @var int Shop ID */
    public $id_shop;

    /** @var string Context type: 'product', 'cms', 'category', 'home', 'other' */
    public $context_type;

    /** @var string|null PrestaShop page name (php_self or module-xxx-yyy) */
    public $page_type;

    /** @var int|null Product ID (only for product context) */
    public $id_product;

    /** @var string|null Product name (only for product context) */
    public $product_name;

    /** @var int|null Category ID (only for product context) */
    public $id_category;

    /** @var int|null Manufacturer ID (only for product context) */
    public $id_manufacturer;

    /** @var string Full page URL where click occurred */
    public $page_url;

    /** @var string Page URL without query string and fragment */
    public $page_path;

    /** @var string|null Page title (non-product contexts) */
    public $page_title;

    /** @var string Configured selector that matched the clicked element */
    public $clicked_class;

    /** @var string Element type: 'whatsapp', 'phone', 'maps', 'other' */
    public $element_type;

    /** @var string|null Traffic source code (see ClickTrackerSource) */
    public $traffic_source;

    /** @var string|null */
    public $utm_source;

    /** @var string|null */
    public $utm_medium;

    /** @var string|null */
    public $utm_campaign;

    /** @var string|null External referrer host */
    public $referrer_host;

    /** @var string|null Device: 'desktop', 'tablet', 'mobile' */
    public $device;

    /** @var string Date and time of the click */
    public $date_add;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'clicktracker_log',
        'primary' => 'id_clicktracker_log',
        'fields' => array(
            'id_shop' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true),
            'context_type' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 20),
            'page_type' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 64, 'allow_null' => true),
            'id_product' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'allow_null' => true),
            'product_name' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 255, 'allow_null' => true),
            'id_category' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'allow_null' => true),
            'id_manufacturer' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'allow_null' => true),
            'page_url' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true, 'size' => 500),
            'page_path' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 500, 'allow_null' => true),
            'page_title' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 255, 'allow_null' => true),
            'clicked_class' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true, 'size' => 255),
            'element_type' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 50),
            'traffic_source' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 32, 'allow_null' => true),
            'utm_source' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 100, 'allow_null' => true),
            'utm_medium' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 100, 'allow_null' => true),
            'utm_campaign' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 255, 'allow_null' => true),
            'referrer_host' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 255, 'allow_null' => true),
            'device' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 10, 'allow_null' => true),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate', 'required' => true),
        ),
    );

    /**
     * Valid context types
     */
    const CONTEXT_PRODUCT = 'product';
    const CONTEXT_CMS = 'cms';
    const CONTEXT_CATEGORY = 'category';
    const CONTEXT_HOME = 'home';
    const CONTEXT_OTHER = 'other';

    /**
     * Valid element types
     */
    const ELEMENT_WHATSAPP = 'whatsapp';
    const ELEMENT_PHONE = 'phone';
    const ELEMENT_MAPS = 'maps';
    const ELEMENT_OTHER = 'other';

    /**
     * Valid devices
     */
    const DEVICE_DESKTOP = 'desktop';
    const DEVICE_TABLET = 'tablet';
    const DEVICE_MOBILE = 'mobile';

    /** @var array Columns of the 1.1.0 schema (1.0.0 has a subset of them) */
    protected static $baseColumns = array(
        'id_shop', 'context_type', 'page_type', 'id_product', 'product_name', 'id_category',
        'page_url', 'page_path', 'page_title', 'clicked_class', 'element_type', 'date_add',
    );

    /** @var array Columns added in 1.2.0 */
    protected static $schema120Columns = array(
        'id_manufacturer', 'traffic_source', 'utm_source', 'utm_medium', 'utm_campaign', 'referrer_host', 'device',
    );

    /**
     * Whether the 1.2.0 upgrade has completed (new columns and tables exist)
     *
     * @return bool
     */
    public static function isSchemaReady()
    {
        return version_compare((string) Configuration::getGlobalValue('CLICKTRACKER_SCHEMA_VERSION'), self::SCHEMA_VERSION, '>=');
    }

    /**
     * Insert a click, writing only whitelisted columns that exist in the current schema.
     * Keeps tracking working if new files are deployed before the upgrade has run.
     *
     * @param array $data Column => value (raw values, escaped here)
     * @return bool
     */
    public static function insertLog(array $data)
    {
        $row = array();

        foreach (self::getWritableColumns() as $column) {
            if (!array_key_exists($column, $data) || $data[$column] === null) {
                continue;
            }
            $row[$column] = is_int($data[$column]) ? $data[$column] : pSQL((string) $data[$column]);
        }

        return (bool) Db::getInstance()->insert('clicktracker_log', $row);
    }

    /**
     * Columns that can be written: all of them once the upgrade is complete, otherwise only
     * those that exist in the table (files deployed before the upgrade, from any older version)
     *
     * @return array
     */
    protected static function getWritableColumns()
    {
        static $existing = null;

        $all = array_merge(self::$baseColumns, self::$schema120Columns);
        if (self::isSchemaReady()) {
            return $all;
        }

        if ($existing === null) {
            $existing = array();
            foreach ((array) Db::getInstance()->executeS('SHOW COLUMNS FROM `' . _DB_PREFIX_ . 'clicktracker_log`') as $column) {
                $existing[] = $column['Field'];
            }
        }

        return array_values(array_intersect($all, $existing));
    }

    /**
     * Count a product page view for the current day
     *
     * @param int $idShop Shop ID
     * @param int $idProduct Product ID
     * @return bool
     */
    public static function addProductView($idShop, $idProduct)
    {
        if (!self::isSchemaReady()) {
            return false;
        }

        return (bool) Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . 'clicktracker_product_view`
            (`id_shop`, `id_product`, `date_view`, `views`)
            VALUES (' . (int) $idShop . ', ' . (int) $idProduct . ', "' . pSQL(date('Y-m-d')) . '", 1)
            ON DUPLICATE KEY UPDATE `views` = `views` + 1');
    }

    /**
     * Get total clicks count with optional filters
     *
     * @param array $filters Optional filters (see buildFilterWhere)
     * @return int Total count
     */
    public static function getTotalClicks($filters = array())
    {
        $where = array_merge(array(self::getShopWhere()), self::buildFilterWhere($filters));

        return (int) Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'clicktracker_log`
            WHERE ' . implode(' AND ', $where));
    }

    /**
     * Get clicks grouped by date for charts, including periods without clicks
     *
     * @param string $start Start date (Y-m-d)
     * @param string $end End date (Y-m-d)
     * @param string $groupBy Group by: 'day' or 'month'
     * @return array Ordered array of date_group => total
     */
    public static function getClicksGroupedByDate($start, $end, $groupBy = 'day')
    {
        $dateFormat = $groupBy === 'month' ? '%Y-%m' : '%Y-%m-%d';

        $rows = Db::getInstance()->executeS('
            SELECT DATE_FORMAT(`date_add`, "' . $dateFormat . '") as date_group, COUNT(*) as total
            FROM `' . _DB_PREFIX_ . 'clicktracker_log`
            WHERE ' . implode(' AND ', array_merge(array(self::getShopWhere()), self::buildDateWhere($start, $end))) . '
            GROUP BY date_group
            ORDER BY date_group ASC
        ');

        $totals = array();
        foreach ((array) $rows as $row) {
            $totals[$row['date_group']] = (int) $row['total'];
        }

        // Fill the gaps so the chart shows periods with zero clicks
        $series = array();
        $phpFormat = $groupBy === 'month' ? 'Y-m' : 'Y-m-d';
        $step = $groupBy === 'month' ? '+1 month' : '+1 day';
        $cursor = strtotime($groupBy === 'month' ? date('Y-m-01', strtotime($start)) : $start);
        $last = strtotime($end);

        while ($cursor !== false && $cursor <= $last) {
            $key = date($phpFormat, $cursor);
            $series[$key] = isset($totals[$key]) ? $totals[$key] : 0;
            $cursor = strtotime($step, $cursor);
        }

        return $series;
    }

    /**
     * Count clicks grouped by a column
     *
     * @param string $column Whitelisted column name
     * @param string|null $start Start date
     * @param string|null $end End date
     * @param array $extraWhere Additional SQL conditions
     * @return array Rows with the column value and 'total', most clicked first
     */
    protected static function groupCount($column, $start = null, $end = null, array $extraWhere = array())
    {
        $where = array_merge(array(self::getShopWhere()), self::buildDateWhere($start, $end), $extraWhere);

        $rows = Db::getInstance()->executeS('
            SELECT `' . bqSQL($column) . '`, COUNT(*) as total
            FROM `' . _DB_PREFIX_ . 'clicktracker_log`
            WHERE ' . implode(' AND ', $where) . '
            GROUP BY `' . bqSQL($column) . '`
            ORDER BY total DESC
        ');

        return $rows ? $rows : array();
    }

    /**
     * @param string|null $start Start date (optional)
     * @param string|null $end End date (optional)
     * @return array element_type, total
     */
    public static function getClicksGroupedByElementType($start = null, $end = null)
    {
        return self::groupCount('element_type', $start, $end);
    }

    /**
     * @param string|null $start Start date (optional)
     * @param string|null $end End date (optional)
     * @return array context_type, total
     */
    public static function getClicksGroupedByContext($start = null, $end = null)
    {
        return self::groupCount('context_type', $start, $end);
    }

    /**
     * @param string|null $start Start date (optional)
     * @param string|null $end End date (optional)
     * @return array clicked_class, total
     */
    public static function getClicksGroupedBySelector($start = null, $end = null)
    {
        return self::groupCount('clicked_class', $start, $end);
    }

    /**
     * @param string|null $start Start date (optional)
     * @param string|null $end End date (optional)
     * @return array traffic_source, total
     */
    public static function getClicksGroupedBySource($start = null, $end = null)
    {
        return self::isSchemaReady() ? self::groupCount('traffic_source', $start, $end) : array();
    }

    /**
     * @param string|null $start Start date (optional)
     * @param string|null $end End date (optional)
     * @return array device, total (rows recorded before 1.2.0 have no device)
     */
    public static function getClicksGroupedByDevice($start = null, $end = null)
    {
        return self::isSchemaReady() ? self::groupCount('device', $start, $end, array('`device` IS NOT NULL')) : array();
    }

    /**
     * Clicks per hour of day, 0-23 (all hours present)
     *
     * @param string $start Start date
     * @param string $end End date
     * @return array hour => total
     */
    public static function getClicksByHour($start, $end)
    {
        return self::countByDatePart('HOUR(`date_add`)', range(0, 23), $start, $end);
    }

    /**
     * Clicks per weekday, 0 = Monday ... 6 = Sunday (all days present)
     *
     * @param string $start Start date
     * @param string $end End date
     * @return array weekday => total
     */
    public static function getClicksByWeekday($start, $end)
    {
        return self::countByDatePart('WEEKDAY(`date_add`)', range(0, 6), $start, $end);
    }

    /**
     * @param string $expression Trusted SQL expression on date_add
     * @param array $keys All expected keys
     * @param string $start Start date
     * @param string $end End date
     * @return array key => total
     */
    protected static function countByDatePart($expression, array $keys, $start, $end)
    {
        $rows = Db::getInstance()->executeS('
            SELECT ' . $expression . ' as part, COUNT(*) as total
            FROM `' . _DB_PREFIX_ . 'clicktracker_log`
            WHERE ' . implode(' AND ', array_merge(array(self::getShopWhere()), self::buildDateWhere($start, $end))) . '
            GROUP BY part
        ');

        $result = array_fill_keys($keys, 0);
        foreach ((array) $rows as $row) {
            $result[(int) $row['part']] = (int) $row['total'];
        }

        return $result;
    }

    /**
     * Get top clicked products
     *
     * @param int $limit Maximum number of results
     * @param string|null $start Start date (optional)
     * @param string|null $end End date (optional)
     * @return array Array of products with click counts
     */
    public static function getTopProducts($limit = 10, $start = null, $end = null)
    {
        $where = array_merge(
            array(
                self::getShopWhere(),
                '`context_type` = "' . self::CONTEXT_PRODUCT . '"',
                '`id_product` IS NOT NULL',
            ),
            self::buildDateWhere($start, $end)
        );

        $rows = Db::getInstance()->executeS('
            SELECT `id_product`, MAX(`product_name`) as product_name, COUNT(*) as total_clicks
            FROM `' . _DB_PREFIX_ . 'clicktracker_log`
            WHERE ' . implode(' AND ', $where) . '
            GROUP BY `id_product`
            ORDER BY total_clicks DESC
            LIMIT ' . (int) $limit
        );

        return $rows ? $rows : array();
    }

    /**
     * Get top categories of clicked products (default category at click time)
     *
     * @param int $limit Maximum number of results
     * @param string $start Start date
     * @param string $end End date
     * @param int $idLang Language for category names
     * @return array id_category, name, total_clicks
     */
    public static function getTopCategories($limit, $start, $end, $idLang)
    {
        $where = array_merge(
            array(self::getShopWhere('l'), 'l.`id_category` IS NOT NULL'),
            self::buildDateWhere($start, $end, 'l')
        );

        $rows = Db::getInstance()->executeS('
            SELECT l.`id_category`, MAX(cl.`name`) as name, COUNT(*) as total_clicks
            FROM `' . _DB_PREFIX_ . 'clicktracker_log` l
            LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl
                ON cl.`id_category` = l.`id_category` AND cl.`id_lang` = ' . (int) $idLang . ' AND cl.`id_shop` = l.`id_shop`
            WHERE ' . implode(' AND ', $where) . '
            GROUP BY l.`id_category`
            ORDER BY total_clicks DESC
            LIMIT ' . (int) $limit
        );

        return $rows ? $rows : array();
    }

    /**
     * Get top brands of clicked products
     *
     * @param int $limit Maximum number of results
     * @param string $start Start date
     * @param string $end End date
     * @return array id_manufacturer, name, total_clicks
     */
    public static function getTopManufacturers($limit, $start, $end)
    {
        if (!self::isSchemaReady()) {
            return array();
        }

        $where = array_merge(
            array(self::getShopWhere('l'), 'l.`id_manufacturer` > 0'),
            self::buildDateWhere($start, $end, 'l')
        );

        $rows = Db::getInstance()->executeS('
            SELECT l.`id_manufacturer`, MAX(m.`name`) as name, COUNT(*) as total_clicks
            FROM `' . _DB_PREFIX_ . 'clicktracker_log` l
            LEFT JOIN `' . _DB_PREFIX_ . 'manufacturer` m ON m.`id_manufacturer` = l.`id_manufacturer`
            WHERE ' . implode(' AND ', $where) . '
            GROUP BY l.`id_manufacturer`
            ORDER BY total_clicks DESC
            LIMIT ' . (int) $limit
        );

        return $rows ? $rows : array();
    }

    /**
     * Product views, clicks and click-through rate.
     * Only days since view counting started are considered, for both views and clicks.
     *
     * @param int $limit Maximum number of products
     * @param string $start Start date (Y-m-d)
     * @param string $end End date (Y-m-d)
     * @param string $since Date-time when view counting started
     * @param int $idLang Language for product names
     * @return array ['from' => effective start, 'views' => total, 'clicks' => total, 'products' => rows]
     */
    public static function getProductCtr($limit, $start, $end, $since, $idLang)
    {
        $result = array('from' => null, 'views' => 0, 'clicks' => 0, 'products' => array());
        if (!self::isSchemaReady() || empty($since)) {
            return $result;
        }

        // Views exist only after $since: clicks are counted from the same moment
        $sinceTs = strtotime($since);
        if ($sinceTs === false) {
            return $result;
        }
        $clicksFrom = max($start . ' 00:00:00', date('Y-m-d H:i:s', $sinceTs));
        $viewsFrom = max($start, date('Y-m-d', $sinceTs));
        if ($viewsFrom > $end) {
            return $result;
        }
        $result['from'] = $clicksFrom;

        $db = Db::getInstance();
        $shopWhere = self::getShopWhere();

        $views = array();
        foreach ((array) $db->executeS('SELECT `id_product`, SUM(`views`) as views
            FROM `' . _DB_PREFIX_ . 'clicktracker_product_view`
            WHERE ' . $shopWhere . ' AND `date_view` BETWEEN "' . pSQL($viewsFrom) . '" AND "' . pSQL($end) . '"
            GROUP BY `id_product`') as $row) {
            $views[(int) $row['id_product']] = (int) $row['views'];
        }

        $clicks = array();
        foreach ((array) $db->executeS('SELECT `id_product`, COUNT(*) as clicks
            FROM `' . _DB_PREFIX_ . 'clicktracker_log`
            WHERE ' . $shopWhere . ' AND `context_type` = "product" AND `id_product` IS NOT NULL
            AND `date_add` >= "' . pSQL($clicksFrom) . '" AND `date_add` <= "' . pSQL($end) . ' 23:59:59"
            GROUP BY `id_product`') as $row) {
            $clicks[(int) $row['id_product']] = (int) $row['clicks'];
        }

        $result['views'] = array_sum($views);
        $result['clicks'] = array_sum($clicks);

        // Products with clicks first (most clicked), then by views
        $ids = array_unique(array_merge(array_keys($clicks), array_keys($views)));
        usort($ids, function ($a, $b) use ($clicks, $views) {
            $ca = isset($clicks[$a]) ? $clicks[$a] : 0;
            $cb = isset($clicks[$b]) ? $clicks[$b] : 0;
            if ($ca !== $cb) {
                return $cb - $ca;
            }

            return (isset($views[$b]) ? $views[$b] : 0) - (isset($views[$a]) ? $views[$a] : 0);
        });
        $ids = array_slice($ids, 0, (int) $limit);
        if (empty($ids)) {
            return $result;
        }

        $names = array();
        foreach ((array) $db->executeS('SELECT `id_product`, `name` FROM `' . _DB_PREFIX_ . 'product_lang`
            WHERE `id_lang` = ' . (int) $idLang . ' AND `id_shop` = ' . (int) Context::getContext()->shop->id . '
            AND `id_product` IN (' . implode(',', array_map('intval', $ids)) . ')') as $row) {
            $names[(int) $row['id_product']] = $row['name'];
        }

        foreach ($ids as $id) {
            $v = isset($views[$id]) ? $views[$id] : 0;
            $c = isset($clicks[$id]) ? $clicks[$id] : 0;
            $result['products'][] = array(
                'id_product' => $id,
                'name' => isset($names[$id]) ? $names[$id] : '#' . $id,
                'views' => $v,
                'clicks' => $c,
                'ctr' => $v > 0 ? round($c / $v * 100, 1) : null,
            );
        }

        return $result;
    }

    /**
     * Get top clicked non-product pages, grouped by normalized URL
     *
     * @param int $limit Maximum number of results
     * @param string|null $start Start date (optional)
     * @param string|null $end End date (optional)
     * @return array Array of pages with click counts
     */
    public static function getTopPages($limit = 10, $start = null, $end = null)
    {
        $where = array_merge(
            array(
                self::getShopWhere(),
                '`context_type` != "' . self::CONTEXT_PRODUCT . '"',
            ),
            self::buildDateWhere($start, $end)
        );

        $rows = Db::getInstance()->executeS('
            SELECT `page_path` as page_url, MAX(`page_title`) as page_title, COUNT(*) as total_clicks
            FROM `' . _DB_PREFIX_ . 'clicktracker_log`
            WHERE ' . implode(' AND ', $where) . '
            GROUP BY `page_path`
            ORDER BY total_clicks DESC
            LIMIT ' . (int) $limit
        );

        return $rows ? $rows : array();
    }

    /**
     * Get logs with pagination and filters
     *
     * @param int $page Page number (1-indexed)
     * @param int $perPage Items per page
     * @param array $filters Optional filters
     * @param string $orderBy Order by field
     * @param string $orderDir Order direction (ASC/DESC)
     * @return array Array with 'items' and 'total'
     */
    public static function getLogsWithPagination($page = 1, $perPage = 20, $filters = array(), $orderBy = 'date_add', $orderDir = 'DESC')
    {
        $page = max(1, (int) $page);
        $perPage = max(1, min(100, (int) $perPage));
        $offset = ($page - 1) * $perPage;

        // Validate order direction
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        // Validate order by field
        $validFields = array('id_clicktracker_log', 'context_type', 'element_type', 'date_add', 'product_name', 'page_title');
        if (!in_array($orderBy, $validFields)) {
            $orderBy = 'date_add';
        }

        $where = array_merge(array(self::getShopWhere()), self::buildFilterWhere($filters));
        $whereClause = 'WHERE ' . implode(' AND ', $where);

        // Get total count
        $total = (int) Db::getInstance()->getValue('
            SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'clicktracker_log` ' . $whereClause
        );

        // Get items
        $items = Db::getInstance()->executeS('
            SELECT * FROM `' . _DB_PREFIX_ . 'clicktracker_log`
            ' . $whereClause . '
            ORDER BY `' . bqSQL($orderBy) . '` ' . $orderDir . '
            LIMIT ' . (int) $offset . ', ' . (int) $perPage
        );

        return array(
            'items' => $items ? $items : array(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        );
    }

    /**
     * Run the export query and return a result to iterate with Db::nextRow()
     *
     * @param array $filters Optional filters
     * @return mixed Query result resource
     */
    public static function queryLogsForExport($filters = array())
    {
        $where = array_merge(array(self::getShopWhere('l')), self::buildFilterWhere($filters, 'l'));
        $manufacturer = self::isSchemaReady()
            ? 'LEFT JOIN `' . _DB_PREFIX_ . 'manufacturer` m ON m.`id_manufacturer` = l.`id_manufacturer`'
            : '';

        return Db::getInstance()->query('
            SELECT l.*' . ($manufacturer ? ', m.`name` as manufacturer_name' : '') . '
            FROM `' . _DB_PREFIX_ . 'clicktracker_log` l
            ' . $manufacturer . '
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY l.`date_add` DESC
        ');
    }

    /**
     * Validate context type
     *
     * @param string $context Context type to validate
     * @return bool True if valid
     */
    public static function isValidContextType($context)
    {
        return in_array($context, array(
            self::CONTEXT_PRODUCT,
            self::CONTEXT_CMS,
            self::CONTEXT_CATEGORY,
            self::CONTEXT_HOME,
            self::CONTEXT_OTHER,
        ), true);
    }

    /**
     * Validate element type
     *
     * @param string $type Element type to validate
     * @return bool True if valid
     */
    public static function isValidElementType($type)
    {
        return in_array($type, array(
            self::ELEMENT_WHATSAPP,
            self::ELEMENT_PHONE,
            self::ELEMENT_MAPS,
            self::ELEMENT_OTHER,
        ), true);
    }

    /**
     * Get most clicked element type
     *
     * @param string|null $start Start date (optional)
     * @param string|null $end End date (optional)
     * @return string|null Most clicked element type or null
     */
    public static function getMostClickedElementType($start = null, $end = null)
    {
        $rows = self::getClicksGroupedByElementType($start, $end);

        return !empty($rows) ? $rows[0]['element_type'] : null;
    }

    /**
     * Strip query string and fragment from a URL
     *
     * @param string $url Full URL
     * @return string
     */
    public static function normalizeUrl($url)
    {
        $url = explode('#', $url, 2);
        $url = explode('?', $url[0], 2);

        return $url[0];
    }

    /**
     * SQL restriction on the shops of the current back office context
     *
     * @param string $alias Optional table alias
     * @return string
     */
    protected static function getShopWhere($alias = '')
    {
        $shopIds = array_map('intval', (array) Shop::getContextListShopID());
        if (empty($shopIds)) {
            $shopIds = array((int) Context::getContext()->shop->id);
        }

        return self::column('id_shop', $alias) . ' IN (' . implode(',', $shopIds) . ')';
    }

    /**
     * @param string $name Column name
     * @param string $alias Optional table alias
     * @return string Quoted column reference
     */
    protected static function column($name, $alias = '')
    {
        return ($alias ? $alias . '.' : '') . '`' . $name . '`';
    }

    /**
     * Build date range conditions
     *
     * @param string|null $start Start date (Y-m-d)
     * @param string|null $end End date (Y-m-d)
     * @param string $alias Optional table alias
     * @return array SQL conditions
     */
    protected static function buildDateWhere($start = null, $end = null, $alias = '')
    {
        $where = array();

        if (!empty($start)) {
            $where[] = self::column('date_add', $alias) . ' >= "' . pSQL($start) . ' 00:00:00"';
        }

        if (!empty($end)) {
            $where[] = self::column('date_add', $alias) . ' <= "' . pSQL($end) . ' 23:59:59"';
        }

        return $where;
    }

    /**
     * Build conditions for the logs list / export filters
     *
     * @param array $filters Filters
     * @param string $alias Optional table alias
     * @return array SQL conditions
     */
    protected static function buildFilterWhere($filters, $alias = '')
    {
        $where = array();

        foreach (array('context_type', 'element_type') as $field) {
            if (!empty($filters[$field])) {
                $where[] = self::column($field, $alias) . ' = "' . pSQL($filters[$field]) . '"';
            }
        }

        if (self::isSchemaReady()) {
            foreach (array('traffic_source', 'device') as $field) {
                if (!empty($filters[$field])) {
                    $where[] = self::column($field, $alias) . ' = "' . pSQL($filters[$field]) . '"';
                }
            }
        }

        $where = array_merge($where, self::buildDateWhere(
            isset($filters['date_from']) ? $filters['date_from'] : null,
            isset($filters['date_to']) ? $filters['date_to'] : null,
            $alias
        ));

        if (!empty($filters['search'])) {
            $search = pSQL($filters['search']);
            $where[] = '(' . self::column('product_name', $alias) . ' LIKE "%' . $search . '%"'
                . ' OR ' . self::column('page_url', $alias) . ' LIKE "%' . $search . '%"'
                . ' OR ' . self::column('page_title', $alias) . ' LIKE "%' . $search . '%")';
        }

        return $where;
    }
}

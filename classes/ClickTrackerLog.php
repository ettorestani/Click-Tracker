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
 * ObjectModel for storing click tracking data.
 * Text values are stored raw and must be escaped on output.
 */
class ClickTrackerLog extends ObjectModel
{
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

    /** @var string Date and time of the click */
    public $date_add;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'clicktracker_log',
        'primary' => 'id_clicktracker_log',
        'fields' => array(
            'id_shop' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true,
            ),
            'context_type' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'required' => true,
                'size' => 20,
            ),
            'page_type' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'size' => 64,
                'allow_null' => true,
            ),
            'id_product' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'allow_null' => true,
            ),
            'product_name' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isString',
                'size' => 255,
                'allow_null' => true,
            ),
            'id_category' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'allow_null' => true,
            ),
            'page_url' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isString',
                'required' => true,
                'size' => 500,
            ),
            'page_path' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isString',
                'size' => 500,
                'allow_null' => true,
            ),
            'page_title' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isString',
                'size' => 255,
                'allow_null' => true,
            ),
            'clicked_class' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isString',
                'required' => true,
                'size' => 255,
            ),
            'element_type' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'required' => true,
                'size' => 50,
            ),
            'date_add' => array(
                'type' => self::TYPE_DATE,
                'validate' => 'isDate',
                'required' => true,
            ),
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
     * Get total clicks count with optional filters
     *
     * @param array $filters Optional filters (context_type, element_type, date_from, date_to)
     * @return int Total count
     */
    public static function getTotalClicks($filters = array())
    {
        $sql = new DbQuery();
        $sql->select('COUNT(*)');
        $sql->from('clicktracker_log');
        $sql->where(self::getShopWhere());

        foreach (self::buildFilterWhere($filters) as $condition) {
            $sql->where($condition);
        }

        return (int) Db::getInstance()->getValue($sql);
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
            WHERE ' . self::getShopWhere() . '
            AND ' . implode(' AND ', self::buildDateWhere($start, $end)) . '
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
     * Get clicks grouped by element type
     *
     * @param string|null $start Start date (optional)
     * @param string|null $end End date (optional)
     * @return array Array with element_type => count
     */
    public static function getClicksGroupedByElementType($start = null, $end = null)
    {
        $sql = new DbQuery();
        $sql->select('`element_type`, COUNT(*) as total');
        $sql->from('clicktracker_log');
        $sql->where(self::getShopWhere());

        foreach (self::buildDateWhere($start, $end) as $condition) {
            $sql->where($condition);
        }

        $sql->groupBy('`element_type`');
        $sql->orderBy('total DESC');

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Get clicks grouped by context type
     *
     * @param string|null $start Start date (optional)
     * @param string|null $end End date (optional)
     * @return array Array with context_type => count
     */
    public static function getClicksGroupedByContext($start = null, $end = null)
    {
        $sql = new DbQuery();
        $sql->select('`context_type`, COUNT(*) as total');
        $sql->from('clicktracker_log');
        $sql->where(self::getShopWhere());

        foreach (self::buildDateWhere($start, $end) as $condition) {
            $sql->where($condition);
        }

        $sql->groupBy('`context_type`');
        $sql->orderBy('total DESC');

        return Db::getInstance()->executeS($sql);
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

        return Db::getInstance()->executeS('
            SELECT `id_product`, MAX(`product_name`) as product_name, COUNT(*) as total_clicks
            FROM `' . _DB_PREFIX_ . 'clicktracker_log`
            WHERE ' . implode(' AND ', $where) . '
            GROUP BY `id_product`
            ORDER BY total_clicks DESC
            LIMIT ' . (int) $limit
        );
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

        return Db::getInstance()->executeS('
            SELECT `page_path` as page_url, MAX(`page_title`) as page_title, COUNT(*) as total_clicks
            FROM `' . _DB_PREFIX_ . 'clicktracker_log`
            WHERE ' . implode(' AND ', $where) . '
            GROUP BY `page_path`
            ORDER BY total_clicks DESC
            LIMIT ' . (int) $limit
        );
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
     * Run the export query and return an unbuffered result to iterate with Db::nextRow()
     *
     * @param array $filters Optional filters
     * @return mixed Query result resource
     */
    public static function queryLogsForExport($filters = array())
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('clicktracker_log');
        $sql->where(self::getShopWhere());

        foreach (self::buildFilterWhere($filters) as $condition) {
            $sql->where($condition);
        }

        $sql->orderBy('`date_add` DESC');

        return Db::getInstance()->query($sql);
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
     * Get this month's clicks count
     *
     * @return int Click count for current month
     */
    public static function getThisMonthClicks()
    {
        return self::getTotalClicks(array(
            'date_from' => date('Y-m-01'),
            'date_to' => date('Y-m-t'),
        ));
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
     * @return string
     */
    protected static function getShopWhere()
    {
        $shopIds = array_map('intval', (array) Shop::getContextListShopID());
        if (empty($shopIds)) {
            $shopIds = array((int) Context::getContext()->shop->id);
        }

        return '`id_shop` IN (' . implode(',', $shopIds) . ')';
    }

    /**
     * Build date range conditions
     *
     * @param string|null $start Start date (Y-m-d)
     * @param string|null $end End date (Y-m-d)
     * @return array SQL conditions
     */
    protected static function buildDateWhere($start = null, $end = null)
    {
        $where = array();

        if (!empty($start)) {
            $where[] = '`date_add` >= "' . pSQL($start) . ' 00:00:00"';
        }

        if (!empty($end)) {
            $where[] = '`date_add` <= "' . pSQL($end) . ' 23:59:59"';
        }

        return $where;
    }

    /**
     * Build conditions for the logs list / export filters
     *
     * @param array $filters Filters
     * @return array SQL conditions
     */
    protected static function buildFilterWhere($filters)
    {
        $where = array();

        if (!empty($filters['context_type'])) {
            $where[] = '`context_type` = "' . pSQL($filters['context_type']) . '"';
        }

        if (!empty($filters['element_type'])) {
            $where[] = '`element_type` = "' . pSQL($filters['element_type']) . '"';
        }

        $where = array_merge($where, self::buildDateWhere(
            isset($filters['date_from']) ? $filters['date_from'] : null,
            isset($filters['date_to']) ? $filters['date_to'] : null
        ));

        if (!empty($filters['search'])) {
            $search = pSQL($filters['search']);
            $where[] = '(`product_name` LIKE "%' . $search . '%" OR `page_url` LIKE "%' . $search . '%" OR `page_title` LIKE "%' . $search . '%")';
        }

        return $where;
    }
}

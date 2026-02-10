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
 * ObjectModel for storing click tracking data
 */
class ClickTrackerLog extends ObjectModel
{
    /** @var int Log ID */
    public $id_clicktracker_log;

    /** @var string Context type: 'product' or 'cms' */
    public $context_type;

    /** @var int|null Product ID (only for product context) */
    public $id_product;

    /** @var string|null Product name (only for product context) */
    public $product_name;

    /** @var int|null Category ID (only for product context) */
    public $id_category;

    /** @var string Page URL where click occurred */
    public $page_url;

    /** @var string|null Page title (only for CMS context) */
    public $page_title;

    /** @var string CSS class of clicked element */
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
            'context_type' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'required' => true,
                'size' => 20,
            ),
            'id_product' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'allow_null' => true,
            ),
            'product_name' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
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
                'validate' => 'isUrl',
                'required' => true,
                'size' => 500,
            ),
            'page_title' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'size' => 255,
                'allow_null' => true,
            ),
            'clicked_class' => array(
                'type' => self::TYPE_STRING,
                'validate' => 'isGenericName',
                'required' => true,
                'size' => 100,
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

    /**
     * Valid element types
     */
    const ELEMENT_WHATSAPP = 'whatsapp';
    const ELEMENT_PHONE = 'phone';
    const ELEMENT_MAPS = 'maps';
    const ELEMENT_OTHER = 'other';

    /**
     * Get clicks by product ID
     *
     * @param int $id_product Product ID
     * @param int $limit Maximum number of results
     * @return array Array of click logs
     */
    public static function getClicksByProduct($id_product, $limit = 100)
    {
        $id_product = (int) $id_product;

        return Db::getInstance()->executeS('
            SELECT * FROM `' . _DB_PREFIX_ . 'clicktracker_log`
            WHERE `id_product` = ' . $id_product . '
            ORDER BY `date_add` DESC
            LIMIT ' . (int) $limit
        );
    }

    /**
     * Get clicks by date range
     *
     * @param string $start Start date (Y-m-d format)
     * @param string $end End date (Y-m-d format)
     * @param string|null $context Filter by context type
     * @return array Array of click logs
     */
    public static function getClicksByDateRange($start, $end, $context = null)
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('clicktracker_log');
        $sql->where('`date_add` >= "' . pSQL($start) . ' 00:00:00"');
        $sql->where('`date_add` <= "' . pSQL($end) . ' 23:59:59"');

        if ($context !== null) {
            $sql->where('`context_type` = "' . pSQL($context) . '"');
        }

        $sql->orderBy('`date_add` DESC');

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Get clicks by element type
     *
     * @param string $type Element type
     * @param int $limit Maximum number of results
     * @return array Array of click logs
     */
    public static function getClicksByElementType($type, $limit = 100)
    {
        return Db::getInstance()->executeS('
            SELECT * FROM `' . _DB_PREFIX_ . 'clicktracker_log`
            WHERE `element_type` = "' . pSQL($type) . '"
            ORDER BY `date_add` DESC
            LIMIT ' . (int) $limit
        );
    }

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

        if (!empty($filters['context_type'])) {
            $sql->where('`context_type` = "' . pSQL($filters['context_type']) . '"');
        }

        if (!empty($filters['element_type'])) {
            $sql->where('`element_type` = "' . pSQL($filters['element_type']) . '"');
        }

        if (!empty($filters['date_from'])) {
            $sql->where('`date_add` >= "' . pSQL($filters['date_from']) . ' 00:00:00"');
        }

        if (!empty($filters['date_to'])) {
            $sql->where('`date_add` <= "' . pSQL($filters['date_to']) . ' 23:59:59"');
        }

        return (int) Db::getInstance()->getValue($sql);
    }

    /**
     * Get clicks grouped by date for charts
     *
     * @param string $start Start date
     * @param string $end End date
     * @param string $groupBy Group by: 'day', 'week', 'month'
     * @return array Array with date => count
     */
    public static function getClicksGroupedByDate($start, $end, $groupBy = 'day')
    {
        switch ($groupBy) {
            case 'week':
                $dateFormat = '%Y-%u';
                break;
            case 'month':
                $dateFormat = '%Y-%m';
                break;
            default:
                $dateFormat = '%Y-%m-%d';
        }

        return Db::getInstance()->executeS('
            SELECT DATE_FORMAT(`date_add`, "' . $dateFormat . '") as date_group, COUNT(*) as total
            FROM `' . _DB_PREFIX_ . 'clicktracker_log`
            WHERE `date_add` >= "' . pSQL($start) . ' 00:00:00"
            AND `date_add` <= "' . pSQL($end) . ' 23:59:59"
            GROUP BY date_group
            ORDER BY date_group ASC
        ');
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

        if ($start !== null) {
            $sql->where('`date_add` >= "' . pSQL($start) . ' 00:00:00"');
        }

        if ($end !== null) {
            $sql->where('`date_add` <= "' . pSQL($end) . ' 23:59:59"');
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

        if ($start !== null) {
            $sql->where('`date_add` >= "' . pSQL($start) . ' 00:00:00"');
        }

        if ($end !== null) {
            $sql->where('`date_add` <= "' . pSQL($end) . ' 23:59:59"');
        }

        $sql->groupBy('`context_type`');

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
        $where = array();
        $where[] = '`context_type` = "product"';
        $where[] = '`id_product` IS NOT NULL';

        if ($start !== null) {
            $where[] = '`date_add` >= "' . pSQL($start) . ' 00:00:00"';
        }

        if ($end !== null) {
            $where[] = '`date_add` <= "' . pSQL($end) . ' 23:59:59"';
        }

        $whereClause = implode(' AND ', $where);

        return Db::getInstance()->executeS('
            SELECT `id_product`, `product_name`, COUNT(*) as total_clicks
            FROM `' . _DB_PREFIX_ . 'clicktracker_log`
            WHERE ' . $whereClause . '
            GROUP BY `id_product`
            ORDER BY total_clicks DESC
            LIMIT ' . (int) $limit
        );
    }

    /**
     * Get top clicked CMS/blog pages
     *
     * @param int $limit Maximum number of results
     * @param string|null $start Start date (optional)
     * @param string|null $end End date (optional)
     * @return array Array of pages with click counts
     */
    public static function getTopPages($limit = 10, $start = null, $end = null)
    {
        $where = array();
        $where[] = '`context_type` = "cms"';

        if ($start !== null) {
            $where[] = '`date_add` >= "' . pSQL($start) . ' 00:00:00"';
        }

        if ($end !== null) {
            $where[] = '`date_add` <= "' . pSQL($end) . ' 23:59:59"';
        }

        $whereClause = implode(' AND ', $where);

        return Db::getInstance()->executeS('
            SELECT `page_url`, `page_title`, COUNT(*) as total_clicks
            FROM `' . _DB_PREFIX_ . 'clicktracker_log`
            WHERE ' . $whereClause . '
            GROUP BY `page_url`
            ORDER BY total_clicks DESC
            LIMIT ' . (int) $limit
        );
    }

    /**
     * Delete old log entries
     *
     * @param int $days Delete entries older than this many days
     * @return bool Success status
     */
    public static function deleteOldLogs($days)
    {
        $days = (int) $days;
        if ($days < 1) {
            return false;
        }

        return Db::getInstance()->execute('
            DELETE FROM `' . _DB_PREFIX_ . 'clicktracker_log`
            WHERE `date_add` < DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)
        ');
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

        // Build base SQL for filters
        $where = array();

        if (!empty($filters['context_type'])) {
            $where[] = '`context_type` = "' . pSQL($filters['context_type']) . '"';
        }

        if (!empty($filters['element_type'])) {
            $where[] = '`element_type` = "' . pSQL($filters['element_type']) . '"';
        }

        if (!empty($filters['date_from'])) {
            $where[] = '`date_add` >= "' . pSQL($filters['date_from']) . ' 00:00:00"';
        }

        if (!empty($filters['date_to'])) {
            $where[] = '`date_add` <= "' . pSQL($filters['date_to']) . ' 23:59:59"';
        }

        if (!empty($filters['search'])) {
            $search = pSQL($filters['search']);
            $where[] = '(`product_name` LIKE "%' . $search . '%" OR `page_url` LIKE "%' . $search . '%" OR `page_title` LIKE "%' . $search . '%")';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

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
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        );
    }

    /**
     * Get all logs for export with filters
     *
     * @param array $filters Optional filters
     * @return array Array of all matching logs
     */
    public static function getLogsForExport($filters = array())
    {
        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('clicktracker_log');

        if (!empty($filters['context_type'])) {
            $sql->where('`context_type` = "' . pSQL($filters['context_type']) . '"');
        }

        if (!empty($filters['element_type'])) {
            $sql->where('`element_type` = "' . pSQL($filters['element_type']) . '"');
        }

        if (!empty($filters['date_from'])) {
            $sql->where('`date_add` >= "' . pSQL($filters['date_from']) . ' 00:00:00"');
        }

        if (!empty($filters['date_to'])) {
            $sql->where('`date_add` <= "' . pSQL($filters['date_to']) . ' 23:59:59"');
        }

        if (!empty($filters['search'])) {
            $search = pSQL($filters['search']);
            $sql->where('(`product_name` LIKE "%' . $search . '%" OR `page_url` LIKE "%' . $search . '%" OR `page_title` LIKE "%' . $search . '%")');
        }

        $sql->orderBy('`date_add` DESC');

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Validate context type
     *
     * @param string $context Context type to validate
     * @return bool True if valid
     */
    public static function isValidContextType($context)
    {
        return in_array($context, array(self::CONTEXT_PRODUCT, self::CONTEXT_CMS));
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
        ));
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
        $sql = 'SELECT `element_type`, COUNT(*) as total FROM `' . _DB_PREFIX_ . 'clicktracker_log`';

        $where = array();
        if ($start !== null) {
            $where[] = '`date_add` >= "' . pSQL($start) . ' 00:00:00"';
        }
        if ($end !== null) {
            $where[] = '`date_add` <= "' . pSQL($end) . ' 23:59:59"';
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' GROUP BY `element_type` ORDER BY total DESC';

        $result = Db::getInstance()->getRow($sql);

        return $result ? $result['element_type'] : null;
    }

    /**
     * Get this month's clicks count
     *
     * @return int Click count for current month
     */
    public static function getThisMonthClicks()
    {
        $start = date('Y-m-01');
        $end = date('Y-m-t');

        return self::getTotalClicks(array(
            'date_from' => $start,
            'date_to' => $end,
        ));
    }
}

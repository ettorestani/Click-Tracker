<?php
/**
 * Click Tracker Module - Upgrade to 1.2.0
 *
 * Non-destructive and re-runnable: only adds columns/indexes/tables and backfills
 * the new columns of existing rows. Existing click logs are preserved.
 *
 * @author    Ettore Stani
 * @copyright 2024 Ettore Stani
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../classes/ClickTrackerSource.php';

/**
 * @param ClickTracker $module
 * @return bool
 */
function upgrade_module_1_2_0($module)
{
    // Backfills can take a while on large tables; the script is re-runnable if interrupted
    @set_time_limit(0);

    $db = Db::getInstance();
    $table = _DB_PREFIX_ . 'clicktracker_log';

    $columns = array();
    foreach ((array) $db->executeS('SHOW COLUMNS FROM `' . $table . '`') as $column) {
        $columns[$column['Field']] = true;
    }

    $newColumns = array(
        'id_manufacturer' => 'INT(11) UNSIGNED NULL DEFAULT NULL AFTER `id_category`',
        'traffic_source' => 'VARCHAR(32) NULL DEFAULT NULL AFTER `element_type`',
        'utm_source' => 'VARCHAR(100) NULL DEFAULT NULL AFTER `traffic_source`',
        'utm_medium' => 'VARCHAR(100) NULL DEFAULT NULL AFTER `utm_source`',
        'utm_campaign' => 'VARCHAR(255) NULL DEFAULT NULL AFTER `utm_medium`',
        'referrer_host' => 'VARCHAR(255) NULL DEFAULT NULL AFTER `utm_campaign`',
        'device' => 'VARCHAR(10) NULL DEFAULT NULL AFTER `referrer_host`',
    );

    foreach ($newColumns as $name => $definition) {
        if (!isset($columns[$name]) && !$db->execute('ALTER TABLE `' . $table . '` ADD `' . $name . '` ' . $definition)) {
            return false;
        }
    }

    $indexes = array();
    foreach ((array) $db->executeS('SHOW INDEX FROM `' . $table . '`') as $index) {
        $indexes[$index['Key_name']] = true;
    }
    if (!isset($indexes['idx_source_date'])) {
        $db->execute('ALTER TABLE `' . $table . '` ADD INDEX `idx_source_date` (`traffic_source`, `date_add`)');
    }
    if (!isset($indexes['idx_manufacturer'])) {
        $db->execute('ALTER TABLE `' . $table . '` ADD INDEX `idx_manufacturer` (`id_manufacturer`)');
    }

    if (!$db->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'clicktracker_product_view` (
        `id_shop` INT(11) UNSIGNED NOT NULL,
        `id_product` INT(11) UNSIGNED NOT NULL,
        `date_view` DATE NOT NULL,
        `views` INT(11) UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (`id_shop`, `id_product`, `date_view`),
        INDEX `idx_date_view` (`date_view`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci')) {
        return false;
    }

    // Backfill brand of tracked products (current catalog value)
    $db->execute('UPDATE `' . $table . '` l
        INNER JOIN `' . _DB_PREFIX_ . 'product` p ON p.`id_product` = l.`id_product`
        SET l.`id_manufacturer` = p.`id_manufacturer`
        WHERE l.`id_manufacturer` IS NULL AND l.`id_product` IS NOT NULL AND p.`id_manufacturer` > 0');

    // Backfill traffic source from the stored page URL, in batches (referrer was not recorded before 1.2.0)
    $lastId = 0;
    do {
        $rows = $db->executeS('SELECT `id_clicktracker_log`, `page_url` FROM `' . $table . '`
            WHERE `traffic_source` IS NULL AND `id_clicktracker_log` > ' . (int) $lastId . '
            ORDER BY `id_clicktracker_log` ASC LIMIT 500');

        foreach ((array) $rows as $row) {
            $lastId = (int) $row['id_clicktracker_log'];
            $source = ClickTrackerSource::classify($row['page_url'], null);

            $data = array();
            foreach ($source as $field => $value) {
                if ($value !== null) {
                    $data[$field] = pSQL($value);
                }
            }

            if ($data && !$db->update('clicktracker_log', $data, '`id_clicktracker_log` = ' . $lastId)) {
                return false;
            }
        }
    } while (!empty($rows));

    // New configuration keys: defaults keep the site behaviour predictable
    $defaults = array(
        'CLICKTRACKER_FIRST_TOUCH' => 0,
        'CLICKTRACKER_TRACK_VIEWS' => 1,
        'CLICKTRACKER_VIEWS_SINCE' => date('Y-m-d H:i:s'),
    );
    foreach ($defaults as $key => $value) {
        if (Configuration::getGlobalValue($key) === false) {
            Configuration::updateGlobalValue($key, $value);
        }
    }

    // Written last (global, also on multistore): new columns are written only once this is set
    return Configuration::updateGlobalValue('CLICKTRACKER_SCHEMA_VERSION', '1.2.0');
}

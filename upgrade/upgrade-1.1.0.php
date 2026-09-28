<?php
/**
 * Click Tracker Module - Upgrade to 1.1.0
 *
 * Non-destructive: only adds columns/indexes/tables and backfills new columns.
 * Existing click logs are preserved.
 *
 * @author    Ettore Stani
 * @copyright 2024 Ettore Stani
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @param ClickTracker $module
 * @return bool
 */
function upgrade_module_1_1_0($module)
{
    $db = Db::getInstance();
    $table = _DB_PREFIX_ . 'clicktracker_log';

    $columns = array();
    foreach ((array) $db->executeS('SHOW COLUMNS FROM `' . $table . '`') as $column) {
        $columns[$column['Field']] = $column;
    }

    $indexes = array();
    foreach ((array) $db->executeS('SHOW INDEX FROM `' . $table . '`') as $index) {
        $indexes[$index['Key_name']] = true;
    }

    $queries = array();

    if (!isset($columns['id_shop'])) {
        $queries[] = 'ALTER TABLE `' . $table . '` ADD `id_shop` INT(11) UNSIGNED NOT NULL DEFAULT 1 AFTER `id_clicktracker_log`';
    }
    if (!isset($columns['page_type'])) {
        $queries[] = 'ALTER TABLE `' . $table . '` ADD `page_type` VARCHAR(64) NULL DEFAULT NULL AFTER `context_type`';
    }
    if (!isset($columns['page_path'])) {
        $queries[] = 'ALTER TABLE `' . $table . '` ADD `page_path` VARCHAR(500) NULL DEFAULT NULL AFTER `page_url`';
    }
    if (isset($columns['clicked_class']) && stripos($columns['clicked_class']['Type'], 'varchar(255)') === false) {
        $queries[] = 'ALTER TABLE `' . $table . '` MODIFY `clicked_class` VARCHAR(255) NOT NULL';
    }

    foreach ($queries as $query) {
        if (!$db->execute($query)) {
            return false;
        }
    }

    if (!isset($indexes['idx_shop_date'])) {
        $db->execute('ALTER TABLE `' . $table . '` ADD INDEX `idx_shop_date` (`id_shop`, `date_add`)');
    }
    if (!isset($indexes['idx_page_path'])) {
        $db->execute('ALTER TABLE `' . $table . '` ADD INDEX `idx_page_path` (`page_path`(191))');
    }

    // Backfill: shop, normalized path (URL without query string / fragment), page type for products
    $db->execute('UPDATE `' . $table . '` SET `id_shop` = ' . (int) Configuration::get('PS_SHOP_DEFAULT') . ' WHERE `id_shop` = 0');
    $db->execute('UPDATE `' . $table . '`
        SET `page_path` = SUBSTRING_INDEX(SUBSTRING_INDEX(`page_url`, "#", 1), "?", 1)
        WHERE `page_path` IS NULL');
    $db->execute('UPDATE `' . $table . '` SET `page_type` = "product" WHERE `page_type` IS NULL AND `context_type` = "product"');

    // Values were stored HTML-encoded by 1.0.0: decode them (output is escaped by templates)
    $rows = $db->executeS('SELECT `id_clicktracker_log`, `product_name`, `page_title`, `clicked_class`
        FROM `' . $table . '`
        WHERE `product_name` LIKE "%&%;%" OR `page_title` LIKE "%&%;%" OR `clicked_class` LIKE "%&%;%"');
    foreach ((array) $rows as $row) {
        $data = array();
        foreach (array('product_name', 'page_title', 'clicked_class') as $field) {
            if ($row[$field] !== null) {
                $data[$field] = pSQL(html_entity_decode($row[$field], ENT_QUOTES, 'UTF-8'));
            }
        }
        if ($data) {
            $db->update('clicktracker_log', $data, '`id_clicktracker_log` = ' . (int) $row['id_clicktracker_log']);
        }
    }

    // Rate limiting table
    if (!$db->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'clicktracker_rate` (
        `ip_hash` CHAR(40) NOT NULL,
        `window_start` INT(11) UNSIGNED NOT NULL,
        `hits` INT(11) UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (`ip_hash`),
        INDEX `idx_window_start` (`window_start`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci')) {
        return false;
    }

    // New configuration keys (keep current behaviour by default)
    if (Configuration::get('CLICKTRACKER_TRACK_OTHER') === false) {
        Configuration::updateValue('CLICKTRACKER_TRACK_OTHER', 0);
    }
    if (Configuration::get('CLICKTRACKER_DELETE_ON_UNINSTALL') === false) {
        Configuration::updateValue('CLICKTRACKER_DELETE_ON_UNINSTALL', 0);
    }

    // Script is now registered through the media pipeline instead of an inline header block.
    // displayHeader may be stored under its legacy alias "Header": unregister both, ignoring the result.
    $module->unregisterHook('displayHeader');
    $module->unregisterHook('Header');

    return $module->registerHook('actionFrontControllerSetMedia');
}

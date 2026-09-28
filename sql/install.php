<?php
/**
 * Click Tracker Module - SQL Install Script
 *
 * @author    Ettore Stani
 * @copyright 2024 Ettore Stani
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

$sql = array();

// Create main tracking log table
$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'clicktracker_log` (
    `id_clicktracker_log` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_shop` INT(11) UNSIGNED NOT NULL DEFAULT 1,
    `context_type` VARCHAR(20) NOT NULL DEFAULT "product",
    `page_type` VARCHAR(64) NULL DEFAULT NULL,
    `id_product` INT(11) UNSIGNED NULL DEFAULT NULL,
    `product_name` VARCHAR(255) NULL DEFAULT NULL,
    `id_category` INT(11) UNSIGNED NULL DEFAULT NULL,
    `page_url` VARCHAR(500) NOT NULL,
    `page_path` VARCHAR(500) NULL DEFAULT NULL,
    `page_title` VARCHAR(255) NULL DEFAULT NULL,
    `clicked_class` VARCHAR(255) NOT NULL,
    `element_type` VARCHAR(50) NOT NULL,
    `date_add` DATETIME NOT NULL,
    PRIMARY KEY (`id_clicktracker_log`),
    INDEX `idx_id_product` (`id_product`),
    INDEX `idx_date_add` (`date_add`),
    INDEX `idx_context_type` (`context_type`),
    INDEX `idx_context_date` (`context_type`, `date_add`),
    INDEX `idx_element_type` (`element_type`),
    INDEX `idx_shop_date` (`id_shop`, `date_add`),
    INDEX `idx_page_path` (`page_path`(191))
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

// Rate limiting table (one row per hashed client IP, no personal data stored in clear)
$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'clicktracker_rate` (
    `ip_hash` CHAR(40) NOT NULL,
    `window_start` INT(11) UNSIGNED NOT NULL,
    `hits` INT(11) UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`ip_hash`),
    INDEX `idx_window_start` (`window_start`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}

return true;

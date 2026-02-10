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
    `context_type` VARCHAR(20) NOT NULL DEFAULT "product",
    `id_product` INT(11) UNSIGNED NULL DEFAULT NULL,
    `product_name` VARCHAR(255) NULL DEFAULT NULL,
    `id_category` INT(11) UNSIGNED NULL DEFAULT NULL,
    `page_url` VARCHAR(500) NOT NULL,
    `page_title` VARCHAR(255) NULL DEFAULT NULL,
    `clicked_class` VARCHAR(100) NOT NULL,
    `element_type` VARCHAR(50) NOT NULL,
    `date_add` DATETIME NOT NULL,
    PRIMARY KEY (`id_clicktracker_log`),
    INDEX `idx_id_product` (`id_product`),
    INDEX `idx_date_add` (`date_add`),
    INDEX `idx_context_type` (`context_type`),
    INDEX `idx_context_date` (`context_type`, `date_add`),
    INDEX `idx_element_type` (`element_type`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}

return true;

<?php
/**
 * Click Tracker Module - SQL Uninstall Script
 *
 * @author    Ettore Stani
 * @copyright 2024 Ettore Stani
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

$sql = array();

// Rate limiting data is transient: always drop it
$sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'clicktracker_rate`;';

// Click logs are dropped only when explicitly requested in the configuration
if (!empty($deleteLogs)) {
    $sql[] = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'clicktracker_log`;';
}

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}

return true;

<?php
/**
 * Uninstall Meta Description Handler
 *
 * This file runs when the plugin is uninstalled (deleted).
 * It removes all plugin data from the database.
 */

// Exit if not uninstalling
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('mdh_settings');

// Delete all post meta
global $wpdb;

$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_mdh_%'");

// Delete all term meta
$wpdb->query("DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE '_mdh_%'");

// Clear any cached data
wp_cache_flush();

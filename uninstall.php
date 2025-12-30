<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

global $wpdb;

$sessions = $wpdb->prefix . 'bca_sessions';
$events   = $wpdb->prefix . 'bca_events';

$wpdb->query("DROP TABLE IF EXISTS `$events`");
$wpdb->query("DROP TABLE IF EXISTS `$sessions`");

delete_option('bca_version');

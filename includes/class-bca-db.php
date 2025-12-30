<?php
if (!defined('ABSPATH')) exit;

class BCA_DB {

    public static function sessions_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'bca_sessions';
    }

    public static function events_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'bca_events';
    }

    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $sessions = self::sessions_table();
        $events   = self::events_table();

        // Sessions: minimale velden (geen IP, geen full UA)
        $sql_sessions = "
        CREATE TABLE $sessions (
            session_id CHAR(36) NOT NULL,
            first_seen_at DATETIME NOT NULL,
            last_seen_at DATETIME NOT NULL,
            ended_at DATETIME NULL,
            entry_url TEXT NOT NULL,
            entry_referrer TEXT NULL,
            last_url TEXT NOT NULL,
            exit_url TEXT NULL,
            device_type VARCHAR(20) NULL,
            browser_family VARCHAR(30) NULL,
            is_bot TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (session_id),
            KEY last_seen_at (last_seen_at),
            KEY first_seen_at (first_seen_at),
            KEY is_bot (is_bot)
        ) $charset_collate;
        ";

        // Events: nodig voor top pages (30d), time-on-page later, etc.
        $sql_events = "
        CREATE TABLE $events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id CHAR(36) NOT NULL,
            event_type VARCHAR(20) NOT NULL,
            url TEXT NOT NULL,
            referrer TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY created_at (created_at),
            KEY event_type (event_type)
        ) $charset_collate;
        ";

        dbDelta($sql_sessions);
        dbDelta($sql_events);

        update_option('bca_version', BCA_VERSION);
    }
}

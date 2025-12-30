<?php
if (!defined('ABSPATH')) exit;

class BCA_Cron {
    const HOOK = 'bca_cleanup_daily';

    public static function schedule(): void {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + 3600, 'daily', self::HOOK);
        }
    }

    public static function unschedule(): void {
        $ts = wp_next_scheduled(self::HOOK);
        if ($ts) {
            wp_unschedule_event($ts, self::HOOK);
        }
    }

    public static function init(): void {
        add_action(self::HOOK, [__CLASS__, 'cleanup']);
    }

    public static function cleanup(): void {
        global $wpdb;

        $sessions = BCA_DB::sessions_table();
        $events   = BCA_DB::events_table();

        // Retentie: 30 dagen
        $cutoff = gmdate('Y-m-d H:i:s', time() - (30 * 86400));

        // Oude events weg
        $wpdb->query($wpdb->prepare("DELETE FROM $events WHERE created_at < %s", $cutoff));

        // Oude sessions weg (op basis van first_seen)
        $wpdb->query($wpdb->prepare("DELETE FROM $sessions WHERE first_seen_at < %s", $cutoff));
    }
}

BCA_Cron::init();

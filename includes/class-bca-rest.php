<?php
if (!defined('ABSPATH')) exit;

class BCA_Rest {

    public static function register_routes(): void {
        add_action('rest_api_init', function () {
            register_rest_route('bca/v1', '/track', [
                'methods'  => 'POST',
                'permission_callback' => '__return_true',
                'callback' => [__CLASS__, 'track'],
                'args' => [
                    'session_id' => ['required' => true, 'type' => 'string'],
                    'event_type' => ['required' => true, 'type' => 'string'],
                    'url'        => ['required' => true, 'type' => 'string'],
                    'referrer'   => ['required' => false, 'type' => 'string'],
                ],
            ]);

            register_rest_route('bca/v1', '/admin/live', [
                'methods'  => 'GET',
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
                'callback' => [__CLASS__, 'admin_live'],
            ]);

            register_rest_route('bca/v1', '/admin/stats', [
                'methods'  => 'GET',
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
                'callback' => [__CLASS__, 'admin_stats'],
            ]);
        });
    }

    private static function require_nonce(WP_REST_Request $req): void {
        $nonce = $req->get_header('x_wp_nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            // tracking endpoint is publiek; maar we willen geen open endpoint zonder nonce
            throw new WP_Error('bca_nonce', 'Invalid nonce', ['status' => 403]);
        }
    }

    private static function normalize_url(string $url): string {
        $url = trim($url);
        // Alleen path + query bewaren (geen scheme/host) om data te minimaliseren
        $parts = wp_parse_url($url);
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? ('?' . $parts['query']) : '';
        return $path . $query;
    }

    private static function normalize_referrer(?string $ref): ?string {
        if (!$ref) return null;
        $parts = wp_parse_url($ref);
        if (empty($parts['host'])) return null;
        // Alleen host bewaren (source)
        return strtolower($parts['host']);
    }

    public static function track(WP_REST_Request $req) {
        try {
            self::require_nonce($req);
        } catch (WP_Error $e) {
            return $e;
        }

        global $wpdb;

        $ua = $req->get_header('user_agent');
        $is_bot = BCA_Bot_Filter::is_bot_user_agent($ua);

        // Extra: als bot, accepteer stil (geen error) maar sla niet op
        if ($is_bot) {
            return new WP_REST_Response(['ok' => true, 'ignored' => true], 200);
        }

        $session_id = sanitize_text_field((string) $req->get_param('session_id'));
        $event_type = sanitize_text_field((string) $req->get_param('event_type'));
        $url_raw    = (string) $req->get_param('url');
        $ref_raw    = $req->get_param('referrer') ? (string) $req->get_param('referrer') : null;

        // Whitelist event types
        $allowed = ['page_view', 'heartbeat', 'page_exit'];
        if (!in_array($event_type, $allowed, true)) {
            return new WP_REST_Response(['ok' => false, 'error' => 'Invalid event_type'], 400);
        }

        // Session id basic validation (UUID-like)
        if (strlen($session_id) < 10 || strlen($session_id) > 60) {
            return new WP_REST_Response(['ok' => false, 'error' => 'Invalid session_id'], 400);
        }

        $url = self::normalize_url($url_raw);
        $ref = self::normalize_referrer($ref_raw);

        $now = current_time('mysql');

        $sessions = BCA_DB::sessions_table();
        $events   = BCA_DB::events_table();

        $device  = BCA_Bot_Filter::device_type($ua);
        $browser = BCA_Bot_Filter::browser_family($ua);

        // Upsert session
        $existing = $wpdb->get_var($wpdb->prepare("SELECT session_id FROM $sessions WHERE session_id = %s", $session_id));

        if (!$existing) {
            $wpdb->insert($sessions, [
                'session_id' => $session_id,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'entry_url' => $url,
                'entry_referrer' => $ref,
                'last_url' => $url,
                'device_type' => $device,
                'browser_family' => $browser,
                'is_bot' => 0,
            ], ['%s','%s','%s','%s','%s','%s','%s','%s','%d']);
        } else {
            $update = [
                'last_seen_at' => $now,
                'last_url' => $url,
            ];
            if ($event_type === 'page_exit') {
                $update['ended_at'] = $now;
                $update['exit_url'] = $url;
            }
            $wpdb->update($sessions, $update, ['session_id' => $session_id], null, ['%s']);
        }

        // Log event (voor 30d top pages e.d.)
        $wpdb->insert($events, [
            'session_id' => $session_id,
            'event_type' => $event_type,
            'url' => $url,
            'referrer' => $ref,
            'created_at' => $now,
        ], ['%s','%s','%s','%s','%s']);

        return new WP_REST_Response(['ok' => true], 200);
    }

    public static function admin_live(WP_REST_Request $req) {
        global $wpdb;

        $active_window = (int) ($req->get_param('active_window') ?: 90);
        if ($active_window < 30) $active_window = 30;
        if ($active_window > 300) $active_window = 300;

        $sessions = BCA_DB::sessions_table();

        $since = gmdate('Y-m-d H:i:s', time() - $active_window);

        $active_users = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $sessions
             WHERE is_bot = 0 AND last_seen_at >= %s AND (ended_at IS NULL OR ended_at >= %s)",
            $since, $since
        ));

        $top_pages = $wpdb->get_results($wpdb->prepare(
            "SELECT last_url AS url, COUNT(*) AS c
             FROM $sessions
             WHERE is_bot = 0 AND last_seen_at >= %s AND (ended_at IS NULL OR ended_at >= %s)
             GROUP BY last_url
             ORDER BY c DESC
             LIMIT 10",
            $since, $since
        ), ARRAY_A);

        $top_sources = $wpdb->get_results($wpdb->prepare(
            "SELECT COALESCE(entry_referrer, 'direct') AS source, COUNT(*) AS c
             FROM $sessions
             WHERE is_bot = 0 AND last_seen_at >= %s AND (ended_at IS NULL OR ended_at >= %s)
             GROUP BY source
             ORDER BY c DESC
             LIMIT 10",
            $since, $since
        ), ARRAY_A);

        return new WP_REST_Response([
            'active_users' => $active_users,
            'top_active_pages' => $top_pages,
            'top_live_sources' => $top_sources,
            'active_window_seconds' => $active_window,
        ], 200);
    }

    public static function admin_stats(WP_REST_Request $req) {
        global $wpdb;

        $days = (int) ($req->get_param('days') ?: 30);
        if ($days < 1) $days = 1;
        if ($days > 90) $days = 90;

        $sessions = BCA_DB::sessions_table();
        $events   = BCA_DB::events_table();

        $since = gmdate('Y-m-d H:i:s', time() - ($days * 86400));

        $unique_sessions = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $sessions WHERE is_bot = 0 AND first_seen_at >= %s",
            $since
        ));

        // Voor v0.1 is "users" gelijk aan sessies (privacyvriendelijk). Later kun je dit labelen in UI.
        $users_approx = $unique_sessions;

        $top_pages_30d = $wpdb->get_results($wpdb->prepare(
            "SELECT url, COUNT(*) AS c
             FROM $events
             WHERE event_type = 'page_view' AND created_at >= %s
             GROUP BY url
             ORDER BY c DESC
             LIMIT 10",
            $since
        ), ARRAY_A);

        $top_sources_30d = $wpdb->get_results($wpdb->prepare(
            "SELECT COALESCE(referrer, 'direct') AS source, COUNT(*) AS c
             FROM $events
             WHERE event_type = 'page_view' AND created_at >= %s
             GROUP BY source
             ORDER BY c DESC
             LIMIT 10",
            $since
        ), ARRAY_A);

        $top_devices = $wpdb->get_results($wpdb->prepare(
            "SELECT COALESCE(device_type,'unknown') AS device, COUNT(*) AS c
             FROM $sessions
             WHERE is_bot = 0 AND first_seen_at >= %s
             GROUP BY device
             ORDER BY c DESC
             LIMIT 10",
            $since
        ), ARRAY_A);

        $top_browsers = $wpdb->get_results($wpdb->prepare(
            "SELECT COALESCE(browser_family,'unknown') AS browser, COUNT(*) AS c
             FROM $sessions
             WHERE is_bot = 0 AND first_seen_at >= %s
             GROUP BY browser
             ORDER BY c DESC
             LIMIT 10",
            $since
        ), ARRAY_A);

        return new WP_REST_Response([
            'range_days' => $days,
            'users_last_days' => $users_approx,
            'sessions_last_days' => $unique_sessions,
            'top_pages_last_days' => $top_pages_30d,
            'top_sources_last_days' => $top_sources_30d,
            'top_devices_last_days' => $top_devices,
            'top_browsers_last_days' => $top_browsers,
        ], 200);
    }
}

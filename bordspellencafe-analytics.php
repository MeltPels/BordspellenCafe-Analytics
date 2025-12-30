<?php
/**
 * Plugin Name: BordspellenCafe Analytics (Live)
 * Description: Privacyvriendelijke live analytics (sessions, active pages, referrers) voor posts & pages.
 * Version: 0.1.0
 * Author: Melt Pels
 * Text Domain: bca
 */

if (!defined('ABSPATH')) exit;

define('BCA_VERSION', '0.1.0');
define('BCA_PLUGIN_FILE', __FILE__);
define('BCA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BCA_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once BCA_PLUGIN_DIR . 'includes/class-bca-db.php';
require_once BCA_PLUGIN_DIR . 'includes/class-bca-bot-filter.php';
require_once BCA_PLUGIN_DIR . 'includes/class-bca-rest.php';
require_once BCA_PLUGIN_DIR . 'includes/class-bca-admin.php';
require_once BCA_PLUGIN_DIR . 'includes/class-bca-cron.php';

register_activation_hook(__FILE__, function () {
    BCA_DB::install();
    BCA_Cron::schedule();
});

register_deactivation_hook(__FILE__, function () {
    BCA_Cron::unschedule();
});

add_action('plugins_loaded', function () {
    BCA_Rest::register_routes();
    BCA_Admin::init();
});

add_action('wp_enqueue_scripts', function () {
    // Alleen front-end, geen feeds/rss/wp-admin/rest etc.
    if (is_admin()) return;
    if (wp_doing_ajax()) return;
    if (wp_doing_cron()) return;
    if (defined('REST_REQUEST') && REST_REQUEST) return;
    if (is_feed() || is_trackback() || is_robots()) return;
    if (is_preview()) return;

    // Alleen posts + pages (zoals jij wilde)
    if (!is_singular(['post', 'page'])) return;

    // Consent hook (later integreren met bv. Complianz/Cookiebot/Borlabs)
    // Default: tracking aan. Als je dit straks consent-based wil maken, zet dit op false tenzij consent.
    $tracking_allowed = apply_filters('bca_tracking_allowed', true);
    if (!$tracking_allowed) return;

    wp_enqueue_script(
        'bca-tracker',
        BCA_PLUGIN_URL . 'assets/tracker.js',
        [],
        BCA_VERSION,
        true
    );

    wp_localize_script('bca-tracker', 'BCA', [
        'endpoint' => esc_url_raw(rest_url('bca/v1/track')),
        'nonce'    => wp_create_nonce('wp_rest'),
        'contentId'=> (int) get_queried_object_id(),
        'contentType' => is_singular('page') ? 'page' : 'post',
        'heartbeatSeconds' => 15,
        'activeWindowSeconds' => 90,
    ]);
}, 20);

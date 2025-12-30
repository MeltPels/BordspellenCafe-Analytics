<?php
if (!defined('ABSPATH')) exit;

class BCA_Admin {

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
    }

    public static function menu(): void {
        add_menu_page(
            'BCA Analytics',
            'BCA Analytics',
            'manage_options',
            'bca-analytics',
            [__CLASS__, 'render'],
            'dashicons-chart-area',
            80
        );
    }

    public static function assets(string $hook): void {
        if ($hook !== 'toplevel_page_bca-analytics') return;

        wp_enqueue_script(
            'bca-admin',
            BCA_PLUGIN_URL . 'assets/admin.js',
            [],
            BCA_VERSION,
            true
        );

        wp_localize_script('bca-admin', 'BCA_ADMIN', [
            'liveEndpoint' => esc_url_raw(rest_url('bca/v1/admin/live')),
            'statsEndpoint'=> esc_url_raw(rest_url('bca/v1/admin/stats')),
            'nonce'        => wp_create_nonce('wp_rest'),
        ]);
    }

    public static function render(): void {
        echo '<div class="wrap">';
        echo '<h1>BordspellenCafe Analytics</h1>';
        echo '<p>Dashboard komt in de volgende stap. Eerst zorgen we dat tracking + data klopt.</p>';
        echo '<div id="bca-admin-root"></div>';
        echo '</div>';
    }
}

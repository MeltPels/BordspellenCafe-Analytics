<?php
if (!defined('ABSPATH')) exit;

class BCA_Bot_Filter {

    // Basisset; later uitbreidbaar via filter.
    private static function bot_regexes(): array {
        $default = [
            'bot', 'spider', 'crawler', 'slurp',
            'googlebot', 'bingbot', 'duckduckbot', 'baiduspider', 'yandex',
            'ahrefs', 'semrush', 'mj12bot', 'dotbot',
            'facebookexternalhit', 'linkedinbot', 'twitterbot', 'pinterest',
        ];

        return apply_filters('bca_bot_keywords', $default);
    }

    public static function is_bot_user_agent(?string $ua): bool {
        if (!$ua) return true; // geen UA: verdacht
        $ua_l = strtolower($ua);

        foreach (self::bot_regexes() as $kw) {
            if ($kw && str_contains($ua_l, strtolower($kw))) {
                return true;
            }
        }
        return false;
    }

    public static function browser_family(?string $ua): ?string {
        if (!$ua) return null;
        $u = strtolower($ua);

        // Eenvoudige categorisering (geen fingerprinting)
        if (str_contains($u, 'edg/')) return 'Edge';
        if (str_contains($u, 'chrome/') && !str_contains($u, 'edg/')) return 'Chrome';
        if (str_contains($u, 'firefox/')) return 'Firefox';
        if (str_contains($u, 'safari/') && !str_contains($u, 'chrome/')) return 'Safari';
        return 'Other';
    }

    public static function device_type(?string $ua): ?string {
        if (!$ua) return null;
        $u = strtolower($ua);

        if (str_contains($u, 'mobile') || str_contains($u, 'iphone') || str_contains($u, 'android')) return 'mobile';
        if (str_contains($u, 'ipad') || str_contains($u, 'tablet')) return 'tablet';
        return 'desktop';
    }
}

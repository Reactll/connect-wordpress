<?php

defined('ABSPATH') || exit;

/**
 * Page caches keep serving the HTML from before Reactll Connect was there, so the script never loads
 * until someone clears them. Cleared when the plugin is connected, activated or updated — for the
 * caching plugins WordPress sites commonly run. Each call is guarded: a missing plugin is skipped.
 */
class Reactll_Connect_Cache
{
    /** @return list<string> the caches that were cleared */
    public static function purge()
    {
        $cleared = [];
        $try = function ($name, $callable) use (&$cleared) {
            try {
                if ($callable()) $cleared[] = $name;
            } catch (\Throwable $e) {
                // A cache plugin's own error never stops us.
            }
        };

        $try('W3 Total Cache', function () { if (function_exists('w3tc_flush_all')) { w3tc_flush_all(); return true; } return false; });
        $try('WP Fastest Cache', function () {
            if (isset($GLOBALS['wp_fastest_cache']) && method_exists($GLOBALS['wp_fastest_cache'], 'deleteCache')) { $GLOBALS['wp_fastest_cache']->deleteCache(true); return true; }
            if (has_action('wpfc_clear_all_cache')) { do_action('wpfc_clear_all_cache', true); return true; }
            return false;
        });
        $try('WP Rocket', function () { if (function_exists('rocket_clean_domain')) { rocket_clean_domain(); return true; } return false; });
        $try('LiteSpeed Cache', function () { if (defined('LSCWP_V') || has_action('litespeed_purge_all')) { do_action('litespeed_purge_all'); return true; } return false; });
        $try('WP Super Cache', function () { if (function_exists('wp_cache_clear_cache')) { wp_cache_clear_cache(); return true; } return false; });
        $try('Autoptimize', function () { if (class_exists('autoptimizeCache') && method_exists('autoptimizeCache', 'clearall')) { autoptimizeCache::clearall(); return true; } return false; });
        $try('SiteGround Optimizer', function () { if (function_exists('sg_cachepress_purge_cache')) { sg_cachepress_purge_cache(); return true; } return false; });
        $try('Hummingbird', function () { if (has_action('wphb_clear_page_cache')) { do_action('wphb_clear_page_cache'); return true; } return false; });
        $try('Cache Enabler', function () { if (has_action('cache_enabler_clear_complete_cache')) { do_action('cache_enabler_clear_complete_cache'); return true; } return false; });
        $try('Breeze', function () { if (has_action('breeze_clear_all_cache')) { do_action('breeze_clear_all_cache'); return true; } return false; });
        $try('Comet Cache', function () { if (class_exists('comet_cache') && method_exists('comet_cache', 'clear')) { comet_cache::clear(); return true; } return false; });

        update_option('reactll_connect_cache_cleared', ['at' => time(), 'caches' => $cleared], false);

        return $cleared;
    }
}

<?php

defined('ABSPATH') || exit;

/**
 * Every hour: what this site runs (WordPress, PHP, theme, plugins and the updates waiting, form
 * plugins). Reactll answers with the site's settings.
 */
class Reactll_Connect_Heartbeat
{
    const HOOK = 'reactll_connect_heartbeat';

    public static function init()
    {
        add_action(self::HOOK, [__CLASS__, 'send']);
        // A site moved or restored without the schedule gets it back.
        add_action('init', function () {
            if (! wp_next_scheduled(self::HOOK)) wp_schedule_event(time() + 60, 'hourly', self::HOOK);
            if (! wp_next_scheduled('reactll_connect_retry')) wp_schedule_event(time() + 300, 'hourly', 'reactll_connect_retry');
        });
    }

    public static function activate()
    {
        // Pages cached before the plugin was here would never load the script.
        if (Reactll_Connect_Client::connected()) {
            Reactll_Connect_Cache::purge();
        }
        if (! wp_next_scheduled(self::HOOK)) wp_schedule_event(time() + 60, 'hourly', self::HOOK);
        if (! wp_next_scheduled('reactll_connect_retry')) wp_schedule_event(time() + 300, 'hourly', 'reactll_connect_retry');
    }

    public static function deactivate()
    {
        wp_clear_scheduled_hook(self::HOOK);
        wp_clear_scheduled_hook('reactll_connect_retry');
    }

    /** @return array|WP_Error */
    public static function send()
    {
        if (! Reactll_Connect_Client::connected()) {
            return new WP_Error('reactll_connect_not_connected', 'Not connected yet.');
        }
        if (! function_exists('get_plugins')) {
            require_once ABSPATH.'wp-admin/includes/plugin.php';
        }

        $all = get_plugins();
        $active = (array) get_option('active_plugins', []);
        $plugins = [];
        foreach ($active as $file) {
            if (isset($all[$file])) $plugins[] = $all[$file]['Name'].' '.$all[$file]['Version'];
        }

        $updates = [];
        $transient = get_site_transient('update_plugins');
        foreach ((array) ($transient->response ?? []) as $file => $info) {
            $updates[] = [
                'name' => isset($all[$file]) ? $all[$file]['Name'] : $file,
                'from' => isset($all[$file]) ? $all[$file]['Version'] : null,
                'to' => $info->new_version ?? null,
            ];
        }
        $core = get_site_transient('update_core');
        if (! empty($core->updates[0]) && $core->updates[0]->response === 'upgrade') {
            $updates[] = ['name' => 'WordPress', 'from' => get_bloginfo('version'), 'to' => $core->updates[0]->current];
        }

        $theme = wp_get_theme();
        $runtime = array_filter([
            'wordpress' => get_bloginfo('version'),
            'php' => PHP_VERSION,
            'theme' => $theme->get('Name').' '.$theme->get('Version'),
            'woocommerce' => defined('WC_VERSION') ? WC_VERSION : null,
            'multisite' => is_multisite() ? 'yes' : null,
            // Settings → General → Timezone: Reactll counts the site's days on this clock.
            'timezone' => function_exists('wp_timezone_string') ? wp_timezone_string() : null,
        ]);

        $result = Reactll_Connect_Client::post('heartbeat', [
            'platform' => 'wordpress',
            'version' => REACTLL_CONNECT_VERSION,
            'site_url' => home_url('/'),
            'runtime' => $runtime,
            'forms' => Reactll_Connect_Forms::detected(),
            'plugins' => $plugins,
            'updates' => $updates,
        ], 10);

        update_option('reactll_connect_last', [
            'at' => time(),
            'ok' => ! is_wp_error($result),
            'error' => is_wp_error($result) ? $result->get_error_message() : null,
            'site' => is_wp_error($result) ? null : ($result['site']['name'] ?? null),
        ], false);

        if (! is_wp_error($result) && isset($result['settings'])) {
            update_option('reactll_connect_settings', (array) $result['settings'], false);
        }
        // Reactll knows the newest release: when this site is behind, update now (still signature-checked),
        // instead of waiting for WordPress's own twice-a-day check — which heavily cached sites rarely run.
        if (! is_wp_error($result) && ! empty($result['plugin']['latest']) && version_compare($result['plugin']['latest'], REACTLL_CONNECT_VERSION, '>')) {
            Reactll_Connect_Updater::updateNow();
        }
        if (! is_wp_error($result) && isset($result['stats'])) {
            update_option('reactll_connect_stats', (array) $result['stats'] + ['fetched_at' => time()], false);
        }

        return $result;
    }
}

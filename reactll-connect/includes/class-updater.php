<?php

defined('ABSPATH') || exit;

/**
 * Updates from Reactll's own channel instead of wordpress.org: the normal "Update" button and
 * automatic updates, for this plugin only. Every package is checked against the Ed25519 key built
 * into the plugin before WordPress installs it.
 */
class Reactll_Connect_Updater
{
    const SLUG = 'reactll-connect';

    const CACHE = 'reactll_connect_release';

    public static function init()
    {
        add_filter('pre_set_site_transient_update_plugins', [__CLASS__, 'check']);
        add_filter('plugins_api', [__CLASS__, 'info'], 10, 3);
        add_filter('upgrader_pre_download', [__CLASS__, 'verify'], 10, 4);
        add_filter('auto_update_plugin', function ($update, $item) {
            return (isset($item->plugin) && $item->plugin === REACTLL_CONNECT_BASENAME) ? true : $update;
        }, 10, 2);
        add_action('upgrader_process_complete', function ($upgrader, $extra = []) {
            delete_site_transient(self::CACHE);
            // Our own update: clear page caches, so pages built by an older version are rebuilt.
            if (($extra['type'] ?? null) === 'plugin' && in_array(REACTLL_CONNECT_BASENAME, (array) ($extra['plugins'] ?? []), true) && Reactll_Connect_Client::connected()) {
                Reactll_Connect_Cache::purge();
            }
        }, 10, 2);
    }

    /** @return array|null */
    private static function release()
    {
        $cached = get_site_transient(self::CACHE);
        if (is_array($cached)) {
            return $cached ?: null;
        }
        $response = wp_remote_get(Reactll_Connect_Client::endpoint().'/wordpress/info.json', ['timeout' => 8]);
        $data = is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200
            ? []
            : (array) json_decode((string) wp_remote_retrieve_body($response), true);
        set_site_transient(self::CACHE, $data, HOUR_IN_SECONDS);

        return $data ?: null;
    }

    /** Install the newest release now (signature-checked like any update) and keep it active. */
    public static function updateNow()
    {
        if (get_transient('reactll_connect_updating')) {
            return false;
        }
        set_transient('reactll_connect_updating', 1, 15 * MINUTE_IN_SECONDS);
        foreach (['file', 'misc', 'plugin', 'update', 'class-wp-upgrader'] as $file) {
            require_once ABSPATH.'wp-admin/includes/'.$file.'.php';
        }
        // Fresh offers: ours from Reactll (check() fills it in), the rest as WordPress knows them.
        delete_site_transient(self::CACHE);
        delete_site_transient('update_plugins');
        wp_update_plugins();

        $active = is_plugin_active(REACTLL_CONNECT_BASENAME);
        $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
        $result = $upgrader->upgrade(REACTLL_CONNECT_BASENAME);
        if ($active && ! is_plugin_active(REACTLL_CONNECT_BASENAME)) {
            activate_plugin(REACTLL_CONNECT_BASENAME, '', false, true);
        }
        delete_transient('reactll_connect_updating');

        return $result === true;
    }

    public static function check($transient)
    {
        if (! is_object($transient)) {
            return $transient;
        }
        $release = self::release();
        if ($release && ! empty($release['version']) && version_compare($release['version'], REACTLL_CONNECT_VERSION, '>')) {
            $transient->response[REACTLL_CONNECT_BASENAME] = (object) [
                'slug' => self::SLUG,
                'plugin' => REACTLL_CONNECT_BASENAME,
                'new_version' => $release['version'],
                'package' => $release['download_url'],
                'url' => 'https://reactll.com',
                'tested' => $release['tested'] ?? null,
                'requires_php' => $release['requires_php'] ?? null,
            ];
        } else {
            unset($transient->response[REACTLL_CONNECT_BASENAME]);
        }

        return $transient;
    }

    public static function info($result, $action, $args)
    {
        if ($action !== 'plugin_information' || ($args->slug ?? null) !== self::SLUG || ! ($release = self::release())) {
            return $result;
        }

        return (object) [
            'name' => 'Reactll Connect',
            'slug' => self::SLUG,
            'version' => $release['version'],
            'author' => '<a href="https://reactll.com">Reactor Technology</a>',
            'homepage' => 'https://reactll.com',
            'requires' => $release['requires'] ?? '5.8',
            'tested' => $release['tested'] ?? null,
            'requires_php' => $release['requires_php'] ?? '7.4',
            'download_link' => $release['download_url'],
            'sections' => ['changelog' => wp_kses_post($release['changelog'] ?? 'Improvements and fixes.')],
        ];
    }

    /** Download our package ourselves and install it only if its signature checks out. */
    public static function verify($reply, $package, $upgrader, $hook_extra = [])
    {
        if ($reply !== false || ! is_string($package) || strpos($package, '/wordpress/download/reactll-connect-') === false) {
            return $reply;
        }
        if (! function_exists('download_url')) {
            require_once ABSPATH.'wp-admin/includes/file.php';
        }

        $file = download_url($package, 120);
        if (is_wp_error($file)) {
            return $file;
        }
        $sig = wp_remote_get($package.'.sig', ['timeout' => 15]);
        $signature = is_wp_error($sig) ? '' : base64_decode(trim((string) wp_remote_retrieve_body($sig)), true);
        $key = base64_decode(REACTLL_CONNECT_SIGNING_KEY, true);

        $valid = false;
        if ($signature && $key && function_exists('sodium_crypto_sign_verify_detached')) {
            try {
                $valid = sodium_crypto_sign_verify_detached($signature, (string) file_get_contents($file), $key);
            } catch (\Throwable $e) {
                $valid = false;
            }
        }
        if (! $valid) {
            @unlink($file);

            return new WP_Error('reactll_connect_signature', 'Reactll Connect update refused: its signature does not match. Nothing was changed.');
        }

        return $file;
    }
}

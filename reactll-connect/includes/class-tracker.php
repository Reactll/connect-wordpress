<?php

defined('ABSPATH') || exit;

/**
 * Loads the Reactll Connect script. The script itself lives on Reactll's servers, so fixes reach
 * the site without a plugin update. Cookieless; the site's own admins and editors are not counted
 * unless Reactll says to.
 */
class Reactll_Connect_Tracker
{
    const HANDLE = 'reactll-connect';

    public static function init()
    {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_filter('script_loader_tag', [__CLASS__, 'tag'], 10, 2);
    }

    public static function enqueue()
    {
        if (! Reactll_Connect_Client::connected() || is_admin()) {
            return;
        }
        $settings = (array) get_option('reactll_connect_settings', []);
        if (is_user_logged_in() && current_user_can('edit_posts') && empty($settings['count_logged_in'])) {
            return;
        }

        wp_enqueue_script(self::HANDLE, Reactll_Connect_Client::endpoint().'/c.js', [], null, false);
    }

    public static function tag($tag, $handle)
    {
        if ($handle !== self::HANDLE) {
            return $tag;
        }
        $key = esc_attr((string) Reactll_Connect_Client::key());

        return str_replace(' src=', ' defer data-site="'.$key.'" src=', $tag);
    }
}

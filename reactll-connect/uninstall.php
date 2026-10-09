<?php
// Removing the plugin removes its settings and queue; the site's data in Reactll stays there.
defined('WP_UNINSTALL_PLUGIN') || exit;

foreach (['reactll_connect_token', 'reactll_connect_settings', 'reactll_connect_last', 'reactll_connect_queue', 'reactll_connect_stats'] as $option) {
    delete_option($option);
}
delete_site_transient('reactll_connect_release');
wp_clear_scheduled_hook('reactll_connect_heartbeat');
wp_clear_scheduled_hook('reactll_connect_retry');

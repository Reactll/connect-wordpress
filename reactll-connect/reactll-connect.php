<?php
/**
 * Plugin Name:       Reactll Connect
 * Plugin URI:        https://reactll.com
 * Description:       Connects this site to its Reactll dashboard: visits, calls, WhatsApp and directions clicks, form leads and site health. Built and maintained by Reactor Technology.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Reactor Technology
 * Author URI:        https://reactll.com
 * License:           MIT
 * Text Domain:       reactll-connect
 */

defined('ABSPATH') || exit;

define('REACTLL_CONNECT_VERSION', '1.0.0');
define('REACTLL_CONNECT_FILE', __FILE__);
define('REACTLL_CONNECT_BASENAME', plugin_basename(__FILE__));
// Releases are signed with this key's private half (kept offline by Reactor Technology). An update
// whose signature does not match is never installed, whoever serves it.
define('REACTLL_CONNECT_SIGNING_KEY', 'fSKftwkAB1jQw75blPW6wskm5DcAkx1at2emmTc7Qeg=');

require_once __DIR__.'/includes/class-client.php';
require_once __DIR__.'/includes/class-tracker.php';
require_once __DIR__.'/includes/class-forms.php';
require_once __DIR__.'/includes/class-heartbeat.php';
require_once __DIR__.'/includes/class-settings.php';
require_once __DIR__.'/includes/class-updater.php';

Reactll_Connect_Tracker::init();
Reactll_Connect_Forms::init();
Reactll_Connect_Heartbeat::init();
Reactll_Connect_Settings::init();
Reactll_Connect_Updater::init();

register_activation_hook(__FILE__, ['Reactll_Connect_Heartbeat', 'activate']);
register_deactivation_hook(__FILE__, ['Reactll_Connect_Heartbeat', 'deactivate']);

<?php

defined('ABSPATH') || exit;

/**
 * The plugin's page (its own "Reactll" menu item), the dashboard widget and the plugin row links.
 * Numbers come from Reactll with each heartbeat (the site's own week), never computed here.
 */
class Reactll_Connect_Settings
{
    const PAGE = 'reactll-connect';

    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_reactll_connect_save', [__CLASS__, 'save']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('wp_dashboard_setup', [__CLASS__, 'widget']);
        add_filter('plugin_action_links_'.REACTLL_CONNECT_BASENAME, function ($links) {
            array_unshift($links, '<a href="'.esc_url(self::url()).'">'.(Reactll_Connect_Client::connected() ? 'Settings' : '<strong>Connect</strong>').'</a>');

            return $links;
        });
        add_filter('plugin_row_meta', function ($meta, $file) {
            if ($file === REACTLL_CONNECT_BASENAME) {
                $meta[] = '<a href="https://reactll.com" target="_blank" rel="noopener">reactll.com</a>';
            }

            return $meta;
        }, 10, 2);
        add_action('admin_notices', function () {
            if (Reactll_Connect_Client::connected() || ! current_user_can('manage_options') || (isset($_GET['page']) && $_GET['page'] === self::PAGE)) return;
            echo '<div class="notice notice-info"><p><strong>Reactll Connect</strong> is installed. <a href="'.esc_url(self::url()).'">Paste your connection token</a> to start.</p></div>';
        });
    }

    public static function url()
    {
        return admin_url('admin.php?page='.self::PAGE);
    }

    private static function asset($file)
    {
        return plugins_url('assets/'.$file, REACTLL_CONNECT_FILE);
    }

    public static function menu()
    {
        $svg = @file_get_contents(dirname(REACTLL_CONNECT_FILE).'/assets/reactll-mark.svg');
        $icon = $svg ? 'data:image/svg+xml;base64,'.base64_encode($svg) : 'dashicons-chart-area';
        add_menu_page('Reactll Connect', 'Reactll', 'manage_options', self::PAGE, [__CLASS__, 'render'], $icon, 81);
    }

    public static function assets($hook)
    {
        if ($hook === 'toplevel_page_'.self::PAGE || $hook === 'index.php') {
            wp_enqueue_style('reactll-connect-admin', self::asset('admin.css'), [], REACTLL_CONNECT_VERSION);
        }
    }

    public static function save()
    {
        if (! current_user_can('manage_options')) wp_die('Not allowed.');
        check_admin_referer('reactll_connect_save');

        $token = isset($_POST['token']) ? trim(sanitize_text_field(wp_unslash($_POST['token']))) : '';
        if ($token !== '') {
            update_option(Reactll_Connect_Client::OPTION, $token, false);
        }
        $result = Reactll_Connect_Heartbeat::send();

        wp_safe_redirect(add_query_arg(['reactll' => is_wp_error($result) ? 'error' : 'ok'], self::url()));
        exit;
    }

    /** @return array{connected: bool, ok: bool, site: ?string, error: ?string, at: ?int, stats: array} */
    private static function state()
    {
        $last = (array) get_option('reactll_connect_last', []);

        return [
            'connected' => Reactll_Connect_Client::connected(),
            'ok' => ! empty($last['ok']),
            'site' => $last['site'] ?? null,
            'error' => $last['error'] ?? null,
            'at' => isset($last['at']) ? (int) $last['at'] : null,
            'stats' => (array) get_option('reactll_connect_stats', []),
        ];
    }

    private static function tiles(array $stats, $compact = false)
    {
        $visits = (int) ($stats['visits'] ?? 0);
        $before = (int) ($stats['visits_before'] ?? 0);
        $delta = $before > 0 ? (int) round(($visits - $before) / $before * 100) : null;
        $last = ! empty($stats['last_visit']) ? sprintf(__('%s ago'), human_time_diff(strtotime($stats['last_visit']))) : '—';
        $tiles = [
            ['Visits', number_format_i18n($visits), $delta === null ? 'last 7 days' : (($delta >= 0 ? '▲ ' : '▼ ').abs($delta).'% vs the week before'), $delta === null ? '' : ($delta >= 0 ? 'up' : 'down')],
            ['Form leads', number_format_i18n((int) ($stats['leads'] ?? 0)), 'last 7 days', ''],
            ['Calls & WhatsApp', number_format_i18n((int) ($stats['reach'] ?? 0)), 'calls, emails, directions', ''],
            ['Last visit', esc_html($last), 'live from your site', ''],
        ];
        echo '<div class="rc-tiles'.($compact ? ' rc-tiles--compact' : '').'">';
        foreach ($tiles as [$label, $value, $hint, $tone]) {
            echo '<div class="rc-tile"><span class="rc-tile__label">'.esc_html($label).'</span><span class="rc-tile__value">'.$value.'</span><span class="rc-tile__hint '.esc_attr($tone).'">'.esc_html($hint).'</span></div>';
        }
        echo '</div>';
    }

    public static function render()
    {
        $s = self::state();
        $queue = count((array) get_option(Reactll_Connect_Forms::QUEUE, []));
        $forms = Reactll_Connect_Forms::detected();
        $flash = isset($_GET['reactll']) ? sanitize_key($_GET['reactll']) : null;
        $status = ! $s['connected'] ? ['idle', 'Not connected yet'] : ($s['ok'] ? ['on', 'Connected'.($s['site'] ? ' · '.$s['site'] : '')] : ['off', 'Connection problem']);
        ?>
        <div class="wrap rc-wrap">
            <h1 class="screen-reader-text">Reactll Connect</h1>

            <section class="rc-hero">
                <div class="rc-hero__top">
                    <img src="<?php echo esc_url(self::asset('logo-horizontal-white.png')); ?>" alt="Reactll" class="rc-hero__logo">
                    <span class="rc-pill rc-pill--<?php echo esc_attr($status[0]); ?>"><span class="rc-dot"></span><?php echo esc_html($status[1]); ?></span>
                </div>
                <h2 class="rc-hero__title">Connect</h2>
                <p class="rc-hero__lead">This site's visits, calls, WhatsApp and directions clicks, form leads and health — in your Reactll dashboard.</p>
            </section>

            <?php if ($flash) : ?>
                <div class="rc-flash rc-flash--<?php echo $flash === 'ok' ? 'ok' : 'error'; ?>">
                    <?php echo $flash === 'ok' ? 'Connected — Reactll can see this site.' : 'Could not connect: '.esc_html($s['error'] ?: 'unknown error'); ?>
                </div>
            <?php endif; ?>

            <?php if ($s['connected'] && $s['ok']) : ?>
                <div class="rc-section">
                    <h3 class="rc-section__title">This week</h3>
                    <?php self::tiles($s['stats']); ?>
                </div>
            <?php endif; ?>

            <div class="rc-columns">
                <div class="rc-card">
                    <h3 class="rc-section__title">What it sends</h3>
                    <ul class="rc-list">
                        <li><span class="rc-check">✓</span><div><strong>Visits and where they came from</strong><span>Cookieless — no consent banner needed for it. Your admins are not counted.</span></div></li>
                        <li><span class="rc-check">✓</span><div><strong>Calls, WhatsApp, emails, directions</strong><span>Every press on a phone number, WhatsApp link, email or map.</span></div></li>
                        <li><span class="rc-check">✓</span><div><strong>Form leads</strong><span>Only forms your site accepted.<?php if ($queue) : ?> <em><?php echo (int) $queue; ?> waiting to be sent — retried hourly.</em><?php endif; ?></span>
                            <span class="rc-chips"><?php if ($forms) { foreach ($forms as $f) echo '<span class="rc-chip">'.esc_html($f).'</span>'; } else { echo '<span class="rc-chip rc-chip--muted">No supported form plugin found</span>'; } ?></span></div></li>
                        <li><span class="rc-check">✓</span><div><strong>Site health, hourly</strong><span>WordPress and PHP versions, plugins and the updates waiting.</span></div></li>
                    </ul>
                </div>

                <div class="rc-card">
                    <h3 class="rc-section__title"><?php echo $s['connected'] ? 'Connection' : 'Connect this site'; ?></h3>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="reactll_connect_save">
                        <?php wp_nonce_field('reactll_connect_save'); ?>
                        <label for="reactll-token" class="rc-label">Connection token</label>
                        <input id="reactll-token" name="token" type="password" class="rc-input" autocomplete="off"
                               placeholder="<?php echo $s['connected'] ? '•••••••• saved — paste a new one' : 'rcc_…'; ?>">
                        <p class="rc-help">From your Reactll team, or Reactll → Sites → this site → Install.</p>
                        <button type="submit" class="rc-button"><?php echo $s['connected'] ? 'Save & test connection' : 'Connect'; ?></button>
                        <?php if ($s['at']) : ?><p class="rc-help">Last checked <?php echo esc_html(sprintf(__('%s ago'), human_time_diff($s['at']))); ?>.</p><?php endif; ?>
                    </form>
                </div>
            </div>

            <p class="rc-footer">
                <img src="<?php echo esc_url(self::asset('reactll-mark.svg')); ?>" alt="" width="14" height="14">
                Reactll Connect <?php echo esc_html(REACTLL_CONNECT_VERSION); ?> · Built and maintained by Reactor Technology · <a href="https://reactll.com" target="_blank" rel="noopener">reactll.com</a>
            </p>
        </div>
        <?php
    }

    public static function widget()
    {
        if (! current_user_can('manage_options') || ! Reactll_Connect_Client::connected()) {
            return;
        }
        wp_add_dashboard_widget('reactll_connect', 'Reactll — this week', function () {
            $s = self::state();
            echo '<div class="rc-widget">';
            self::tiles($s['stats'], true);
            echo '<p class="rc-widget__foot"><a href="'.esc_url(self::url()).'">Reactll Connect</a> · by Reactor Technology</p></div>';
        });
    }
}

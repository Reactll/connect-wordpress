<?php

defined('ABSPATH') || exit;

/** Settings → Reactll Connect: paste the token, test the connection, see the last heartbeat. */
class Reactll_Connect_Settings
{
    const PAGE = 'reactll-connect';

    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_reactll_connect_save', [__CLASS__, 'save']);
        add_filter('plugin_action_links_'.REACTLL_CONNECT_BASENAME, function ($links) {
            array_unshift($links, '<a href="'.esc_url(admin_url('options-general.php?page='.self::PAGE)).'">Settings</a>');

            return $links;
        });
        add_action('admin_notices', function () {
            if (Reactll_Connect_Client::connected() || ! current_user_can('manage_options') || (isset($_GET['page']) && $_GET['page'] === self::PAGE)) return;
            echo '<div class="notice notice-info"><p><strong>Reactll Connect</strong> is installed. <a href="'.esc_url(admin_url('options-general.php?page='.self::PAGE)).'">Paste your connection token</a> to start.</p></div>';
        });
    }

    public static function menu()
    {
        add_options_page('Reactll Connect', 'Reactll Connect', 'manage_options', self::PAGE, [__CLASS__, 'render']);
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
        $status = is_wp_error($result) ? 'error' : 'ok';

        wp_safe_redirect(add_query_arg(['page' => self::PAGE, 'reactll' => $status], admin_url('options-general.php')));
        exit;
    }

    public static function render()
    {
        $connected = Reactll_Connect_Client::connected();
        $last = (array) get_option('reactll_connect_last', []);
        $queue = count((array) get_option(Reactll_Connect_Forms::QUEUE, []));
        ?>
        <div class="wrap">
            <h1>Reactll Connect</h1>
            <p>Sends this site's visits, calls, WhatsApp and directions clicks, form leads and health to its Reactll dashboard. No cookies; your own admins are not counted.</p>

            <?php if (isset($_GET['reactll'])) : ?>
                <div class="notice notice-<?php echo $_GET['reactll'] === 'ok' ? 'success' : 'error'; ?>"><p>
                    <?php echo $_GET['reactll'] === 'ok' ? 'Connected. Reactll can see this site.' : 'Could not connect: '.esc_html($last['error'] ?? 'unknown error'); ?>
                </p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="reactll_connect_save">
                <?php wp_nonce_field('reactll_connect_save'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="reactll-token">Connection token</label></th>
                        <td>
                            <input id="reactll-token" name="token" type="password" class="regular-text code" autocomplete="off" placeholder="<?php echo $connected ? '•••••••• (saved — paste a new one to replace it)' : 'rcc_…'; ?>">
                            <p class="description">From the site's page in Reactll (Sites → this site → Install).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Status</th>
                        <td>
                            <?php if (! $connected) : ?>
                                Not connected yet.
                            <?php elseif (! empty($last['at'])) : ?>
                                <?php echo ! empty($last['ok']) ? '✅ Connected'.(! empty($last['site']) ? ' as <strong>'.esc_html($last['site']).'</strong>' : '') : '⚠️ '.esc_html($last['error'] ?? 'Last check failed'); ?>
                                — last checked <?php echo esc_html(human_time_diff((int) $last['at'])); ?> ago.
                            <?php else : ?>
                                Token saved; not checked yet.
                            <?php endif; ?>
                            <?php if ($queue) : ?><br><?php echo (int) $queue; ?> form lead(s) waiting to be sent — retried every hour.<?php endif; ?>
                            <br>Forms found: <?php echo esc_html(implode(', ', Reactll_Connect_Forms::detected()) ?: 'none of the supported form plugins'); ?>.
                        </td>
                    </tr>
                </table>
                <?php submit_button($connected ? 'Save and test connection' : 'Connect'); ?>
            </form>
            <p class="description" style="margin-top:2em">Reactll Connect <?php echo esc_html(REACTLL_CONNECT_VERSION); ?> · Reactor Technology · <a href="https://reactll.com" target="_blank" rel="noopener">reactll.com</a></p>
        </div>
        <?php
    }
}

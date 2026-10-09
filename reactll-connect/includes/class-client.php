<?php

defined('ABSPATH') || exit;

/**
 * The connection to Reactll: the token pasted in the settings (endpoint, public key, secret) and
 * signed calls. Signature = hex(HMAC-SHA256(secret, "{timestamp}.{body}")).
 */
class Reactll_Connect_Client
{
    const OPTION = 'reactll_connect_token';

    /** @return array{u: string, k: string, s: string}|null */
    public static function credentials()
    {
        $token = trim((string) get_option(self::OPTION, ''));
        if (strpos($token, 'rcc_') !== 0) {
            return null;
        }
        $json = base64_decode(strtr(substr($token, 4), '-_', '+/'), true);
        $data = $json ? json_decode($json, true) : null;
        if (! is_array($data) || empty($data['u']) || empty($data['k']) || empty($data['s'])) {
            return null;
        }

        return ['u' => rtrim($data['u'], '/'), 'k' => $data['k'], 's' => $data['s']];
    }

    public static function connected()
    {
        return self::credentials() !== null;
    }

    public static function endpoint()
    {
        $c = self::credentials();

        return $c ? $c['u'] : 'https://connect.reactll.com/v1';
    }

    public static function key()
    {
        $c = self::credentials();

        return $c ? $c['k'] : null;
    }

    /**
     * POST a signed JSON body. Returns the decoded answer, or WP_Error.
     *
     * @return array|WP_Error
     */
    public static function post($path, array $payload, $timeout = 5)
    {
        $c = self::credentials();
        if (! $c) {
            return new WP_Error('reactll_connect_not_connected', 'Paste the connection token from Reactll first.');
        }

        $body = wp_json_encode($payload);
        $ts = (string) time();
        $response = wp_remote_post($c['u'].'/'.ltrim($path, '/'), [
            'timeout' => $timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Reactll-Key' => $c['k'],
                'X-Reactll-Timestamp' => $ts,
                'X-Reactll-Signature' => hash_hmac('sha256', $ts.'.'.$body, $c['s']),
                'User-Agent' => 'ReactllConnect/'.REACTLL_CONNECT_VERSION.' WordPress/'.get_bloginfo('version'),
            ],
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($code < 200 || $code >= 300) {
            $message = is_array($data) && ! empty($data['error']) ? $data['error'] : 'HTTP '.$code;

            return new WP_Error('reactll_connect_http', $message, ['status' => $code]);
        }

        return is_array($data) ? $data : [];
    }
}

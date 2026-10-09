<?php

defined('ABSPATH') || exit;

/**
 * Sends every successfully sent form to Reactll as a lead: Contact Form 7, Gravity Forms, Elementor
 * Pro, WPForms and Fluent Forms. Only after the form plugin accepted it, so a failed validation is
 * never a lead. A send that fails waits in a small queue and is retried hourly.
 */
class Reactll_Connect_Forms
{
    const QUEUE = 'reactll_connect_queue';

    const QUEUE_MAX = 50;

    public static function init()
    {
        add_action('wpcf7_mail_sent', [__CLASS__, 'cf7']);
        add_action('gform_after_submission', [__CLASS__, 'gravity'], 10, 2);
        add_action('elementor_pro/forms/new_record', [__CLASS__, 'elementor'], 10, 2);
        add_action('wpforms_process_complete', [__CLASS__, 'wpforms'], 10, 4);
        add_action('fluentform/submission_inserted', [__CLASS__, 'fluent'], 10, 3);
        add_action('reactll_connect_retry', [__CLASS__, 'retry']);
    }

    /** The form plugins this site has — reported in the heartbeat. */
    public static function detected()
    {
        $found = [];
        if (defined('WPCF7_VERSION')) $found[] = 'Contact Form 7';
        if (class_exists('GFForms')) $found[] = 'Gravity Forms';
        if (defined('ELEMENTOR_PRO_VERSION')) $found[] = 'Elementor Pro';
        if (function_exists('wpforms')) $found[] = 'WPForms';
        if (defined('FLUENTFORM')) $found[] = 'Fluent Forms';

        return $found;
    }

    public static function cf7($form)
    {
        if (! class_exists('WPCF7_Submission') || ! ($submission = WPCF7_Submission::get_instance())) {
            return;
        }
        $fields = [];
        foreach ((array) $submission->get_posted_data() as $key => $value) {
            if (strpos((string) $key, '_') === 0) continue;
            $fields[$key] = is_array($value) ? implode(', ', $value) : $value;
        }
        self::send('cf7-'.$form->id(), $form->title(), $fields);
    }

    public static function gravity($entry, $form)
    {
        $fields = [];
        foreach ((array) ($form['fields'] ?? []) as $field) {
            if (in_array($field->type, ['html', 'section', 'page', 'captcha', 'password'], true)) continue;
            $value = method_exists($field, 'get_value_export') ? $field->get_value_export($entry) : rgar($entry, (string) $field->id);
            if ($value !== '' && $value !== null) $fields[$field->label ?: 'field_'.$field->id] = $value;
        }
        self::send('gf-'.$form['id'], $form['title'] ?? 'Gravity form', $fields, 'gf-entry-'.$entry['id']);
    }

    public static function elementor($record, $handler)
    {
        $fields = [];
        foreach ((array) $record->get('fields') as $id => $field) {
            if (in_array($field['type'] ?? '', ['password', 'recaptcha', 'recaptcha_v3', 'honeypot', 'html'], true)) continue;
            $fields[! empty($field['title']) ? $field['title'] : $id] = $field['value'] ?? '';
        }
        self::send('elementor', (string) $record->get_form_settings('form_name'), $fields);
    }

    public static function wpforms($fields, $entry, $form_data, $entry_id)
    {
        $out = [];
        foreach ((array) $fields as $field) {
            if (in_array($field['type'] ?? '', ['password', 'captcha', 'html'], true)) continue;
            $out[! empty($field['name']) ? $field['name'] : 'field_'.$field['id']] = $field['value'] ?? '';
        }
        self::send('wpforms-'.($form_data['id'] ?? ''), $form_data['settings']['form_title'] ?? 'WPForms', $out, $entry_id ? 'wpforms-entry-'.$entry_id : null);
    }

    public static function fluent($entry_id, $form_data, $form)
    {
        $out = [];
        foreach ((array) $form_data as $key => $value) {
            if (strpos((string) $key, '_') === 0) continue;
            $out[$key] = is_array($value) ? implode(' ', array_filter(array_map('strval', $value))) : $value;
        }
        self::send('fluent-'.($form->id ?? ''), $form->title ?? 'Fluent form', $out, 'fluent-entry-'.$entry_id);
    }

    private static function send($form_key, $form_name, array $fields, $id = null)
    {
        if (! Reactll_Connect_Client::connected()) {
            return;
        }
        $payload = [
            'id' => $id ?: $form_key.'-'.wp_generate_uuid4(),
            'form' => $form_name ?: $form_key,
            'page' => wp_get_referer() ?: (isset($_SERVER['REQUEST_URI']) ? home_url(wp_unslash($_SERVER['REQUEST_URI'])) : null),
            'pv' => isset($_POST['_rc_pv']) ? sanitize_text_field(wp_unslash($_POST['_rc_pv'])) : null,
            'submitted_at' => gmdate('c'),
            'fields' => array_map(function ($v) { return is_scalar($v) ? (string) $v : wp_json_encode($v); }, $fields),
        ];

        $result = Reactll_Connect_Client::post('submissions', $payload, 3);
        if (is_wp_error($result) && ! in_array(self::status($result), [409, 422], true)) {
            self::queue($payload);
        }
    }

    private static function status(WP_Error $error)
    {
        $data = $error->get_error_data();

        return is_array($data) ? (int) ($data['status'] ?? 0) : 0;
    }

    private static function queue(array $payload)
    {
        $queue = (array) get_option(self::QUEUE, []);
        $queue[] = $payload;
        update_option(self::QUEUE, array_slice($queue, -self::QUEUE_MAX), false);
    }

    /** Hourly: send what could not be sent. The id is kept, so Reactll never doubles a lead. */
    public static function retry()
    {
        $queue = (array) get_option(self::QUEUE, []);
        if (! $queue) return;
        $left = [];
        foreach ($queue as $payload) {
            $result = Reactll_Connect_Client::post('submissions', $payload, 5);
            // Kept unless Reactll took it (2xx), already has it (409) or will never take it (422).
            if (is_wp_error($result) && ! in_array(self::status($result), [409, 422], true)) {
                $left[] = $payload;
            }
        }
        update_option(self::QUEUE, $left, false);
    }
}

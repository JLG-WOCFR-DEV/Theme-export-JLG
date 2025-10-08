<?php
if (!defined('ABSPATH')) {
    exit;
}

class TEJLG_Export_Notifications {
    const SETTINGS_OPTION = 'tejlg_export_notifications_settings';

    /**
     * Initializes hooks for export notifications.
     */
    public static function init() {
        add_action('tejlg_export_history_recorded', [__CLASS__, 'maybe_dispatch_history_notification'], 10, 3);
    }

    /**
     * Returns normalized notification settings.
     *
     * @return array<string,mixed>
     */
    public static function get_settings() {
        $stored = get_option(self::SETTINGS_OPTION, []);

        if (!is_array($stored)) {
            $stored = [];
        }

        return self::normalize_settings($stored);
    }

    /**
     * Updates notification settings in the database.
     *
     * @param array<string,mixed> $settings
     *
     * @return array<string,mixed>
     */
    public static function update_settings($settings) {
        $normalized = self::normalize_settings($settings);

        update_option(self::SETTINGS_OPTION, $normalized, false);

        return $normalized;
    }

    /**
     * Normalizes the notification settings structure.
     *
     * @param array<string,mixed> $settings
     *
     * @return array<string,mixed>
     */
    public static function normalize_settings($settings) {
        if (!is_array($settings)) {
            $settings = [];
        }

        $raw_recipients = isset($settings['recipients']) ? (string) $settings['recipients'] : '';
        $recipients     = implode("\n", self::sanitize_recipient_list($raw_recipients));

        $raw_results = [];

        if (isset($settings['enabled_results'])) {
            if (is_array($settings['enabled_results'])) {
                $raw_results = $settings['enabled_results'];
            } else {
                $raw_results = explode(',', (string) $settings['enabled_results']);
            }
        }

        $allowed_results = [
            TEJLG_Export_History::RESULT_SUCCESS,
            TEJLG_Export_History::RESULT_WARNING,
            TEJLG_Export_History::RESULT_ERROR,
            TEJLG_Export_History::RESULT_INFO,
        ];

        $enabled_results = [];

        foreach ($raw_results as $result) {
            $sanitized = sanitize_key((string) $result);

            if (in_array($sanitized, $allowed_results, true)) {
                $enabled_results[$sanitized] = true;
            }
        }

        if (empty($enabled_results)) {
            $enabled_results = [
                TEJLG_Export_History::RESULT_ERROR   => true,
                TEJLG_Export_History::RESULT_WARNING => true,
            ];
        }

        return [
            'recipients'      => $recipients,
            'enabled_results' => array_keys($enabled_results),
        ];
    }

    /**
     * Returns the list of sanitized e-mail recipients.
     *
     * @param string $recipients
     *
     * @return string[]
     */
    public static function sanitize_recipient_list($recipients) {
        if (!is_string($recipients)) {
            return [];
        }

        $chunks = preg_split('/[\s,;]+/', wp_unslash($recipients));

        if (false === $chunks) {
            $chunks = [];
        }

        $emails = [];

        foreach ($chunks as $candidate) {
            if (!is_scalar($candidate)) {
                continue;
            }

            $candidate = trim((string) $candidate);

            if ('' === $candidate || !is_email($candidate)) {
                continue;
            }

            $emails[$candidate] = $candidate;
        }

        return array_values($emails);
    }

    /**
     * Determines whether notifications are enabled for a result type.
     *
     * @param string $result
     *
     * @return bool
     */
    public static function is_result_enabled($result) {
        $settings = self::get_settings();
        $result   = sanitize_key((string) $result);

        if (empty($settings['enabled_results']) || !is_array($settings['enabled_results'])) {
            return false;
        }

        return in_array($result, $settings['enabled_results'], true);
    }

    /**
     * Sends a notification e-mail when a history entry matches configured filters.
     *
     * @param array<string,mixed> $entry
     * @param array<string,mixed> $job
     * @param array<string,mixed> $context
     */
    public static function maybe_dispatch_history_notification($entry, $job, $context) {
        if (!is_array($entry) || empty($entry['result'])) {
            return;
        }

        if (!is_array($job)) {
            $job = [];
        }

        if (!is_array($context)) {
            $context = [];
        }

        $event = self::build_event($entry, $job, $context);

        if (empty($event['result'])) {
            return;
        }

        if (!self::is_result_enabled($event['result'])) {
            return;
        }

        $include_scheduled = apply_filters('tejlg_export_notifications_include_scheduled', false, $entry, $job, $context, $event);

        if (!$include_scheduled && $event['is_scheduled']) {
            do_action('tejlg_export_notifications_dispatched', $event, null, $entry, $job, $context, false);

            return;
        }

        /**
         * Filters the normalized event payload before it is used to build the notification.
         *
         * @param array<string,mixed>  $event   Normalized event payload.
         * @param array<string,mixed>  $entry   Raw history entry.
         * @param array<string,mixed>  $job     Export job payload.
         * @param array<string,mixed>  $context Additional context provided when recording the entry.
         */
        $event = apply_filters('tejlg_export_notifications_event', $event, $entry, $job, $context);

        if (!is_array($event) || empty($event['result'])) {
            return;
        }

        /**
         * Filters whether the plugin should send the default e-mail for the event.
         *
         * Returning false prevents the built-in mail while still triggering
         * `tejlg_export_notifications_dispatched` with a `$sent` flag set to false.
         *
         * @param bool                 $should_send Whether to send the e-mail.
         * @param array<string,mixed>  $event       Normalized event payload.
         * @param array<string,mixed>  $entry       Raw history entry.
         * @param array<string,mixed>  $job         Export job payload.
         * @param array<string,mixed>  $context     Additional context provided when recording the entry.
         */
        $should_send = apply_filters('tejlg_export_notifications_should_send_mail', true, $event, $entry, $job, $context);

        if (false === $should_send) {
            do_action('tejlg_export_notifications_dispatched', $event, null, $entry, $job, $context, false);

            return;
        }

        $recipients = self::get_recipients($entry, $job, $context, $event);

        if (empty($recipients)) {
            do_action('tejlg_export_notifications_dispatched', $event, null, $entry, $job, $context, false);

            return;
        }

        $body    = self::build_body_from_event($event, $entry, $job, $context);
        $subject = self::build_subject_from_event($event, $entry, $job, $context);

        $payload = [
            'to'           => $recipients,
            'subject'      => $subject,
            'message'      => $body['text'],
            'message_html' => $body['html'],
            'headers'      => ['Content-Type: text/plain; charset=UTF-8'],
            'attachments'  => [],
            'event'        => $event,
        ];

        /**
         * Filters the final mail payload (recipients, subject, body and headers).
         *
         * @param array<string,mixed>  $payload Normalized mail payload.
         * @param array<string,mixed>  $entry   Raw history entry.
         * @param array<string,mixed>  $job     Export job payload.
         * @param array<string,mixed>  $context Additional context provided when recording the entry.
         * @param array<string,mixed>  $event   Normalized event payload.
         */
        $payload = apply_filters('tejlg_export_notifications_mail', $payload, $entry, $job, $context, $event);

        if (!is_array($payload)) {
            do_action('tejlg_export_notifications_dispatched', $event, null, $entry, $job, $context, false);

            return;
        }

        $to          = self::normalize_recipients(isset($payload['to']) ? $payload['to'] : []);
        $subject     = isset($payload['subject']) ? (string) $payload['subject'] : '';
        $message     = isset($payload['message']) ? (string) $payload['message'] : '';
        $headers     = self::normalize_headers(isset($payload['headers']) ? $payload['headers'] : []);
        $attachments = self::normalize_attachments(isset($payload['attachments']) ? $payload['attachments'] : []);

        if ('' === $subject || '' === $message || empty($to)) {
            do_action('tejlg_export_notifications_dispatched', $event, $payload, $entry, $job, $context, false);

            return;
        }

        $sent = wp_mail($to, $subject, $message, $headers, $attachments);

        /**
         * Fires after the notification has been processed.
         *
         * Observers can use the normalized event payload to relay the information
         * to third-party services (Slack, PagerDuty, etc.) even when the default
         * mail is skipped or fails to send.
         *
         * @param array<string,mixed>      $event   Normalized event payload.
         * @param array<string,mixed>|null $payload Mail payload (null if skipped).
         * @param array<string,mixed>      $entry   Raw history entry.
         * @param array<string,mixed>      $job     Export job payload.
         * @param array<string,mixed>      $context Additional context provided when recording the entry.
         * @param bool                     $sent    Whether wp_mail() reported success.
         */
        do_action('tejlg_export_notifications_dispatched', $event, $payload, $entry, $job, $context, (bool) $sent);
    }

    /**
     * Builds the default notification subject.
     *
     * @param array<string,mixed> $entry
     * @param array<string,mixed> $job
     * @param array<string,mixed> $context
     *
     * @return string
     */
    private static function build_subject_from_event($event, $entry, $job, $context) {
        $blogname = isset($event['site']['name']) ? (string) $event['site']['name'] : wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $label    = isset($event['result_label']) ? (string) $event['result_label'] : self::get_result_label(TEJLG_Export_History::RESULT_INFO);

        $subject = sprintf(
            /* translators: %s: site name. */
            __('[%s] Rapport d’export de thème', 'theme-export-jlg'),
            $blogname
        );

        $subject = apply_filters('tejlg_export_notifications_subject', $subject, $entry, $job, $context, $label, $event);

        return (string) $subject;
    }

    /**
     * Builds the default notification body in text and HTML formats.
     *
     * @param array<string,mixed> $entry
     * @param array<string,mixed> $job
     * @param array<string,mixed> $context
     * @param array<string,mixed> $event
     *
     * @return array{html:string,text:string}
     */
    private static function build_body_from_event($event, $entry, $job, $context) {
        $result_label   = isset($event['result_label']) ? (string) $event['result_label'] : self::get_result_label(TEJLG_Export_History::RESULT_INFO);
        $completed_at   = isset($event['completed_at']) ? (string) $event['completed_at'] : '';
        $origin_label   = isset($event['origin_label']) ? (string) $event['origin_label'] : self::get_origin_label('');
        $user_name      = isset($event['user']['name']) ? (string) $event['user']['name'] : '';
        $duration_label = isset($event['duration_label']) ? (string) $event['duration_label'] : '';
        $size_label     = isset($event['size_label']) ? (string) $event['size_label'] : '';
        $exclusions     = isset($event['exclusions']) && is_array($event['exclusions']) ? $event['exclusions'] : [];
        $status_message = isset($event['status_message']) ? (string) $event['status_message'] : '';
        $persistent_url = isset($event['persistent_url']) ? (string) $event['persistent_url'] : '';
        $job_id         = isset($event['job_id']) ? (string) $event['job_id'] : '';
        $site_url       = isset($event['site']['url']) ? (string) $event['site']['url'] : home_url();

        $text_paragraphs = [
            sprintf(
                /* translators: 1: export result label, 2: completion datetime. */
                __('Un export de thème s’est terminé avec le statut « %s » le %s.', 'theme-export-jlg'),
                $result_label,
                $completed_at
            ),
            sprintf(
                /* translators: %s: export origin label. */
                __('Déclencheur : %s.', 'theme-export-jlg'),
                $origin_label
            ),
        ];

        $html_paragraphs = [
            sprintf(
                /* translators: 1: export result label, 2: completion datetime. */
                __('Un export de thème s’est terminé avec le statut « %1$s » le %2$s.', 'theme-export-jlg'),
                '<strong>' . esc_html($result_label) . '</strong>',
                '<strong>' . esc_html($completed_at) . '</strong>'
            ),
            sprintf(
                /* translators: %s: export origin label. */
                __('Déclencheur : %s.', 'theme-export-jlg'),
                esc_html($origin_label)
            ),
        ];

        if ('' !== $user_name) {
            $text_paragraphs[] = sprintf(
                /* translators: %s: user display name. */
                __('Initiateur : %s.', 'theme-export-jlg'),
                $user_name
            );

            $html_paragraphs[] = sprintf(
                /* translators: %s: user display name. */
                __('Initiateur : %s.', 'theme-export-jlg'),
                esc_html($user_name)
            );
        }

        if ('' !== $duration_label) {
            $text_paragraphs[] = sprintf(
                /* translators: %s: duration. */
                __('Durée totale : %s.', 'theme-export-jlg'),
                $duration_label
            );

            $html_paragraphs[] = sprintf(
                /* translators: %s: duration. */
                __('Durée totale : %s.', 'theme-export-jlg'),
                esc_html($duration_label)
            );
        }

        if ('' !== $size_label) {
            $text_paragraphs[] = sprintf(
                /* translators: %s: archive size. */
                __('Taille de l’archive : %s.', 'theme-export-jlg'),
                $size_label
            );

            $html_paragraphs[] = sprintf(
                /* translators: %s: archive size. */
                __('Taille de l’archive : %s.', 'theme-export-jlg'),
                esc_html($size_label)
            );
        }

        if (!empty($exclusions)) {
            $exclusion_text = implode(', ', array_map('sanitize_text_field', $exclusions));

            $text_paragraphs[] = sprintf(
                /* translators: %s: exclusion patterns. */
                __('Motifs d’exclusion appliqués : %s.', 'theme-export-jlg'),
                $exclusion_text
            );

            $html_paragraphs[] = sprintf(
                /* translators: %s: exclusion patterns. */
                __('Motifs d’exclusion appliqués : %s.', 'theme-export-jlg'),
                esc_html($exclusion_text)
            );
        }

        if ('' !== $status_message) {
            $status_text = wp_strip_all_tags($status_message);

            $text_paragraphs[] = sprintf(
                /* translators: %s: status message. */
                __('Détails : %s.', 'theme-export-jlg'),
                $status_text
            );

            $html_paragraphs[] = sprintf(
                /* translators: %s: status message. */
                __('Détails : %s.', 'theme-export-jlg'),
                esc_html($status_text)
            );
        }

        if ('' !== $persistent_url) {
            $text_paragraphs[] = sprintf(
                /* translators: %s: download URL. */
                __('Télécharger l’archive : %s', 'theme-export-jlg'),
                $persistent_url
            );

            $html_paragraphs[] = sprintf(
                /* translators: %s: download URL. */
                __('Télécharger l’archive : %s', 'theme-export-jlg'),
                sprintf('<a href="%1$s">%1$s</a>', esc_url($persistent_url))
            );
        }

        if ('' !== $job_id) {
            $text_paragraphs[] = sprintf(
                /* translators: %s: job identifier. */
                __('Identifiant de tâche : %s.', 'theme-export-jlg'),
                $job_id
            );

            $html_paragraphs[] = sprintf(
                /* translators: %s: job identifier. */
                __('Identifiant de tâche : %s.', 'theme-export-jlg'),
                esc_html($job_id)
            );
        }

        $text_paragraphs[] = sprintf(
            /* translators: %s: site home URL. */
            __('Site : %s', 'theme-export-jlg'),
            $site_url
        );
        $html_paragraphs[] = sprintf(
            /* translators: %s: site home URL. */
            __('Site : %s', 'theme-export-jlg'),
            sprintf('<a href="%1$s">%1$s</a>', esc_url($site_url))
        );

        $signature = __('— Theme Export JLG', 'theme-export-jlg');

        $text_paragraphs[] = $signature;
        $html_paragraphs[] = esc_html($signature);

        $text_body = implode("\n\n", $text_paragraphs);

        /**
         * Filters the notification body before it is sent.
         *
         * @param string               $body    Email body.
         * @param array<string,mixed>  $entry   History entry.
         * @param array<string,mixed>  $job     Export job payload.
         * @param array<string,mixed>  $context Additional context provided when recording the entry.
         * @param string               $result_label Human readable result label.
         * @param array<string,mixed>  $event   Normalized event payload.
         */
        $text_body = apply_filters('tejlg_export_notifications_body', $text_body, $entry, $job, $context, $result_label, $event);

        $html_body = self::build_body_html($html_paragraphs);

        return [
            'text' => (string) $text_body,
            'html' => $html_body,
        ];
    }

    /**
     * Builds the HTML body using sanitized paragraphs.
     *
     * @param string[] $paragraphs
     *
     * @return string
     */
    private static function build_body_html($paragraphs) {
        if (!is_array($paragraphs)) {
            return '';
        }

        $html_parts = [];

        foreach ($paragraphs as $paragraph) {
            if (!is_scalar($paragraph)) {
                continue;
            }

            $html_parts[] = '<p>' . $paragraph . '</p>';
        }

        return implode("\n", $html_parts);
    }

    /**
     * Builds a normalized event payload consumed by filters and observers.
     *
     * @param array<string,mixed> $entry
     * @param array<string,mixed> $job
     * @param array<string,mixed> $context
     *
     * @return array<string,mixed>
     */
    private static function build_event($entry, $job, $context) {
        $result = isset($entry['result']) ? sanitize_key((string) $entry['result']) : '';

        if ('' === $result) {
            $result = TEJLG_Export_History::RESULT_INFO;
        }

        $origin = isset($entry['origin']) ? sanitize_key((string) $entry['origin']) : '';

        if ('' === $origin) {
            $origin = 'web';
        }

        $timestamp = isset($entry['timestamp']) ? (int) $entry['timestamp'] : time();

        if ($timestamp <= 0) {
            $timestamp = time();
        }

        $date_format = get_option('date_format', 'Y-m-d');
        $time_format = get_option('time_format', 'H:i');
        $datetime    = trim($date_format . ' ' . $time_format);

        if (function_exists('wp_date')) {
            $completed_at = wp_date($datetime, $timestamp);
        } else {
            $completed_at = date_i18n($datetime, $timestamp);
        }

        $duration_seconds = null;

        if (isset($entry['duration']) && is_numeric($entry['duration'])) {
            $duration_seconds = max(0, (int) $entry['duration']);
        } elseif (isset($job['duration']) && is_numeric($job['duration'])) {
            $duration_seconds = max(0, (int) $job['duration']);
        }

        $duration_label = '';

        if (null !== $duration_seconds) {
            $duration_label = human_readable_duration($duration_seconds);
        }

        $size_bytes = null;

        if (isset($entry['zip_file_size']) && is_numeric($entry['zip_file_size'])) {
            $size_bytes = max(0, (int) $entry['zip_file_size']);
        }

        $size_label = '';

        if (null !== $size_bytes && $size_bytes > 0) {
            $size_label = size_format($size_bytes);
        }

        $user_id   = isset($entry['user_id']) ? (int) $entry['user_id'] : 0;
        $user_name = '';

        if (!empty($entry['user_name'])) {
            $user_name = sanitize_text_field((string) $entry['user_name']);
        } elseif ($user_id > 0) {
            $user = get_userdata($user_id);

            if ($user instanceof WP_User) {
                $user_name = $user->display_name;
            }
        }

        $exclusions = [];

        if (!empty($entry['exclusions']) && is_array($entry['exclusions'])) {
            foreach ($entry['exclusions'] as $exclusion) {
                if (!is_scalar($exclusion)) {
                    continue;
                }

                $value = trim((string) $exclusion);

                if ('' === $value) {
                    continue;
                }

                $exclusions[] = sanitize_text_field($value);
            }
        }

        $status_message = '';

        if (!empty($entry['status_message'])) {
            $status_message = wp_strip_all_tags((string) $entry['status_message']);
        }

        $persistent_url = '';

        if (!empty($entry['persistent_url'])) {
            $persistent_url = esc_url_raw((string) $entry['persistent_url']);
        }

        $job_id = '';

        if (!empty($entry['job_id'])) {
            $job_id = sanitize_text_field((string) $entry['job_id']);
        } elseif (!empty($context['job_id'])) {
            $job_id = sanitize_text_field((string) $context['job_id']);
        } elseif (!empty($job['id'])) {
            $job_id = sanitize_text_field((string) $job['id']);
        }

        $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

        return [
            'result'        => $result,
            'result_label'  => self::get_result_label($result),
            'origin'        => $origin,
            'origin_label'  => self::get_origin_label($origin),
            'is_scheduled'  => ('schedule' === $origin),
            'timestamp'     => $timestamp,
            'completed_at'  => $completed_at,
            'completed_at_gmt' => gmdate(DATE_ATOM, $timestamp),
            'duration'      => $duration_seconds,
            'duration_label'=> $duration_label,
            'size'          => $size_bytes,
            'size_label'    => $size_label,
            'user'          => [
                'id'   => $user_id,
                'name' => $user_name,
            ],
            'exclusions'    => $exclusions,
            'status_message'=> $status_message,
            'persistent_url'=> $persistent_url,
            'job_id'        => $job_id,
            'site'          => [
                'name' => $blogname,
                'url'  => home_url(),
            ],
        ];
    }

    /**
     * Returns the list of recipients for the notification.
     *
     * @param array<string,mixed> $entry
     * @param array<string,mixed> $job
     * @param array<string,mixed> $context
     *
     * @return string[]
     */
    /**
     * Normalizes a list of recipients to an array of sanitized addresses.
     *
     * @param mixed $recipients
     *
     * @return string[]
     */
    private static function normalize_recipients($recipients) {
        if (is_string($recipients)) {
            return self::sanitize_recipient_list($recipients);
        }

        if (!is_array($recipients)) {
            return [];
        }

        $sanitized = [];

        foreach ($recipients as $recipient) {
            if (!is_scalar($recipient)) {
                continue;
            }

            $candidate = trim((string) $recipient);

            if ('' === $candidate) {
                continue;
            }

            if (preg_match('/^(.*)<([^<>]+)>$/', $candidate, $matches)) {
                $label = trim($matches[1]);
                $email = trim($matches[2]);

                if (is_email($email)) {
                    $value = '' !== $label ? sprintf('%s <%s>', $label, $email) : $email;
                    $sanitized[$email] = $value;

                    continue;
                }
            }

            if (is_email($candidate)) {
                $sanitized[$candidate] = $candidate;

                continue;
            }

            foreach (self::sanitize_recipient_list($candidate) as $email) {
                $sanitized[$email] = $email;
            }
        }

        return array_values($sanitized);
    }

    /**
     * Normalizes headers to an array of strings.
     *
     * @param mixed $headers
     *
     * @return string[]
     */
    private static function normalize_headers($headers) {
        if (is_string($headers)) {
            $headers = preg_split('/\r?\n/', $headers);
        }

        if (!is_array($headers)) {
            return [];
        }

        $normalized = [];

        foreach ($headers as $header) {
            if (!is_scalar($header)) {
                continue;
            }

            $value = trim((string) $header);

            if ('' === $value) {
                continue;
            }

            $normalized[] = $value;
        }

        return $normalized;
    }

    /**
     * Normalizes attachments to an array of file paths or URLs.
     *
     * @param mixed $attachments
     *
     * @return string[]
     */
    private static function normalize_attachments($attachments) {
        if (is_string($attachments)) {
            $attachments = preg_split('/\r?\n/', $attachments);
        }

        if (!is_array($attachments)) {
            return [];
        }

        $normalized = [];

        foreach ($attachments as $attachment) {
            if (!is_scalar($attachment)) {
                continue;
            }

            $value = trim((string) $attachment);

            if ('' === $value) {
                continue;
            }

            $normalized[] = $value;
        }

        return $normalized;
    }

    /**
     * Returns the list of recipients for the notification.
     *
     * @param array<string,mixed> $entry
     * @param array<string,mixed> $job
     * @param array<string,mixed> $context
     * @param array<string,mixed> $event
     *
     * @return string[]
     */
    private static function get_recipients($entry, $job, $context, $event) {
        $settings = self::get_settings();
        $recipients = [];

        if (!empty($settings['recipients'])) {
            $recipients = self::sanitize_recipient_list($settings['recipients']);
        }

        if (empty($recipients)) {
            $admin_email = get_option('admin_email');

            if (is_email($admin_email)) {
                $recipients[] = $admin_email;
            }
        }

        /**
         * Filters the list of recipients receiving export notifications.
         *
         * @param string[]             $recipients Recipient email addresses.
         * @param array<string,mixed>  $entry      History entry.
         * @param array<string,mixed>  $job        Export job payload.
         * @param array<string,mixed>  $context    Additional context.
         * @param array<string,mixed>  $settings   Normalized notification settings.
         * @param array<string,mixed>  $event      Normalized event payload.
         */
        $recipients = apply_filters('tejlg_export_notifications_recipients', $recipients, $entry, $job, $context, $settings, $event);

        if (!is_array($recipients)) {
            return [];
        }

        $sanitized = [];

        foreach ($recipients as $recipient) {
            if (!is_scalar($recipient)) {
                continue;
            }

            $email = trim((string) $recipient);

            if ('' === $email || !is_email($email)) {
                continue;
            }

            $sanitized[$email] = $email;
        }

        return array_values($sanitized);
    }

    /**
     * Maps a result key to a human readable label.
     *
     * @param string $result
     *
     * @return string
     */
    private static function get_result_label($result) {
        switch ($result) {
            case TEJLG_Export_History::RESULT_SUCCESS:
                return __('Succès', 'theme-export-jlg');
            case TEJLG_Export_History::RESULT_WARNING:
                return __('Avertissement', 'theme-export-jlg');
            case TEJLG_Export_History::RESULT_ERROR:
                return __('Erreur', 'theme-export-jlg');
            default:
                return __('Information', 'theme-export-jlg');
        }
    }

    /**
     * Maps an origin key to a human readable label.
     *
     * @param string $origin
     *
     * @return string
     */
    private static function get_origin_label($origin) {
        switch ($origin) {
            case 'cli':
                return __('Ligne de commande', 'theme-export-jlg');
            case 'schedule':
                return __('Planification', 'theme-export-jlg');
            case 'web':
                return __('Interface web', 'theme-export-jlg');
            default:
                return __('Origine inconnue', 'theme-export-jlg');
        }
    }
}

if (!function_exists('human_readable_duration')) {
    /**
     * Formats a duration in seconds to a localized string.
     *
     * @param int $seconds
     *
     * @return string
     */
    function human_readable_duration($seconds) {
        $seconds = max(0, (int) $seconds);

        if ($seconds < MINUTE_IN_SECONDS) {
            return sprintf(_n('%d seconde', '%d secondes', $seconds, 'theme-export-jlg'), $seconds);
        }

        if ($seconds < HOUR_IN_SECONDS) {
            $minutes = (int) round($seconds / MINUTE_IN_SECONDS);

            return sprintf(_n('%d minute', '%d minutes', $minutes, 'theme-export-jlg'), $minutes);
        }

        if ($seconds < DAY_IN_SECONDS) {
            $hours = floor($seconds / HOUR_IN_SECONDS);
            $minutes = (int) round(($seconds % HOUR_IN_SECONDS) / MINUTE_IN_SECONDS);

            if ($minutes > 0) {
                return sprintf(
                    /* translators: 1: hours count, 2: minutes count. */
                    _n('%1$d heure %2$d minute', '%1$d heures %2$d minutes', $hours, 'theme-export-jlg'),
                    $hours,
                    $minutes
                );
            }

            return sprintf(_n('%d heure', '%d heures', $hours, 'theme-export-jlg'), $hours);
        }

        $days = floor($seconds / DAY_IN_SECONDS);

        return sprintf(_n('%d jour', '%d jours', $days, 'theme-export-jlg'), $days);
    }
}

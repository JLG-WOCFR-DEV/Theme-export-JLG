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

        if (!self::is_result_enabled($entry['result'])) {
            return;
        }

        $include_scheduled = apply_filters('tejlg_export_notifications_include_scheduled', false, $entry, $job, $context);

        if (!$include_scheduled && isset($entry['origin']) && 'schedule' === (string) $entry['origin']) {
            return;
        }

        $recipients = self::get_recipients($entry, $job, $context);

        if (empty($recipients)) {
            return;
        }

        $subject = self::build_subject($entry, $job, $context);
        $body    = self::build_body($entry, $job, $context);

        $payload = [
            'to'      => $recipients,
            'subject' => $subject,
            'message' => $body,
        ];

        $payload = apply_filters('tejlg_export_notifications_mail', $payload, $entry, $job, $context);

        if (!is_array($payload) || empty($payload['to']) || empty($payload['subject']) || empty($payload['message'])) {
            return;
        }

        $to      = $payload['to'];
        $subject = (string) $payload['subject'];
        $message = (string) $payload['message'];

        if (empty($to)) {
            return;
        }

        wp_mail($to, $subject, $message);
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
    private static function build_subject($entry, $job, $context) {
        $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $result   = isset($entry['result']) ? (string) $entry['result'] : TEJLG_Export_History::RESULT_INFO;
        $label    = self::get_result_label($result);

        $subject = sprintf(
            /* translators: %s: site name. */
            __('[%s] Rapport d’export de thème', 'theme-export-jlg'),
            $blogname
        );

        $subject = apply_filters('tejlg_export_notifications_subject', $subject, $entry, $job, $context, $label);

        return (string) $subject;
    }

    /**
     * Builds the default notification body.
     *
     * @param array<string,mixed> $entry
     * @param array<string,mixed> $job
     * @param array<string,mixed> $context
     *
     * @return string
     */
    private static function build_body($entry, $job, $context) {
        $result       = isset($entry['result']) ? (string) $entry['result'] : TEJLG_Export_History::RESULT_INFO;
        $result_label = self::get_result_label($result);
        $origin_label = self::get_origin_label(isset($entry['origin']) ? (string) $entry['origin'] : '');
        $timestamp    = isset($entry['timestamp']) ? (int) $entry['timestamp'] : time();

        $date_format = get_option('date_format', 'Y-m-d');
        $time_format = get_option('time_format', 'H:i');
        $datetime    = trim($date_format . ' ' . $time_format);

        if (function_exists('wp_date')) {
            $completed_at = wp_date($datetime, $timestamp);
        } else {
            $completed_at = date_i18n($datetime, $timestamp);
        }

        $duration = '';

        if (!empty($entry['duration'])) {
            $duration = human_readable_duration((int) $entry['duration']);
        }

        if ('' === $duration && isset($job['duration']) && is_numeric($job['duration'])) {
            $duration = human_readable_duration((int) $job['duration']);
        }

        $size_label = '';

        if (!empty($entry['zip_file_size'])) {
            $size_label = size_format((int) $entry['zip_file_size']);
        }

        $user_label = '';

        if (!empty($entry['user_name'])) {
            $user_label = $entry['user_name'];
        } elseif (!empty($entry['user_id'])) {
            $user = get_userdata((int) $entry['user_id']);

            if ($user instanceof WP_User) {
                $user_label = $user->display_name;
            }
        }

        $paragraphs = [
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

        if ('' !== $user_label) {
            $paragraphs[] = sprintf(
                /* translators: %s: user display name. */
                __('Initiateur : %s.', 'theme-export-jlg'),
                $user_label
            );
        }

        if ('' !== $duration) {
            $paragraphs[] = sprintf(
                /* translators: %s: duration. */
                __('Durée totale : %s.', 'theme-export-jlg'),
                $duration
            );
        }

        if ('' !== $size_label) {
            $paragraphs[] = sprintf(
                /* translators: %s: archive size. */
                __('Taille de l’archive : %s.', 'theme-export-jlg'),
                $size_label
            );
        }

        if (!empty($entry['exclusions']) && is_array($entry['exclusions'])) {
            $paragraphs[] = sprintf(
                /* translators: %s: exclusion patterns. */
                __('Motifs d’exclusion appliqués : %s.', 'theme-export-jlg'),
                implode(', ', array_map('sanitize_text_field', $entry['exclusions']))
            );
        }

        if (!empty($entry['status_message'])) {
            $paragraphs[] = sprintf(
                /* translators: %s: status message. */
                __('Détails : %s.', 'theme-export-jlg'),
                $entry['status_message']
            );
        }

        if (!empty($entry['persistent_url'])) {
            $paragraphs[] = sprintf(
                /* translators: %s: download URL. */
                __('Télécharger l’archive : %s', 'theme-export-jlg'),
                $entry['persistent_url']
            );
        }

        if (!empty($entry['job_id'])) {
            $paragraphs[] = sprintf(
                /* translators: %s: job identifier. */
                __('Identifiant de tâche : %s.', 'theme-export-jlg'),
                $entry['job_id']
            );
        }

        $paragraphs[] = sprintf(
            /* translators: %s: site home URL. */
            __('Site : %s', 'theme-export-jlg'),
            home_url()
        );

        $paragraphs[] = __('— Theme Export JLG', 'theme-export-jlg');

        $body = implode("\n\n", $paragraphs);

        /**
         * Filters the notification body before it is sent.
         *
         * @param string               $body    Email body.
         * @param array<string,mixed>  $entry   History entry.
         * @param array<string,mixed>  $job     Export job payload.
         * @param array<string,mixed>  $context Additional context provided when recording the entry.
         * @param string               $result_label Human readable result label.
         */
        $body = apply_filters('tejlg_export_notifications_body', $body, $entry, $job, $context, $result_label);

        return (string) $body;
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
    private static function get_recipients($entry, $job, $context) {
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
         */
        $recipients = apply_filters('tejlg_export_notifications_recipients', $recipients, $entry, $job, $context, $settings);

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

<?php
require_once __DIR__ . '/class-tejlg-export-history.php';
require_once __DIR__ . '/class-tejlg-zip-writer.php';

class TEJLG_Export {

    const SCHEDULE_SETTINGS_OPTION = 'tejlg_export_schedule_settings';
    const SCHEDULE_EVENT_HOOK      = 'tejlg_scheduled_theme_export';
    const CLEANUP_EVENT_HOOK       = 'tejlg_cleanup_theme_exports';
    const MAX_EXCLUSION_PATTERNS   = 200;
    const MAX_EXCLUSION_PATTERN_LENGTH = 255;

    /**
     * Normalise et sécurise une liste de motifs d'exclusion.
     *
     * @param string|array $raw_patterns Motifs provenant d'une saisie utilisateur.
     * @param int|null     $max_patterns Nombre maximum de motifs acceptés.
     *
     * @return array<int, string> Liste nettoyée de motifs.
     */
    public static function sanitize_exclusion_patterns($raw_patterns, $max_patterns = null) {
        $max_patterns = is_int($max_patterns) && $max_patterns > 0
            ? $max_patterns
            : self::MAX_EXCLUSION_PATTERNS;

        if (is_string($raw_patterns)) {
            $candidates = preg_split('/[,\r\n]+/', $raw_patterns);
        } elseif (is_array($raw_patterns)) {
            $candidates = $raw_patterns;
        } else {
            $candidates = [];
        }

        if (!is_array($candidates)) {
            $candidates = [];
        }

        $sanitized = [];

        foreach ($candidates as $candidate) {
            if (is_array($candidate) || is_object($candidate)) {
                continue;
            }

            $pattern = (string) $candidate;
            $pattern = wp_check_invalid_utf8($pattern, true);
            $pattern = wp_strip_all_tags($pattern);
            $pattern = preg_replace('/[\x00-\x1F\x7F]/', '', $pattern);

            if (!is_string($pattern)) {
                continue;
            }

            $pattern = trim($pattern);
            $pattern = preg_replace('#^[\\/]+#', '', $pattern);

            if (!is_string($pattern)) {
                continue;
            }

            if ('' === $pattern) {
                continue;
            }

            if (function_exists('mb_substr')) {
                $pattern = mb_substr($pattern, 0, self::MAX_EXCLUSION_PATTERN_LENGTH);
            } else {
                $pattern = substr($pattern, 0, self::MAX_EXCLUSION_PATTERN_LENGTH);
            }

            if ('' === $pattern) {
                continue;
            }

            if (in_array($pattern, $sanitized, true)) {
                continue;
            }

            $sanitized[] = $pattern;

            if (count($sanitized) >= $max_patterns) {
                break;
            }
        }

        return $sanitized;
    }

    /**
     * Renvoie la liste des motifs au format texte prêt à être stocké.
     *
     * @param string|array $raw_patterns Motifs provenant d'une saisie utilisateur.
     * @param int|null     $max_patterns Nombre maximum de motifs acceptés.
     *
     * @return string Motifs normalisés, séparés par des retours à la ligne.
     */
    public static function sanitize_exclusion_patterns_string($raw_patterns, $max_patterns = null) {
        $patterns = self::sanitize_exclusion_patterns($raw_patterns, $max_patterns);

        if (empty($patterns)) {
            return '';
        }

        return implode("\n", $patterns);
    }

    public static function get_available_schedule_frequencies() {
        $frequencies = [
            'disabled'   => __('Désactivé', 'theme-export-jlg'),
            'hourly'     => __('Toutes les heures', 'theme-export-jlg'),
            'twicedaily' => __('Deux fois par jour', 'theme-export-jlg'),
            'daily'      => __('Une fois par jour', 'theme-export-jlg'),
            'weekly'     => __('Une fois par semaine', 'theme-export-jlg'),
        ];

        /**
         * Permet de modifier la liste des fréquences proposées pour la planification.
         *
         * @param array<string,string> $frequencies
         */
        return apply_filters('tejlg_export_schedule_frequencies', $frequencies);
    }

    public static function get_default_schedule_settings() {
        return [
            'frequency'      => 'disabled',
            'exclusions'     => '',
            'retention_days' => 30,
            'run_time'       => '00:00',
        ];
    }

    public static function get_schedule_settings() {
        $stored = get_option(self::SCHEDULE_SETTINGS_OPTION, []);

        if (!is_array($stored)) {
            $stored = [];
        }

        $settings = array_merge(self::get_default_schedule_settings(), $stored);

        return self::normalize_schedule_settings($settings);
    }

    public static function update_schedule_settings($settings) {
        $normalized = self::normalize_schedule_settings($settings);

        update_option(self::SCHEDULE_SETTINGS_OPTION, $normalized, false);

        return $normalized;
    }

    private static function normalize_schedule_settings($settings) {
        $defaults    = self::get_default_schedule_settings();
        $frequencies = array_keys(self::get_available_schedule_frequencies());

        $frequency = isset($settings['frequency']) ? sanitize_key((string) $settings['frequency']) : $defaults['frequency'];

        if (!in_array($frequency, $frequencies, true)) {
            $frequency = $defaults['frequency'];
        }

        $exclusions = isset($settings['exclusions']) ? (string) $settings['exclusions'] : $defaults['exclusions'];
        $exclusions = (string) wp_unslash($exclusions);
        $exclusions = self::sanitize_exclusion_patterns_string($exclusions);

        $retention = isset($settings['retention_days']) ? (int) $settings['retention_days'] : (int) $defaults['retention_days'];

        if ($retention < 0) {
            $retention = 0;
        }

        $default_run_time = isset($defaults['run_time']) ? (string) $defaults['run_time'] : '00:00';
        $run_time         = isset($settings['run_time']) ? (string) $settings['run_time'] : $default_run_time;
        $run_time         = trim($run_time);

        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $run_time, $matches)) {
            $run_time = $default_run_time;
        } else {
            $run_time = sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
        }

        return [
            'frequency'      => $frequency,
            'exclusions'     => $exclusions,
            'retention_days' => $retention,
            'run_time'       => $run_time,
        ];
    }

    public static function maybe_schedule_theme_export_event() {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
            return;
        }

        $settings  = self::get_schedule_settings();
        $frequency = isset($settings['frequency']) ? (string) $settings['frequency'] : 'disabled';

        if ('disabled' === $frequency) {
            self::clear_scheduled_theme_export_event();

            return;
        }

        if (false !== wp_next_scheduled(self::SCHEDULE_EVENT_HOOK)) {
            return;
        }

        $first_run = self::calculate_next_schedule_timestamp($settings);

        if (!is_int($first_run)) {
            $first_run = time() + MINUTE_IN_SECONDS;
        }

        /**
         * Filtre l'horodatage du premier export planifié.
         *
         * @param int   $first_run Horodatage en secondes.
         * @param array $settings  Réglages de planification.
         */
        $first_run = apply_filters('tejlg_export_schedule_first_run', $first_run, $settings);

        /**
         * Filtre l'horodatage final utilisé pour la planification de l'export.
         *
         * @param int   $first_run Horodatage en secondes.
         * @param array $settings  Réglages de planification normalisés.
         */
        $first_run = apply_filters('tejlg_export_schedule_timestamp', $first_run, $settings);

        if (!is_int($first_run) || $first_run <= time()) {
            $first_run = time() + MINUTE_IN_SECONDS;
        }

        wp_schedule_event($first_run, $frequency, self::SCHEDULE_EVENT_HOOK);
    }

    public static function reschedule_theme_export_event() {
        self::clear_scheduled_theme_export_event();
        self::maybe_schedule_theme_export_event();
    }

    public static function clear_scheduled_theme_export_event() {
        if (!function_exists('wp_clear_scheduled_hook')) {
            return;
        }

        wp_clear_scheduled_hook(self::SCHEDULE_EVENT_HOOK);
    }

    public static function get_next_scheduled_export_timestamp() {
        if (!function_exists('wp_next_scheduled')) {
            return false;
        }

        return wp_next_scheduled(self::SCHEDULE_EVENT_HOOK);
    }

    public static function calculate_next_schedule_timestamp($settings, $reference_time = null) {
        if (!is_array($settings)) {
            $settings = [];
        }

        $normalized = self::normalize_schedule_settings($settings);

        if (!isset($normalized['frequency']) || 'disabled' === $normalized['frequency']) {
            return null;
        }

        $reference_time = is_int($reference_time) ? $reference_time : time();

        $timezone = self::get_site_timezone();

        try {
            $now = (new \DateTimeImmutable('@' . $reference_time))->setTimezone($timezone);
        } catch (\Exception $e) {
            $now = new \DateTimeImmutable('@' . $reference_time);
        }

        $run_time_parts = explode(':', isset($normalized['run_time']) ? (string) $normalized['run_time'] : '00:00');
        $hour           = isset($run_time_parts[0]) ? (int) $run_time_parts[0] : 0;
        $minute         = isset($run_time_parts[1]) ? (int) $run_time_parts[1] : 0;

        $next_run  = $now->setTime($hour, $minute, 0);
        $frequency = isset($normalized['frequency']) ? (string) $normalized['frequency'] : 'daily';

        if ('hourly' === $frequency || 'twicedaily' === $frequency) {
            $interval = 'hourly' === $frequency ? HOUR_IN_SECONDS : 12 * HOUR_IN_SECONDS;

            if ($next_run->getTimestamp() > $reference_time) {
                $diff = $next_run->getTimestamp() - $reference_time;
                $steps = (int) floor($diff / $interval);

                if ($diff > $interval && $steps > 0) {
                    $next_run = $next_run->modify(sprintf('-%d seconds', $steps * $interval));
                }
            }

            if ($next_run->getTimestamp() <= $reference_time) {
                $next_run = self::advance_to_next_interval($next_run, (int) $reference_time, $interval);
            }

            return (int) $next_run->getTimestamp();
        }

        if ($next_run->getTimestamp() < $reference_time) {
            switch ($frequency) {
                case 'weekly':
                    $next_run = $next_run->modify('+1 week');

                    break;

                default:
                    $next_run = $next_run->modify('+1 day');
            }
        }

        return (int) $next_run->getTimestamp();
    }

    private static function advance_to_next_interval(\DateTimeImmutable $start, $reference_time, $interval_in_seconds) {
        if ($interval_in_seconds <= 0) {
            return $start;
        }

        $diff = $reference_time - $start->getTimestamp();

        if ($diff < 0) {
            return $start;
        }

        $steps = (int) floor($diff / $interval_in_seconds);

        if ($steps > 0) {
            $start = $start->modify(sprintf('+%d seconds', $steps * $interval_in_seconds));
        }

        if ($start->getTimestamp() < $reference_time) {
            $start = $start->modify(sprintf('+%d seconds', $interval_in_seconds));
        }

        return $start;
    }

    private static function get_site_timezone() {
        if (function_exists('wp_timezone')) {
            return wp_timezone();
        }

        $timezone_string = get_option('timezone_string');

        if (is_string($timezone_string) && '' !== $timezone_string) {
            try {
                return new \DateTimeZone($timezone_string);
            } catch (\Exception $e) {
                // Fallback to offset handling below.
            }
        }

        $offset   = (float) get_option('gmt_offset', 0);
        $hours    = (int) $offset;
        $minutes  = (int) round(abs($offset - $hours) * 60);
        $sign     = $offset < 0 ? '-' : '+';
        $timezone = sprintf('%s%02d:%02d', $sign, abs($hours), abs($minutes));

        try {
            return new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            return new \DateTimeZone('UTC');
        }
    }

    public static function ensure_cleanup_event_scheduled() {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
            return;
        }

        if (false !== wp_next_scheduled(self::CLEANUP_EVENT_HOOK)) {
            return;
        }

        wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', self::CLEANUP_EVENT_HOOK);
    }

    public static function clear_cleanup_event() {
        if (!function_exists('wp_clear_scheduled_hook')) {
            return;
        }

        wp_clear_scheduled_hook(self::CLEANUP_EVENT_HOOK);
    }

    private static function get_schedule_exclusion_list($settings) {
        $raw = isset($settings['exclusions']) ? (string) $settings['exclusions'] : '';

        if ('' === trim($raw)) {
            return [];
        }

        $split = preg_split('/[,\n]+/', $raw);

        if (false === $split) {
            return [];
        }

        $patterns = array_values(array_filter(
            array_map(
                static function ($pattern) {
                    if (!is_scalar($pattern)) {
                        return '';
                    }

                    return trim((string) $pattern);
                },
                $split
            ),
            static function ($pattern) {
                return '' !== $pattern;
            }
        ));

        return self::sanitize_exclusion_patterns($patterns);
    }

    public static function run_scheduled_theme_export() {
        $settings  = self::get_schedule_settings();
        $frequency = isset($settings['frequency']) ? (string) $settings['frequency'] : 'disabled';

        if ('disabled' === $frequency) {
            self::clear_scheduled_theme_export_event();

            return;
        }

        $exclusions = self::get_schedule_exclusion_list($settings);
        $result     = self::export_theme($exclusions);

        if (is_wp_error($result)) {
            self::notify_scheduled_export_failure($result->get_error_message(), $settings, null, $result, $exclusions);

            return;
        }

        $job_id = (string) $result;
        $job    = self::get_job($job_id);

        if (is_array($job)) {
            $job['created_via'] = 'schedule';
            self::persist_job($job);
        }

        self::run_pending_export_jobs();

        $job = self::get_job($job_id);

        if (!is_array($job)) {
            self::notify_scheduled_export_failure(
                esc_html__("L'export planifié a échoué : la tâche générée est introuvable.", 'theme-export-jlg'),
                $settings,
                null,
                null,
                $exclusions
            );

            return;
        }

        $status = isset($job['status']) ? (string) $job['status'] : '';

        if ('completed' !== $status) {
            $message = isset($job['message']) && is_string($job['message']) && '' !== $job['message']
                ? $job['message']
                : esc_html__("L'export planifié n'a pas pu être finalisé.", 'theme-export-jlg');

            self::notify_scheduled_export_failure($message, $settings, $job, null, $exclusions);

            return;
        }

        $persistence = self::persist_export_archive($job);

        $persistent_path = isset($persistence['path']) ? (string) $persistence['path'] : '';
        $persistent_url  = isset($persistence['url']) ? (string) $persistence['url'] : '';

        if ('' === $persistent_path || '' === $persistent_url) {
            $failure_message = esc_html__(
                "Impossible de conserver l'archive de l'export planifié : aucune destination valide n'a été générée.",
                'theme-export-jlg'
            );

            self::mark_job_failed(
                $job_id,
                $failure_message,
                [
                    'failure_code' => 'persistence_failed',
                ]
            );

            $failed_job = self::get_job($job_id);

            if (!is_array($failed_job)) {
                $failed_job = $job;
                $failed_job['status']  = 'failed';
                $failed_job['message'] = $failure_message;
            }

            TEJLG_Export_History::record_job(
                $failed_job,
                [
                    'origin' => 'schedule',
                ]
            );

            $error = new WP_Error(
                'tejlg_persist_export_archive_missing_destination',
                $failure_message,
                [
                    'persistence' => $persistence,
                ]
            );

            self::notify_scheduled_export_failure($failure_message, $settings, $failed_job, $error, $exclusions);

            return;
        }

        $delete_context = [
            'origin' => 'schedule',
            'reason' => 'persisted',
        ];

        if ('' !== $persistent_path) {
            $delete_context['persistent_path'] = $persistent_path;
        }

        if ('' !== $persistent_url) {
            $delete_context['download_url'] = $persistent_url;
        }

        self::delete_job($job_id, $delete_context);

        self::cleanup_persisted_archives();

        self::notify_scheduled_export_success($job, $settings, $persistence, $exclusions);
    }

    private static function notify_scheduled_export_success($job, $settings, $persistence, array $exclusions) {
        $context = [
            'type'        => 'success',
            'job'         => $job,
            'settings'    => $settings,
            'persistence' => $persistence,
            'exclusions'  => $exclusions,
        ];

        $recipient = self::get_scheduled_notification_recipient('success', $context);

        if (!$recipient) {
            return;
        }

        $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

        $subject = sprintf(__('Export de thème programmé réussi – %s', 'theme-export-jlg'), $blogname);

        $completed_at = isset($job['completed_at']) ? (int) $job['completed_at'] : time();
        $date_format  = get_option('date_format', 'Y-m-d');
        $time_format  = get_option('time_format', 'H:i');
        $datetime     = trim($date_format . ' ' . $time_format);

        if (function_exists('wp_date')) {
            $formatted_date = wp_date($datetime, $completed_at);
        } else {
            $formatted_date = date_i18n($datetime, $completed_at);
        }

        $download_url = isset($persistence['url']) ? (string) $persistence['url'] : '';
        $retention    = isset($settings['retention_days']) ? (int) $settings['retention_days'] : 0;

        $paragraphs = [
            sprintf(__('Un export planifié du thème a été généré le %s.', 'theme-export-jlg'), $formatted_date),
        ];

        if ('' !== $download_url) {
            $paragraphs[] = sprintf(__('Téléchargez l’archive : %s', 'theme-export-jlg'), $download_url);
        }

        if (!empty($exclusions)) {
            $paragraphs[] = sprintf(__('Motifs d’exclusion appliqués : %s', 'theme-export-jlg'), implode(', ', $exclusions));
        }

        if ($retention > 0) {
            $paragraphs[] = sprintf(__('Les archives sont conservées pendant %d jours.', 'theme-export-jlg'), $retention);
        }

        $body = implode("\n\n", $paragraphs) . "\n\n" . __('— Theme Export JLG', 'theme-export-jlg');

        $context['subject'] = $subject;
        $context['body']    = $body;

        $subject = apply_filters('tejlg_scheduled_export_notification_subject', $subject, $context);
        $body    = apply_filters('tejlg_scheduled_export_notification_body', $body, $context);

        wp_mail($recipient, $subject, $body);
    }

    private static function notify_scheduled_export_failure($message, $settings, $job = null, $error = null, array $exclusions = []) {
        $context = [
            'type'       => 'error',
            'settings'   => $settings,
            'job'        => $job,
            'error'      => $error,
            'exclusions' => $exclusions,
        ];

        $recipient = self::get_scheduled_notification_recipient('error', $context);

        if (!$recipient) {
            return;
        }

        $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $subject  = sprintf(__('Échec de l’export de thème programmé – %s', 'theme-export-jlg'), $blogname);

        $message = is_string($message) ? trim(wp_strip_all_tags($message)) : '';

        if ('' === $message) {
            $message = __('Une erreur inconnue est survenue lors de la génération de l’archive.', 'theme-export-jlg');
        }

        $paragraphs = [
            __('L’export planifié du thème a échoué.', 'theme-export-jlg'),
            $message,
        ];

        if (is_array($job) && isset($job['id'])) {
            $paragraphs[] = sprintf(__('Identifiant de tâche : %s', 'theme-export-jlg'), (string) $job['id']);
        }

        if (!empty($exclusions)) {
            $paragraphs[] = sprintf(__('Motifs d’exclusion appliqués : %s', 'theme-export-jlg'), implode(', ', $exclusions));
        }

        $paragraphs[] = sprintf(__('Site : %s', 'theme-export-jlg'), home_url());

        $body = implode("\n\n", $paragraphs) . "\n\n" . __('— Theme Export JLG', 'theme-export-jlg');

        $context['subject'] = $subject;
        $context['body']    = $body;

        $subject = apply_filters('tejlg_scheduled_export_notification_subject', $subject, $context);
        $body    = apply_filters('tejlg_scheduled_export_notification_body', $body, $context);

        wp_mail($recipient, $subject, $body);
    }

    private static function get_scheduled_notification_recipient($type, array $context) {
        $recipient = get_option('admin_email');

        /**
         * Permet de modifier le destinataire des notifications d’export planifié.
         *
         * @param string|false $recipient Adresse e-mail du destinataire.
         * @param string       $type      Type de notification (success|error).
         * @param array        $context   Contexte de la notification.
         */
        $recipient = apply_filters('tejlg_scheduled_export_notification_recipient', $recipient, $type, $context);

        if (!is_string($recipient)) {
            return false;
        }

        $recipient = trim($recipient);

        if ('' === $recipient || !is_email($recipient)) {
            return false;
        }

        return $recipient;
    }

    /**
     * Crée et télécharge l'archive ZIP du thème actif.
     *
     * Les jobs sont traités immédiatement lorsque WP-Cron est indisponible ou
     * lorsqu'aucun évènement n'a pu être planifié, afin d'imiter le
     * comportement attendu dans les environnements professionnels :
     *
     * - la constante DISABLE_WP_CRON (ou tout autre flag équivalent) force
     *   l'exécution immédiate du job ;
     * - l'absence d'évènement planifié après la tentative de `dispatch`
     *   déclenche également l'exécution immédiate.
     */
    public static function export_theme($exclusions = []) {
        $exclusions = self::sanitize_exclusion_patterns($exclusions);

        $theme = wp_get_theme();
        $theme_dir_path = $theme->get_stylesheet_directory();

        if (!is_dir($theme_dir_path) || !is_readable($theme_dir_path)) {
            return new WP_Error('tejlg_theme_directory_unreadable', esc_html__("Impossible d'accéder au dossier du thème actif.", 'theme-export-jlg'));
        }

        $theme_slug    = $theme->get_stylesheet();
        $zip_file_name = $theme_slug . '.zip';
        $zip_file_path = wp_tempnam($zip_file_name);

        if (!$zip_file_path) {
            return new WP_Error('tejlg_zip_temp_creation_failed', esc_html__("Impossible de créer le fichier temporaire pour l'archive ZIP.", 'theme-export-jlg'));
        }

        if (file_exists($zip_file_path) && !self::delete_temp_file($zip_file_path)) {
            return new WP_Error('tejlg_zip_temp_cleanup_failed', esc_html__("Impossible de préparer le fichier temporaire pour l'archive ZIP.", 'theme-export-jlg'));
        }

        $zip_writer = TEJLG_Zip_Writer::create($zip_file_path);

        if (is_wp_error($zip_writer)) {
            self::delete_temp_file($zip_file_path);

            return new WP_Error(
                'tejlg_zip_open_failed',
                esc_html__("Impossible de créer l'archive ZIP.", 'theme-export-jlg')
            );
        }

        $zip_root_directory = rtrim($theme_slug, '/') . '/';

        if (true !== $zip_writer->add_directory($zip_root_directory)) {
            $zip_writer->close();
            self::delete_temp_file($zip_file_path);

            return new WP_Error(
                'tejlg_zip_root_dir_failed',
                sprintf(
                    /* translators: %s: slug of the theme used as the root directory of the ZIP archive. */
                    esc_html__("Impossible d'ajouter le dossier racine « %s » à l'archive ZIP.", 'theme-export-jlg'),
                    esc_html($zip_root_directory)
                )
            );
        }

        $zip_writer->close();

        $normalized_theme_dir = self::normalize_path($theme_dir_path);

        try {
            $queue = self::collect_theme_export_items(
                $theme_dir_path,
                $normalized_theme_dir,
                $zip_root_directory,
                $exclusions
            );
        } catch (RuntimeException $exception) {
            self::delete_temp_file($zip_file_path);

            return new WP_Error('tejlg_theme_export_queue_failed', $exception->getMessage());
        }

        $queue_items = isset($queue['items']) && is_array($queue['items']) ? $queue['items'] : [];
        $files_count = isset($queue['files_count']) ? (int) $queue['files_count'] : 0;

        if ($files_count < 1) {
            self::delete_temp_file($zip_file_path);

            return new WP_Error(
                'tejlg_theme_export_no_files',
                esc_html__("Erreur : tous les fichiers ont été exclus de l'export. Vérifiez vos motifs.", 'theme-export-jlg')
            );
        }

        $job_id = self::generate_job_id();

        $current_user_id   = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        $current_user_name = '';

        if ($current_user_id > 0) {
            $current_user = get_userdata($current_user_id);

            if ($current_user instanceof WP_User) {
                $current_user_name = $current_user->display_name;
            }
        }

        $job = [
            'id'                => $job_id,
            'status'            => 'queued',
            'progress'          => 0,
            'processed_items'   => 0,
            'total_items'       => count($queue_items),
            'zip_path'          => $zip_file_path,
            'zip_file_name'     => $zip_file_name,
            'directories_added' => [
                $zip_root_directory => true,
            ],
            'exclusions'        => $exclusions,
            'created_at'        => time(),
            'updated_at'        => time(),
            'message'           => '',
            'created_by'        => $current_user_id,
            'created_by_name'   => $current_user_name,
            'created_via'       => (defined('WP_CLI') && WP_CLI) ? 'cli' : 'web',
        ];

        self::persist_job($job);
        self::remember_job_for_current_user($job_id);

        $process = self::get_export_process();

        foreach ($queue_items as $item) {
            $process->push_to_queue(
                [
                    'job_id'               => $job_id,
                    'type'                 => isset($item['type']) ? $item['type'] : 'file',
                    'real_path'            => isset($item['real_path']) ? $item['real_path'] : '',
                    'relative_path_in_zip' => isset($item['relative_path_in_zip']) ? $item['relative_path_in_zip'] : '',
                ]
            );
        }

        $process->save();
        $process->dispatch();

        $should_run_immediately = apply_filters('tejlg_export_run_jobs_immediately', defined('WP_RUNNING_TESTS'));

        $cron_disabled = false;

        if (defined('DISABLE_WP_CRON')) {
            $cron_disabled = function_exists('wp_validate_boolean')
                ? wp_validate_boolean(DISABLE_WP_CRON)
                : (bool) DISABLE_WP_CRON;
        }

        $cron_hook_identifier = method_exists($process, 'get_cron_hook_identifier')
            ? (string) $process->get_cron_hook_identifier()
            : '';

        $event_scheduled = '' !== $cron_hook_identifier
            && function_exists('wp_next_scheduled')
            && false !== wp_next_scheduled($cron_hook_identifier);

        if (!$should_run_immediately && ($cron_disabled || !$event_scheduled)) {
            $should_run_immediately = true;
        }

        if ($should_run_immediately) {
            self::run_pending_export_jobs();
        }

        return $job_id;
    }

    public static function preview_theme_export_files($raw_patterns) {
        $raw_input = is_string($raw_patterns) ? $raw_patterns : '';

        $entries = [];

        if ('' !== $raw_input) {
            $split = preg_split('/[,\n]+/', $raw_input);

            if (false !== $split) {
                foreach ($split as $pattern) {
                    if (!is_scalar($pattern)) {
                        continue;
                    }

                    $trimmed = trim((string) $pattern);

                    if ('' === $trimmed) {
                        continue;
                    }

                    $entries[] = [
                        'original'  => $trimmed,
                        'sanitized' => ltrim($trimmed, '/'),
                    ];
                }
            }
        }

        $sanitized_inputs = array_map(
            static function ($entry) {
                return isset($entry['sanitized']) ? (string) $entry['sanitized'] : '';
            },
            $entries
        );

        $sanitized_patterns = self::sanitize_exclusion_patterns($sanitized_inputs);

        $display_map = [];

        foreach ($entries as $entry) {
            $sanitized = isset($entry['sanitized']) ? (string) $entry['sanitized'] : '';

            if ('' === $sanitized) {
                continue;
            }

            if (!isset($display_map[$sanitized])) {
                $display_map[$sanitized] = isset($entry['original']) ? (string) $entry['original'] : $sanitized;
            }
        }

        $invalid_patterns = [];

        foreach ($entries as $entry) {
            $sanitized = isset($entry['sanitized']) ? (string) $entry['sanitized'] : '';

            if ('' === $sanitized) {
                $invalid_patterns[] = isset($entry['original']) ? (string) $entry['original'] : '';
            }
        }

        foreach ($sanitized_patterns as $pattern) {
            if (!self::is_valid_exclusion_pattern($pattern)) {
                $invalid_patterns[] = isset($display_map[$pattern]) ? $display_map[$pattern] : $pattern;
            }
        }

        $invalid_patterns = array_values(
            array_filter(
                array_unique(array_map('strval', $invalid_patterns)),
                static function ($pattern) {
                    return '' !== $pattern;
                }
            )
        );

        if (!empty($invalid_patterns)) {
            return new WP_Error(
                'tejlg_invalid_exclusion_patterns',
                esc_html__("Erreur : certains motifs sont invalides. Corrigez-les puis réessayez.", 'theme-export-jlg'),
                [
                    'invalid_patterns' => $invalid_patterns,
                    'status'           => 422,
                ]
            );
        }

        $theme = wp_get_theme();
        $theme_dir_path = $theme->get_stylesheet_directory();

        if (!is_dir($theme_dir_path) || !is_readable($theme_dir_path)) {
            return new WP_Error(
                'tejlg_theme_directory_unreadable',
                esc_html__("Impossible d'accéder au dossier du thème actif.", 'theme-export-jlg')
            );
        }

        $theme_slug = $theme->get_stylesheet();
        $zip_root_directory = rtrim($theme_slug, '/') . '/';
        $normalized_theme_dir = self::normalize_path($theme_dir_path);

        try {
            $queue = self::collect_theme_export_items(
                $theme_dir_path,
                $normalized_theme_dir,
                $zip_root_directory,
                $sanitized_patterns
            );
        } catch (RuntimeException $exception) {
            return new WP_Error(
                'tejlg_theme_export_queue_failed',
                $exception->getMessage()
            );
        }

        $included = [];

        if (isset($queue['items']) && is_array($queue['items'])) {
            foreach ($queue['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $type = isset($item['type']) ? (string) $item['type'] : '';

                if ('file' !== $type) {
                    continue;
                }

                $relative_in_zip = isset($item['relative_path_in_zip']) ? (string) $item['relative_path_in_zip'] : '';

                if ('' === $relative_in_zip) {
                    continue;
                }

                if ('' !== $zip_root_directory && 0 === strpos($relative_in_zip, $zip_root_directory)) {
                    $relative_in_zip = substr($relative_in_zip, strlen($zip_root_directory));
                }

                $relative = ltrim($relative_in_zip, '/');

                if ('' === $relative) {
                    continue;
                }

                $included[] = $relative;
            }
        }

        $included = array_values(array_unique(array_map('strval', $included)));
        sort($included, SORT_NATURAL | SORT_FLAG_CASE);

        $all_files = self::list_theme_files($theme_dir_path, $normalized_theme_dir);

        $excluded = array_values(array_diff($all_files, $included));

        return [
            'included'      => $included,
            'excluded'      => $excluded,
            'includedCount' => count($included),
            'excludedCount' => count($excluded),
        ];
    }

    private static function collect_theme_export_items($theme_dir_path, $normalized_theme_dir, $zip_root_directory, $exclusions) {
        try {
            $directory_iterator = new RecursiveDirectoryIterator(
                $theme_dir_path,
                FilesystemIterator::SKIP_DOTS
            );
        } catch (UnexpectedValueException $exception) {
            throw new RuntimeException(
                esc_html__("Impossible de parcourir les fichiers du thème pour l'export.", 'theme-export-jlg'),
                0,
                $exception
            );
        }

        $filter_iterator = new RecursiveCallbackFilterIterator(
            $directory_iterator,
            static function (SplFileInfo $file) use ($normalized_theme_dir, $exclusions) {
                if ($file->isLink()) {
                    return false;
                }

                $real_path = $file->getRealPath();

                if (false === $real_path) {
                    return false;
                }

                $normalized_file_path = TEJLG_Export::normalize_path($real_path);

                if (!TEJLG_Export::is_path_within_base($normalized_file_path, $normalized_theme_dir)) {
                    return false;
                }

                $relative_path = TEJLG_Export::get_relative_path($normalized_file_path, $normalized_theme_dir);

                if ($file->isDir()) {
                    return '' === $relative_path || !TEJLG_Export::should_exclude_file($relative_path, $exclusions);
                }

                if ('' === $relative_path) {
                    return false;
                }

                return !TEJLG_Export::should_exclude_file($relative_path, $exclusions);
            }
        );

        $iterator = new RecursiveIteratorIterator(
            $filter_iterator,
            RecursiveIteratorIterator::SELF_FIRST,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );

        $items       = [];
        $files_count = 0;

        foreach ($iterator as $file) {
            $real_path = $file->getRealPath();

            if (false === $real_path) {
                continue;
            }

            $normalized_file_path = self::normalize_path($real_path);

            if (!self::is_path_within_base($normalized_file_path, $normalized_theme_dir)) {
                continue;
            }

            $relative_path = self::get_relative_path($normalized_file_path, $normalized_theme_dir);

            if ('' === $relative_path) {
                continue;
            }

            $relative_path_in_zip = $zip_root_directory . ltrim($relative_path, '/');

            if ($file->isDir()) {
                $items[] = [
                    'type'                 => 'dir',
                    'real_path'            => $real_path,
                    'relative_path_in_zip' => rtrim($relative_path_in_zip, '/') . '/',
                ];

                continue;
            }

            $items[] = [
                'type'                 => 'file',
                'real_path'            => $real_path,
                'relative_path_in_zip' => $relative_path_in_zip,
            ];

            $files_count++;
        }

        return [
            'items'       => $items,
            'files_count' => $files_count,
        ];
    }

    private static function generate_job_id() {
        if (function_exists('wp_generate_uuid4')) {
            $raw_id = wp_generate_uuid4();
        } else {
            $raw_id = uniqid('tejlg_export_', true);
        }

        $sanitized = strtolower(preg_replace('/[^a-z0-9_]/', '', (string) $raw_id));

        if ('' === $sanitized) {
            $sanitized = 'tejlg_export_' . wp_rand(1000, 9999);
        }

        return $sanitized;
    }

    private static function get_export_process() {
        return new TEJLG_Export_Process();
    }

    private static function get_user_job_meta_key() {
        return '_tejlg_last_theme_export_job_id';
    }

    private static function remember_job_for_current_user($job_id) {
        if (!function_exists('get_current_user_id')) {
            return;
        }

        $job_id = sanitize_key((string) $job_id);

        if ('' === $job_id) {
            return;
        }

        $user_id = get_current_user_id();

        if ($user_id <= 0) {
            return;
        }

        update_user_meta($user_id, self::get_user_job_meta_key(), $job_id);
    }

    public static function get_user_job_reference($user_id = 0) {
        if (!function_exists('get_current_user_id')) {
            return '';
        }

        $user_id = (int) $user_id;

        if ($user_id <= 0) {
            $user_id = (int) get_current_user_id();
        }

        if ($user_id <= 0) {
            return '';
        }

        $stored = get_user_meta($user_id, self::get_user_job_meta_key(), true);

        if (!is_string($stored) || '' === $stored) {
            return '';
        }

        return sanitize_key($stored);
    }

    public static function clear_user_job_reference($job_id, $user_id = 0) {
        if (!function_exists('get_current_user_id')) {
            return;
        }

        $job_id = sanitize_key((string) $job_id);

        if ('' === $job_id) {
            return;
        }

        $user_id = (int) $user_id;

        if ($user_id <= 0) {
            $user_id = (int) get_current_user_id();
        }

        if ($user_id <= 0) {
            return;
        }

        $stored = get_user_meta($user_id, self::get_user_job_meta_key(), true);

        if (!is_string($stored) || '' === $stored) {
            return;
        }

        if (sanitize_key($stored) !== $job_id) {
            return;
        }

        delete_user_meta($user_id, self::get_user_job_meta_key());
    }

    public static function get_current_user_job_snapshot() {
        $job_id = self::get_user_job_reference();

        if ('' === $job_id) {
            return null;
        }

        $job = self::get_job($job_id);

        if (null === $job) {
            self::clear_user_job_reference($job_id);

            return null;
        }

        return [
            'job_id' => $job_id,
            'job'    => self::prepare_job_response($job),
            'status' => isset($job['status']) ? (string) $job['status'] : '',
        ];
    }

    private static function get_job_option_name($job_id) {
        $job_id = trim((string) $job_id);

        return 'tejlg_export_job_' . $job_id;
    }

    private static function get_export_process_prefix() {
        return 'wp_background_process_tejlg_theme_export';
    }

    private static function get_export_queue_option_name() {
        return self::get_export_process_prefix() . '_queue';
    }

    private static function get_export_process_lock_key() {
        return self::get_export_process_prefix() . '_process_lock';
    }

    private static function clear_job_from_queue($job_id) {
        $job_id = sanitize_key((string) $job_id);

        if ('' === $job_id) {
            return;
        }

        $queue_option = self::get_export_queue_option_name();
        $queue        = get_option($queue_option, []);

        if (!is_array($queue) || empty($queue)) {
            return;
        }

        $modified = false;

        foreach ($queue as $batch_index => $batch) {
            if (!is_array($batch) || empty($batch)) {
                continue;
            }

            $new_batch = [];

            foreach ($batch as $item) {
                if (!is_array($item)) {
                    $new_batch[] = $item;
                    continue;
                }

                $item_job_id = isset($item['job_id']) ? sanitize_key((string) $item['job_id']) : '';

                if ('' === $item_job_id || $item_job_id !== $job_id) {
                    $new_batch[] = $item;
                    continue;
                }

                $modified = true;
            }

            if (!empty($new_batch)) {
                $queue[$batch_index] = array_values($new_batch);
                continue;
            }

            unset($queue[$batch_index]);
            $modified = true;
        }

        if (!$modified) {
            return;
        }

        $queue = array_values($queue);

        if (empty($queue)) {
            delete_option($queue_option);
        } else {
            update_option($queue_option, $queue, false);
        }
    }

    public static function persist_job($job) {
        if (!is_array($job) || empty($job['id'])) {
            return;
        }

        $job['updated_at'] = isset($job['updated_at']) ? (int) $job['updated_at'] : time();

        update_option(self::get_job_option_name($job['id']), $job, false);
    }

    public static function get_job($job_id) {
        $job = get_option(self::get_job_option_name($job_id), false);

        if (!is_array($job) || empty($job['id'])) {
            return null;
        }

        return $job;
    }

    public static function delete_job($job_id, array $context = []) {
        $job = self::get_job($job_id);

        if (null !== $job) {
            if (isset($context['persistent_path']) && is_string($context['persistent_path']) && '' !== $context['persistent_path']) {
                $job['persistent_path'] = $context['persistent_path'];
            }

            if (isset($context['download_url']) && is_string($context['download_url']) && '' !== $context['download_url']) {
                $job['persistent_url'] = $context['download_url'];
            }

            TEJLG_Export_History::record_job($job, $context);
        }

        if (null !== $job && !empty($job['zip_path']) && file_exists($job['zip_path'])) {
            $zip_path         = (string) $job['zip_path'];
            $persistent_path  = isset($job['persistent_path']) ? (string) $job['persistent_path'] : '';
            $normalized_zip   = self::normalize_path($zip_path);
            $normalized_persi = '' !== $persistent_path ? self::normalize_path($persistent_path) : '';

            if ('' === $normalized_persi || $normalized_zip !== $normalized_persi) {
                self::delete_temp_file($zip_path);
            }
        }

        delete_option(self::get_job_option_name($job_id));
    }

    public static function cleanup_persisted_archives($retention_days = null) {
        $settings = self::get_schedule_settings();

        if (null === $retention_days) {
            $retention_days = isset($settings['retention_days']) ? (int) $settings['retention_days'] : 0;
        }

        $retention_days = (int) apply_filters('tejlg_export_retention_days', $retention_days, $settings);

        if ($retention_days <= 0) {
            return;
        }

        $uploads = wp_upload_dir();

        if (!is_array($uploads) || !empty($uploads['error'])) {
            return;
        }

        $base_dir = isset($uploads['basedir']) ? (string) $uploads['basedir'] : '';

        if ('' === $base_dir) {
            return;
        }

        $target_directory = trailingslashit($base_dir) . 'theme-export-jlg/';

        if (!is_dir($target_directory)) {
            return;
        }

        $threshold = time() - ($retention_days * DAY_IN_SECONDS);

        if ($threshold <= 0) {
            return;
        }

        try {
            $iterator = new DirectoryIterator($target_directory);
        } catch (UnexpectedValueException $exception) {
            return;
        }

        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isDot() || !$fileinfo->isFile()) {
                continue;
            }

            $mtime = $fileinfo->getMTime();

            if ($mtime > 0 && $mtime <= $threshold) {
                $path = $fileinfo->getPathname();

                if (is_string($path) && '' !== $path) {
                    @unlink($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                }
            }
        }

        try {
            $cleanup_iterator = new FilesystemIterator($target_directory);
        } catch (UnexpectedValueException $exception) {
            return;
        }

        if (!$cleanup_iterator->valid()) {
            @rmdir($target_directory); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        }
    }

    public static function cleanup_stale_jobs($max_age = null) {
        global $wpdb;

        $max_age = null === $max_age ? HOUR_IN_SECONDS : (int) $max_age;

        if ($max_age <= 0) {
            $max_age = HOUR_IN_SECONDS;
        }

        $threshold = time() - $max_age;
        $option_prefix = 'tejlg_export_job_';
        $options_table = $wpdb->options;
        $like_pattern  = $wpdb->esc_like($option_prefix) . '%';

        $option_names = (array) $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$options_table} WHERE option_name LIKE %s",
                $like_pattern
            )
        );

        if (empty($option_names)) {
            return;
        }

        foreach ($option_names as $option_name) {
            if (!is_string($option_name) || '' === $option_name) {
                continue;
            }

            $job_id = substr($option_name, strlen($option_prefix));

            if ('' === $job_id) {
                continue;
            }

            $job = self::get_job($job_id);

            if (null === $job) {
                delete_option($option_name);
                continue;
            }

            $status = isset($job['status']) ? (string) $job['status'] : '';
            $updated_at = isset($job['updated_at']) ? (int) $job['updated_at'] : 0;

            if (in_array($status, ['queued', 'processing'], true)) {
                if ($updated_at > 0 && $updated_at <= $threshold) {
                    $message = esc_html__('Export interrompu automatiquement : la tâche est restée inactive trop longtemps.', 'theme-export-jlg');

                    self::mark_job_failed(
                        $job_id,
                        $message,
                        [
                            'failure_code' => 'timeout',
                        ]
                    );

                    continue;
                }

                continue;
            }

            if (!in_array($status, ['completed', 'failed', 'cancelled'], true)) {
                continue;
            }

            $completed_at = isset($job['completed_at']) ? (int) $job['completed_at'] : 0;
            $reference    = $completed_at > 0 ? $completed_at : $updated_at;

            if ($reference <= 0 || $reference > $threshold) {
                continue;
            }

            self::delete_job($job_id, [
                'origin' => 'cleanup',
            ]);
        }

        self::cleanup_persisted_archives();
    }

    public static function mark_job_failed($job_id, $message, $context = []) {
        $job = self::get_job($job_id);

        if (null === $job) {
            return;
        }

        $job['status']   = 'failed';
        $job['message']  = is_string($message) ? $message : '';
        $job['progress'] = isset($job['progress']) ? (int) $job['progress'] : 0;
        $job['updated_at'] = time();
        $job['completed_at'] = time();

        if (is_array($context) && isset($context['failure_code'])) {
            $failure_code = (string) $context['failure_code'];
            if ('' !== $failure_code) {
                $job['failure_code'] = $failure_code;
            } else {
                unset($job['failure_code']);
            }
        } else {
            unset($job['failure_code']);
        }

        if (!empty($job['zip_path']) && file_exists($job['zip_path'])) {
            self::delete_temp_file($job['zip_path']);
        }

        self::clear_job_from_queue($job_id);

        $job_owner = isset($job['created_by']) ? (int) $job['created_by'] : 0;

        if ($job_owner > 0) {
            self::clear_user_job_reference($job_id, $job_owner);
        }

        self::persist_job($job);
    }

    public static function cancel_job($job_id) {
        $job_id = sanitize_key((string) $job_id);

        if ('' === $job_id) {
            return new WP_Error('tejlg_export_invalid_job', esc_html__('Identifiant de tâche manquant.', 'theme-export-jlg'));
        }

        $job = self::get_job($job_id);

        if (null === $job) {
            return new WP_Error('tejlg_export_job_not_found', esc_html__('Tâche introuvable ou expirée.', 'theme-export-jlg'));
        }

        $status = isset($job['status']) ? (string) $job['status'] : '';

        if (in_array($status, ['completed', 'failed', 'cancelled'], true)) {
            return new WP_Error(
                'tejlg_export_job_not_cancellable',
                esc_html__("Cette exportation ne peut plus être annulée.", 'theme-export-jlg')
            );
        }

        $zip_path = isset($job['zip_path']) ? (string) $job['zip_path'] : '';

        if ('' !== $zip_path && file_exists($zip_path)) {
            self::delete_temp_file($zip_path);
        }

        $job['status']            = 'cancelled';
        $job['progress']          = 0;
        $job['processed_items']   = 0;
        $job['directories_added'] = [];
        $job['zip_path']          = '';
        $job['zip_file_size']     = 0;
        $job['message']           = esc_html__('Export annulé.', 'theme-export-jlg');
        $job['updated_at']        = time();
        $job['completed_at']      = time();

        self::persist_job($job);

        $process = self::get_export_process();

        if (method_exists($process, 'cancel_process')) {
            $process->cancel_process();
        }

        self::clear_job_from_queue($job_id);

        $cron_hook = method_exists($process, 'get_cron_hook_identifier')
            ? (string) $process->get_cron_hook_identifier()
            : '';

        if ($cron_hook && function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook($cron_hook);
        }

        $lock_key = self::get_export_process_lock_key();

        if ($lock_key) {
            delete_transient($lock_key);
        }

        self::clear_user_job_reference($job_id);

        return $job;
    }

    public static function finalize_job($job) {
        if (!is_array($job) || empty($job['id'])) {
            return;
        }

        $job_id  = $job['id'];
        $zip_path = isset($job['zip_path']) ? (string) $job['zip_path'] : '';

        if ('' === $zip_path || !file_exists($zip_path)) {
            self::mark_job_failed($job_id, esc_html__("Impossible de finaliser l'archive d'export.", 'theme-export-jlg'));
            return;
        }

        $zip_file_size = filesize($zip_path);

        $zip_file_size = apply_filters('tejlg_export_zip_file_size', $zip_file_size, $zip_path);

        if (!is_numeric($zip_file_size)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[Theme Export JLG] Unable to determine ZIP size for download: %s (value: %s)', $zip_path, var_export($zip_file_size, true)));
            }

            self::mark_job_failed($job_id, esc_html__("Impossible de déterminer la taille de l'archive ZIP.", 'theme-export-jlg'));
            return;
        }

        $job['status']            = 'completed';
        $job['progress']          = 100;
        $job['zip_file_size']     = (int) $zip_file_size;
        $job['directories_added'] = [];
        $job['completed_at']      = time();
        $job['updated_at']        = time();

        self::persist_job($job);
    }

    public static function run_pending_export_jobs() {
        $process = self::get_export_process();
        $process->handle();
    }

    public static function get_export_job_status($job_id) {
        return self::get_job($job_id);
    }


    private static function prepare_job_response($job) {
        if (!is_array($job)) {
            return null;
        }

        $progress        = isset($job['progress']) ? max(0, min(100, (int) $job['progress'])) : 0;
        $processed_items = isset($job['processed_items']) ? (int) $job['processed_items'] : 0;
        $total_items     = isset($job['total_items']) ? (int) $job['total_items'] : 0;

        return [
            'id'               => isset($job['id']) ? (string) $job['id'] : '',
            'status'           => isset($job['status']) ? (string) $job['status'] : 'queued',
            'progress'         => $progress,
            'processed_items'  => $processed_items,
            'total_items'      => $total_items,
            'message'          => isset($job['message']) && is_string($job['message']) ? $job['message'] : '',
            'zip_file_size'    => isset($job['zip_file_size']) ? (int) $job['zip_file_size'] : 0,
            'zip_file_name'    => isset($job['zip_file_name']) ? (string) $job['zip_file_name'] : '',
            'created_at'       => isset($job['created_at']) ? (int) $job['created_at'] : 0,
            'updated_at'       => isset($job['updated_at']) ? (int) $job['updated_at'] : 0,
            'failure_code'     => isset($job['failure_code']) ? (string) $job['failure_code'] : '',
        ];
    }

    public static function ajax_start_theme_export() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Accès refusé.', 'theme-export-jlg')], 403);
        }

        check_ajax_referer('tejlg_start_theme_export', 'nonce');

        $raw_exclusions   = isset($_POST['exclusions']) ? wp_unslash((string) $_POST['exclusions']) : '';
        $exclusions       = self::sanitize_exclusion_patterns($raw_exclusions);
        $stored_exclusion = self::sanitize_exclusion_patterns_string($exclusions);

        update_option(TEJLG_Admin_Export_Page::EXCLUSION_PATTERNS_OPTION, $stored_exclusion, false);

        $result = self::export_theme($exclusions);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 400);
        }

        $job_id = (string) $result;
        $job    = self::get_job($job_id);

        wp_send_json_success([
            'job_id'        => $job_id,
            'job'           => self::prepare_job_response($job),
            'downloadNonce' => wp_create_nonce('tejlg_download_theme_export_' . $job_id),
        ]);
    }

    public static function ajax_get_theme_export_status() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Accès refusé.', 'theme-export-jlg')], 403);
        }

        check_ajax_referer('tejlg_theme_export_status', 'nonce');

        $job_id = isset($_REQUEST['job_id']) ? sanitize_key(wp_unslash((string) $_REQUEST['job_id'])) : '';

        if ('' === $job_id) {
            wp_send_json_error(['message' => esc_html__('Identifiant de tâche manquant.', 'theme-export-jlg')], 400);
        }

        self::cleanup_stale_jobs();

        $job = self::get_job($job_id);

        if (null === $job) {
            self::clear_user_job_reference($job_id);
            wp_send_json_error(['message' => esc_html__('Tâche introuvable ou expirée.', 'theme-export-jlg')], 404);
        }

        $response = [
            'job' => self::prepare_job_response($job),
        ];

        $job_status = isset($job['status']) ? (string) $job['status'] : '';

        if (isset($job['status']) && 'completed' === $job['status']) {
            $download_nonce = wp_create_nonce('tejlg_download_theme_export_' . $job_id);
            $response['download_url'] = add_query_arg(
                [
                    'action'  => 'tejlg_download_theme_export',
                    'job_id'  => rawurlencode($job_id),
                    '_wpnonce' => $download_nonce,
                ],
                admin_url('admin-ajax.php')
            );
        }

        if (in_array($job_status, ['completed', 'failed', 'cancelled'], true)) {
            self::clear_user_job_reference($job_id);
        }

        wp_send_json_success($response);
    }

    public static function ajax_cancel_theme_export() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Accès refusé.', 'theme-export-jlg')], 403);
        }

        check_ajax_referer('tejlg_cancel_theme_export', 'nonce');

        $job_id = isset($_POST['job_id']) ? sanitize_key(wp_unslash((string) $_POST['job_id'])) : '';

        if ('' === $job_id) {
            wp_send_json_error(['message' => esc_html__('Identifiant de tâche manquant.', 'theme-export-jlg')], 400);
        }

        $result = self::cancel_job($job_id);

        if (is_wp_error($result)) {
            $error_code = $result->get_error_code();
            $status     = 400;

            if ('tejlg_export_job_not_found' === $error_code) {
                $status = 404;
            } elseif ('tejlg_export_job_not_cancellable' === $error_code) {
                $status = 409;
            }

            wp_send_json_error(['message' => $result->get_error_message()], $status);
        }

        wp_send_json_success([
            'job_id' => $job_id,
            'job'    => self::prepare_job_response($result),
        ]);
    }

    public static function ajax_download_theme_export() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Accès refusé.', 'theme-export-jlg')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        $job_id = isset($_REQUEST['job_id']) ? sanitize_key(wp_unslash((string) $_REQUEST['job_id'])) : '';

        if ('' === $job_id) {
            wp_die(esc_html__('Identifiant de tâche manquant.', 'theme-export-jlg'));
        }

        check_ajax_referer('tejlg_download_theme_export_' . $job_id);

        $job = self::get_job($job_id);

        if (null === $job || !isset($job['status']) || 'completed' !== $job['status']) {
            wp_die(esc_html__("Cette archive n'est pas disponible.", 'theme-export-jlg'));
        }

        $zip_path = isset($job['zip_path']) ? (string) $job['zip_path'] : '';

        if ('' === $zip_path || !file_exists($zip_path)) {
            self::delete_job($job_id, [
                'origin' => 'ajax',
                'reason' => 'missing_zip',
            ]);
            wp_die(esc_html__('Le fichier ZIP généré est introuvable.', 'theme-export-jlg'));
        }

        $zip_file_name = isset($job['zip_file_name']) && '' !== $job['zip_file_name']
            ? $job['zip_file_name']
            : basename($zip_path);
        $zip_file_size = isset($job['zip_file_size']) ? (int) $job['zip_file_size'] : (int) filesize($zip_path);

        $should_stream = apply_filters('tejlg_export_stream_zip_archive', true, $zip_path, $zip_file_name, $zip_file_size);

        if (!$should_stream) {
            wp_send_json_success([
                'path'     => $zip_path,
                'filename' => $zip_file_name,
                'size'     => $zip_file_size,
            ]);
        }

        nocache_headers();
        self::clear_output_buffers();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zip_file_name . '"');
        header('Content-Length: ' . (string) $zip_file_size);

        readfile($zip_path);
        flush();

        $persistence = self::persist_export_archive($job);

        $delete_context = [
            'origin' => 'ajax',
            'reason' => 'downloaded',
        ];

        if (!empty($persistence['path'])) {
            $delete_context['persistent_path'] = $persistence['path'];
        }

        if (!empty($persistence['url'])) {
            $delete_context['download_url'] = $persistence['url'];
        }

        self::delete_job($job_id, $delete_context);
        exit;
    }

    /**
     * Persists a generated ZIP archive into the public uploads directory.
     *
     * @param array|string $job Job array or ZIP file path.
     *
     * @return array{path:string,url:string}
     */
    public static function persist_export_archive($job) {
        $zip_path               = '';
        $zip_file_name         = '';
        $job_id                = '';
        $existing_persistent   = '';
        $existing_persistent_url = '';

        if (is_array($job)) {
            $zip_path       = isset($job['zip_path']) ? (string) $job['zip_path'] : '';
            $zip_file_name  = isset($job['zip_file_name']) ? (string) $job['zip_file_name'] : '';
            $job_id         = isset($job['id']) ? (string) $job['id'] : '';
            $existing_persistent = isset($job['persistent_path']) ? (string) $job['persistent_path'] : '';
            $existing_persistent_url = isset($job['persistent_url']) ? (string) $job['persistent_url'] : '';
        } elseif (is_string($job)) {
            $zip_path = $job;
        }

        if ('' !== $existing_persistent && file_exists($existing_persistent)) {
            return [
                'path' => $existing_persistent,
                'url'  => $existing_persistent_url,
            ];
        }

        if ('' === $zip_path || !file_exists($zip_path)) {
            return [
                'path' => '',
                'url'  => '',
            ];
        }

        if ('' === $zip_file_name) {
            $zip_file_name = basename($zip_path);
        }

        $uploads = wp_upload_dir();

        if (!is_array($uploads) || !empty($uploads['error'])) {
            return [
                'path' => '',
                'url'  => '',
            ];
        }

        $base_dir = isset($uploads['basedir']) ? (string) $uploads['basedir'] : '';
        $base_url = isset($uploads['baseurl']) ? (string) $uploads['baseurl'] : '';

        if ('' === $base_dir || '' === $base_url) {
            return [
                'path' => '',
                'url'  => '',
            ];
        }

        $target_directory = trailingslashit($base_dir) . 'theme-export-jlg/';

        if (!wp_mkdir_p($target_directory)) {
            return [
                'path' => '',
                'url'  => '',
            ];
        }

        $target_directory = trailingslashit($target_directory);

        $filename = $zip_file_name;

        if ('' !== $job_id) {
            $filename = sprintf('%s-%s', $job_id, $filename);
        }

        $filename = wp_unique_filename($target_directory, $filename);
        $destination = $target_directory . $filename;

        if (self::normalize_path($zip_path) === self::normalize_path($destination)) {
            return [
                'path' => $destination,
                'url'  => trailingslashit($base_url) . 'theme-export-jlg/' . rawurlencode($filename),
            ];
        }

        if (!copy($zip_path, $destination)) {
            return [
                'path' => '',
                'url'  => '',
            ];
        }

        $file_perms = apply_filters('tejlg_export_persisted_file_permissions', 0644, $destination, $job);

        if (is_int($file_perms)) {
            @chmod($destination, $file_perms); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        }

        $url = trailingslashit($base_url) . 'theme-export-jlg/' . rawurlencode($filename);

        return [
            'path' => $destination,
            'url'  => $url,
        ];
    }
    /**
     * Aborts the ZIP export, cleans up temporary files and stops execution.
     *
     * @param ZipArchive $zip           Archive instance to close.
     * @param string     $zip_file_path Path to the temporary ZIP file.
     * @param string     $message       Sanitized error message displayed to the user.
     */
    private static function abort_zip_export(ZipArchive $zip, $zip_file_path, $message) {
        $zip->close();

        if (file_exists($zip_file_path)) {
            self::delete_temp_file($zip_file_path);
        }

        wp_die($message);
    }

    /**
     * Exporte toutes les compositions en JSON.
     *
     * @param array $pattern_ids Liste optionnelle d'identifiants de compositions à exporter.
     * @param bool  $is_portable Active le nettoyage « portable » du contenu.
     */
    public static function export_patterns_json($pattern_ids = [], $is_portable = false) {
        $sanitized_ids = array_filter(array_map('intval', (array) $pattern_ids));
        $batch_size    = (int) apply_filters('tejlg_export_patterns_batch_size', 100);

        if ($batch_size < 1) {
            $batch_size = 100;
        }

        $args = [
            'post_type'              => 'wp_block',
            'posts_per_page'         => $batch_size,
            'post_status'            => 'publish',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'lazy_load_term_meta'    => false,
        ];

        if (!empty($sanitized_ids)) {
            $args['post__in'] = $sanitized_ids;
            $args['orderby']  = 'post__in';
        }

        $temp_file = wp_tempnam('tejlg-patterns-export');

        if (empty($temp_file)) {
            wp_die(esc_html__("Une erreur critique est survenue lors de la préparation du fichier JSON d'export.", 'theme-export-jlg'));
        }

        $handle = fopen($temp_file, 'w');

        if (false !== $handle) {
            /**
             * Filters the file handle used to write the exported patterns JSON file.
             *
             * This filter can be used to provide a custom stream resource when generating
             * the export, for instance in tests.
             *
             * @param resource|false $handle    File handle returned by `fopen()`.
             * @param string         $temp_file Absolute path to the temporary JSON file.
             */
            $handle = apply_filters('tejlg_export_patterns_file_handle', $handle, $temp_file);
        }

        if (false === $handle || !is_resource($handle)) {
            @unlink($temp_file);
            wp_die(esc_html__("Impossible de créer le flux de téléchargement pour l'export JSON.", 'theme-export-jlg'));
        }

        $has_written_items = false;
        self::write_to_handle_or_fail($handle, $temp_file, "[\n");

        $page = 1;

        while (true) {
            $args['paged'] = $page;
            $patterns_query = new WP_Query($args);

            if (!$patterns_query->have_posts()) {
                wp_reset_postdata();
                break;
            }

            $current_batch_count = count($patterns_query->posts);

            while ($patterns_query->have_posts()) {
                $patterns_query->the_post();

                $content = self::get_sanitized_content();
                if ($is_portable) {
                    $content = self::clean_pattern_content($content);
                }

                $slug = get_post_field('post_name', get_the_ID());
                if ('' === $slug) {
                    $slug = sanitize_title(get_the_title());
                }

                $post_id = get_the_ID();

                $pattern_data = [
                    'title'   => get_the_title(),
                    'slug'    => $slug,
                    'content' => $content,
                ];

                $excerpt = get_post_field('post_excerpt', $post_id);

                if (is_string($excerpt)) {
                    $excerpt = trim($excerpt);

                    if ('' !== $excerpt) {
                        $pattern_data['post_excerpt'] = $excerpt;
                    }
                }

                $taxonomies = self::get_pattern_taxonomies_payload($post_id);

                if (!empty($taxonomies)) {
                    $pattern_data['taxonomies'] = $taxonomies;
                }

                $meta = self::get_pattern_meta_payload($post_id);

                if (!empty($meta)) {
                    $pattern_data['meta'] = $meta;
                }

                if (!array_key_exists('viewportWidth', $pattern_data) && isset($meta['viewportWidth'])) {
                    $viewport_width = $meta['viewportWidth'];

                    if (is_numeric($viewport_width)) {
                        $pattern_data['viewportWidth'] = (int) $viewport_width;
                    }
                }

                $encoded_pattern = wp_json_encode($pattern_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

                if (false === $encoded_pattern || (function_exists('json_last_error') && json_last_error() !== JSON_ERROR_NONE)) {
                    fclose($handle);
                    @unlink($temp_file);

                    $json_error_message = function_exists('json_last_error_msg')
                        ? json_last_error_msg()
                        : esc_html__('Erreur JSON inconnue.', 'theme-export-jlg');

                    wp_die(
                        esc_html(
                            sprintf(
                                __('Une erreur critique est survenue lors de la création du fichier JSON : %s. Cela peut être dû à des caractères invalides dans une de vos compositions.', 'theme-export-jlg'),
                                $json_error_message
                            )
                        )
                    );
                }

                $formatted_pattern = self::indent_json_fragment($encoded_pattern);

                if ($has_written_items) {
                    self::write_to_handle_or_fail($handle, $temp_file, ",\n" . $formatted_pattern);
                } else {
                    self::write_to_handle_or_fail($handle, $temp_file, $formatted_pattern);
                    $has_written_items = true;
                }
            }

            wp_reset_postdata();

            if ($current_batch_count < $batch_size) {
                break;
            }

            $page++;
        }

        self::write_to_handle_or_fail($handle, $temp_file, $has_written_items ? "\n]\n" : "]\n");
        fclose($handle);

        $filename = empty($sanitized_ids) ? 'exported-patterns.json' : 'selected-patterns.json';
        return self::stream_json_file($temp_file, $filename);
    }

    private static function get_pattern_taxonomies_payload($post_id) {
        $default_taxonomies = ['wp_pattern_category', 'wp_pattern_tag'];

        $taxonomies = apply_filters('tejlg_export_patterns_taxonomies', $default_taxonomies, $post_id);

        if (!is_array($taxonomies) || empty($taxonomies)) {
            return [];
        }

        $payload = [];

        foreach ($taxonomies as $taxonomy) {
            if (!is_string($taxonomy) || '' === $taxonomy) {
                continue;
            }

            $taxonomy = trim($taxonomy);

            if ('' === $taxonomy || !taxonomy_exists($taxonomy)) {
                continue;
            }

            $terms = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'slugs']);

            if (is_wp_error($terms) || empty($terms)) {
                continue;
            }

            $sanitized_terms = array_values(
                array_filter(
                    array_unique(
                        array_map(
                            static function ($term) {
                                if (!is_scalar($term)) {
                                    return '';
                                }

                                $term = trim((string) $term);

                                if ('' === $term) {
                                    return '';
                                }

                                $sanitized = sanitize_title($term);

                                return '' === $sanitized ? '' : $sanitized;
                            },
                            (array) $terms
                        )
                    ),
                    static function ($term) {
                        return '' !== $term;
                    }
                )
            );

            if (!empty($sanitized_terms)) {
                $payload[$taxonomy] = $sanitized_terms;
            }
        }

        return $payload;
    }

    private static function get_pattern_meta_payload($post_id) {
        $registered_meta_keys = function_exists('get_registered_meta_keys')
            ? get_registered_meta_keys('post', 'wp_block')
            : [];

        $default_meta_keys = [];

        if (is_array($registered_meta_keys)) {
            foreach ($registered_meta_keys as $meta_key => $meta_args) {
                if (!is_string($meta_key) || '' === $meta_key) {
                    continue;
                }

                $show_in_rest = is_array($meta_args) && isset($meta_args['show_in_rest'])
                    ? $meta_args['show_in_rest']
                    : false;

                if ($show_in_rest) {
                    $default_meta_keys[] = $meta_key;
                }
            }
        }

        $default_meta_keys[] = 'viewportWidth';

        $meta_keys = apply_filters('tejlg_export_patterns_meta_keys', array_unique($default_meta_keys), $post_id);

        if (!is_array($meta_keys) || empty($meta_keys)) {
            return [];
        }

        $payload = [];

        foreach ($meta_keys as $meta_key) {
            if (!is_string($meta_key)) {
                continue;
            }

            $meta_key = trim($meta_key);

            if ('' === $meta_key) {
                continue;
            }

            $value = get_post_meta($post_id, $meta_key, true);

            if (is_string($value)) {
                if ('' === $value && '0' !== $value) {
                    continue;
                }

                $payload[$meta_key] = $value;
                continue;
            }

            if (is_numeric($value) || is_bool($value)) {
                $payload[$meta_key] = $value;
                continue;
            }

            if (is_array($value)) {
                $normalizer = static function ($item) {
                    if (is_string($item)) {
                        return $item;
                    }

                    if (is_numeric($item) || is_bool($item) || null === $item) {
                        return $item;
                    }

                    return is_object($item) ? (array) $item : $item;
                };

                $normalized = function_exists('map_deep')
                    ? map_deep($value, $normalizer)
                    : self::map_deep_compat($value, $normalizer);

                if (!empty($normalized)) {
                    $payload[$meta_key] = $normalized;
                }
            }
        }

        return $payload;
    }

    private static function map_deep_compat($value, callable $callback) {
        if (is_array($value)) {
            foreach ($value as $key => $sub_value) {
                $value[$key] = self::map_deep_compat($sub_value, $callback);
            }

            return $value;
        }

        return $callback($value);
    }

    private static function write_to_handle_or_fail($handle, $temp_file, $data) {
        $bytes_written = fwrite($handle, $data);

        if (false === $bytes_written) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            if (file_exists($temp_file)) {
                @unlink($temp_file);
            }

            wp_die(
                esc_html__(
                    "Une erreur critique est survenue lors de l'écriture du fichier JSON d'export.",
                    'theme-export-jlg'
                )
            );
        }
    }

    private static function normalize_path($path) {
        $normalized = function_exists('wp_normalize_path')
            ? wp_normalize_path($path)
            : str_replace('\\', '/', $path);

        return rtrim($normalized, '/');
    }

    private static function is_path_within_base($path, $base) {
        if ('' === $base) {
            return true;
        }

        return $path === $base || 0 === strpos($path, $base . '/');
    }

    private static function get_relative_path($path, $base) {
        if ($path === $base) {
            return '';
        }

        $relative = substr($path, strlen($base));
        $relative = ltrim($relative, '/');

        return $relative;
    }

    private static function should_exclude_file($relative_path, $patterns) {
        if (empty($patterns)) {
            return false;
        }

        foreach ($patterns as $pattern) {
            $normalized_pattern = function_exists('wp_normalize_path')
                ? wp_normalize_path($pattern)
                : str_replace('\\', '/', $pattern);

            if ('' === $normalized_pattern) {
                continue;
            }

            $normalized_pattern = ltrim($normalized_pattern, '/');

            if (function_exists('wp_match_path_pattern')) {
                if (wp_match_path_pattern($normalized_pattern, $relative_path)) {
                    return true;
                }
            } elseif (function_exists('fnmatch')) {
                if (fnmatch($normalized_pattern, $relative_path)) {
                    return true;
                }
            } else {
                $regex = '#^' . str_replace('\\*', '.*', preg_quote($normalized_pattern, '#')) . '$#i';
                if (preg_match($regex, $relative_path)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function list_theme_files($theme_dir_path, $normalized_theme_dir) {
        $files = [];

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($theme_dir_path, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
                RecursiveIteratorIterator::CATCH_GET_CHILD
            );
        } catch (UnexpectedValueException $exception) {
            return [];
        }

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }

            $real_path = $file->getRealPath();

            if (false === $real_path) {
                continue;
            }

            $normalized_file_path = self::normalize_path($real_path);

            if (!self::is_path_within_base($normalized_file_path, $normalized_theme_dir)) {
                continue;
            }

            $relative_path = self::get_relative_path($normalized_file_path, $normalized_theme_dir);

            if ('' === $relative_path) {
                continue;
            }

            $files[] = $relative_path;
        }

        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        return $files;
    }

    private static function is_valid_exclusion_pattern($pattern) {
        if (!is_string($pattern) || '' === $pattern) {
            return false;
        }

        if (false !== strpos($pattern, '..')) {
            return false;
        }

        return !preg_match('/[^A-Za-z0-9._\-\/\* ]/u', $pattern);
    }

    /**
     * Safely removes a temporary file, logging failures when possible.
     *
     * @param string $file_path Absolute path to the file to delete.
     * @return bool True on success or if the file is absent, false otherwise.
     */
    private static function delete_temp_file($file_path) {
        if (empty($file_path) || !file_exists($file_path)) {
            return true;
        }

        if (@unlink($file_path)) {
            return true;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[Theme Export JLG] Unable to delete temporary file: %s', $file_path));
        }

        return false;
    }

    /**
     * Exporte uniquement les compositions dont les IDs sont fournis.
     *
     * @param array $pattern_ids Liste d'identifiants de compositions à exporter.
     * @param bool  $is_portable Active le nettoyage « portable » du contenu.
     */
    public static function export_selected_patterns_json($pattern_ids, $is_portable = false) {
        return self::export_patterns_json($pattern_ids, $is_portable);
    }

    public static function export_global_styles() {
        if (!function_exists('wp_get_global_settings')) {
            add_settings_error(
                'tejlg_admin_messages',
                'global_styles_export_unsupported',
                esc_html__("Erreur : Cette version de WordPress ne permet pas l'export des réglages globaux.", 'theme-export-jlg'),
                'error'
            );

            return;
        }

        $settings = wp_get_global_settings();

        if (!is_array($settings)) {
            $settings = [];
        }

        $stylesheet = '';

        if (function_exists('wp_get_global_stylesheet')) {
            $raw_stylesheet = wp_get_global_stylesheet();
            $stylesheet     = is_string($raw_stylesheet) ? $raw_stylesheet : '';
        }

        $theme     = wp_get_theme();
        $theme_name = is_object($theme) ? $theme->get('Name') : '';
        $theme_slug = is_object($theme) ? $theme->get_stylesheet() : '';

        $payload = [
            'meta' => [
                'generated_at' => gmdate('c'),
                'site_url'     => home_url('/'),
                'wp_version'   => get_bloginfo('version'),
                'tejlg_version' => defined('TEJLG_VERSION') ? TEJLG_VERSION : null,
                'theme'        => [
                    'name'       => $theme_name,
                    'stylesheet' => $theme_slug,
                ],
            ],
            'data' => [
                'settings'   => $settings,
                'stylesheet' => $stylesheet,
            ],
        ];

        if (null === $payload['meta']['tejlg_version']) {
            unset($payload['meta']['tejlg_version']);
        }

        $json_options = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $json_options |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        $json_data = wp_json_encode($payload, $json_options);

        if (false === $json_data || (function_exists('json_last_error') && json_last_error() !== JSON_ERROR_NONE)) {
            add_settings_error(
                'tejlg_admin_messages',
                'global_styles_export_json_error',
                esc_html__("Erreur : Impossible de générer le fichier JSON des styles globaux.", 'theme-export-jlg'),
                'error'
            );

            return;
        }

        $temp_file = wp_tempnam('tejlg-global-styles');

        if (empty($temp_file)) {
            add_settings_error(
                'tejlg_admin_messages',
                'global_styles_export_tmp_error',
                esc_html__("Erreur : Impossible de préparer le fichier d'export des styles globaux.", 'theme-export-jlg'),
                'error'
            );

            return;
        }

        $bytes = file_put_contents($temp_file, $json_data);

        if (false === $bytes) {
            @unlink($temp_file);

            add_settings_error(
                'tejlg_admin_messages',
                'global_styles_export_write_error',
                esc_html__("Erreur : Impossible d'écrire le fichier d'export des styles globaux.", 'theme-export-jlg'),
                'error'
            );

            return;
        }

        $filename = sanitize_key($theme_slug);
        $filename = '' !== $filename ? 'global-styles-' . $filename . '.json' : 'global-styles.json';

        self::stream_json_file($temp_file, $filename);
    }
    
    /**
     * Récupère le contenu et garantit qu'il est en UTF-8 valide.
     */
    private static function get_sanitized_content() {
        $content = get_post_field('post_content', get_the_ID());
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        }

        if (function_exists('wp_check_invalid_utf8')) {
            return wp_check_invalid_utf8($content, true);
        }

        return (string) $content;
    }

    /**
     * Nettoie le contenu d'une composition pour la rendre portable.
     */
    private static function clean_pattern_content($content) {
        $blocks = function_exists('parse_blocks') ? parse_blocks($content) : [];

        if (!empty($blocks)) {
            $blocks = array_map([__CLASS__, 'clean_block_recursive'], $blocks);

            if (function_exists('serialize_block')) {
                $content = implode('', array_map('serialize_block', $blocks));
            } elseif (function_exists('render_block')) {
                $content = implode('', array_map('render_block', $blocks));
            }
        }

        // 1. Remplace les URLs absolues du site par des URLs relatives
        $home_url = get_home_url();
        $home_parts = wp_parse_url($home_url);
        $home_path  = '';
        $allowed_ports = [];

        if (!empty($home_parts['path'])) {
            $trimmed_path = trim($home_parts['path'], '/');

            if ('' !== $trimmed_path) {
                $home_path = '/' . $trimmed_path;
            }
        }

        if (!empty($home_parts['host'])) {
            $host_pattern = preg_quote($home_parts['host'], '#');
            $port_pattern = '';

            if (!empty($home_parts['port'])) {
                $allowed_ports[] = (string) $home_parts['port'];
            } else {
                $scheme = isset($home_parts['scheme']) ? strtolower($home_parts['scheme']) : '';

                if ('http' === $scheme) {
                    $allowed_ports[] = '80';
                } elseif ('https' === $scheme) {
                    $allowed_ports[] = '443';
                }
            }

            if (!empty($allowed_ports)) {
                $escaped_ports = array_map(
                    static function ($port) {
                        return preg_quote($port, '#');
                    },
                    $allowed_ports
                );
                $port_pattern = '(?::(?:' . implode('|', $escaped_ports) . '))?';
            }

            $pattern = '#https?:\/\/' . $host_pattern . $port_pattern . '(?=[\/\?#]|$)([\/\?#][^\s"\'>]*)?#i';
            $relative_content = preg_replace_callback(
                $pattern,
                static function ($matches) use ($home_path) {
                    $relative = wp_make_link_relative($matches[0]);

                    if ('' !== $relative && preg_match('#^https?://#i', $relative)) {
                        $parsed_url = wp_parse_url($matches[0]);

                        $path      = isset($parsed_url['path']) ? $parsed_url['path'] : '';
                        $query     = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
                        $fragment  = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
                        $relative  = $path . $query . $fragment;
                    }

                    if ('' !== $home_path && 0 === strpos($relative, $home_path)) {
                        $remaining = substr($relative, strlen($home_path));

                        if ($remaining === '' || in_array($remaining[0], ['/', '?', '#'], true)) {
                            $has_duplicate_prefix = 0 === strpos($remaining, $home_path)
                                && ('' === substr($remaining, strlen($home_path))
                                    || in_array(substr($remaining, strlen($home_path), 1), ['/', '?', '#'], true));

                            if ($has_duplicate_prefix) {
                                $relative = $home_path . substr($remaining, strlen($home_path));
                            }

                            if ($relative === '' || '/' !== $relative[0]) {
                                $relative = '/' . ltrim($relative, '/');
                            }
                        }
                    }

                    if ('' === $relative) {
                        return '/';
                    }

                    if ('/' !== $relative[0]) {
                        $relative = '/' . ltrim($relative, '/');
                    }

                    return $relative;
                },
                $content
            );

            if (null !== $relative_content) {
                $content = $relative_content;
            }
        }

        return $content;
    }

    /**
     * Supprime récursivement les métadonnées des blocs.
     */
    private static function clean_block_recursive($block) {
        if (isset($block['attrs']) && is_array($block['attrs'])) {
            $block['attrs'] = self::clean_metadata_recursive($block['attrs']);
            $block['attrs'] = self::reset_block_ids_recursive($block['attrs']);
        }

        if (!empty($block['innerBlocks'])) {
            $block['innerBlocks'] = array_map([__CLASS__, 'clean_block_recursive'], $block['innerBlocks']);
        }

        return $block;
    }

    /**
     * Parcourt récursivement une structure de données pour supprimer la clé "metadata".
     */
    private static function clean_metadata_recursive($data) {
        if (!is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if ('metadata' === $key) {
                unset($data[$key]);
                continue;
            }

            $data[$key] = self::clean_metadata_recursive($value);
        }

        return $data;
    }

    /**
     * Réinitialise récursivement les identifiants présents dans les attributs de blocs.
     */
    private static function reset_block_ids_recursive($data) {
        if (!is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if ('id' === $key) {
                $data[$key] = self::neutralize_single_id_value($value);
                continue;
            }

            if ('ids' === $key) {
                $data[$key] = self::neutralize_ids_collection($value);
                continue;
            }

            $data[$key] = self::reset_block_ids_recursive($value);
        }

        return $data;
    }

    /**
     * Normalise une valeur d'identifiant simple.
     */
    private static function neutralize_single_id_value($value) {
        if (is_array($value)) {
            return self::reset_block_ids_recursive($value);
        }

        return 0;
    }

    /**
     * Normalise une collection d'identifiants.
     */
    private static function neutralize_ids_collection($value) {
        if (!is_array($value)) {
            return [];
        }

        return array_map([__CLASS__, 'neutralize_single_id_value'], $value);
    }

    /**
     * Gère la création et le téléchargement du fichier JSON.
     */
    private static function download_json( $data, $filename = 'exported-patterns.json' ) {
        $json_options = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE;

        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $json_options |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        $json_data = wp_json_encode($data, $json_options);

        if (false === $json_data || (function_exists('json_last_error') && json_last_error() !== JSON_ERROR_NONE)) {
            $json_error_message = function_exists('json_last_error_msg') ? json_last_error_msg() : __('Erreur JSON inconnue.', 'theme-export-jlg');

            wp_die(
                esc_html(
                    sprintf(
                        __('Une erreur critique est survenue lors de la création du fichier JSON : %s. Cela peut être dû à des caractères invalides dans une de vos compositions.', 'theme-export-jlg'),
                        $json_error_message
                    )
                )
            );
        }

        $temp_file = wp_tempnam('tejlg-patterns-export');

        if (empty($temp_file)) {
            wp_die(esc_html__("Une erreur critique est survenue lors de la préparation du fichier JSON d'export.", 'theme-export-jlg'));
        }

        $bytes = file_put_contents($temp_file, $json_data);

        if (false === $bytes) {
            @unlink($temp_file);
            wp_die(esc_html__("Impossible d'écrire le fichier d'export JSON sur le disque.", 'theme-export-jlg'));
        }

        self::stream_json_file($temp_file, $filename);
    }

    private static function clear_output_buffers() {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    private static function indent_json_fragment($json, $depth = 1) {
        $json  = trim((string) $json);
        $lines = explode("\n", $json);
        $indent = str_repeat('    ', max(0, (int) $depth));

        $lines = array_map(
            static function ($line) use ($indent) {
                return $indent . rtrim($line, "\r");
            },
            $lines
        );

        return implode("\n", $lines);
    }

    private static function stream_json_file($file_path, $filename) {
        if (!@file_exists($file_path) || !is_readable($file_path)) {
            @unlink($file_path);
            wp_die(esc_html__("Le fichier d'export JSON est introuvable ou illisible.", 'theme-export-jlg'));
        }

        $should_stream = apply_filters('tejlg_export_stream_json_file', true, $file_path, $filename);

        if (!$should_stream) {
            $contents = @file_get_contents($file_path);
            @unlink($file_path);

            return false === $contents ? '' : $contents;
        }

        $file_size = filesize($file_path);

        nocache_headers();
        self::clear_output_buffers();

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        if (false !== $file_size) {
            header('Content-Length: ' . $file_size);
        }

        $handle = fopen($file_path, 'rb');

        if (false === $handle) {
            @unlink($file_path);
            wp_die(esc_html__("Impossible de lire le fichier d'export JSON.", 'theme-export-jlg'));
        }

        while (!feof($handle)) {
            echo fread($handle, 8192);
        }

        fclose($handle);
        @unlink($file_path);
        flush();
        exit;
    }
}
<?php

class TEJLG_Admin_Debug_Page extends TEJLG_Admin_Page {
    const METRICS_ICON_OPTION  = 'tejlg_metrics_icon_size';
    const METRICS_ICON_DEFAULT = 60;
    const METRICS_ICON_MIN     = 12;
    const METRICS_ICON_MAX     = 128;

    const DOWNLOAD_REQUEST_FLAG  = 'tejlg_debug_download_report';
    const DOWNLOAD_NONCE_FIELD   = 'tejlg_debug_download_report_nonce';
    const DOWNLOAD_NONCE_ACTION  = 'tejlg_debug_download_report_action';

    private $page_slug;

    public function __construct($template_dir, $page_slug) {
        parent::__construct($template_dir);
        $this->page_slug = $page_slug;
    }

    public function handle_request() {
        if ('POST' !== $this->get_request_method()) {
            return;
        }

        if ($this->is_download_request()) {
            $this->handle_debug_report_download();

            return;
        }

        $this->handle_metrics_request();
    }

    private function handle_metrics_request() {
        if (!isset($_POST['tejlg_metrics_settings_nonce'])) {
            return;
        }

        $nonce = wp_unslash($_POST['tejlg_metrics_settings_nonce']);

        if (!is_string($nonce) || !wp_verify_nonce($nonce, 'tejlg_metrics_settings_action')) {
            return;
        }

        $raw_icon_size     = isset($_POST['tejlg_metrics_icon_size']) ? wp_unslash($_POST['tejlg_metrics_icon_size']) : '';
        $icon_size_input   = is_scalar($raw_icon_size) ? trim((string) $raw_icon_size) : '';
        $default_icon_size = $this->get_default_metrics_icon_size();

        $messages = [];

        if ('' === $icon_size_input || !is_numeric($icon_size_input)) {
            $icon_size = $default_icon_size;
            $messages[] = [
                'code'    => 'metrics_settings_invalid',
                'message' => esc_html__("Valeur invalide pour la taille des icônes. La valeur par défaut a été appliquée.", 'theme-export-jlg'),
                'type'    => 'error',
            ];
        } else {
            $numeric_value = (float) $icon_size_input;
            $icon_size     = $this->sanitize_metrics_icon_size($numeric_value);

            $messages[] = [
                'code'    => 'metrics_settings_updated',
                'message' => esc_html__('Les réglages des indicateurs ont été mis à jour.', 'theme-export-jlg'),
                'type'    => 'updated',
            ];

            if ($icon_size !== (int) round($numeric_value)) {
                $messages[] = [
                    'code'    => 'metrics_settings_adjusted',
                    'message' => esc_html__('La valeur a été ajustée pour rester dans la plage autorisée (12 à 128 px).', 'theme-export-jlg'),
                    'type'    => 'updated',
                ];
            }
        }

        update_option(self::METRICS_ICON_OPTION, $icon_size);

        $has_error = false;

        foreach ($messages as $message) {
            if (isset($message['type']) && 'error' === $message['type']) {
                $has_error = true;
            }

            add_settings_error('tejlg_debug_messages', $message['code'], $message['message'], $message['type']);
        }

        $errors = get_settings_errors('tejlg_debug_messages');
        set_transient('settings_errors', $errors, 30);

        $redirect_url = add_query_arg(
            [
                'page'             => $this->page_slug,
                'tab'              => 'debug',
                'settings-updated' => $has_error ? 'false' : 'true',
            ],
            admin_url('admin.php')
        );

        $fallback_url = admin_url('admin.php?page=' . $this->page_slug . '&tab=debug');
        $redirect_url = wp_validate_redirect($redirect_url, $fallback_url);

        wp_safe_redirect($redirect_url);
        exit;
    }

    private function is_download_request() {
        if (!isset($_POST[self::DOWNLOAD_REQUEST_FLAG])) {
            return false;
        }

        $nonce = isset($_POST[self::DOWNLOAD_NONCE_FIELD]) ? wp_unslash($_POST[self::DOWNLOAD_NONCE_FIELD]) : '';

        if (!is_string($nonce)) {
            return false;
        }

        return wp_verify_nonce($nonce, self::DOWNLOAD_NONCE_ACTION);
    }

    private function handle_debug_report_download() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!function_exists('gzencode')) {
            $this->add_debug_settings_error(
                'tejlg_debug_report_compression',
                esc_html__('La compression Gzip n’est pas disponible sur ce serveur.', 'theme-export-jlg'),
                'error'
            );

            return;
        }

        $report = $this->collect_debug_report_data();
        $json   = wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (false === $json) {
            $this->add_debug_settings_error(
                'tejlg_debug_report_encoding',
                esc_html__("Impossible de générer le rapport JSON.", 'theme-export-jlg'),
                'error'
            );

            return;
        }

        $compressed = gzencode($json);

        if (false === $compressed) {
            $this->add_debug_settings_error(
                'tejlg_debug_report_compression_failure',
                esc_html__("La compression du rapport a échoué.", 'theme-export-jlg'),
                'error'
            );

            return;
        }

        $filename = sprintf('tejlg-debug-report-%s.json.gz', gmdate('Ymd-His'));
        $file_size = strlen($compressed);

        nocache_headers();
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $file_size);

        $this->log_debug_report_download($filename, $file_size, $report);

        echo $compressed; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary stream for download.
        exit;
    }

    private function collect_debug_report_data() {
        $cron_details = $this->get_cron_status_details();

        return [
            'generated_at_gmt'   => gmdate('c'),
            'generated_at_local' => current_datetime()->format(DATE_ATOM),
            'site'               => [
                'name'       => get_bloginfo('name'),
                'home_url'   => home_url(),
                'site_url'   => site_url(),
                'locale'     => get_locale(),
                'timezone'   => wp_timezone_string(),
            ],
            'versions'           => [
                'plugin'    => defined('TEJLG_VERSION') ? TEJLG_VERSION : '',
                'wordpress' => get_bloginfo('version'),
                'php'       => PHP_VERSION,
            ],
            'php_extensions'     => [
                'ziparchive' => class_exists('ZipArchive'),
                'mbstring'   => extension_loaded('mbstring'),
            ],
            'wordpress_limits'   => [
                'wp_memory_limit'     => WP_MEMORY_LIMIT,
                'upload_max_filesize' => ini_get('upload_max_filesize'),
            ],
            'wp_cron'            => $cron_details,
            'custom_patterns'    => $this->get_custom_patterns_summary(),
        ];
    }

    private function log_debug_report_download($filename, $file_size, array $report) {
        if (!class_exists('TEJLG_Export_History')) {
            return;
        }

        $job_id    = uniqid('debug-report-', true);
        $timestamp = time();

        $job = [
            'id'            => $job_id,
            'status'        => 'debug_report',
            'zip_file_name' => $filename,
            'zip_file_size' => $file_size,
            'created_at'    => $timestamp,
            'completed_at'  => $timestamp,
            'exclusions'    => [],
        ];

        $context = [
            'user_id'   => get_current_user_id(),
            'origin'    => 'debug_report',
            'timestamp' => $timestamp,
        ];

        TEJLG_Export_History::record_job($job, $context);
    }

    public function render() {
        $zip_status = class_exists('ZipArchive')
            ? sprintf('<span style="color:green;">%s</span>', esc_html__('Oui', 'theme-export-jlg'))
            : sprintf('<span style="color:red;">%s</span>', esc_html__('Non (Export de thème impossible)', 'theme-export-jlg'));

        $mbstring_status = extension_loaded('mbstring')
            ? sprintf('<span style="color:green;">%s</span>', esc_html__('Activée', 'theme-export-jlg'))
            : sprintf('<span style="color:red; font-weight: bold;">%s</span>', esc_html__('Manquante (CRITIQUE pour la fiabilité des exports JSON)', 'theme-export-jlg'));

        $cron_status = $this->get_cron_status();

        $context = [
            'metrics_icon_size' => $this->get_metrics_icon_size(),
            'metrics_icon_min'  => self::METRICS_ICON_MIN,
            'metrics_icon_max'  => self::METRICS_ICON_MAX,
            'zip_status'        => $zip_status,
            'mbstring_status'   => $mbstring_status,
            'cron_status'       => $cron_status,
        ];

        settings_errors('tejlg_debug_messages');

        $this->render_template('debug.php', $context);
    }

    public function get_metrics_icon_size() {
        $stored_value = get_option(self::METRICS_ICON_OPTION, self::METRICS_ICON_DEFAULT);

        return $this->sanitize_metrics_icon_size($stored_value);
    }

    private function get_default_metrics_icon_size() {
        return self::METRICS_ICON_DEFAULT;
    }

    private function sanitize_metrics_icon_size($value) {
        if (is_string($value)) {
            $value = trim($value);
        }

        if (!is_numeric($value)) {
            $value = self::METRICS_ICON_DEFAULT;
        }

        $value = (int) round((float) $value);

        if ($value < self::METRICS_ICON_MIN) {
            return self::METRICS_ICON_MIN;
        }

        if ($value > self::METRICS_ICON_MAX) {
            return self::METRICS_ICON_MAX;
        }

        return $value;
    }

    private function get_cron_status() {
        $details = $this->get_cron_status_details();

        if ($details['disabled']) {
            return sprintf(
                '<span style="color:red; font-weight:bold;">%s</span>',
                esc_html__(
                    'Désactivé : DISABLE_WP_CRON est défini. Configurez une tâche cron système pour exécuter wp-cron.php manuellement.',
                    'theme-export-jlg'
                )
            );
        }

        $next_cron_text = null !== $details['next_event_local']
            ? sprintf(
                __('Prochain événement planifié : %s.', 'theme-export-jlg'),
                $details['next_event_local']
            )
            : __('Aucun événement planifié actuellement.', 'theme-export-jlg');

        $status_text = $details['alternate']
            ? sprintf(
                __('Actif (mode alternatif). %s', 'theme-export-jlg'),
                $next_cron_text
            )
            : sprintf(
                __('Actif. %s', 'theme-export-jlg'),
                $next_cron_text
            );

        return sprintf(
            '<span style="color:green;">%s</span>',
            esc_html($status_text)
        );
    }

    private function get_cron_status_details() {
        $is_cron_disabled    = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $is_alternate_cron   = defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON;
        $next_cron_timestamp = wp_next_scheduled('wp_version_check');

        $next_event_local = null;
        $next_event_gmt   = null;

        if (false !== $next_cron_timestamp) {
            $next_event_local = wp_date(
                get_option('date_format') . ' ' . get_option('time_format'),
                $next_cron_timestamp
            );
            $next_event_gmt = gmdate('c', $next_cron_timestamp);
        }

        return [
            'disabled'        => $is_cron_disabled,
            'alternate'       => $is_alternate_cron,
            'next_event_gmt'  => $next_event_gmt,
            'next_event_local'=> $next_event_local,
        ];
    }

    private function get_custom_patterns_summary() {
        $current_user_id = get_current_user_id();

        $query = new WP_Query(
            [
                'post_type'      => 'wp_block',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
                'meta_query'     => [
                    'relation' => 'OR',
                    [
                        'key'     => 'wp_block_type',
                        'value'   => 'pattern',
                        'compare' => '=',
                    ],
                    [
                        'key'     => 'wp_block_type',
                        'compare' => 'NOT EXISTS',
                    ],
                ],
            ]
        );

        $patterns = [];

        if ($query->have_posts()) {
            foreach ($query->posts as $pattern_post) {
                $wp_block_type = get_post_meta($pattern_post->ID, 'wp_block_type', true);

                if ((int) $pattern_post->post_author !== (int) $current_user_id && 'pattern' !== $wp_block_type) {
                    continue;
                }

                $patterns[] = [
                    'id'         => $pattern_post->ID,
                    'title'      => get_the_title($pattern_post),
                    'slug'       => $pattern_post->post_name,
                    'author'     => $pattern_post->post_author,
                    'is_global'  => 'pattern' === $wp_block_type,
                    'modified_gmt' => $pattern_post->post_modified_gmt,
                ];
            }
        }

        wp_reset_postdata();

        return $patterns;
    }

    private function add_debug_settings_error($code, $message, $type) {
        add_settings_error('tejlg_debug_messages', $code, $message, $type);
        $errors = get_settings_errors('tejlg_debug_messages');
        set_transient('settings_errors', $errors, 30);

        $redirect_url = add_query_arg(
            [
                'page' => $this->page_slug,
                'tab'  => 'debug',
            ],
            admin_url('admin.php')
        );

        $fallback_url = admin_url('admin.php?page=' . $this->page_slug . '&tab=debug');
        $redirect_url = wp_validate_redirect($redirect_url, $fallback_url);

        wp_safe_redirect($redirect_url);
        exit;
    }

    private function get_request_method() {
        if (!isset($_SERVER['REQUEST_METHOD'])) {
            return '';
        }

        return strtoupper(sanitize_key(wp_unslash($_SERVER['REQUEST_METHOD'])));
    }
}

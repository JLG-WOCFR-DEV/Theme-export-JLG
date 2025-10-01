<?php

class TEJLG_Admin_Debug_Page extends TEJLG_Admin_Page {
    const METRICS_ICON_OPTION  = 'tejlg_metrics_icon_size';
    const METRICS_ICON_DEFAULT = 60;
    const METRICS_ICON_MIN     = 12;
    const METRICS_ICON_MAX     = 128;

    private $page_slug;

    public function __construct($template_dir, $page_slug) {
        parent::__construct($template_dir);
        $this->page_slug = $page_slug;
    }

    public function handle_request() {
        if (!isset($_POST['tejlg_metrics_settings_nonce']) || !wp_verify_nonce($_POST['tejlg_metrics_settings_nonce'], 'tejlg_metrics_settings_action')) {
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
        $is_cron_disabled   = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $is_alternate_cron  = defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON;
        $next_cron_timestamp = wp_next_scheduled('wp_version_check');

        if ($is_cron_disabled) {
            return sprintf(
                '<span style="color:red; font-weight:bold;">%s</span>',
                esc_html__(
                    'Désactivé : DISABLE_WP_CRON est défini. Configurez une tâche cron système pour exécuter wp-cron.php manuellement.',
                    'theme-export-jlg'
                )
            );
        }

        $next_cron_text = false !== $next_cron_timestamp
            ? sprintf(
                __('Prochain événement planifié : %s.', 'theme-export-jlg'),
                wp_date(
                    get_option('date_format') . ' ' . get_option('time_format'),
                    $next_cron_timestamp
                )
            )
            : __('Aucun événement planifié actuellement.', 'theme-export-jlg');

        $status_text = $is_alternate_cron
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
}

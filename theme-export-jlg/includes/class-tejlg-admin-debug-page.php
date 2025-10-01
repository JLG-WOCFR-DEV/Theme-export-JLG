<?php

class TEJLG_Admin_Debug_Page {
    private const METRICS_ICON_OPTION  = 'tejlg_metrics_icon_size';
    private const METRICS_ICON_DEFAULT = 60;
    private const METRICS_ICON_MIN     = 12;
    private const METRICS_ICON_MAX     = 128;

    /**
     * Handle debug tab form submissions.
     */
    public function handle_request() {
        if (!$this->is_nonce_valid('tejlg_metrics_settings_nonce', 'tejlg_metrics_settings_action')) {
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
                'message' => esc_html__('Valeur invalide pour la taille des icônes. La valeur par défaut a été appliquée.', 'theme-export-jlg'),
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
                'page'             => 'theme-export-jlg',
                'tab'              => 'debug',
                'settings-updated' => $has_error ? 'false' : 'true',
            ],
            admin_url('admin.php')
        );

        $fallback_url = admin_url('admin.php?page=theme-export-jlg&tab=debug');
        $redirect_url = wp_validate_redirect($redirect_url, $fallback_url);

        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Render the debug tab.
     */
    public function render() {
        settings_errors('tejlg_debug_messages');

        $metrics_icon_size = $this->get_metrics_icon_size();

        $system_rows = [
            [
                'label'      => esc_html__('Version de WordPress', 'theme-export-jlg'),
                'value'      => esc_html(get_bloginfo('version')),
                'allow_html' => false,
            ],
            [
                'label'      => esc_html__('Version de PHP', 'theme-export-jlg'),
                'value'      => esc_html(PHP_VERSION),
                'allow_html' => false,
            ],
            [
                'label'      => wp_kses_post(__('Classe <code>ZipArchive</code> disponible', 'theme-export-jlg')),
                'value'      => wp_kses_post($this->get_zip_status()),
                'allow_html' => true,
            ],
            [
                'label'      => wp_kses_post(__('Extension PHP <code>mbstring</code>', 'theme-export-jlg')),
                'value'      => wp_kses_post($this->get_mbstring_status()),
                'allow_html' => true,
            ],
            [
                'label'      => esc_html__('Statut WP-Cron', 'theme-export-jlg'),
                'value'      => wp_kses_post($this->get_cron_status()),
                'allow_html' => true,
            ],
            [
                'label'      => esc_html__('Limite de mémoire WP', 'theme-export-jlg'),
                'value'      => esc_html(WP_MEMORY_LIMIT),
                'allow_html' => false,
            ],
            [
                'label'      => esc_html__('Taille max. d\'upload', 'theme-export-jlg'),
                'value'      => esc_html(ini_get('upload_max_filesize')),
                'allow_html' => false,
            ],
        ];

        $patterns_info = $this->get_custom_patterns_information();

        $this->render_template('debug', [
            'metrics_icon_size' => $metrics_icon_size,
            'metrics_icon_min'  => self::METRICS_ICON_MIN,
            'metrics_icon_max'  => self::METRICS_ICON_MAX,
            'metrics_label'     => esc_attr__('Indicateurs de performance', 'theme-export-jlg'),
            'system_rows'       => $system_rows,
            'patterns_info'     => $patterns_info,
        ]);
    }

    public function get_metrics_icon_size() {
        $stored_value = get_option(self::METRICS_ICON_OPTION, self::METRICS_ICON_DEFAULT);

        return $this->sanitize_metrics_icon_size($stored_value);
    }

    private function get_zip_status() {
        return class_exists('ZipArchive')
            ? sprintf('<span style="color:green;">%s</span>', esc_html__('Oui', 'theme-export-jlg'))
            : sprintf('<span style="color:red;">%s</span>', esc_html__('Non (Export de thème impossible)', 'theme-export-jlg'));
    }

    private function get_mbstring_status() {
        return extension_loaded('mbstring')
            ? sprintf('<span style="color:green;">%s</span>', esc_html__('Activée', 'theme-export-jlg'))
            : sprintf('<span style="color:red; font-weight: bold;">%s</span>', esc_html__('Manquante (CRITIQUE pour la fiabilité des exports JSON)', 'theme-export-jlg'));
    }

    private function get_cron_status() {
        $is_cron_disabled    = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $is_alternate_cron   = defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON;
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
                /* translators: %s: formatted date of the next scheduled WP-Cron event. */
                __('Prochain événement planifié : %s.', 'theme-export-jlg'),
                wp_date(
                    get_option('date_format') . ' ' . get_option('time_format'),
                    $next_cron_timestamp
                )
            )
            : __('Aucun événement planifié actuellement.', 'theme-export-jlg');

        $status_text = $is_alternate_cron
            ? sprintf(
                /* translators: %s: information about the next scheduled WP-Cron event. */
                __('Actif (mode alternatif). %s', 'theme-export-jlg'),
                $next_cron_text
            )
            : sprintf(
                /* translators: %s: information about the next scheduled WP-Cron event. */
                __('Actif. %s', 'theme-export-jlg'),
                $next_cron_text
            );

        return sprintf(
            '<span style="color:green;">%s</span>',
            esc_html($status_text)
        );
    }

    private function get_custom_patterns_information() {
        $info = [
            'has_registry'   => class_exists('WP_Block_Patterns_Registry'),
            'count'          => 0,
            'patterns'       => [],
            'message'        => '',
        ];

        if (!$info['has_registry']) {
            $info['message'] = esc_html__("Cette version de WordPress ne prend pas en charge les compositions personnalisées enregistrées via le registre. Mettez à jour WordPress pour afficher cette liste.", 'theme-export-jlg');
            return $info;
        }

        $patterns = WP_Block_Patterns_Registry::get_instance()->get_all_registered();
        $custom_patterns = array_filter(
            $patterns,
            static function ($pattern) {
                return ! (isset($pattern['source']) && 'core' === $pattern['source']);
            }
        );

        if (empty($custom_patterns)) {
            $info['message'] = esc_html__("Aucune composition personnalisée n'a été trouvée.", 'theme-export-jlg');
            return $info;
        }

        $info['count'] = count($custom_patterns);
        $info['message'] = esc_html(
            sprintf(
                _n('%d composition personnalisée trouvée :', '%d compositions personnalisées trouvées :', $info['count'], 'theme-export-jlg'),
                $info['count']
            )
        );

        foreach ($custom_patterns as $pattern) {
            $info['patterns'][] = [
                'title' => isset($pattern['title']) ? (string) $pattern['title'] : '',
                'slug'  => isset($pattern['name']) ? (string) $pattern['name'] : '',
            ];
        }

        return $info;
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

    private function is_nonce_valid($nonce_key, $action) {
        return isset($_POST[$nonce_key]) && wp_verify_nonce($_POST[$nonce_key], $action);
    }

    private function render_template($template, array $context = []) {
        $template_path = $this->locate_template($template);

        if (!$template_path) {
            return;
        }

        extract($context);
        include $template_path;
    }

    private function locate_template($template) {
        $template_directory = defined('TEJLG_PATH')
            ? trailingslashit(TEJLG_PATH) . 'templates/admin/'
            : trailingslashit(dirname(__DIR__)) . 'templates/admin/';

        $file = $template_directory . $template . '.php';

        if (file_exists($file)) {
            return $file;
        }

        return false;
    }
}

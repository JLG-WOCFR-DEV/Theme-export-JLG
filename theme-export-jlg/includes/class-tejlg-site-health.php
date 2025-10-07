<?php

class TEJLG_Site_Health {
    public static function init() {
        add_filter('debug_information', [ __CLASS__, 'register_debug_information' ]);
        add_filter('site_status_tests', [ __CLASS__, 'register_tests' ]);
    }

    /**
     * Ajoute une section dédiée dans les rapports Site Health.
     *
     * @param array<string,mixed> $info
     *
     * @return array<string,mixed>
     */
    public static function register_debug_information($info) {
        if (!class_exists('TEJLG_Admin_Debug_Page')) {
            return $info;
        }

        $snapshot = TEJLG_Admin_Debug_Page::get_debug_report_snapshot();

        $versions   = isset($snapshot['versions']) ? (array) $snapshot['versions'] : [];
        $extensions = isset($snapshot['php_extensions']) ? (array) $snapshot['php_extensions'] : [];
        $limits     = isset($snapshot['wordpress_limits']) ? (array) $snapshot['wordpress_limits'] : [];
        $cron       = isset($snapshot['wp_cron']) ? (array) $snapshot['wp_cron'] : [];
        $patterns   = isset($snapshot['custom_patterns']) ? (array) $snapshot['custom_patterns'] : [];

        $pattern_count       = count($patterns);
        $global_pattern_count = count(array_filter($patterns, static function ($pattern) {
            return is_array($pattern) && !empty($pattern['is_global']);
        }));

        $fields = [
            'plugin_version' => [
                'label' => esc_html__('Version du plugin', 'theme-export-jlg'),
                'value' => isset($versions['plugin']) ? $versions['plugin'] : '',
            ],
            'wordpress_version' => [
                'label' => esc_html__('Version de WordPress', 'theme-export-jlg'),
                'value' => isset($versions['wordpress']) ? $versions['wordpress'] : '',
            ],
            'php_version' => [
                'label' => esc_html__('Version de PHP', 'theme-export-jlg'),
                'value' => isset($versions['php']) ? $versions['php'] : '',
            ],
            'zip_extension' => [
                'label' => esc_html__('Extension ZipArchive', 'theme-export-jlg'),
                'value' => !empty($extensions['ziparchive'])
                    ? esc_html__('Disponible', 'theme-export-jlg')
                    : esc_html__('Manquante', 'theme-export-jlg'),
            ],
            'mbstring_extension' => [
                'label' => esc_html__('Extension mbstring', 'theme-export-jlg'),
                'value' => !empty($extensions['mbstring'])
                    ? esc_html__('Disponible', 'theme-export-jlg')
                    : esc_html__('Manquante', 'theme-export-jlg'),
            ],
            'wp_memory_limit' => [
                'label' => esc_html__('WP_MEMORY_LIMIT', 'theme-export-jlg'),
                'value' => isset($limits['wp_memory_limit']) ? $limits['wp_memory_limit'] : '',
            ],
            'upload_max_filesize' => [
                'label' => esc_html__('upload_max_filesize', 'theme-export-jlg'),
                'value' => isset($limits['upload_max_filesize']) ? $limits['upload_max_filesize'] : '',
            ],
            'wp_cron_status' => [
                'label' => esc_html__('Statut de WP-Cron', 'theme-export-jlg'),
                'value' => !empty($cron['disabled'])
                    ? esc_html__('Désactivé (DISABLE_WP_CRON)', 'theme-export-jlg')
                    : esc_html__('Actif', 'theme-export-jlg'),
                'debug' => isset($cron['alternate']) && $cron['alternate'] ? esc_html__('Mode alternatif actif', 'theme-export-jlg') : '',
            ],
            'wp_cron_next_event' => [
                'label' => esc_html__('Prochain événement WP-Cron', 'theme-export-jlg'),
                'value' => isset($cron['next_event_local']) && $cron['next_event_local']
                    ? $cron['next_event_local']
                    : '—',
                'debug' => isset($cron['next_event_gmt']) && $cron['next_event_gmt'] ? $cron['next_event_gmt'] : '',
            ],
            'patterns_count' => [
                'label' => esc_html__('Compositions disponibles', 'theme-export-jlg'),
                'value' => sprintf(
                    _n('%s composition', '%s compositions', $pattern_count, 'theme-export-jlg'),
                    number_format_i18n($pattern_count)
                ),
                'debug' => sprintf(
                    /* translators: %s is the number of global patterns. */
                    esc_html__('%s composition(s) globales.', 'theme-export-jlg'),
                    number_format_i18n($global_pattern_count)
                ),
            ],
        ];

        $info['theme-export-jlg'] = [
            'label'  => esc_html__('Theme Export - JLG', 'theme-export-jlg'),
            'fields' => apply_filters('tejlg_site_health_fields', $fields, $snapshot),
        ];

        return $info;
    }

    /**
     * Enregistre un test Site Health pour contrôler les extensions PHP critiques.
     *
     * @param array<string,array<string,mixed>> $tests
     *
     * @return array<string,array<string,mixed>>
     */
    public static function register_tests($tests) {
        $tests['direct']['tejlg_required_extensions'] = [
            'label' => esc_html__('Theme Export - JLG : extensions critiques', 'theme-export-jlg'),
            'test'  => [ __CLASS__, 'test_required_extensions' ],
        ];

        return $tests;
    }

    /**
     * Vérifie la présence des extensions PHP indispensables.
     *
     * @return array<string,mixed>
     */
    public static function test_required_extensions() {
        $missing = [];

        if (!class_exists('ZipArchive')) {
            $missing[] = 'ZipArchive';
        }

        if (!extension_loaded('mbstring')) {
            $missing[] = 'mbstring';
        }

        $badge = [
            'label' => esc_html__('Theme Export - JLG', 'theme-export-jlg'),
            'color' => 'blue',
        ];

        if (empty($missing)) {
            return [
                'label'       => esc_html__('Toutes les extensions requises sont disponibles.', 'theme-export-jlg'),
                'status'      => 'good',
                'badge'       => $badge,
                'description' => esc_html__("ZipArchive et mbstring sont chargées : les exports ZIP et JSON fonctionneront correctement.", 'theme-export-jlg'),
                'actions'     => [],
                'test'        => 'tejlg_required_extensions',
            ];
        }

        $missing_list = implode(', ', $missing);

        return [
            'label'       => esc_html__('Extensions PHP manquantes pour Theme Export - JLG', 'theme-export-jlg'),
            'status'      => 'critical',
            'badge'       => $badge,
            'description' => sprintf(
                /* translators: %s is the comma-separated list of missing PHP extensions. */
                esc_html__('Activez ou installez les extensions suivantes : %s.', 'theme-export-jlg'),
                esc_html($missing_list)
            ),
            'actions'     => [
                sprintf(
                    '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                    esc_url('https://www.php.net/manual/fr/'),
                    esc_html__('Documentation PHP', 'theme-export-jlg')
                ),
            ],
            'test'        => 'tejlg_required_extensions',
        ];
    }
}

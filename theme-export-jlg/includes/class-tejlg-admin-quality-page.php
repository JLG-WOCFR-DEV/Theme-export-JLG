<?php
require_once __DIR__ . '/class-tejlg-export-history.php';
require_once __DIR__ . '/class-tejlg-export-notifications.php';

class TEJLG_Admin_Quality_Page extends TEJLG_Admin_Page {
    private $page_slug;

    public function __construct($template_dir, $page_slug) {
        parent::__construct($template_dir);
        $this->page_slug = $page_slug;
    }

    public function render() {
        $context = $this->prepare_quality_context();
        $this->render_template('quality.php', $context);
    }

    private function prepare_quality_context() {
        $date_format = get_option('date_format', 'Y-m-d');
        $time_format = get_option('time_format', 'H:i');
        $datetime_format = trim($date_format . ' ' . $time_format);

        $schedule_settings = TEJLG_Export::get_schedule_settings();
        $schedule_frequency_value = isset($schedule_settings['frequency'])
            ? (string) $schedule_settings['frequency']
            : 'disabled';
        $schedule_is_active = ('disabled' !== $schedule_frequency_value);

        $portable_mode_enabled = $this->get_portable_mode_preference(false);

        $notification_settings = TEJLG_Export_Notifications::get_settings();
        $notification_recipients_value = isset($notification_settings['recipients'])
            ? (string) $notification_settings['recipients']
            : '';
        $notification_enabled_results = isset($notification_settings['enabled_results']) && is_array($notification_settings['enabled_results'])
            ? array_map('sanitize_key', $notification_settings['enabled_results'])
            : [];
        $notification_enabled_lookup = [];

        foreach ($notification_enabled_results as $enabled_result) {
            $notification_enabled_lookup[$enabled_result] = true;
        }

        $notification_recipient_list = array_filter(
            array_map('trim', preg_split('/[\r\n,]+/', $notification_recipients_value))
        );
        $notification_recipient_list = array_values(array_unique($notification_recipient_list));
        $notification_recipient_count = count($notification_recipient_list);
        $notification_recipient_count_label = sprintf(
            _n('%d destinataire', '%d destinataires', $notification_recipient_count, 'theme-export-jlg'),
            $notification_recipient_count
        );

        $history_stats = TEJLG_Export_History::get_recent_stats();
        $history_snapshot = TEJLG_Export_History::get_entries([
            'per_page' => 1,
            'paged'    => 1,
            'orderby'  => 'timestamp',
            'order'    => 'desc',
        ]);
        $history_entries = isset($history_snapshot['entries']) && is_array($history_snapshot['entries'])
            ? $history_snapshot['entries']
            : [];
        $latest_export = !empty($history_entries) ? $history_entries[0] : null;

        $history_result_labels = [
            'success' => __('Succès', 'theme-export-jlg'),
            'warning' => __('Avertissement', 'theme-export-jlg'),
            'error'   => __('Échec', 'theme-export-jlg'),
            'info'    => __('Information', 'theme-export-jlg'),
        ];

        $history_origin_labels = [
            'web'      => __('Interface', 'theme-export-jlg'),
            'cli'      => __('WP-CLI', 'theme-export-jlg'),
            'schedule' => __('Planification', 'theme-export-jlg'),
        ];

        $monitoring_counts = [
            'success' => 0,
            'warning' => 0,
            'error'   => 0,
            'info'    => 0,
        ];

        if (isset($history_stats['counts']) && is_array($history_stats['counts'])) {
            foreach ($monitoring_counts as $key => $value) {
                if (isset($history_stats['counts'][$key])) {
                    $monitoring_counts[$key] = (int) $history_stats['counts'][$key];
                }
            }
        }

        $monitoring_total_recent = isset($history_stats['total_recent'])
            ? (int) $history_stats['total_recent']
            : ($monitoring_counts['success'] + $monitoring_counts['warning'] + $monitoring_counts['error'] + $monitoring_counts['info']);
        $monitoring_window_days  = isset($history_stats['window_days']) ? (int) $history_stats['window_days'] : 7;
        $monitoring_uptime_rate  = isset($history_stats['uptime_rate']) ? $history_stats['uptime_rate'] : null;

        if (null !== $monitoring_uptime_rate) {
            $monitoring_uptime_rate = (float) $monitoring_uptime_rate;
        }

        $monitoring_value = ($monitoring_total_recent > 0 && null !== $monitoring_uptime_rate)
            ? sprintf(
                /* translators: %s: success rate percentage. */
                __('%s %% de succès', 'theme-export-jlg'),
                number_format_i18n($monitoring_uptime_rate, 1)
            )
            : __('Aucune donnée récente', 'theme-export-jlg');

        if ($monitoring_total_recent <= 0) {
            $monitoring_breakdown_label = __('Surveillance inactive : aucune exécution récente.', 'theme-export-jlg');
        } else {
            $monitoring_breakdown_label = sprintf(
                /* translators: 1: export count, 2: success count, 3: warning count, 4: error count. */
                __('Sur %1$d export(s) : %2$d succès · %3$d avertissements · %4$d erreurs', 'theme-export-jlg'),
                $monitoring_total_recent,
                $monitoring_counts['success'],
                $monitoring_counts['warning'],
                $monitoring_counts['error']
            );
        }

        $monitoring_window_label = sprintf(
            _n('Période analysée : %d jour', 'Période analysée : %d jours', max(1, $monitoring_window_days), 'theme-export-jlg'),
            max(1, $monitoring_window_days)
        );

        $monitoring_last_entry_label = __('Dernier événement : aucun export enregistré.', 'theme-export-jlg');
        $monitoring_last_details     = '';
        $monitoring_latest_entry     = isset($history_stats['latest_entry']) && is_array($history_stats['latest_entry'])
            ? $history_stats['latest_entry']
            : null;

        if (is_array($monitoring_latest_entry) && !empty($monitoring_latest_entry)) {
            $last_result_key = isset($monitoring_latest_entry['result']) ? (string) $monitoring_latest_entry['result'] : '';
            $last_result_label = isset($history_result_labels[$last_result_key])
                ? $history_result_labels[$last_result_key]
                : __('Statut inconnu', 'theme-export-jlg');

            $last_timestamp = isset($monitoring_latest_entry['timestamp']) ? (int) $monitoring_latest_entry['timestamp'] : 0;

            if ($last_timestamp > 0) {
                if (function_exists('wp_date')) {
                    $last_date_label = wp_date($datetime_format, $last_timestamp);
                } else {
                    $last_date_label = date_i18n($datetime_format, $last_timestamp);
                }
            } else {
                $last_date_label = __('Date inconnue', 'theme-export-jlg');
            }

            $monitoring_last_entry_label = sprintf(
                /* translators: 1: result label, 2: formatted date. */
                __('Dernier événement : %1$s – %2$s', 'theme-export-jlg'),
                $last_result_label,
                $last_date_label
            );

            $last_details_parts = [];

            if (!empty($monitoring_latest_entry['duration']) && function_exists('human_readable_duration')) {
                $last_details_parts[] = human_readable_duration((int) $monitoring_latest_entry['duration']);
            }

            if (!empty($monitoring_latest_entry['zip_file_size'])) {
                $last_details_parts[] = size_format((int) $monitoring_latest_entry['zip_file_size'], 2);
            }

            if (!empty($monitoring_latest_entry['origin'])) {
                $origin_key = (string) $monitoring_latest_entry['origin'];
                $origin_label = isset($history_origin_labels[$origin_key]) ? $history_origin_labels[$origin_key] : $origin_key;
                $last_details_parts[] = sprintf(
                    /* translators: %s: export origin label. */
                    __('Origine : %s', 'theme-export-jlg'),
                    $origin_label
                );
            }

            if (!empty($monitoring_latest_entry['status_message'])) {
                $last_details_parts[] = wp_strip_all_tags((string) $monitoring_latest_entry['status_message']);
            }

            if (!empty($last_details_parts)) {
                $monitoring_last_details = implode(' · ', array_map('sanitize_text_field', $last_details_parts));
            }
        }

        $quality_score_cap = static function ($score) {
            if (!is_numeric($score)) {
                return 0;
            }

            $score = (float) $score;

            return (int) max(0, min(100, round($score)));
        };

        $ui_ux_score = 68;

        if ($schedule_is_active) {
            $ui_ux_score += 8;
        }

        if (!empty($latest_export)) {
            $ui_ux_score += 6;
        }

        if (!empty($notification_enabled_lookup)) {
            $ui_ux_score += 6;
        }

        if ($portable_mode_enabled) {
            $ui_ux_score += 4;
        }

        $ui_ux_score = $quality_score_cap($ui_ux_score);

        $accessibility_score = 70;

        if ($portable_mode_enabled) {
            $accessibility_score += 6;
        }

        if (!empty($notification_recipient_list)) {
            $accessibility_score += 4;
        }

        if ($monitoring_total_recent > 0) {
            $accessibility_score += 4;
        }

        $accessibility_score = $quality_score_cap($accessibility_score);

        if ($monitoring_total_recent > 0 && null !== $monitoring_uptime_rate) {
            $reliability_score = $quality_score_cap($monitoring_uptime_rate);
        } else {
            $fallback_reliability = 60;

            if ($schedule_is_active) {
                $fallback_reliability += 12;
            }

            if (!empty($history_entries)) {
                $fallback_reliability += 8;
            }

            if (!empty($notification_enabled_lookup)) {
                $fallback_reliability += 6;
            }

            $reliability_score = $quality_score_cap($fallback_reliability);
        }

        $visual_score = 74;

        if ($schedule_is_active) {
            $visual_score += 4;
        }

        if ($portable_mode_enabled) {
            $visual_score += 3;
        }

        if (!empty($notification_enabled_lookup)) {
            $visual_score += 3;
        }

        $visual_score = $quality_score_cap($visual_score);

        $quality_badge_map = [
            'excellent' => [
                'label' => __('Aligné sur les standards pro', 'theme-export-jlg'),
                'class' => 'is-excellent',
            ],
            'solid' => [
                'label' => __('Solide et évolutif', 'theme-export-jlg'),
                'class' => 'is-solid',
            ],
            'watch' => [
                'label' => __('À renforcer', 'theme-export-jlg'),
                'class' => 'is-watch',
            ],
        ];

        $quality_badge_for_score = static function ($score) use ($quality_badge_map) {
            $score = (int) $score;

            if ($score >= 85) {
                return $quality_badge_map['excellent'];
            }

            if ($score >= 70) {
                return $quality_badge_map['solid'];
            }

            return $quality_badge_map['watch'];
        };

        if ($monitoring_total_recent > 0) {
            $reliability_context = sprintf(
                /* translators: 1: monitoring value label, 2: monitoring breakdown label. */
                __('%1$s · %2$s', 'theme-export-jlg'),
                $monitoring_value,
                $monitoring_breakdown_label
            );
        } else {
            $reliability_context = __('Activez la planification ou lancez un export manuel pour alimenter les indicateurs de fiabilité.', 'theme-export-jlg');
        }

        if ($schedule_is_active) {
            $ui_ux_context = __('Planification active et notifications rapprochent l’expérience de celles proposées par les suites professionnelles.', 'theme-export-jlg');
        } else {
            $ui_ux_context = __('Activez la planification pour proposer une expérience continue comparable aux solutions pro.', 'theme-export-jlg');
        }

        if (!empty($notification_recipient_list)) {
            $accessibility_context = sprintf(
                /* translators: %s: recipients count label. */
                __('Notifications configurées : %s.', 'theme-export-jlg'),
                $notification_recipient_count_label
            );
        } else {
            $accessibility_context = __('Ajoutez des notifications e-mail ou webhook pour prévenir toute erreur sans surveillance manuelle.', 'theme-export-jlg');
        }

        $visual_context = __('Interface basée sur les composants WordPress et les variables de couleur de l’administration.', 'theme-export-jlg');

        $quality_sections = [
            [
                'id'          => 'ui-ux',
                'label'       => __('UI / UX', 'theme-export-jlg'),
                'score'       => $ui_ux_score,
                'badge'       => $quality_badge_for_score($ui_ux_score),
                'summary'     => __('L’expérience couvre les besoins essentiels avec une navigation claire inspirée des tableaux de bord professionnels.', 'theme-export-jlg'),
                'context'     => $ui_ux_context,
                'strengths'   => [
                    __('File d’attente des exports avec annulation et suivi en temps réel.', 'theme-export-jlg'),
                    __('Bannière de raccourcis et cartes synthétiques pour accéder rapidement aux actions critiques.', 'theme-export-jlg'),
                    __('Sélecteur de compositions paginé avec filtres, recherche et prévisualisation intégrée.', 'theme-export-jlg'),
                ],
                'roadmap'     => [
                    __('Introduire un assistant multi-étapes avec résumé exportable (PDF/CSV).', 'theme-export-jlg'),
                    __('Ajouter un panneau latéral contextualisé pour guider chaque étape clé.', 'theme-export-jlg'),
                ],
            ],
            [
                'id'          => 'accessibility',
                'label'       => __('Accessibilité', 'theme-export-jlg'),
                'score'       => $accessibility_score,
                'badge'       => $quality_badge_for_score($accessibility_score),
                'summary'     => __('Les dropzones, focus visibles et composants natifs offrent une base inclusive conforme aux attentes pro.', 'theme-export-jlg'),
                'context'     => $accessibility_context,
                'strengths'   => [
                    __('Dropzones utilisables au clavier avec messages d’aide fournis via ARIA.', 'theme-export-jlg'),
                    __('Focus renforcés sur les boutons et onglets pour une navigation claire.', 'theme-export-jlg'),
                    __('Utilisation des variables de contraste de l’administration pour respecter les préférences utilisateurs.', 'theme-export-jlg'),
                ],
                'roadmap'     => [
                    __('Ajouter des raccourcis clavier dédiés aux actions fréquentes (export, sélection).', 'theme-export-jlg'),
                    __('Intégrer un audit automatique des contrastes et un rapport téléchargeable.', 'theme-export-jlg'),
                ],
            ],
            [
                'id'          => 'reliability',
                'label'       => __('Fiabilité', 'theme-export-jlg'),
                'score'       => $reliability_score,
                'badge'       => $quality_badge_for_score($reliability_score),
                'summary'     => __('Le suivi des exports, l’historique détaillé et les notifications rapprochent la supervision des solutions managées.', 'theme-export-jlg'),
                'context'     => $reliability_context,
                'strengths'   => [
                    __('Historique persistant avec durée, taille et origine de chaque export.', 'theme-export-jlg'),
                    __('Planification WP-Cron et purge automatique des archives anciennes.', 'theme-export-jlg'),
                    __('Alertes e-mail configurables pour chaque statut critique.', 'theme-export-jlg'),
                ],
                'roadmap'     => [
                    __('Proposer un connecteur de stockage externe (S3, SFTP) pour une redondance pro.', 'theme-export-jlg'),
                    __('Automatiser l’analyse des rapports et l’envoi de webhooks détaillés.', 'theme-export-jlg'),
                ],
            ],
            [
                'id'          => 'visual',
                'label'       => __('Aspect visuel', 'theme-export-jlg'),
                'score'       => $visual_score,
                'badge'       => $quality_badge_for_score($visual_score),
                'summary'     => __('La charte reprend les codes de l’interface WordPress avec des cartes élevées et badges dynamiques.', 'theme-export-jlg'),
                'context'     => $visual_context,
                'strengths'   => [
                    __('Cartes « components-card » et variables CSS harmonisées avec le mode sombre.', 'theme-export-jlg'),
                    __('Bannière et dashboard condensés pour rappeler les solutions pro.', 'theme-export-jlg'),
                    __('Graphismes cohérents dans l’éditeur de site grâce aux tokens partagés.', 'theme-export-jlg'),
                ],
                'roadmap'     => [
                    __('Activer une vue compacte et mobile avec accordéons et bouton d’actions rapides.', 'theme-export-jlg'),
                    __('Transformer l’historique en timeline interactive pour un rendu premium.', 'theme-export-jlg'),
                ],
            ],
        ];

        $quality_sections = array_map(
            static function ($section) {
                $badge = isset($section['badge']) && is_array($section['badge']) ? $section['badge'] : ['label' => '', 'class' => ''];

                $section['badge_label'] = isset($badge['label']) ? (string) $badge['label'] : '';
                $section['badge_class'] = isset($badge['class']) ? (string) $badge['class'] : '';
                $section['score'] = isset($section['score']) ? (int) $section['score'] : 0;
                $section['score_label'] = sprintf(
                    /* translators: 1: benchmark label, 2: score value. */
                    __('%1$s : %2$d sur 100', 'theme-export-jlg'),
                    isset($section['label']) ? (string) $section['label'] : '',
                    $section['score']
                );

                return $section;
            },
            $quality_sections
        );

        $quality_summary_lines = array_filter([
            $monitoring_value,
            $monitoring_breakdown_label,
            $monitoring_window_label,
            $monitoring_last_entry_label,
            $monitoring_last_details,
        ],
            static function ($line) {
                return is_string($line) && '' !== trim($line);
            }
        );

        return [
            'quality_page_title'   => __('Comparaison avec les extensions pro', 'theme-export-jlg'),
            'quality_intro'        => __('Découvrez comment Theme Export - JLG se positionne face aux suites professionnelles grâce à ces indicateurs continus.', 'theme-export-jlg'),
            'quality_sections'     => $quality_sections,
            'quality_summary_lines'=> array_values($quality_summary_lines),
        ];
    }

    private function get_portable_mode_preference($default) {
        $stored = get_option(TEJLG_Admin_Export_Page::PORTABLE_MODE_OPTION, null);

        if (null === $stored) {
            return (bool) $default;
        }

        return '1' === (string) $stored;
    }
}

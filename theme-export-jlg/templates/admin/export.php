<?php
/** @var string $page_slug */
/** @var string $child_theme_value */
/** @var string $exclusion_patterns_value */
/** @var bool   $portable_mode_enabled */
/** @var array  $schedule_settings */
/** @var array  $schedule_frequencies */
/** @var int|false $schedule_next_run */
/** @var array  $history_entries */
/** @var int    $history_total */
/** @var int    $history_total_all */
/** @var array  $history_pagination_links */
/** @var int    $history_current_page */
/** @var int    $history_total_pages */
/** @var int    $history_per_page */
/** @var array  $history_filter_values */
/** @var array  $history_selected_filters */
/** @var array  $history_base_args */
/** @var array  $history_stats */
/** @var array  $notification_settings */

$export_tab_url = add_query_arg([
    'page' => $page_slug,
    'tab'  => 'export',
], admin_url('admin.php'));

$select_patterns_url = add_query_arg([
    'page'   => $page_slug,
    'tab'    => 'export',
    'action' => 'select_patterns',
], admin_url('admin.php'));

$schedule_frequency_value  = isset($schedule_settings['frequency']) ? (string) $schedule_settings['frequency'] : 'disabled';
$schedule_exclusions_value = isset($schedule_settings['exclusions']) ? (string) $schedule_settings['exclusions'] : '';
$schedule_retention_value  = isset($schedule_settings['retention_days']) ? (int) $schedule_settings['retention_days'] : 0;
$schedule_run_time_value   = isset($schedule_settings['run_time']) ? (string) $schedule_settings['run_time'] : '00:00';

if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $schedule_run_time_value)) {
    $schedule_run_time_value = '00:00';
}

$site_timezone_string = function_exists('wp_timezone_string') ? wp_timezone_string() : get_option('timezone_string');

if (!is_string($site_timezone_string) || '' === $site_timezone_string) {
    $offset          = (float) get_option('gmt_offset', 0);
    $offset_hours    = (int) $offset;
    $offset_minutes  = (int) round(abs($offset - $offset_hours) * 60);
    $offset_sign     = $offset < 0 ? '-' : '+';
    $site_timezone_string = sprintf('%s%02d:%02d', $offset_sign, abs($offset_hours), abs($offset_minutes));
}

$date_format = get_option('date_format', 'Y-m-d');
$time_format = get_option('time_format', 'H:i');
$datetime_format = trim($date_format . ' ' . $time_format);

$current_theme = wp_get_theme();

$history_filter_values = is_array($history_filter_values) ? $history_filter_values : ['origins' => [], 'results' => []];
$history_selected_filters = is_array($history_selected_filters)
    ? wp_parse_args($history_selected_filters, [
        'result'  => '',
        'origin'  => '',
        'orderby' => 'timestamp',
        'order'   => 'desc',
    ])
    : [
        'result'  => '',
        'origin'  => '',
        'orderby' => 'timestamp',
        'order'   => 'desc',
    ];
$history_base_args = is_array($history_base_args) ? $history_base_args : [
    'page' => $page_slug,
    'tab'  => 'export',
];

$history_stats = is_array($history_stats) ? $history_stats : [];
$notification_settings = is_array($notification_settings) ? $notification_settings : [];

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

$available_history_results = isset($history_filter_values['results']) && is_array($history_filter_values['results'])
    ? $history_filter_values['results']
    : [];
$available_history_origins = isset($history_filter_values['origins']) && is_array($history_filter_values['origins'])
    ? $history_filter_values['origins']
    : [];

$available_history_results = array_values(array_unique(array_merge(array_keys($history_result_labels), $available_history_results)));
sort($available_history_results);

$available_history_origins = array_values(array_unique(array_merge(array_keys($history_origin_labels), $available_history_origins)));
sort($available_history_origins);

$latest_export = !empty($history_entries) ? $history_entries[0] : null;

$notification_recipients_value = isset($notification_settings['recipients']) ? (string) $notification_settings['recipients'] : '';
$notification_enabled_results = isset($notification_settings['enabled_results']) && is_array($notification_settings['enabled_results'])
    ? array_map('sanitize_key', $notification_settings['enabled_results'])
    : [];
$notification_enabled_lookup = [];

$notification_recipient_list = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $notification_recipients_value)));
$notification_recipient_list = array_values(array_unique($notification_recipient_list));
$notification_recipient_count = count($notification_recipient_list);
$notification_recipient_count_label = sprintf(
    _n('%d destinataire', '%d destinataires', $notification_recipient_count, 'theme-export-jlg'),
    $notification_recipient_count
);

foreach ($notification_enabled_results as $enabled_result) {
    $notification_enabled_lookup[$enabled_result] = true;
}

$latest_export_status = __('Aucun export enregistré', 'theme-export-jlg');
$latest_export_date = __('Lancez un premier export pour voir son statut ici.', 'theme-export-jlg');
$latest_export_size = '—';
$latest_export_exclusions = __('Aucun motif', 'theme-export-jlg');
$current_exclusion_summary = __('Aucun motif', 'theme-export-jlg');

if (is_array($latest_export)) {
    $latest_status_key = isset($latest_export['result']) ? (string) $latest_export['result'] : '';
    $latest_status_label = isset($history_result_labels[$latest_status_key])
        ? $history_result_labels[$latest_status_key]
        : (isset($latest_export['status']) && '' !== $latest_export['status']
            ? (string) $latest_export['status']
            : __('Inconnu', 'theme-export-jlg'));

    $latest_export_status = sprintf(
        /* translators: %s: export status label */
        __('Statut : %s', 'theme-export-jlg'),
        $latest_status_label
    );

    $latest_timestamp = isset($latest_export['timestamp']) ? (int) $latest_export['timestamp'] : 0;

    if ($latest_timestamp > 0) {
        if (function_exists('wp_date')) {
            $latest_export_date = wp_date($datetime_format, $latest_timestamp);
        } else {
            $latest_export_date = date_i18n($datetime_format, $latest_timestamp);
        }
    } else {
        $latest_export_date = __('Date inconnue', 'theme-export-jlg');
    }

    $latest_size_bytes = isset($latest_export['zip_file_size']) ? (int) $latest_export['zip_file_size'] : 0;
    $latest_export_size = $latest_size_bytes > 0 ? size_format($latest_size_bytes, 2) : __('Inconnue', 'theme-export-jlg');

    $latest_exclusions = isset($latest_export['exclusions']) ? (array) $latest_export['exclusions'] : [];
    $latest_exclusions_clean = array_filter(array_map('sanitize_text_field', $latest_exclusions));
    $latest_export_exclusions = !empty($latest_exclusions_clean)
        ? implode(', ', $latest_exclusions_clean)
        : __('Aucun motif', 'theme-export-jlg');
}

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

$monitoring_total_recent = isset($history_stats['total_recent']) ? (int) $history_stats['total_recent'] : ($monitoring_counts['success'] + $monitoring_counts['warning'] + $monitoring_counts['error'] + $monitoring_counts['info']);
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

$exclusion_summary_patterns = preg_split('/[\r\n,]+/', (string) $exclusion_patterns_value);

if (is_array($exclusion_summary_patterns)) {
    $exclusion_summary_patterns = array_filter(array_map('sanitize_text_field', array_map('trim', $exclusion_summary_patterns)));
    if (!empty($exclusion_summary_patterns)) {
        $current_exclusion_summary = implode(', ', $exclusion_summary_patterns);
    }
}

$schedule_is_active = 'disabled' !== $schedule_frequency_value;
$schedule_frequency_label = $schedule_is_active && isset($schedule_frequencies[$schedule_frequency_value])
    ? (string) $schedule_frequencies[$schedule_frequency_value]
    : __('Planification désactivée', 'theme-export-jlg');

if ($schedule_is_active && empty($schedule_frequency_label)) {
    $schedule_frequency_label = __('Planification active', 'theme-export-jlg');
}

if ($schedule_is_active && !empty($schedule_next_run)) {
    if (function_exists('wp_date')) {
        $schedule_next_run_label = wp_date($datetime_format, (int) $schedule_next_run);
    } else {
        $schedule_next_run_label = date_i18n($datetime_format, (int) $schedule_next_run);
    }
} else {
    $schedule_next_run_label = __('Aucune exécution planifiée', 'theme-export-jlg');
}

$schedule_retention_label = $schedule_retention_value > 0
    ? sprintf(
        _n('%d jour', '%d jours', $schedule_retention_value, 'theme-export-jlg'),
        $schedule_retention_value
    )
    : __('Conservation illimitée', 'theme-export-jlg');

$history_total_all = isset($history_total_all) ? (int) $history_total_all : (int) $history_total;
$history_total_all_label = number_format_i18n($history_total_all);
$history_total_filtered_label = number_format_i18n((int) $history_total);

if ($schedule_is_active) {
    $schedule_summary_description = sprintf(
        /* translators: 1: schedule frequency label, 2: next run label. */
        __('%1$s · prochaine exécution : %2$s', 'theme-export-jlg'),
        $schedule_frequency_label,
        $schedule_next_run_label
    );
} else {
    $schedule_summary_description = __('Planification désactivée', 'theme-export-jlg');
}

$schedule_section_open = $schedule_is_active
    || '' !== trim($schedule_exclusions_value)
    || !empty($notification_enabled_results);

$history_filters_active = (
    (isset($history_selected_filters['result']) && '' !== $history_selected_filters['result'])
    || (isset($history_selected_filters['origin']) && '' !== $history_selected_filters['origin'])
    || (isset($history_selected_filters['orderby']) && 'timestamp' !== $history_selected_filters['orderby'])
    || (isset($history_selected_filters['order']) && 'desc' !== $history_selected_filters['order'])
);

$history_summary_inline = sprintf(
    /* translators: 1: number of displayed exports, 2: total number of exports recorded. */
    __('%1$s export(s) visibles sur %2$s', 'theme-export-jlg'),
    $history_total_filtered_label,
    $history_total_all_label
);

$history_section_open = $history_filters_active;
?>
<div class="tejlg-export-banner" role="region" aria-labelledby="tejlg-export-banner-title">
    <h2 id="tejlg-export-banner-title" class="screen-reader-text"><?php esc_html_e('Raccourcis d’export', 'theme-export-jlg'); ?></h2>
    <div class="tejlg-export-banner__grid">
        <div class="tejlg-export-banner__item">
            <span class="tejlg-export-banner__label"><?php esc_html_e('Dernier export', 'theme-export-jlg'); ?></span>
            <span class="tejlg-export-banner__value"><?php echo esc_html($latest_export_date); ?></span>
            <span class="tejlg-export-banner__meta"><?php echo esc_html($latest_export_status); ?></span>
        </div>
        <div class="tejlg-export-banner__item">
            <span class="tejlg-export-banner__label"><?php esc_html_e('Prochain export', 'theme-export-jlg'); ?></span>
            <span class="tejlg-export-banner__value"><?php echo esc_html($schedule_next_run_label); ?></span>
            <span class="tejlg-export-banner__meta"><?php echo esc_html($schedule_frequency_label); ?></span>
        </div>
        <div class="tejlg-export-banner__item tejlg-export-banner__item--cta">
            <span class="tejlg-export-banner__label"><?php esc_html_e('Exporter maintenant', 'theme-export-jlg'); ?></span>
            <a class="button button-primary wp-ui-primary" href="#tejlg-theme-export-form" data-banner-cta><?php esc_html_e('Lancer un export', 'theme-export-jlg'); ?></a>
            <span class="tejlg-export-banner__meta"><?php esc_html_e('Accédez directement à l’assistant en 3 étapes.', 'theme-export-jlg'); ?></span>
        </div>
    </div>
</div>
<section class="tejlg-dashboard" aria-labelledby="tejlg-dashboard-title">
    <div class="tejlg-dashboard__header">
        <h2 id="tejlg-dashboard-title"><?php esc_html_e('Vue d’ensemble des exports', 'theme-export-jlg'); ?></h2>
        <p class="tejlg-dashboard__intro"><?php esc_html_e('Surveillez vos archives, la planification et les alertes avant de lancer une nouvelle action.', 'theme-export-jlg'); ?></p>
    </div>
    <div class="tejlg-dashboard__grid">
        <div class="tejlg-dashboard__card components-card is-elevated">
            <div class="components-card__body">
                <span class="tejlg-dashboard__label"><?php esc_html_e('Dernier export', 'theme-export-jlg'); ?></span>
                <strong class="tejlg-dashboard__value"><?php echo esc_html($latest_export_date); ?></strong>
                <span class="tejlg-dashboard__meta"><?php echo esc_html($latest_export_status); ?></span>
                <span class="tejlg-dashboard__meta">
                    <?php
                    printf(
                        /* translators: %s: archive size label */
                        esc_html__('Taille : %s', 'theme-export-jlg'),
                        esc_html($latest_export_size)
                    );
                    ?>
                </span>
                <span class="tejlg-dashboard__meta">
                    <?php
                    printf(
                        /* translators: %s: exclusion patterns list */
                        esc_html__('Motifs : %s', 'theme-export-jlg'),
                        esc_html($latest_export_exclusions)
                    );
                    ?>
                </span>
            </div>
        </div>
        <div class="tejlg-dashboard__card components-card is-elevated">
            <div class="components-card__body">
                <span class="tejlg-dashboard__label"><?php esc_html_e('Planification', 'theme-export-jlg'); ?></span>
                <strong class="tejlg-dashboard__value"><?php echo esc_html($schedule_frequency_label); ?></strong>
                <span class="tejlg-dashboard__meta"><?php echo esc_html($schedule_next_run_label); ?></span>
                <span class="tejlg-dashboard__meta">
                    <?php
                    printf(
                        /* translators: %s: schedule time */
                        esc_html__('Heure cible : %s', 'theme-export-jlg'),
                        esc_html($schedule_run_time_value)
                    );
                    ?>
                </span>
                <span class="tejlg-dashboard__meta"><?php echo esc_html($schedule_retention_label); ?></span>
            </div>
        </div>
        <div class="tejlg-dashboard__card components-card is-elevated">
            <div class="components-card__body">
                <span class="tejlg-dashboard__label"><?php esc_html_e('Archives suivies', 'theme-export-jlg'); ?></span>
                <strong class="tejlg-dashboard__value"><?php echo esc_html($history_total_all_label); ?></strong>
                <span class="tejlg-dashboard__meta"><?php esc_html_e('Nombre total d’exports enregistrés', 'theme-export-jlg'); ?></span>
                <span class="tejlg-dashboard__meta">
                    <?php
                    printf(
                        /* translators: %s: timezone label */
                        esc_html__('Fuseau horaire : %s', 'theme-export-jlg'),
                        esc_html($site_timezone_string)
                    );
                    ?>
                </span>
            </div>
        </div>
        <div class="tejlg-dashboard__card components-card is-elevated">
            <div class="components-card__body">
                <span class="tejlg-dashboard__label"><?php esc_html_e('Surveillance & alertes', 'theme-export-jlg'); ?></span>
                <strong class="tejlg-dashboard__value"><?php echo esc_html($monitoring_value); ?></strong>
                <span class="tejlg-dashboard__meta"><?php echo esc_html($monitoring_breakdown_label); ?></span>
                <span class="tejlg-dashboard__meta"><?php echo esc_html($monitoring_window_label); ?></span>
                <span class="tejlg-dashboard__meta"><?php echo esc_html($monitoring_last_entry_label); ?></span>
                <?php if ('' !== $monitoring_last_details) : ?>
                    <span class="tejlg-dashboard__meta"><?php echo esc_html($monitoring_last_details); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<div class="tejlg-accessibility-tools" role="region" aria-labelledby="tejlg-accessibility-tools-title">
    <div class="tejlg-contrast-toggle">
        <span id="tejlg-accessibility-tools-title" class="tejlg-accessibility-tools__title"><?php esc_html_e('Lisibilité', 'theme-export-jlg'); ?></span>
        <div class="tejlg-contrast-toggle__control">
            <input
                type="checkbox"
                id="tejlg-contrast-toggle-input"
                data-contrast-toggle
                aria-describedby="tejlg-contrast-toggle-help"
            >
            <label for="tejlg-contrast-toggle-input"><?php esc_html_e('Activer le mode contraste élevé', 'theme-export-jlg'); ?></label>
        </div>
        <p id="tejlg-contrast-toggle-help" class="description"><?php esc_html_e('Renforce les contrastes et simplifie les arrière-plans de cette interface. Votre préférence est retenue sur cet appareil.', 'theme-export-jlg'); ?></p>
    </div>
</div>

<h2><?php esc_html_e('Actions sur le Thème Actif', 'theme-export-jlg'); ?></h2>
<div class="tejlg-cards-container tejlg-cards-container--actions">
    <div class="tejlg-card tejlg-card--primary components-card is-elevated">
        <div class="components-card__body">
            <h3><?php esc_html_e('Exporter le Thème Actif (.zip)', 'theme-export-jlg'); ?></h3>
            <p><?php echo wp_kses_post(__('Crée une archive <code>.zip</code> de votre thème. Idéal pour les sauvegardes ou les migrations.', 'theme-export-jlg')); ?></p>
            <form
                id="tejlg-theme-export-form"
                class="tejlg-theme-export-form"
                method="post"
                action="<?php echo esc_url($export_tab_url); ?>"
                data-export-form
                data-step-form
            >
                <?php wp_nonce_field('tejlg_theme_export_action', 'tejlg_theme_export_nonce'); ?>
                <noscript>
                    <div class="notice notice-warning tejlg-nojs-notice">
                        <p><?php esc_html_e('JavaScript est désactivé : toutes les étapes sont affichées en continu. Complétez les champs puis validez l’export avec le bouton final.', 'theme-export-jlg'); ?></p>
                    </div>
                </noscript>
                <ol class="tejlg-stepper" data-stepper>
                    <li class="tejlg-stepper__step" data-stepper-item>
                        <span class="tejlg-stepper__index" aria-hidden="true">1</span>
                        <span class="tejlg-stepper__label"><?php esc_html_e('Périmètre', 'theme-export-jlg'); ?></span>
                    </li>
                    <li class="tejlg-stepper__step" data-stepper-item>
                        <span class="tejlg-stepper__index" aria-hidden="true">2</span>
                        <span class="tejlg-stepper__label"><?php esc_html_e('Filtres', 'theme-export-jlg'); ?></span>
                    </li>
                    <li class="tejlg-stepper__step" data-stepper-item>
                        <span class="tejlg-stepper__index" aria-hidden="true">3</span>
                        <span class="tejlg-stepper__label"><?php esc_html_e('Validation', 'theme-export-jlg'); ?></span>
                    </li>
                </ol>
                <div class="tejlg-steps" data-steps>
                    <section class="tejlg-step is-active" data-step="0" aria-labelledby="tejlg-export-step-intro">
                        <h4 id="tejlg-export-step-intro" class="tejlg-step__title" tabindex="-1"><?php esc_html_e('Vérification du thème actif', 'theme-export-jlg'); ?></h4>
                        <p class="description"><?php esc_html_e('Passez en revue les informations clés du thème avant de générer l’archive.', 'theme-export-jlg'); ?></p>
                        <dl class="tejlg-step__details">
                            <div>
                                <dt><?php esc_html_e('Nom', 'theme-export-jlg'); ?></dt>
                                <dd><?php echo esc_html($current_theme->get('Name')); ?></dd>
                            </div>
                            <div>
                                <dt><?php esc_html_e('Version', 'theme-export-jlg'); ?></dt>
                                <dd><?php echo esc_html($current_theme->get('Version')); ?></dd>
                            </div>
                            <div>
                                <dt><?php esc_html_e('Dossier', 'theme-export-jlg'); ?></dt>
                                <dd><?php echo esc_html($current_theme->get_stylesheet()); ?></dd>
                            </div>
                        </dl>
                        <div class="tejlg-step__actions">
                            <button type="button" class="button button-primary wp-ui-primary" data-step-next><?php esc_html_e('Définir les filtres', 'theme-export-jlg'); ?></button>
                        </div>
                    </section>
                    <section class="tejlg-step" data-step="1" aria-labelledby="tejlg-export-step-filters">
                        <h4 id="tejlg-export-step-filters" class="tejlg-step__title" tabindex="-1"><?php esc_html_e('Motifs d’exclusion (optionnel)', 'theme-export-jlg'); ?></h4>
                        <p class="description"><?php esc_html_e('Affinez l’export en excluant certains fichiers. Vous pourrez tester vos motifs avant la confirmation.', 'theme-export-jlg'); ?></p>
                        <label for="tejlg_exclusion_patterns" class="tejlg-step__field-label"><?php esc_html_e('Motifs à exclure', 'theme-export-jlg'); ?></label>
                        <textarea
                            name="tejlg_exclusion_patterns"
                            id="tejlg_exclusion_patterns"
                            class="large-text code"
                            rows="4"
                            placeholder="<?php echo esc_attr__('Ex. : assets/*.scss', 'theme-export-jlg'); ?>"
                            aria-describedby="tejlg_exclusion_patterns_description"
                        ><?php echo esc_textarea($exclusion_patterns_value); ?></textarea>
                        <span id="tejlg_exclusion_patterns_description" class="description"><?php esc_html_e('Indiquez un motif par ligne ou séparez-les par des virgules (joker * accepté).', 'theme-export-jlg'); ?></span>
                        <div class="tejlg-pattern-test" data-pattern-test>
                            <div class="tejlg-pattern-test__actions">
                                <button
                                    type="button"
                                    class="button button-secondary wp-ui-secondary"
                                    data-pattern-test-trigger
                                    aria-describedby="tejlg-pattern-test-help"
                                ><?php esc_html_e('Tester les motifs', 'theme-export-jlg'); ?></button>
                                <span class="spinner" aria-hidden="true" data-pattern-test-spinner></span>
                            </div>
                            <p id="tejlg-pattern-test-help" class="description"><?php esc_html_e('Vérifiez les fichiers inclus/exclus avant de lancer un export.', 'theme-export-jlg'); ?></p>
                            <p
                                class="tejlg-pattern-test__invalid"
                                data-pattern-test-invalid
                                role="alert"
                                aria-live="polite"
                                hidden
                            ></p>
                            <div
                                class="tejlg-pattern-test__feedback notice notice-info"
                                data-pattern-test-feedback
                                role="status"
                                aria-live="polite"
                                hidden
                            >
                                <p class="tejlg-pattern-test__summary" data-pattern-test-summary></p>
                                <p class="tejlg-pattern-test__message" data-pattern-test-message></p>
                                <div class="tejlg-pattern-test__lists" data-pattern-test-lists>
                                    <div class="tejlg-pattern-test__list">
                                        <h4><?php esc_html_e('Fichiers inclus', 'theme-export-jlg'); ?></h4>
                                        <ul data-pattern-test-included></ul>
                                    </div>
                                    <div class="tejlg-pattern-test__list">
                                        <h4><?php esc_html_e('Fichiers exclus', 'theme-export-jlg'); ?></h4>
                                        <ul data-pattern-test-excluded></ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tejlg-step__actions">
                            <button type="button" class="button button-secondary wp-ui-secondary" data-step-prev><?php esc_html_e('Retour', 'theme-export-jlg'); ?></button>
                            <button type="button" class="button button-primary wp-ui-primary" data-step-next><?php esc_html_e('Passer à la validation', 'theme-export-jlg'); ?></button>
                        </div>
                    </section>
                    <section class="tejlg-step" data-step="2" aria-labelledby="tejlg-export-step-review">
                        <h4 id="tejlg-export-step-review" class="tejlg-step__title" tabindex="-1"><?php esc_html_e('Résumé et lancement', 'theme-export-jlg'); ?></h4>
                        <p class="description"><?php esc_html_e('Relisez les paramètres et lancez l’export. Vous pourrez suivre la progression en direct.', 'theme-export-jlg'); ?></p>
                        <ul class="tejlg-step-summary">
                            <li>
                                <strong><?php esc_html_e('Thème', 'theme-export-jlg'); ?> :</strong>
                                <span><?php echo esc_html($current_theme->get('Name')); ?></span>
                            </li>
                            <li>
                                <strong><?php esc_html_e('Motifs d’exclusion', 'theme-export-jlg'); ?> :</strong>
                                <span
                                    data-step-summary-exclusions
                                    data-step-summary-empty="<?php echo esc_attr__('Aucun motif', 'theme-export-jlg'); ?>"
                                ><?php echo esc_html($current_exclusion_summary); ?></span>
                            </li>
                        </ul>
                        <div class="tejlg-step__actions">
                            <button type="button" class="button button-secondary wp-ui-secondary" data-step-prev><?php esc_html_e('Retour', 'theme-export-jlg'); ?></button>
                            <div class="tejlg-step__cta">
                                <button type="submit" class="button button-primary wp-ui-primary" data-export-start><?php esc_html_e("Lancer l'export du thème", 'theme-export-jlg'); ?></button>
                                <span class="spinner" aria-hidden="true" data-export-spinner></span>
                            </div>
                        </div>
                    </section>
                </div>
                <div
                    class="tejlg-theme-export-feedback notice notice-info"
                    data-export-feedback
                    hidden
                    role="region"
                    aria-live="polite"
                    aria-atomic="false"
                    aria-labelledby="tejlg-theme-export-status"
                >
                    <p
                        id="tejlg-theme-export-status"
                        class="tejlg-theme-export-status"
                        role="status"
                        data-export-status-text
                    ><?php esc_html_e('En attente de démarrage…', 'theme-export-jlg'); ?></p>
                    <progress
                        value="0"
                        max="100"
                        aria-labelledby="tejlg-theme-export-status"
                        data-export-progress-bar
                    ></progress>
                    <p class="description" data-export-message aria-live="polite"></p>
                    <p class="description tejlg-export-feedback__hint" data-export-guidance hidden aria-live="polite"></p>
                    <div class="tejlg-job-meta" data-export-job-meta hidden>
                        <p class="tejlg-job-meta__title" data-export-job-title><?php esc_html_e('Diagnostic de la tâche', 'theme-export-jlg'); ?></p>
                        <dl class="tejlg-job-meta__grid">
                            <div class="tejlg-job-meta__row">
                                <dt><?php esc_html_e('Identifiant de la tâche', 'theme-export-jlg'); ?></dt>
                                <dd>
                                    <code data-export-job-id></code>
                                    <button type="button" class="button-link tejlg-job-meta__copy" data-export-job-copy>
                                        <?php esc_html_e('Copier l’ID', 'theme-export-jlg'); ?>
                                    </button>
                                </dd>
                            </div>
                            <div class="tejlg-job-meta__row">
                                <dt><?php esc_html_e('Statut courant', 'theme-export-jlg'); ?></dt>
                                <dd data-export-job-status></dd>
                            </div>
                            <div class="tejlg-job-meta__row">
                                <dt><?php esc_html_e('Code d’erreur', 'theme-export-jlg'); ?></dt>
                                <dd data-export-job-code></dd>
                            </div>
                            <div class="tejlg-job-meta__row">
                                <dt><?php esc_html_e('Dernier message', 'theme-export-jlg'); ?></dt>
                                <dd data-export-job-message></dd>
                            </div>
                        </dl>
                        <p class="tejlg-job-meta__updated" data-export-job-updated></p>
                        <p class="tejlg-job-meta__hint" data-export-job-hint hidden role="status" aria-live="polite"></p>
                    </div>
                    <p>
                        <button type="button" class="button button-secondary wp-ui-secondary" data-export-retry hidden>
                            <?php esc_html_e('Relancer la vérification', 'theme-export-jlg'); ?>
                        </button>
                    </p>
                    <p><button type="button" class="button button-secondary wp-ui-secondary" data-export-cancel hidden><?php esc_html_e("Annuler l'export", 'theme-export-jlg'); ?></button></p>
                    <p><a href="#" class="button button-secondary wp-ui-secondary" data-export-download hidden target="_blank" rel="noopener"><?php esc_html_e("Télécharger l'archive ZIP", 'theme-export-jlg'); ?></a></p>
                </div>
            </form>
        </div>
    </div>
    </div>
</div>

<details class="tejlg-collapsible" id="tejlg-schedule-panel" data-tejlg-persist="panel"<?php echo $schedule_section_open ? ' open' : ''; ?>>
    <summary class="tejlg-collapsible__summary">
        <span class="tejlg-collapsible__title"><?php esc_html_e('Planification & alertes', 'theme-export-jlg'); ?></span>
        <span class="tejlg-collapsible__description"><?php echo esc_html($schedule_summary_description); ?></span>
    </summary>
    <div class="tejlg-collapsible__content">
        <div class="tejlg-card components-card is-elevated">
            <div class="components-card__body">
                <h3><?php esc_html_e('Planification des exports de thème', 'theme-export-jlg'); ?></h3>
                <p><?php esc_html_e('Automatisez la génération d’archives ZIP du thème actif et contrôlez leur conservation.', 'theme-export-jlg'); ?></p>
                <form method="post" action="<?php echo esc_url($export_tab_url); ?>">
                    <?php wp_nonce_field('tejlg_schedule_settings_action', 'tejlg_schedule_settings_nonce'); ?>
                    <div class="tejlg-schedule-grid">
                        <div class="tejlg-card components-card is-elevated tejlg-schedule-card">
                            <div class="components-card__body">
                                <fieldset class="tejlg-schedule-card__fieldset">
                                    <legend class="tejlg-schedule-card__legend"><?php esc_html_e('Cadence & rétention', 'theme-export-jlg'); ?></legend>
                                    <p class="tejlg-schedule-card__summary"><?php esc_html_e('Définissez la fréquence des exports automatisés et la durée de conservation des archives.', 'theme-export-jlg'); ?></p>
                                    <div class="tejlg-schedule-card__fields">
                                        <div class="tejlg-field">
                                            <label for="tejlg_schedule_frequency"><?php esc_html_e('Fréquence', 'theme-export-jlg'); ?></label>
                                            <select name="tejlg_schedule_frequency" id="tejlg_schedule_frequency">
                                                <?php foreach ($schedule_frequencies as $frequency_value => $frequency_label) : ?>
                                                    <option value="<?php echo esc_attr($frequency_value); ?>" <?php selected($schedule_frequency_value, $frequency_value); ?>>
                                                        <?php echo esc_html($frequency_label); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (!empty($schedule_next_run)) : ?>
                                                <p class="description">
                                                    <?php
                                                    $next_run_format = trim(get_option('date_format', 'Y-m-d') . ' ' . get_option('time_format', 'H:i'));
                                                    if (function_exists('wp_date')) {
                                                        $next_run_label = wp_date($next_run_format, (int) $schedule_next_run);
                                                    } else {
                                                        $next_run_label = date_i18n($next_run_format, (int) $schedule_next_run);
                                                    }
                                                    printf(
                                                        esc_html__('Prochaine exécution prévue : %s', 'theme-export-jlg'),
                                                        esc_html($next_run_label)
                                                    );
                                                    ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="tejlg-field">
                                            <label for="tejlg_schedule_run_time"><?php esc_html_e("Heure d’exécution", 'theme-export-jlg'); ?></label>
                                            <input
                                                type="time"
                                                id="tejlg_schedule_run_time"
                                                name="tejlg_schedule_run_time"
                                                value="<?php echo esc_attr($schedule_run_time_value); ?>"
                                                step="60"
                                            >
                                            <p class="description">
                                                <?php
                                                printf(
                                                    esc_html__('Fuseau horaire du site : %s', 'theme-export-jlg'),
                                                    esc_html($site_timezone_string)
                                                );
                                                ?>
                                            </p>
                                        </div>
                                        <div class="tejlg-field">
                                            <label for="tejlg_schedule_retention"><?php esc_html_e('Rétention (jours)', 'theme-export-jlg'); ?></label>
                                            <input
                                                type="number"
                                                min="0"
                                                class="small-text"
                                                name="tejlg_schedule_retention"
                                                id="tejlg_schedule_retention"
                                                value="<?php echo esc_attr($schedule_retention_value); ?>"
                                            >
                                            <p class="description"><?php esc_html_e('Durée de conservation des archives stockées dans la médiathèque. Indiquez 0 pour désactiver le nettoyage automatique.', 'theme-export-jlg'); ?></p>
                                        </div>
                                    </div>
                                </fieldset>
                            </div>
                        </div>
                        <div class="tejlg-card components-card is-elevated tejlg-schedule-card">
                            <div class="components-card__body">
                                <fieldset class="tejlg-schedule-card__fieldset">
                                    <legend class="tejlg-schedule-card__legend"><?php esc_html_e('Affiner les archives planifiées', 'theme-export-jlg'); ?></legend>
                                    <p class="tejlg-schedule-card__summary"><?php esc_html_e('Excluez les fichiers temporaires ou volumineux pour accélérer les exports programmés.', 'theme-export-jlg'); ?></p>
                                    <label for="tejlg_schedule_exclusions"><?php esc_html_e('Motifs d’exclusion', 'theme-export-jlg'); ?></label>
                                    <textarea
                                        name="tejlg_schedule_exclusions"
                                        id="tejlg_schedule_exclusions"
                                        class="large-text code"
                                        rows="4"
                                        placeholder="<?php echo esc_attr__('Ex. : assets/*.scss', 'theme-export-jlg'); ?>"
                                    ><?php echo esc_textarea($schedule_exclusions_value); ?></textarea>
                                    <p class="description"><?php esc_html_e('Un motif par ligne ou séparé par des virgules. Ces exclusions s’appliquent uniquement aux exports planifiés.', 'theme-export-jlg'); ?></p>
                                </fieldset>
                            </div>
                        </div>
                        <div class="tejlg-card components-card is-elevated tejlg-schedule-card">
                            <div class="components-card__body">
                                <fieldset class="tejlg-schedule-card__fieldset">
                                    <legend class="tejlg-schedule-card__legend"><?php esc_html_e('Notifications & alertes', 'theme-export-jlg'); ?></legend>
                                    <div class="tejlg-chip-field" data-tejlg-recipient-chips data-recipient-input="tejlg_notifications_emails">
                                        <div class="tejlg-chip-field__header">
                                            <span class="tejlg-chip-field__title"><?php esc_html_e('Destinataires actifs', 'theme-export-jlg'); ?></span>
                                            <span
                                                class="tejlg-chip-field__count"
                                                data-chip-count
                                                data-label-singular="<?php echo esc_attr__('%d destinataire', 'theme-export-jlg'); ?>"
                                                data-label-plural="<?php echo esc_attr__('%d destinataires', 'theme-export-jlg'); ?>"
                                            ><?php echo esc_html($notification_recipient_count_label); ?></span>
                                        </div>
                                        <div class="tejlg-chip-field__list" data-chip-list role="list" aria-live="polite" aria-atomic="true">
                                            <?php foreach ($notification_recipient_list as $recipient) : ?>
                                                <span class="tejlg-chip" role="listitem"><?php echo esc_html($recipient); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <p class="tejlg-chip-field__empty" data-chip-empty<?php echo $notification_recipient_count > 0 ? ' hidden' : ''; ?>><?php esc_html_e('Aucun destinataire ajouté', 'theme-export-jlg'); ?></p>
                                    </div>
                                    <label for="tejlg_notifications_emails"><?php esc_html_e('Destinataires des alertes', 'theme-export-jlg'); ?></label>
                                    <textarea
                                        name="tejlg_notifications_emails"
                                        id="tejlg_notifications_emails"
                                        class="large-text code"
                                        rows="3"
                                        placeholder="<?php echo esc_attr__('admin@example.com', 'theme-export-jlg'); ?>"
                                    ><?php echo esc_textarea($notification_recipients_value); ?></textarea>
                                    <p class="description"><?php esc_html_e('Une adresse par ligne (ou séparée par des virgules). L’e-mail administrateur du site est utilisé par défaut si cette liste est vide.', 'theme-export-jlg'); ?></p>
                                    <div class="tejlg-checkbox-grid">
                                        <span class="tejlg-checkbox-grid__label"><?php esc_html_e('Évènements surveillés', 'theme-export-jlg'); ?></span>
                                        <div class="tejlg-checkbox-grid__items">
                                            <label class="tejlg-checkbox-grid__item">
                                                <input type="checkbox" name="tejlg_notifications_events[]" value="error" <?php checked(!empty($notification_enabled_lookup['error'])); ?>>
                                                <span><?php esc_html_e('Échecs', 'theme-export-jlg'); ?></span>
                                            </label>
                                            <label class="tejlg-checkbox-grid__item">
                                                <input type="checkbox" name="tejlg_notifications_events[]" value="warning" <?php checked(!empty($notification_enabled_lookup['warning'])); ?>>
                                                <span><?php esc_html_e('Annulations / avertissements', 'theme-export-jlg'); ?></span>
                                            </label>
                                            <label class="tejlg-checkbox-grid__item">
                                                <input type="checkbox" name="tejlg_notifications_events[]" value="success" <?php checked(!empty($notification_enabled_lookup['success'])); ?>>
                                                <span><?php esc_html_e('Succès', 'theme-export-jlg'); ?></span>
                                            </label>
                                            <label class="tejlg-checkbox-grid__item">
                                                <input type="checkbox" name="tejlg_notifications_events[]" value="info" <?php checked(!empty($notification_enabled_lookup['info'])); ?>>
                                                <span><?php esc_html_e('Informations', 'theme-export-jlg'); ?></span>
                                            </label>
                                        </div>
                                    </div>
                                    <p class="description"><?php esc_html_e('Les notifications s’appliquent aux exports manuels et WP-CLI. Utilisez les filtres PHP pour inclure les exports planifiés si nécessaire.', 'theme-export-jlg'); ?></p>
                                </fieldset>
                            </div>
                        </div>
                    </div>
                    <div class="tejlg-schedule-actions">
                        <button type="submit" class="button button-secondary wp-ui-secondary"><?php esc_html_e('Enregistrer la planification', 'theme-export-jlg'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</details>

<details class="tejlg-collapsible tejlg-collapsible--grid" id="tejlg-advanced-tools" data-tejlg-persist="panel">
    <summary class="tejlg-collapsible__summary">
        <span class="tejlg-collapsible__title"><?php esc_html_e('Exports complémentaires & outils avancés', 'theme-export-jlg'); ?></span>
        <span class="tejlg-collapsible__description"><?php esc_html_e('Compositions, styles globaux et création de thème enfant.', 'theme-export-jlg'); ?></span>
    </summary>
    <div class="tejlg-collapsible__content">
        <div class="tejlg-cards-container tejlg-cards-container--secondary">
            <div class="tejlg-card components-card is-elevated">
                <div class="components-card__body">
                    <h3><?php esc_html_e('Exporter les Compositions (.json)', 'theme-export-jlg'); ?></h3>
                    <p><?php echo wp_kses_post(__('Générez un fichier <code>.json</code> contenant vos compositions.', 'theme-export-jlg')); ?></p>
                    <form method="post" action="">
                        <?php wp_nonce_field('tejlg_export_action', 'tejlg_nonce'); ?>
                        <p><label><input type="checkbox" name="export_portable" value="1" <?php checked($portable_mode_enabled); ?>> <strong><?php esc_html_e('Export portable', 'theme-export-jlg'); ?></strong> <?php esc_html_e('(compatibilité maximale)', 'theme-export-jlg'); ?></label></p>
                        <p><button type="submit" name="tejlg_export_patterns" class="button button-primary wp-ui-primary"><?php esc_html_e('Exporter TOUTES les compositions', 'theme-export-jlg'); ?></button></p>
                    </form>
                    <p>
                        <a href="<?php echo esc_url($select_patterns_url); ?>" class="button wp-ui-secondary"><?php esc_html_e('Exporter une sélection...', 'theme-export-jlg'); ?></a>
                    </p>
                </div>
            </div>
            <div class="tejlg-card components-card is-elevated">
                <div class="components-card__body">
                    <h3><?php esc_html_e('Exporter les Styles Globaux (.json)', 'theme-export-jlg'); ?></h3>
                    <p><?php echo wp_kses_post(__('Téléchargez les réglages globaux pour répliquer la configuration <code>theme.json</code>.', 'theme-export-jlg')); ?></p>
                    <form method="post" action="<?php echo esc_url($export_tab_url); ?>">
                        <?php wp_nonce_field('tejlg_export_global_styles_action', 'tejlg_export_global_styles_nonce'); ?>
                        <p><button type="submit" name="tejlg_export_global_styles" class="button button-secondary wp-ui-secondary"><?php esc_html_e('Exporter les styles globaux', 'theme-export-jlg'); ?></button></p>
                    </form>
                </div>
            </div>
            <div class="tejlg-card components-card is-elevated">
                <div class="components-card__body">
                    <h3><?php esc_html_e('Créer un Thème Enfant', 'theme-export-jlg'); ?></h3>
                    <p><?php echo wp_kses_post(__('Générez un thème enfant basé sur votre thème actuel. Saisissez un nom personnalisé.', 'theme-export-jlg')); ?></p>
                    <form method="post" action="<?php echo esc_url($export_tab_url); ?>">
                        <?php wp_nonce_field('tejlg_create_child_action', 'tejlg_create_child_nonce'); ?>
                        <p>
                            <label for="child_theme_name"><?php esc_html_e('Nom du thème enfant :', 'theme-export-jlg'); ?></label>
                            <input type="text" name="child_theme_name" id="child_theme_name" class="regular-text" value="<?php echo esc_attr($child_theme_value); ?>" placeholder="<?php echo esc_attr(wp_get_theme()->get('Name') . ' ' . __('Enfant', 'theme-export-jlg')); ?>" required>
                        </p>
                        <p><button type="submit" name="tejlg_create_child" class="button button-primary wp-ui-primary"><?php esc_html_e('Créer le Thème Enfant', 'theme-export-jlg'); ?></button></p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</details>

<details class="tejlg-collapsible" id="tejlg-history-panel" data-tejlg-persist="panel"<?php echo $history_section_open ? ' open' : ''; ?>>
    <summary class="tejlg-collapsible__summary">
        <span class="tejlg-collapsible__title"><?php esc_html_e('Historique des exports', 'theme-export-jlg'); ?></span>
        <span class="tejlg-collapsible__description"><?php echo esc_html($history_summary_inline); ?></span>
    </summary>
    <div class="tejlg-collapsible__content">
        <div class="tejlg-card components-card is-elevated">
            <div class="components-card__body">
                <form method="get" class="tejlg-history-filters" aria-label="<?php esc_attr_e('Filtres de l\'historique d\'export', 'theme-export-jlg'); ?>">
                    <input type="hidden" name="page" value="<?php echo esc_attr($history_base_args['page']); ?>">
                    <input type="hidden" name="tab" value="<?php echo esc_attr($history_base_args['tab']); ?>">
                    <div class="tejlg-history-filters__group">
                        <label for="tejlg-history-result"><?php esc_html_e('Statut', 'theme-export-jlg'); ?></label>
                        <select name="history_result" id="tejlg-history-result">
                            <option value=""><?php esc_html_e('Tous les statuts', 'theme-export-jlg'); ?></option>
                            <?php foreach ($available_history_results as $result_key) :
                                $result_label = isset($history_result_labels[$result_key])
                                    ? $history_result_labels[$result_key]
                                    : ucfirst($result_key);
                            ?>
                                <option value="<?php echo esc_attr($result_key); ?>" <?php selected($history_selected_filters['result'], $result_key); ?>>
                                    <?php echo esc_html($result_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="tejlg-history-filters__group">
                        <label for="tejlg-history-origin"><?php esc_html_e('Origine', 'theme-export-jlg'); ?></label>
                        <select name="history_origin" id="tejlg-history-origin">
                            <option value=""><?php esc_html_e('Toutes les origines', 'theme-export-jlg'); ?></option>
                            <?php foreach ($available_history_origins as $origin_key) :
                                $origin_label = isset($history_origin_labels[$origin_key])
                                    ? $history_origin_labels[$origin_key]
                                    : ucfirst($origin_key);
                            ?>
                                <option value="<?php echo esc_attr($origin_key); ?>" <?php selected($history_selected_filters['origin'], $origin_key); ?>>
                                    <?php echo esc_html($origin_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="tejlg-history-filters__group">
                        <label for="tejlg-history-orderby"><?php esc_html_e('Trier par', 'theme-export-jlg'); ?></label>
                        <select name="history_orderby" id="tejlg-history-orderby">
                            <option value="timestamp" <?php selected($history_selected_filters['orderby'], 'timestamp'); ?>><?php esc_html_e('Date', 'theme-export-jlg'); ?></option>
                            <option value="duration" <?php selected($history_selected_filters['orderby'], 'duration'); ?>><?php esc_html_e('Durée', 'theme-export-jlg'); ?></option>
                            <option value="zip_file_size" <?php selected($history_selected_filters['orderby'], 'zip_file_size'); ?>><?php esc_html_e('Taille', 'theme-export-jlg'); ?></option>
                        </select>
                    </div>
                    <div class="tejlg-history-filters__group">
                        <label for="tejlg-history-order"><?php esc_html_e('Ordre', 'theme-export-jlg'); ?></label>
                        <select name="history_order" id="tejlg-history-order">
                            <option value="desc" <?php selected($history_selected_filters['order'], 'desc'); ?>><?php esc_html_e('Décroissant', 'theme-export-jlg'); ?></option>
                            <option value="asc" <?php selected($history_selected_filters['order'], 'asc'); ?>><?php esc_html_e('Croissant', 'theme-export-jlg'); ?></option>
                        </select>
                    </div>
                    <div class="tejlg-history-filters__actions">
                        <button type="submit" class="button button-secondary wp-ui-secondary"><?php esc_html_e('Filtrer', 'theme-export-jlg'); ?></button>
                        <a class="button button-link" href="<?php echo esc_url(add_query_arg($history_base_args, admin_url('admin.php'))); ?>"><?php esc_html_e('Réinitialiser', 'theme-export-jlg'); ?></a>
                    </div>
                </form>
                <p class="tejlg-history-summary">
                    <?php
                    printf(
                        /* translators: 1: number of displayed exports, 2: total number of exports recorded */
                        esc_html__('Affichage de %1$s export(s) sur %2$s.', 'theme-export-jlg'),
                        esc_html($history_total_filtered_label),
                        esc_html($history_total_all_label)
                    );
                    ?>
                </p>
                <?php if (!empty($history_entries)) : ?>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e('Tâche', 'theme-export-jlg'); ?></th>
                                <th scope="col"><?php esc_html_e('Utilisateur', 'theme-export-jlg'); ?></th>
                                <th scope="col"><?php esc_html_e('Date', 'theme-export-jlg'); ?></th>
                                <th scope="col"><?php esc_html_e('Durée', 'theme-export-jlg'); ?></th>
                                <th scope="col"><?php esc_html_e('Taille', 'theme-export-jlg'); ?></th>
                                <th scope="col"><?php esc_html_e('Exclusions', 'theme-export-jlg'); ?></th>
                                <th scope="col"><?php esc_html_e('Origine', 'theme-export-jlg'); ?></th>
                                <th scope="col"><?php esc_html_e('Statut', 'theme-export-jlg'); ?></th>
                                <th scope="col"><?php esc_html_e('Téléchargement', 'theme-export-jlg'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history_entries as $entry) :
                                $job_id   = isset($entry['job_id']) ? (string) $entry['job_id'] : '';
                                $file_name = isset($entry['zip_file_name']) && '' !== $entry['zip_file_name'] ? (string) $entry['zip_file_name'] : __('Archive ZIP', 'theme-export-jlg');
                                $user_name = isset($entry['user_name']) ? (string) $entry['user_name'] : '';
                                $user_id   = isset($entry['user_id']) ? (int) $entry['user_id'] : 0;

                                if ('' === $user_name) {
                                    $user_name = $user_id > 0
                                        ? sprintf(__('Utilisateur #%d', 'theme-export-jlg'), $user_id)
                                        : __('Système', 'theme-export-jlg');
                                }

                                $timestamp = isset($entry['timestamp']) ? (int) $entry['timestamp'] : 0;
                                $formatted_date = '';

                                if ($timestamp > 0) {
                                    if (function_exists('wp_date')) {
                                        $formatted_date = wp_date($datetime_format, $timestamp);
                                    } else {
                                        $formatted_date = date_i18n($datetime_format, $timestamp);
                                    }
                                }

                                $duration_seconds = isset($entry['duration']) ? (int) $entry['duration'] : 0;
                                $duration_label   = __('Instantané', 'theme-export-jlg');

                                if ($duration_seconds > 0) {
                                    $duration_parts = [];

                                    $hours = (int) floor($duration_seconds / HOUR_IN_SECONDS);
                                    if ($hours > 0) {
                                        $duration_parts[] = sprintf(
                                            _n('%d heure', '%d heures', $hours, 'theme-export-jlg'),
                                            $hours
                                        );
                                    }

                                    $remaining = $duration_seconds % HOUR_IN_SECONDS;
                                    $minutes   = (int) floor($remaining / MINUTE_IN_SECONDS);
                                    if ($minutes > 0) {
                                        $duration_parts[] = sprintf(
                                            _n('%d minute', '%d minutes', $minutes, 'theme-export-jlg'),
                                            $minutes
                                        );
                                    }

                                    $seconds = $remaining % MINUTE_IN_SECONDS;
                                    if ($seconds > 0 || empty($duration_parts)) {
                                        $duration_parts[] = sprintf(
                                            _n('%d seconde', '%d secondes', $seconds, 'theme-export-jlg'),
                                            $seconds
                                        );
                                    }

                                    $duration_label = implode(' ', $duration_parts);
                                }

                                $size_bytes = isset($entry['zip_file_size']) ? (int) $entry['zip_file_size'] : 0;
                                $size_label = $size_bytes > 0 ? size_format($size_bytes, 2) : __('Inconnue', 'theme-export-jlg');

                                $exclusions = isset($entry['exclusions']) ? (array) $entry['exclusions'] : [];
                                $exclusions_clean = array_map('sanitize_text_field', $exclusions);
                                $exclusions_label = !empty($exclusions_clean)
                                    ? implode(', ', $exclusions_clean)
                                    : __('Aucune', 'theme-export-jlg');

                                $download_url = isset($entry['persistent_url']) ? esc_url($entry['persistent_url']) : '';

                                $origin_key = isset($entry['origin']) ? (string) $entry['origin'] : '';
                                $origin_label = isset($history_origin_labels[$origin_key])
                                    ? $history_origin_labels[$origin_key]
                                    : ($origin_key ? ucfirst($origin_key) : __('Non défini', 'theme-export-jlg'));

                                $context_label = isset($entry['context']) ? (string) $entry['context'] : '';

                                $status_key = isset($entry['result']) ? (string) $entry['result'] : '';
                                $status_label = isset($history_result_labels[$status_key])
                                    ? $history_result_labels[$status_key]
                                    : (isset($entry['status']) && '' !== $entry['status']
                                        ? (string) $entry['status']
                                        : __('Inconnu', 'theme-export-jlg'));

                                $status_message = isset($entry['status_message']) ? (string) $entry['status_message'] : '';
                                $status_css = '' !== $status_key
                                    ? 'tejlg-history-status--' . sanitize_html_class($status_key)
                                    : 'tejlg-history-status--neutral';

                            ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($file_name); ?></strong><br>
                                        <code><?php echo esc_html($job_id); ?></code>
                                    </td>
                                    <td><?php echo esc_html($user_name); ?></td>
                                    <td><?php echo esc_html($formatted_date); ?></td>
                                    <td><?php echo esc_html($duration_label); ?></td>
                                    <td><?php echo esc_html($size_label); ?></td>
                                    <td><?php echo esc_html($exclusions_label); ?></td>
                                    <td>
                                        <span class="tejlg-history-origin"><?php echo esc_html($origin_label); ?></span>
                                        <?php if ('' !== $context_label && $context_label !== $origin_label) : ?>
                                            <span class="tejlg-history-origin__context"><?php echo esc_html($context_label); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="tejlg-history-status <?php echo esc_attr($status_css); ?>"><?php echo esc_html($status_label); ?></span>
                                        <?php if ('' !== $status_message) : ?>
                                            <span class="tejlg-history-status__message"><?php echo esc_html($status_message); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ('' !== $download_url) : ?>
                                            <a href="<?php echo esc_url($download_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Télécharger', 'theme-export-jlg'); ?></a>
                                        <?php else : ?>
                                            <span aria-hidden="true">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (!empty($history_pagination_links)) : ?>
                        <div class="tablenav bottom">
                            <div class="tablenav-pages">
                                <?php echo wp_kses_post(implode(' ', $history_pagination_links)); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else : ?>
                    <p><?php esc_html_e("Aucun export n'a encore été enregistré.", 'theme-export-jlg'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</details>

<?php
/** @var int    $metrics_icon_size */
/** @var int    $metrics_icon_min */
/** @var int    $metrics_icon_max */
/** @var string $zip_status */
/** @var string $mbstring_status */
/** @var string $cron_status */
?>
<div id="tejlg-section-debug" class="tejlg-section-anchor" tabindex="-1"></div>
<div class="tejlg-card components-card is-elevated">
    <div class="components-card__body">
        <form method="post" action="" class="metrics-settings-form" novalidate>
            <?php wp_nonce_field('tejlg_metrics_settings_action', 'tejlg_metrics_settings_nonce'); ?>
            <label for="tejlg_metrics_icon_size">
                <?php esc_html_e('Taille des icônes des métriques (px) :', 'theme-export-jlg'); ?>
            </label>
            <input
                type="number"
                id="tejlg_metrics_icon_size"
                name="tejlg_metrics_icon_size"
                min="<?php echo esc_attr($metrics_icon_min); ?>"
                max="<?php echo esc_attr($metrics_icon_max); ?>"
                step="1"
                value="<?php echo esc_attr($metrics_icon_size); ?>"
                aria-describedby="tejlg-metrics-icon-size-description"
            >
            <button type="submit" class="button button-secondary wp-ui-secondary">
                <?php esc_html_e('Enregistrer', 'theme-export-jlg'); ?>
            </button>
            <p id="tejlg-metrics-icon-size-description" class="description">
                <?php esc_html_e('La barre de menu des métriques utilisera cette taille pour aligner les icônes.', 'theme-export-jlg'); ?>
            </p>
        </form>
    </div>
</div>

<?php include __DIR__ . '/quick-actions.php'; ?>
<div class="tejlg-card components-card is-elevated">
    <div class="components-card__body">
        <form method="post" action="" class="debug-report-download-form">
            <?php wp_nonce_field('tejlg_debug_download_report_action', 'tejlg_debug_download_report_nonce'); ?>
            <input type="hidden" name="tejlg_debug_download_report" value="1">
            <button type="submit" class="button button-primary wp-ui-primary">
                <?php esc_html_e('Télécharger le rapport', 'theme-export-jlg'); ?>
            </button>
            <p class="description">
                <?php esc_html_e('Génère un rapport JSON compressé contenant les informations listées ci-dessous.', 'theme-export-jlg'); ?>
            </p>
        </form>
    </div>
</div>
<div class="tejlg-card components-card is-elevated">
    <div class="components-card__body">
        <div class="metrics-badge" role="group" aria-label="<?php esc_attr_e('Indicateurs de performance', 'theme-export-jlg'); ?>">
            <div class="metric metric-fps">
                <span class="metric-icon metric-icon-fps dashicons dashicons-dashboard" aria-hidden="true"></span>
                <span class="metric-label"><?php esc_html_e('FPS', 'theme-export-jlg'); ?></span>
                <span class="metric-value" id="tejlg-metric-fps">--</span>
            </div>
            <div class="metric metric-latency">
                <span class="metric-icon metric-icon-latency dashicons dashicons-clock" aria-hidden="true"></span>
                <span class="metric-label"><?php esc_html_e('Latence', 'theme-export-jlg'); ?></span>
                <span class="metric-value" id="tejlg-metric-latency">--</span>
            </div>
        </div>
    </div>
</div>
<h2><?php esc_html_e('Outils de Débogage', 'theme-export-jlg'); ?></h2>
<p><?php esc_html_e('Ces informations peuvent vous aider à diagnostiquer des problèmes liés à votre configuration ou à vos données.', 'theme-export-jlg'); ?></p>
<div id="debug-accordion" class="tejlg-card components-card is-elevated">
    <div class="components-card__body">
        <div class="accordion-section">
            <button
                type="button"
                class="accordion-section-title"
                id="tejlg-debug-system-info-trigger"
                aria-expanded="false"
                aria-controls="tejlg-debug-system-info-content"
            >
                <?php esc_html_e('Informations Système & WordPress', 'theme-export-jlg'); ?>
            </button>
            <div
                class="accordion-section-content"
                id="tejlg-debug-system-info-content"
                role="region"
                aria-labelledby="tejlg-debug-system-info-trigger"
                hidden
                aria-hidden="true"
            >
                <div class="tejlg-table-scroll">
                    <table class="widefat striped">
                        <tbody>
                            <tr>
                                <td><?php esc_html_e('Version de WordPress', 'theme-export-jlg'); ?></td>
                                <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e('Version de PHP', 'theme-export-jlg'); ?></td>
                                <td><?php echo esc_html(PHP_VERSION); ?></td>
                            </tr>
                            <tr>
                                <td><?php echo wp_kses_post(__('Classe <code>ZipArchive</code> disponible', 'theme-export-jlg')); ?></td>
                                <td><?php echo wp_kses_post($zip_status); ?></td>
                            </tr>
                            <tr>
                                <td><?php echo wp_kses_post(__('Extension PHP <code>mbstring</code>', 'theme-export-jlg')); ?></td>
                                <td><?php echo wp_kses_post($mbstring_status); ?></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e('Statut WP-Cron', 'theme-export-jlg'); ?></td>
                                <td><?php echo wp_kses_post($cron_status); ?></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e('Limite de mémoire WP', 'theme-export-jlg'); ?></td>
                                <td><?php echo esc_html(WP_MEMORY_LIMIT); ?></td>
                            </tr>
                            <tr>
                                <td><?php esc_html_e('Taille max. d\'upload', 'theme-export-jlg'); ?></td>
                                <td><?php echo esc_html(ini_get('upload_max_filesize')); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="accordion-section">
            <button
                type="button"
                class="accordion-section-title"
                id="tejlg-debug-custom-patterns-trigger"
                aria-expanded="false"
                aria-controls="tejlg-debug-custom-patterns-content"
            >
                <?php esc_html_e('Compositions personnalisées enregistrées', 'theme-export-jlg'); ?>
            </button>
            <div
                class="accordion-section-content"
                id="tejlg-debug-custom-patterns-content"
                role="region"
                aria-labelledby="tejlg-debug-custom-patterns-trigger"
                hidden
                aria-hidden="true"
            >
            <?php if (empty($custom_patterns)) : ?>
                <p><?php esc_html_e('Aucune composition personnalisée n\'a été trouvée.', 'theme-export-jlg'); ?></p>
            <?php else : ?>
                <p
                    id="tejlg-custom-patterns-count"
                    aria-live="polite"
                    class="tejlg-custom-patterns-summary"
                >
                    <?php
                    printf(
                        esc_html(
                            _n(
                                '%d composition personnalisée trouvée :',
                                '%d compositions personnalisées trouvées :',
                                $custom_patterns_count,
                                'theme-export-jlg'
                            )
                        ),
                        (int) $custom_patterns_count
                    );
                    ?>
                </p>
                <ul class="tejlg-custom-patterns-list" role="list" aria-labelledby="tejlg-custom-patterns-count">
                    <?php
                    $date_format = trim(get_option('date_format', 'Y-m-d') . ' ' . get_option('time_format', 'H:i'));
                    foreach ($custom_patterns as $pattern) :
                        $title = isset($pattern['title']) ? (string) $pattern['title'] : '';
                        $slug  = isset($pattern['slug']) ? (string) $pattern['slug'] : '';
                        $is_global = !empty($pattern['is_global']);
                        $is_owned  = !empty($pattern['is_owned']);
                        $type_label = $is_global
                            ? esc_html__('Global', 'theme-export-jlg')
                            : ($is_owned ? esc_html__('Personnel', 'theme-export-jlg') : esc_html__('Partagé', 'theme-export-jlg'));

                        $modified_timestamp = isset($pattern['modified_gmt']) ? (int) $pattern['modified_gmt'] : 0;
                        $modified_label     = '';
                        $modified_attr      = '';

                        if ($modified_timestamp > 0) {
                            if (function_exists('wp_date')) {
                                $modified_label = wp_date($date_format, $modified_timestamp);
                            } else {
                                $modified_label = date_i18n($date_format, $modified_timestamp);
                            }

                            $modified_attr = gmdate('c', $modified_timestamp);
                        }
                    ?>
                        <li class="tejlg-custom-patterns-list__item">
                            <span class="tejlg-custom-patterns-list__title"><?php echo esc_html($title); ?></span>
                            <?php if ('' !== $slug) : ?>
                                <span class="tejlg-custom-patterns-list__slug">
                                    <?php esc_html_e('Slug :', 'theme-export-jlg'); ?>
                                    <code><?php echo esc_html($slug); ?></code>
                                </span>
                            <?php endif; ?>
                            <span class="tejlg-custom-patterns-list__type"><?php echo esc_html($type_label); ?></span>
                            <?php if ('' !== $modified_label) : ?>
                                <span class="tejlg-custom-patterns-list__modified">
                                    <?php esc_html_e('Modifié le :', 'theme-export-jlg'); ?>
                                    <time datetime="<?php echo esc_attr($modified_attr); ?>">
                                        <?php echo esc_html($modified_label); ?>
                                    </time>
                                </span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            </div>
        </div>
    </div>
</div>

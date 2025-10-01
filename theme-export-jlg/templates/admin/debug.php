<?php
/** @var int $metrics_icon_size */
/** @var int $metrics_icon_min */
/** @var int $metrics_icon_max */
/** @var string $metrics_label */
/** @var array $system_rows */
/** @var array $patterns_info */
?>
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
    <button type="submit" class="button button-secondary">
        <?php esc_html_e('Enregistrer', 'theme-export-jlg'); ?>
    </button>
    <p id="tejlg-metrics-icon-size-description" class="description">
        <?php esc_html_e('La barre de menu des métriques utilisera cette taille pour aligner les icônes.', 'theme-export-jlg'); ?>
    </p>
</form>
<div class="metrics-badge" role="group" aria-label="<?php echo esc_attr($metrics_label); ?>">
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
<h2><?php esc_html_e('Outils de Débogage', 'theme-export-jlg'); ?></h2>
<p><?php esc_html_e('Ces informations peuvent vous aider à diagnostiquer des problèmes liés à votre configuration ou à vos données.', 'theme-export-jlg'); ?></p>
<div id="debug-accordion">
    <div class="accordion-section">
        <h3 class="accordion-section-title"><?php esc_html_e('Informations Système & WordPress', 'theme-export-jlg'); ?></h3>
        <div class="accordion-section-content">
            <table class="widefat striped">
                <tbody>
                    <?php foreach ($system_rows as $row): ?>
                        <tr>
                            <td><?php echo $row['label']; ?></td>
                            <td>
                                <?php if (!empty($row['allow_html'])): ?>
                                    <?php echo $row['value']; ?>
                                <?php else: ?>
                                    <?php echo esc_html($row['value']); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="accordion-section">
        <h3 class="accordion-section-title"><?php esc_html_e('Compositions personnalisées enregistrées', 'theme-export-jlg'); ?></h3>
        <div class="accordion-section-content">
            <?php if (!empty($patterns_info['patterns'])): ?>
                <p><?php echo esc_html($patterns_info['message']); ?></p>
                <ul>
                    <?php foreach ($patterns_info['patterns'] as $pattern): ?>
                        <li><strong><?php echo esc_html($pattern['title']); ?></strong> (<?php esc_html_e('Slug :', 'theme-export-jlg'); ?> <code><?php echo esc_html($pattern['slug']); ?></code>)</li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p><?php echo esc_html($patterns_info['message']); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
/** @var int    $metrics_icon_size */
/** @var int    $metrics_icon_min */
/** @var int    $metrics_icon_max */
/** @var string $zip_status */
/** @var string $mbstring_status */
/** @var string $cron_status */
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
<h2><?php esc_html_e('Outils de Débogage', 'theme-export-jlg'); ?></h2>
<p><?php esc_html_e('Ces informations peuvent vous aider à diagnostiquer des problèmes liés à votre configuration ou à vos données.', 'theme-export-jlg'); ?></p>
<div id="debug-accordion">
    <div class="accordion-section">
        <h3 class="accordion-section-title"><?php esc_html_e('Informations Système & WordPress', 'theme-export-jlg'); ?></h3>
        <div class="accordion-section-content">
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
    <div class="accordion-section">
        <h3 class="accordion-section-title"><?php esc_html_e('Compositions personnalisées enregistrées', 'theme-export-jlg'); ?></h3>
        <div class="accordion-section-content">
            <?php
            $current_user_id = get_current_user_id();
            $custom_patterns_query = new WP_Query(
                array(
                    'post_type'      => 'wp_block',
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                    'meta_query'     => array(
                        'relation' => 'OR',
                        array(
                            'key'     => 'wp_block_type',
                            'value'   => 'pattern',
                            'compare' => '=',
                        ),
                        array(
                            'key'     => 'wp_block_type',
                            'compare' => 'NOT EXISTS',
                        ),
                    ),
                )
            );

            $custom_patterns = array();

            if ($custom_patterns_query->have_posts()) {
                foreach ($custom_patterns_query->posts as $pattern_post) {
                    $wp_block_type = get_post_meta($pattern_post->ID, 'wp_block_type', true);

                    if ((int) $pattern_post->post_author === (int) $current_user_id || 'pattern' === $wp_block_type) {
                        $custom_patterns[] = $pattern_post;
                    }
                }
            }

            wp_reset_postdata();

            if (empty($custom_patterns)) {
                echo '<p>' . esc_html__('Aucune composition personnalisée n\'a été trouvée.', 'theme-export-jlg') . '</p>';
            } else {
                $count = count($custom_patterns);
                printf(
                    '<p>%s</p>',
                    esc_html(
                        sprintf(
                            _n('%d composition personnalisée trouvée :', '%d compositions personnalisées trouvées :', $count, 'theme-export-jlg'),
                            $count
                        )
                    )
                );
                echo '<ul>';
                foreach ($custom_patterns as $pattern_post) {
                    printf(
                        '<li><strong>%1$s</strong> (%2$s <code>%3$s</code>)</li>',
                        esc_html(get_the_title($pattern_post)),
                        esc_html__('Slug :', 'theme-export-jlg'),
                        esc_html($pattern_post->post_name)
                    );
                }
                echo '</ul>';
            }
            ?>
        </div>
    </div>
</div>

<?php
/** @var string $page_slug */
/** @var string $child_theme_value */
/** @var string $exclusion_patterns_value */
/** @var bool   $portable_mode_enabled */
/** @var array  $history_entries */
/** @var int    $history_total */
/** @var array  $history_pagination_links */
/** @var int    $history_current_page */
/** @var int    $history_total_pages */
/** @var int    $history_per_page */

$export_tab_url = add_query_arg([
    'page' => $page_slug,
    'tab'  => 'export',
], admin_url('admin.php'));

$select_patterns_url = add_query_arg([
    'page'   => $page_slug,
    'tab'    => 'export',
    'action' => 'select_patterns',
], admin_url('admin.php'));
?>
<h2><?php esc_html_e('Actions sur le Thème Actif', 'theme-export-jlg'); ?></h2>
<div class="tejlg-cards-container">
    <div class="tejlg-card components-card is-elevated">
        <div class="components-card__body">
            <h3><?php esc_html_e('Exporter le Thème Actif (.zip)', 'theme-export-jlg'); ?></h3>
            <p><?php echo wp_kses_post(__('Crée une archive <code>.zip</code> de votre thème. Idéal pour les sauvegardes ou les migrations.', 'theme-export-jlg')); ?></p>
            <form
                id="tejlg-theme-export-form"
                class="tejlg-theme-export-form"
                method="post"
                action="<?php echo esc_url($export_tab_url); ?>"
                data-export-form
            >
                <?php wp_nonce_field('tejlg_theme_export_action', 'tejlg_theme_export_nonce'); ?>
                <p>
                    <label for="tejlg_exclusion_patterns"><?php esc_html_e('Motifs d\'exclusion (optionnel) :', 'theme-export-jlg'); ?></label><br>
                    <textarea
                        name="tejlg_exclusion_patterns"
                        id="tejlg_exclusion_patterns"
                        class="large-text code"
                        rows="4"
                        placeholder="<?php echo esc_attr__('Ex. : assets/*.scss', 'theme-export-jlg'); ?>"
                        aria-describedby="tejlg_exclusion_patterns_description"
                    ><?php echo esc_textarea($exclusion_patterns_value); ?></textarea>
                    <span id="tejlg_exclusion_patterns_description" class="description"><?php esc_html_e('Indiquez un motif par ligne ou séparez-les par des virgules (joker * accepté).', 'theme-export-jlg'); ?></span>
                </p>
                <p class="tejlg-theme-export-actions">
                    <button type="submit" class="button button-primary wp-ui-primary" data-export-start><?php esc_html_e("Lancer l'export du thème", 'theme-export-jlg'); ?></button>
                    <span class="spinner" aria-hidden="true" data-export-spinner></span>
                </p>
                <div class="tejlg-theme-export-feedback notice notice-info" data-export-feedback hidden>
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
                    <p class="description" data-export-message></p>
                    <p><button type="button" class="button button-secondary wp-ui-secondary" data-export-cancel hidden><?php esc_html_e("Annuler l'export", 'theme-export-jlg'); ?></button></p>
                    <p><a href="#" class="button button-secondary wp-ui-secondary" data-export-download hidden target="_blank" rel="noopener"><?php esc_html_e("Télécharger l'archive ZIP", 'theme-export-jlg'); ?></a></p>
                </div>
            </form>
        </div>
    </div>
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

<h2><?php esc_html_e('Historique des exports', 'theme-export-jlg'); ?></h2>
<div class="tejlg-card components-card is-elevated">
    <div class="components-card__body">
        <?php if (!empty($history_entries)) : ?>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Tâche', 'theme-export-jlg'); ?></th>
                        <th scope="col"><?php esc_html_e('Utilisateur', 'theme-export-jlg'); ?></th>
                        <th scope="col"><?php esc_html_e('Date', 'theme-export-jlg'); ?></th>
                        <th scope="col"><?php esc_html_e('Taille', 'theme-export-jlg'); ?></th>
                        <th scope="col"><?php esc_html_e('Exclusions', 'theme-export-jlg'); ?></th>
                        <th scope="col"><?php esc_html_e('Statut', 'theme-export-jlg'); ?></th>
                        <th scope="col"><?php esc_html_e('Téléchargement', 'theme-export-jlg'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $date_format = get_option('date_format', 'Y-m-d');
                    $time_format = get_option('time_format', 'H:i');
                    $datetime_format = trim($date_format . ' ' . $time_format);

                    foreach ($history_entries as $entry) :
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

                        $size_bytes = isset($entry['zip_file_size']) ? (int) $entry['zip_file_size'] : 0;
                        $size_label = $size_bytes > 0 ? size_format($size_bytes, 2) : __('Inconnue', 'theme-export-jlg');

                        $exclusions = isset($entry['exclusions']) ? (array) $entry['exclusions'] : [];
                        $exclusions_clean = array_map('sanitize_text_field', $exclusions);
                        $exclusions_label = !empty($exclusions_clean)
                            ? implode(', ', $exclusions_clean)
                            : __('Aucune', 'theme-export-jlg');

                        $status_label = isset($entry['status']) && '' !== $entry['status']
                            ? (string) $entry['status']
                            : __('Inconnu', 'theme-export-jlg');

                        $download_url = isset($entry['persistent_url']) ? esc_url($entry['persistent_url']) : '';
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($file_name); ?></strong><br>
                                <code><?php echo esc_html($job_id); ?></code>
                            </td>
                            <td><?php echo esc_html($user_name); ?></td>
                            <td><?php echo esc_html($formatted_date); ?></td>
                            <td><?php echo esc_html($size_label); ?></td>
                            <td><?php echo esc_html($exclusions_label); ?></td>
                            <td><?php echo esc_html($status_label); ?></td>
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

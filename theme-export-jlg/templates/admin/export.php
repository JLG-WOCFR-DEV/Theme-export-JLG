<?php
/** @var string $page_slug */
/** @var string $child_theme_value */
/** @var array  $exclusion_presets */
/** @var array  $selected_exclusion_presets */
/** @var string $exclusion_custom_value */
/** @var array  $exclusion_summary */
/** @var bool   $portable_mode_enabled */

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
    <div class="tejlg-card">
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
            <fieldset class="tejlg-exclusion-fieldset">
                <legend><?php esc_html_e("Motifs d'exclusion", 'theme-export-jlg'); ?></legend>
                <p class="description"><?php esc_html_e('Sélectionnez les éléments à exclure de l’archive. Ajoutez des motifs personnalisés si nécessaire.', 'theme-export-jlg'); ?></p>
                <div class="tejlg-exclusion-presets" role="group" aria-label="<?php echo esc_attr__("Motifs d'exclusion prédéfinis", 'theme-export-jlg'); ?>">
                    <?php foreach ($exclusion_presets as $preset_key => $preset_config) :
                        $preset_id = 'tejlg_exclusion_preset_' . sanitize_html_class($preset_key);
                        $is_checked = in_array($preset_key, $selected_exclusion_presets, true);
                        ?>
                        <label for="<?php echo esc_attr($preset_id); ?>" class="tejlg-exclusion-preset">
                            <input
                                type="checkbox"
                                name="tejlg_exclusion_presets[]"
                                id="<?php echo esc_attr($preset_id); ?>"
                                value="<?php echo esc_attr($preset_key); ?>"
                                <?php checked($is_checked); ?>
                                data-exclusion-preset
                            >
                            <span class="tejlg-exclusion-preset-label"><?php echo esc_html($preset_config['label']); ?></span>
                            <?php if (!empty($preset_config['description'])) : ?>
                                <span class="description"><?php echo esc_html($preset_config['description']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($preset_config['patterns'])) : ?>
                                <span class="screen-reader-text"><?php esc_html_e('Motifs exclus :', 'theme-export-jlg'); ?></span>
                                <ul class="tejlg-exclusion-patterns">
                                    <?php foreach ((array) $preset_config['patterns'] as $pattern) : ?>
                                        <li><code><?php echo esc_html((string) $pattern); ?></code></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="tejlg-exclusion-custom">
                    <label for="tejlg_exclusion_custom" class="tejlg-exclusion-custom-label"><?php esc_html_e('Motifs personnalisés', 'theme-export-jlg'); ?></label>
                    <input
                        type="text"
                        name="tejlg_exclusion_custom"
                        id="tejlg_exclusion_custom"
                        class="regular-text"
                        value="<?php echo esc_attr($exclusion_custom_value); ?>"
                        placeholder="<?php echo esc_attr__('Ex. : assets/*.scss, *.map', 'theme-export-jlg'); ?>"
                        data-exclusion-custom
                        aria-describedby="tejlg_exclusion_custom_description"
                    >
                    <p id="tejlg_exclusion_custom_description" class="description"><?php esc_html_e('Séparez plusieurs motifs par une virgule.', 'theme-export-jlg'); ?></p>
                </div>
                <div class="tejlg-exclusion-summary">
                    <strong><?php esc_html_e('Motifs actuellement exclus :', 'theme-export-jlg'); ?></strong>
                    <ul
                        class="tejlg-exclusion-summary-list"
                        data-exclusion-summary
                        data-empty-text="<?php echo esc_attr__('Aucun motif d’exclusion sélectionné.', 'theme-export-jlg'); ?>"
                    >
                        <?php if (empty($exclusion_summary)) : ?>
                            <li class="description" data-summary-empty><?php esc_html_e('Aucun motif d’exclusion sélectionné.', 'theme-export-jlg'); ?></li>
                        <?php else : ?>
                            <?php foreach ($exclusion_summary as $pattern) : ?>
                                <li><code><?php echo esc_html($pattern); ?></code></li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </fieldset>
            <p class="tejlg-theme-export-actions">
                <button type="submit" class="button button-primary" data-export-start><?php esc_html_e("Lancer l'export du thème", 'theme-export-jlg'); ?></button>
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
                <p><a href="#" class="button button-secondary" data-export-download hidden target="_blank" rel="noopener"><?php esc_html_e("Télécharger l'archive ZIP", 'theme-export-jlg'); ?></a></p>
            </div>
        </form>
    </div>
    <div class="tejlg-card">
        <h3><?php esc_html_e('Exporter les Compositions (.json)', 'theme-export-jlg'); ?></h3>
        <p><?php echo wp_kses_post(__('Générez un fichier <code>.json</code> contenant vos compositions.', 'theme-export-jlg')); ?></p>
        <form method="post" action="">
            <?php wp_nonce_field('tejlg_export_action', 'tejlg_nonce'); ?>
            <p><label><input type="checkbox" name="export_portable" value="1" <?php checked($portable_mode_enabled); ?>> <strong><?php esc_html_e('Export portable', 'theme-export-jlg'); ?></strong> <?php esc_html_e('(compatibilité maximale)', 'theme-export-jlg'); ?></label></p>
            <p><button type="submit" name="tejlg_export_patterns" class="button button-primary"><?php esc_html_e('Exporter TOUTES les compositions', 'theme-export-jlg'); ?></button></p>
        </form>
        <p>
            <a href="<?php echo esc_url($select_patterns_url); ?>" class="button"><?php esc_html_e('Exporter une sélection...', 'theme-export-jlg'); ?></a>
        </p>
    </div>
    <div class="tejlg-card">
        <h3><?php esc_html_e('Exporter les Styles Globaux (.json)', 'theme-export-jlg'); ?></h3>
        <p><?php echo wp_kses_post(__('Téléchargez les réglages globaux pour répliquer la configuration <code>theme.json</code>.', 'theme-export-jlg')); ?></p>
        <form method="post" action="<?php echo esc_url($export_tab_url); ?>">
            <?php wp_nonce_field('tejlg_export_global_styles_action', 'tejlg_export_global_styles_nonce'); ?>
            <p><button type="submit" name="tejlg_export_global_styles" class="button button-secondary"><?php esc_html_e('Exporter les styles globaux', 'theme-export-jlg'); ?></button></p>
        </form>
    </div>
    <div class="tejlg-card">
        <h3><?php esc_html_e('Créer un Thème Enfant', 'theme-export-jlg'); ?></h3>
        <p><?php echo wp_kses_post(__('Générez un thème enfant basé sur votre thème actuel. Saisissez un nom personnalisé.', 'theme-export-jlg')); ?></p>
        <form method="post" action="<?php echo esc_url($export_tab_url); ?>">
            <?php wp_nonce_field('tejlg_create_child_action', 'tejlg_create_child_nonce'); ?>
            <p>
                <label for="child_theme_name"><?php esc_html_e('Nom du thème enfant :', 'theme-export-jlg'); ?></label>
                <input type="text" name="child_theme_name" id="child_theme_name" class="regular-text" value="<?php echo esc_attr($child_theme_value); ?>" placeholder="<?php echo esc_attr(wp_get_theme()->get('Name') . ' ' . __('Enfant', 'theme-export-jlg')); ?>" required>
            </p>
            <p><button type="submit" name="tejlg_create_child" class="button button-primary"><?php esc_html_e('Créer le Thème Enfant', 'theme-export-jlg'); ?></button></p>
        </form>
    </div>
</div>

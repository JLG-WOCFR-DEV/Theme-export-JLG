<?php
/** @var string $export_tab_url */
/** @var array $patterns */
/** @var bool $has_patterns */
/** @var string $pagination_links */
?>
<p><a href="<?php echo esc_url($export_tab_url); ?>">&larr; <?php esc_html_e('Retour aux outils principaux', 'theme-export-jlg'); ?></a></p>
<h2><?php esc_html_e('Exporter une sélection de compositions', 'theme-export-jlg'); ?></h2>
<p><?php echo wp_kses_post(__('Cochez les compositions que vous souhaitez inclure dans votre fichier d\'exportation <code>.json</code>.', 'theme-export-jlg')); ?></p>

<?php if (!$has_patterns): ?>
    <p><?php esc_html_e('Aucune composition personnalisée n\'a été trouvée.', 'theme-export-jlg'); ?></p>
<?php else: ?>
    <form method="post" action="">
        <?php wp_nonce_field('tejlg_export_selected_action', 'tejlg_export_selected_nonce'); ?>

        <div class="pattern-selection-list">
            <p class="select-all-wrapper"><label><input type="checkbox" id="select-all-export-patterns"> <strong><?php esc_html_e('Tout sélectionner', 'theme-export-jlg'); ?></strong></label></p>
            <p class="pattern-selection-search">
                <label class="screen-reader-text" for="pattern-search"><?php esc_html_e('Rechercher une composition', 'theme-export-jlg'); ?></label>
                <input type="search" id="pattern-search" placeholder="<?php echo esc_attr__('Rechercher…', 'theme-export-jlg'); ?>" aria-controls="pattern-selection-items">
            </p>
            <ul class="pattern-selection-items" id="pattern-selection-items" aria-live="polite" data-searchable="true">
                <?php foreach ($patterns as $pattern): ?>
                    <li class="pattern-selection-item" data-label="<?php echo esc_attr($pattern['title']); ?>">
                        <label>
                            <input type="checkbox" name="selected_patterns[]" value="<?php echo esc_attr($pattern['id']); ?>">
                            <?php echo esc_html($pattern['title']); ?>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (!empty($pagination_links)): ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php echo wp_kses_post($pagination_links); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <p><label><input type="checkbox" name="export_portable" value="1" checked> <strong><?php esc_html_e('Générer un export "portable"', 'theme-export-jlg'); ?></strong> <?php esc_html_e('(Recommandé pour migrer vers un autre site)', 'theme-export-jlg'); ?></label></p>

        <p><button type="submit" name="tejlg_export_selected_patterns" class="button button-primary button-hero"><?php esc_html_e('Exporter la sélection', 'theme-export-jlg'); ?></button></p>
    </form>
<?php endif; ?>

<?php
/** @var string $page_slug */
/** @var array  $theme_file_info */
/** @var array  $patterns_file_info */
/** @var array  $global_styles_file_info */

$import_tab_url = add_query_arg([
    'page' => $page_slug,
    'tab'  => 'import',
], admin_url('admin.php'));
?>
<h2><?php esc_html_e('Tutoriel : Que pouvez-vous importer ?', 'theme-export-jlg'); ?></h2>
<div class="tejlg-cards-container">
    <div class="tejlg-card components-card is-elevated">
        <div class="components-card__body">
            <h3><?php echo esc_html(sprintf(__('Importer un Thème (%s)', 'theme-export-jlg'), $theme_file_info['display'])); ?></h3>
            <p><?php echo wp_kses_post(sprintf(__('Téléversez une archive %s d\'un thème. Le plugin l\'installera (capacité WordPress « Installer des thèmes » requise). <strong>Attention :</strong> Un thème existant sera remplacé.', 'theme-export-jlg'), $theme_file_info['code'])); ?></p>
            <form id="tejlg-import-theme-form" method="post" action="<?php echo esc_url($import_tab_url); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('tejlg_import_theme_action', 'tejlg_import_theme_nonce'); ?>
                <input type="hidden" name="tejlg_confirm_theme_overwrite" id="tejlg_confirm_theme_overwrite" value="<?php echo esc_attr('0'); ?>">
                <p><label for="theme_zip"><?php echo esc_html(sprintf(__('Fichier du thème (%s) :', 'theme-export-jlg'), $theme_file_info['display'])); ?></label><br><input type="file" id="theme_zip" name="theme_zip" accept="<?php echo esc_attr($theme_file_info['accept']); ?>" required></p>
                <p><button type="submit" name="tejlg_import_theme" class="button button-primary wp-ui-primary"><?php esc_html_e('Importer le Thème', 'theme-export-jlg'); ?></button></p>
            </form>
        </div>
    </div>
    <div class="tejlg-card components-card is-elevated">
        <div class="components-card__body">
            <h3><?php echo esc_html(sprintf(__('Importer des Compositions (%s)', 'theme-export-jlg'), $patterns_file_info['display'])); ?></h3>
            <p><?php echo wp_kses_post(sprintf(__('Téléversez un fichier %s (généré par l\'export). Vous pourrez choisir quelles compositions importer à l\'étape suivante.', 'theme-export-jlg'), $patterns_file_info['code'])); ?></p>
            <form method="post" action="<?php echo esc_url($import_tab_url); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('tejlg_import_patterns_step1_action', 'tejlg_import_patterns_step1_nonce'); ?>
                <p><label for="patterns_json"><?php echo esc_html(sprintf(__('Fichier des compositions (%s) :', 'theme-export-jlg'), $patterns_file_info['display'])); ?></label><br><input type="file" id="patterns_json" name="patterns_json" accept="<?php echo esc_attr($patterns_file_info['accept']); ?>" required></p>
                <p><button type="submit" name="tejlg_import_patterns_step1" class="button button-primary wp-ui-primary"><?php esc_html_e('Analyser et prévisualiser', 'theme-export-jlg'); ?></button></p>
            </form>
        </div>
    </div>
    <div class="tejlg-card components-card is-elevated">
        <div class="components-card__body">
            <h3><?php echo esc_html(sprintf(__('Importer les Styles Globaux (%s)', 'theme-export-jlg'), $global_styles_file_info['display'])); ?></h3>
            <p><?php echo wp_kses_post(sprintf(__('Téléversez le fichier exporté des réglages globaux (%s) pour appliquer les mêmes paramètres <code>theme.json</code> sur ce site.', 'theme-export-jlg'), $global_styles_file_info['code'])); ?></p>
            <form method="post" action="<?php echo esc_url($import_tab_url); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('tejlg_import_global_styles_action', 'tejlg_import_global_styles_nonce'); ?>
                <p><label for="global_styles_json"><?php echo esc_html(sprintf(__('Fichier des styles globaux (%s) :', 'theme-export-jlg'), $global_styles_file_info['display'])); ?></label><br><input type="file" id="global_styles_json" name="global_styles_json" accept="<?php echo esc_attr($global_styles_file_info['accept']); ?>" required></p>
                <p><button type="submit" name="tejlg_import_global_styles" class="button button-primary wp-ui-primary"><?php esc_html_e('Importer les styles globaux', 'theme-export-jlg'); ?></button></p>
            </form>
        </div>
    </div>
</div>

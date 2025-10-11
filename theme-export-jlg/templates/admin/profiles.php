<?php
/** @var string $page_slug */
/** @var string $export_action */
/** @var string $export_nonce_action */
/** @var string $export_nonce_name */
/** @var string $import_action */
/** @var string $import_nonce_action */
/** @var string $import_nonce_name */

$admin_post_url = admin_url('admin-post.php');
?>
<div id="tejlg-section-profiles" class="tejlg-section-anchor" tabindex="-1"></div>
<div class="tejlg-card">
    <h2 class="tejlg-card__title"><?php esc_html_e('Profils de réglages', 'theme-export-jlg'); ?></h2>
    <p class="tejlg-card__description">
        <?php esc_html_e('Exportez les réglages du plugin ou appliquez un profil signé pour synchroniser plusieurs sites.', 'theme-export-jlg'); ?>
    </p>
    <div class="tejlg-card__content tejlg-card__content--columns">
        <div class="tejlg-card__column">
            <h3 class="tejlg-card__subtitle"><?php esc_html_e('Exporter un profil', 'theme-export-jlg'); ?></h3>
            <p>
                <?php esc_html_e('Générez un fichier JSON signé contenant les réglages actuels. Le téléchargement démarre immédiatement.', 'theme-export-jlg'); ?>
            </p>
            <form method="post" action="<?php echo esc_url($admin_post_url); ?>">
                <?php wp_nonce_field($export_nonce_action, $export_nonce_name); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr($export_action); ?>">
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Télécharger le profil JSON', 'theme-export-jlg'); ?>
                    </button>
                </p>
            </form>
        </div>
        <div class="tejlg-card__column">
            <h3 class="tejlg-card__subtitle"><?php esc_html_e('Importer un profil', 'theme-export-jlg'); ?></h3>
            <p>
                <?php esc_html_e('Chargez un profil précédemment exporté pour appliquer les réglages correspondants.', 'theme-export-jlg'); ?>
            </p>
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url($admin_post_url); ?>">
                <?php wp_nonce_field($import_nonce_action, $import_nonce_name); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr($import_action); ?>">
                <p>
                    <label class="screen-reader-text" for="tejlg-profiles-file">
                        <?php esc_html_e('Fichier de profil JSON', 'theme-export-jlg'); ?>
                    </label>
                    <input type="file" id="tejlg-profiles-file" name="tejlg_profiles_file" accept="application/json">
                </p>
                <p class="submit">
                    <button type="submit" class="button button-secondary">
                        <?php esc_html_e('Importer le profil', 'theme-export-jlg'); ?>
                    </button>
                </p>
            </form>
        </div>
    </div>
</div>

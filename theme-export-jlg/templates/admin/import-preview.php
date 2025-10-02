<?php
/** @var string $page_slug */
/** @var string $transient_id */
/** @var array  $patterns */
/** @var array  $encoding_failures */
/** @var array  $warnings */
/** @var string $global_styles */

$import_tab_url    = add_query_arg([
    'page' => $page_slug,
    'tab'  => 'import',
], admin_url('admin.php'));
$has_global_styles = '' !== trim($global_styles);
?>
<h2><?php esc_html_e('Étape 2 : Choisir les compositions à importer', 'theme-export-jlg'); ?></h2>
<p><?php esc_html_e('Cochez les compositions à importer. Vous pouvez prévisualiser le rendu et inspecter le code du bloc (le code CSS du thème est masqué par défaut).', 'theme-export-jlg'); ?></p>
<form method="post" action="<?php echo esc_url($import_tab_url); ?>">
    <?php wp_nonce_field('tejlg_import_patterns_step2_action', 'tejlg_import_patterns_step2_nonce'); ?>
    <input type="hidden" name="transient_id" value="<?php echo esc_attr($transient_id); ?>">
    <div id="patterns-preview-list">
        <div style="margin-bottom:15px;">
            <label><input type="checkbox" id="select-all-patterns" checked> <strong><?php esc_html_e('Tout sélectionner', 'theme-export-jlg'); ?></strong></label>
        </div>
        <?php foreach ($patterns as $pattern_data): ?>
            <div class="pattern-item">
                <div class="pattern-selector">
                    <label>
                        <input type="checkbox" name="selected_patterns[]" value="<?php echo esc_attr($pattern_data['index']); ?>" checked>
                        <strong><?php echo esc_html($pattern_data['title']); ?></strong>
                    </label>
                </div>
                <div class="pattern-preview-wrapper">
                    <iframe class="pattern-preview-iframe" title="<?php echo esc_attr($pattern_data['iframe_title']); ?>" sandbox="allow-same-origin" loading="lazy"></iframe>
                    <div class="pattern-preview-message notice notice-warning" role="status" aria-live="polite" hidden></div>
                    <script
                        type="application/json"
                        class="pattern-preview-data"
                        data-tejlg-stylesheets="<?php echo esc_attr($pattern_data['iframe_stylesheets_json']); ?>"
                    ><?php echo $pattern_data['iframe_json']; ?></script>
                </div>

                <div class="pattern-controls">
                    <button
                        type="button"
                        class="button-link toggle-code-view"
                        aria-controls="pattern-code-view-<?php echo esc_attr($pattern_data['index']); ?>"
                        aria-expanded="false"
                    >
                        <?php esc_html_e('Afficher le code du bloc', 'theme-export-jlg'); ?>
                    </button>
                </div>

                <div
                    class="pattern-code-view"
                    id="pattern-code-view-<?php echo esc_attr($pattern_data['index']); ?>"
                    hidden
                >
                    <pre><code><?php echo esc_html($pattern_data['content']); ?></code></pre>
                    <?php if ($has_global_styles): ?>
                        <p class="pattern-global-css-link">
                            <button
                                type="button"
                                class="button-link global-css-trigger"
                                data-target="#tejlg-global-css"
                            >
                                <?php esc_html_e('Afficher le CSS global du thème', 'theme-export-jlg'); ?>
                            </button>
                        </p>
                    <?php endif; ?>
                </div>

            </div>
        <?php endforeach; ?>
        <?php if ($has_global_styles): ?>
            <div class="global-css-container">
                <details class="css-accordion" id="tejlg-global-css">
                    <summary><?php esc_html_e('Afficher le CSS global du thème', 'theme-export-jlg'); ?></summary>
                    <pre><code><?php echo esc_html($global_styles); ?></code></pre>
                </details>
            </div>
        <?php endif; ?>
        <?php if (!empty($encoding_failures)): ?>
            <div class="notice notice-warning">
                <?php foreach ($encoding_failures as $failure_message): ?>
                    <p><?php echo esc_html($failure_message); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($warnings)): ?>
            <div class="notice notice-warning">
                <?php foreach ($warnings as $warning_message): ?>
                    <p><?php echo esc_html($warning_message); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <p><button type="submit" name="tejlg_import_patterns_step2" class="button button-primary button-hero"><?php esc_html_e('Importer la sélection', 'theme-export-jlg'); ?></button></p>
</form>

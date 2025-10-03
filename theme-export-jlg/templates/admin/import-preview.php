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
$has_global_styles       = '' !== trim($global_styles);
$global_css_section_id   = 'tejlg-global-css';
$show_preview_label      = esc_html__('Prévisualiser', 'theme-export-jlg');
$hide_preview_label      = esc_html__('Masquer la prévisualisation', 'theme-export-jlg');
$loading_preview_label   = esc_html__('Chargement de la prévisualisation…', 'theme-export-jlg');
$empty_excerpt_fallback  = esc_html__('Aucun aperçu disponible.', 'theme-export-jlg');
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
            <?php
            $preview_area_id      = 'pattern-preview-area-' . (int) $pattern_data['index'];
            $preview_excerpt      = isset($pattern_data['preview_excerpt']) ? (string) $pattern_data['preview_excerpt'] : '';
            $preview_word_count   = isset($pattern_data['preview_word_count']) ? (int) $pattern_data['preview_word_count'] : 0;
            $preview_block_count  = isset($pattern_data['preview_block_count']) ? (int) $pattern_data['preview_block_count'] : 0;
            $pattern_title        = isset($pattern_data['title']) ? (string) $pattern_data['title'] : '';
            $preview_excerpt_attr = '' !== $preview_excerpt ? $preview_excerpt : $empty_excerpt_fallback;
            ?>
            <div
                class="pattern-item"
                data-tejlg-preview-item="true"
                data-label="<?php echo esc_attr($pattern_title); ?>"
                data-excerpt="<?php echo esc_attr($preview_excerpt_attr); ?>"
            >
                <div class="pattern-selector">
                    <label>
                        <input type="checkbox" name="selected_patterns[]" value="<?php echo esc_attr($pattern_data['index']); ?>" checked>
                        <strong><?php echo esc_html($pattern_title); ?></strong>
                    </label>
                </div>

                <div class="pattern-compact" data-tejlg-preview-compact>
                    <div class="pattern-compact-thumbnail" aria-hidden="true"></div>
                    <div class="pattern-compact-details">
                        <p class="pattern-compact-title"><?php echo esc_html($pattern_title); ?></p>
                        <p class="pattern-compact-excerpt">
                            <?php echo esc_html('' !== $preview_excerpt ? $preview_excerpt : $empty_excerpt_fallback); ?>
                        </p>
                        <ul class="pattern-compact-meta">
                            <li>
                                <?php
                                $block_plural_choice = (1 === $preview_block_count) ? 1 : 2;
                                printf(
                                    /* translators: %s: number of blocks. */
                                    _n('%s bloc', '%s blocs', $block_plural_choice, 'theme-export-jlg'),
                                    number_format_i18n($preview_block_count)
                                );
                                ?>
                            </li>
                            <li>
                                <?php
                                $word_plural_choice = (1 === $preview_word_count) ? 1 : 2;
                                printf(
                                    /* translators: %s: number of words. */
                                    _n('%s mot', '%s mots', $word_plural_choice, 'theme-export-jlg'),
                                    number_format_i18n($preview_word_count)
                                );
                                ?>
                            </li>
                        </ul>
                    </div>
                    <div class="pattern-compact-actions">
                        <button
                            type="button"
                            class="button button-secondary pattern-preview-toggle"
                            data-tejlg-preview-trigger="true"
                            data-preview-label-show="<?php echo esc_attr($show_preview_label); ?>"
                            data-preview-label-hide="<?php echo esc_attr($hide_preview_label); ?>"
                            aria-controls="<?php echo esc_attr($preview_area_id); ?>"
                            aria-expanded="false"
                        >
                            <?php echo esc_html($show_preview_label); ?>
                        </button>
                    </div>
                </div>

                <div
                    class="pattern-preview-area"
                    id="<?php echo esc_attr($preview_area_id); ?>"
                    data-tejlg-preview-area="true"
                    hidden
                    aria-hidden="true"
                >
                    <div
                        class="pattern-preview-loading"
                        data-tejlg-preview-loading="true"
                        role="status"
                        aria-live="polite"
                        hidden
                    >
                        <?php echo esc_html($loading_preview_label); ?>
                    </div>
                    <div class="pattern-preview-wrapper">
                        <iframe class="pattern-preview-iframe" title="<?php echo esc_attr($pattern_data['iframe_title']); ?>" sandbox="allow-same-origin" loading="lazy"></iframe>
                        <div class="pattern-preview-message notice notice-warning" role="status" aria-live="polite" hidden></div>
                        <script
                            type="application/json"
                            class="pattern-preview-data"
                            data-tejlg-stylesheets="<?php echo esc_attr($pattern_data['iframe_stylesheets_json']); ?>"
                            data-tejlg-stylesheet-links-html="<?php echo esc_attr(isset($pattern_data['iframe_stylesheet_links_json']) ? $pattern_data['iframe_stylesheet_links_json'] : '""'); ?>"
                        ><?php echo $pattern_data['iframe_json']; ?></script>
                    </div>
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
                            <a
                                class="button-link global-css-trigger"
                                href="#<?php echo esc_attr($global_css_section_id); ?>"
                                aria-controls="<?php echo esc_attr($global_css_section_id); ?>"
                            >
                                <?php esc_html_e('Afficher le CSS global du thème', 'theme-export-jlg'); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>

            </div>
        <?php endforeach; ?>
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
    <?php if ($has_global_styles): ?>
        <div class="global-css-container">
            <details
                class="css-accordion"
                id="<?php echo esc_attr($global_css_section_id); ?>"
                data-tejlg-global-css="true"
            >
                <summary><?php esc_html_e('Afficher le CSS global du thème', 'theme-export-jlg'); ?></summary>
                <pre><code><?php echo esc_html($global_styles); ?></code></pre>
            </details>
        </div>
    <?php endif; ?>
    <p><button type="submit" name="tejlg_import_patterns_step2" class="button button-primary button-hero"><?php esc_html_e('Importer la sélection', 'theme-export-jlg'); ?></button></p>
</form>

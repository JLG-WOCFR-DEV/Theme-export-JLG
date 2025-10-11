<?php
/** @var string    $page_slug */
/** @var WP_Query  $patterns_query */
/** @var array     $pattern_entries */
/** @var int       $per_page */
/** @var int       $current_page */
/** @var int       $total_pages */
/** @var string    $pagination_base */
/** @var bool      $portable_mode_enabled */

$back_url = add_query_arg([
    'page' => $page_slug,
    'tab'  => 'export',
], admin_url('admin.php'));
?>
<p><a href="<?php echo esc_url($back_url); ?>">&larr; <?php esc_html_e('Retour aux outils principaux', 'theme-export-jlg'); ?></a></p>
<h2><?php esc_html_e('Exporter une sélection de compositions', 'theme-export-jlg'); ?></h2>
<p><?php echo wp_kses_post(__('Cochez les compositions que vous souhaitez inclure dans votre fichier d\'exportation <code>.json</code> et cliquez sur « Prévisualiser » pour vérifier le rendu avant l\'export.', 'theme-export-jlg')); ?></p>

<?php if (empty($pattern_entries)): ?>
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
            <p id="pattern-selection-status" aria-live="polite" class="screen-reader-text"></p>
            <div
                class="pattern-selection-feedback"
                role="status"
                aria-live="polite"
                aria-atomic="true"
            >
                <p
                    id="pattern-selection-count"
                    class="pattern-selection-count"
                    data-pattern-selection-count
                ></p>
            </div>
            <ul class="pattern-selection-items" id="pattern-selection-items" aria-live="polite" data-searchable="true">
                <?php foreach ($pattern_entries as $entry): ?>
                    <?php
                    $terms_display = '';
                    $term_tokens_attr = '';

                    if (!empty($entry['term_labels']) && is_array($entry['term_labels'])) {
                        $term_badges = array_map(
                            static function ($label) {
                                return '<span class="pattern-selection-term">' . esc_html((string) $label) . '</span>';
                            },
                            $entry['term_labels']
                        );
                        $terms_display = implode('', $term_badges);
                    }

                    if (!empty($entry['term_tokens']) && is_array($entry['term_tokens'])) {
                        $term_tokens_attr = implode(' ', array_map('sanitize_title', $entry['term_tokens']));
                    }

                    $search_attr = isset($entry['search_haystack']) ? (string) $entry['search_haystack'] : '';
                    $date_attr = isset($entry['date_machine']) ? (string) $entry['date_machine'] : '';
                    $timestamp_attr = isset($entry['timestamp']) ? (string) $entry['timestamp'] : '';
                    $original_index = isset($entry['display_index']) ? (int) $entry['display_index'] : 0;
                    ?>
                    <li
                        class="pattern-selection-item pattern-item"
                        data-label="<?php echo esc_attr($entry['title']); ?>"
                        data-terms="<?php echo esc_attr($term_tokens_attr); ?>"
                        data-date="<?php echo esc_attr($date_attr); ?>"
                        data-timestamp="<?php echo esc_attr($timestamp_attr); ?>"
                        data-original-index="<?php echo esc_attr($original_index); ?>"
                        data-search="<?php echo esc_attr($search_attr); ?>"
                    >
                        <div class="pattern-selector">
                            <label>
                                <input type="checkbox" name="selected_patterns[]" value="<?php echo esc_attr($entry['id']); ?>">
                                <span class="pattern-selection-content">
                                    <span class="pattern-selection-title"><?php echo esc_html($entry['title']); ?></span>
                                    <?php if ('' !== $entry['date_display'] || '' !== $terms_display): ?>
                                        <span class="pattern-selection-meta">
                                            <?php if ('' !== $entry['date_display']): ?>
                                                <span class="pattern-selection-date"><?php echo esc_html($entry['date_display']); ?></span>
                                            <?php endif; ?>
                                            <?php if ('' !== $terms_display): ?>
                                                <span class="pattern-selection-terms" aria-label="<?php echo esc_attr__('Catégories', 'theme-export-jlg'); ?>">
                                                    <?php echo wp_kses_post($terms_display); ?>
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($entry['excerpt'])): ?>
                                        <span class="pattern-selection-excerpt"><?php echo esc_html($entry['excerpt']); ?></span>
                                    <?php endif; ?>
                                </span>
                            </label>
                        </div>
                        <div class="pattern-preview-wrapper" data-preview-state="compact">
                            <div class="pattern-preview-compact" data-preview-compact>
                                <div class="pattern-preview-compact-thumbnail" aria-hidden="true"></div>
                                <div class="pattern-preview-compact-details">
                                    <?php if ('' !== $entry['date_display'] || '' !== $terms_display): ?>
                                        <div class="pattern-preview-compact-meta">
                                            <?php if ('' !== $entry['date_display']): ?>
                                                <span class="pattern-preview-compact-date"><?php echo esc_html($entry['date_display']); ?></span>
                                            <?php endif; ?>
                                            <?php if ('' !== $terms_display): ?>
                                                <span class="pattern-preview-compact-categories" aria-label="<?php echo esc_attr__('Catégories associées', 'theme-export-jlg'); ?>">
                                                    <?php echo wp_kses_post($terms_display); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <button
                                        type="button"
                                        class="button button-secondary pattern-preview-trigger"
                                        data-preview-trigger="expand"
                                        aria-controls="<?php echo esc_attr($entry['preview_live_id']); ?>"
                                        aria-expanded="false"
                                    >
                                        <?php esc_html_e('Prévisualiser', 'theme-export-jlg'); ?>
                                    </button>
                                </div>
                            </div>
                            <div
                                class="pattern-preview-live"
                                data-preview-live
                                id="<?php echo esc_attr($entry['preview_live_id']); ?>"
                                hidden
                            >
                                <button
                                    type="button"
                                    class="button-link pattern-preview-trigger pattern-preview-trigger--collapse"
                                    data-preview-trigger="collapse"
                                    aria-controls="<?php echo esc_attr($entry['preview_live_id']); ?>"
                                    aria-expanded="true"
                                >
                                    <?php esc_html_e('Masquer la prévisualisation', 'theme-export-jlg'); ?>
                                </button>
                                <div class="pattern-preview-loading" data-preview-loading role="status" aria-live="polite" hidden>
                                    <span class="spinner is-active" aria-hidden="true"></span>
                                    <span class="pattern-preview-loading-text"><?php esc_html_e('Chargement de la prévisualisation…', 'theme-export-jlg'); ?></span>
                                </div>
                                <iframe class="pattern-preview-iframe" title="<?php echo esc_attr($entry['iframe_title']); ?>" sandbox="allow-same-origin" loading="lazy"></iframe>
                                <div class="pattern-preview-message notice notice-warning" role="status" aria-live="polite" hidden></div>
                                <script
                                    type="application/json"
                                    class="pattern-preview-data"
                                    data-tejlg-stylesheets="<?php echo esc_attr($entry['stylesheets_json']); ?>"
                                    data-tejlg-stylesheet-links-html="<?php echo esc_attr($entry['stylesheet_links_json']); ?>"
                                ><?php echo $entry['iframe_json']; ?></script>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($per_page > 0 && $total_pages > 1): ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        echo wp_kses_post(
                            paginate_links([
                                'base'      => $pagination_base,
                                'format'    => '',
                                'current'   => $current_page,
                                'total'     => $total_pages,
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                            ])
                        );
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <p><label><input type="checkbox" name="export_portable" value="1" <?php checked($portable_mode_enabled); ?>> <strong><?php esc_html_e('Générer un export "portable"', 'theme-export-jlg'); ?></strong> <?php esc_html_e('(Recommandé pour migrer vers un autre site)', 'theme-export-jlg'); ?></label></p>

        <p><button type="submit" name="tejlg_export_selected_patterns" class="button button-primary button-hero" data-pattern-submit="true"><?php esc_html_e('Exporter la sélection', 'theme-export-jlg'); ?></button></p>
    </form>
<?php endif; ?>

<?php include __DIR__ . '/quick-actions.php'; ?>

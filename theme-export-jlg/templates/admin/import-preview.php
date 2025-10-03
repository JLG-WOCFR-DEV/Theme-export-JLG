<?php
/** @var string $page_slug */
/** @var string $transient_id */
/** @var array  $patterns */
/** @var array  $encoding_failures */
/** @var array  $warnings */
/** @var string $global_styles */
/** @var array  $category_filters */
/** @var array  $date_filters */
/** @var bool   $has_uncategorized */
/** @var bool   $has_undated */
/** @var string $default_sort */

$import_tab_url    = add_query_arg([
    'page' => $page_slug,
    'tab'  => 'import',
], admin_url('admin.php'));
$has_global_styles      = '' !== trim($global_styles);
$global_css_section_id = 'tejlg-global-css';
$category_filters   = is_array($category_filters) ? $category_filters : [];
$date_filters       = is_array($date_filters) ? $date_filters : [];
$has_uncategorized  = (bool) $has_uncategorized;
$has_undated        = (bool) $has_undated;
$default_sort       = is_string($default_sort) ? trim($default_sort) : 'title-asc';
if ('' === $default_sort) {
    $default_sort = 'title-asc';
}
$controls_help_id = 'tejlg-import-controls-help';
?>
<h2><?php esc_html_e('Étape 2 : Choisir les compositions à importer', 'theme-export-jlg'); ?></h2>
<p><?php esc_html_e('Cochez les compositions à importer. Vous pouvez prévisualiser le rendu et inspecter le code du bloc (le code CSS du thème est masqué par défaut).', 'theme-export-jlg'); ?></p>
<form method="post" action="<?php echo esc_url($import_tab_url); ?>">
    <?php wp_nonce_field('tejlg_import_patterns_step2_action', 'tejlg_import_patterns_step2_nonce'); ?>
    <input type="hidden" name="transient_id" value="<?php echo esc_attr($transient_id); ?>">
    <div
        id="patterns-preview-list"
        class="pattern-import-layout"
        data-default-sort="<?php echo esc_attr($default_sort); ?>"
    >
        <div class="pattern-import-actions">
            <label>
                <input
                    type="checkbox"
                    id="select-all-patterns"
                    checked
                    aria-describedby="<?php echo esc_attr($controls_help_id); ?>"
                >
                <strong><?php esc_html_e('Tout sélectionner', 'theme-export-jlg'); ?></strong>
            </label>
            <p class="description">
                <?php esc_html_e('La case « Tout sélectionner » agit uniquement sur les compositions visibles après application de la recherche, des filtres et du tri.', 'theme-export-jlg'); ?>
            </p>
        </div>
        <p id="<?php echo esc_attr($controls_help_id); ?>" class="pattern-import-instructions">
            <?php esc_html_e('Utilisez la recherche, les filtres (catégorie, période) et le tri pour réduire la liste. Le compteur suivant annonce en continu le nombre de compositions visibles aux lecteurs d’écran.', 'theme-export-jlg'); ?>
        </p>
        <div class="pattern-import-toolbar" role="group" aria-label="<?php esc_attr_e('Filtres d’importation des compositions', 'theme-export-jlg'); ?>">
            <div class="pattern-import-control pattern-import-control--search">
                <label class="screen-reader-text" for="tejlg-import-pattern-search"><?php esc_html_e('Rechercher une composition à importer', 'theme-export-jlg'); ?></label>
                <input
                    type="search"
                    id="tejlg-import-pattern-search"
                    placeholder="<?php echo esc_attr__('Rechercher…', 'theme-export-jlg'); ?>"
                    aria-describedby="<?php echo esc_attr($controls_help_id); ?>"
                    aria-controls="patterns-preview-items"
                >
            </div>
            <div class="pattern-import-control">
                <label for="tejlg-import-filter-category"><?php esc_html_e('Catégorie', 'theme-export-jlg'); ?></label>
                <select
                    id="tejlg-import-filter-category"
                    aria-describedby="<?php echo esc_attr($controls_help_id); ?>"
                >
                    <option value=""><?php esc_html_e('Toutes les catégories', 'theme-export-jlg'); ?></option>
                    <?php foreach ($category_filters as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                    <?php if ($has_uncategorized): ?>
                        <option value="__no-category__"><?php esc_html_e('Sans catégorie', 'theme-export-jlg'); ?></option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="pattern-import-control">
                <label for="tejlg-import-filter-date"><?php esc_html_e('Période', 'theme-export-jlg'); ?></label>
                <select
                    id="tejlg-import-filter-date"
                    aria-describedby="<?php echo esc_attr($controls_help_id); ?>"
                >
                    <option value=""><?php esc_html_e('Toutes les périodes', 'theme-export-jlg'); ?></option>
                    <?php foreach ($date_filters as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                    <?php if ($has_undated): ?>
                        <option value="__no-date__"><?php esc_html_e('Sans date', 'theme-export-jlg'); ?></option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="pattern-import-control">
                <label for="tejlg-import-sort"><?php esc_html_e('Trier par', 'theme-export-jlg'); ?></label>
                <select
                    id="tejlg-import-sort"
                    aria-describedby="<?php echo esc_attr($controls_help_id); ?>"
                >
                    <option value="title-asc" <?php selected($default_sort, 'title-asc'); ?>><?php esc_html_e('Titre (A → Z)', 'theme-export-jlg'); ?></option>
                    <option value="title-desc" <?php selected($default_sort, 'title-desc'); ?>><?php esc_html_e('Titre (Z → A)', 'theme-export-jlg'); ?></option>
                    <option value="date-desc" <?php selected($default_sort, 'date-desc'); ?>><?php esc_html_e('Date (du plus récent au plus ancien)', 'theme-export-jlg'); ?></option>
                    <option value="date-asc" <?php selected($default_sort, 'date-asc'); ?>><?php esc_html_e('Date (du plus ancien au plus récent)', 'theme-export-jlg'); ?></option>
                    <option value="original" <?php selected($default_sort, 'original'); ?>><?php esc_html_e('Ordre du fichier importé', 'theme-export-jlg'); ?></option>
                </select>
            </div>
        </div>
        <p id="pattern-import-status" class="pattern-import-status" aria-live="polite" aria-atomic="true"></p>
        <div class="pattern-import-items" id="patterns-preview-items" data-searchable="true">
            <?php foreach ($patterns as $pattern_data): ?>
                <?php
                $category_tokens = isset($pattern_data['categories']) && is_array($pattern_data['categories'])
                    ? array_filter($pattern_data['categories'], 'is_scalar')
                    : [];
                $category_tokens_attr = implode(' ', array_map('sanitize_title', $category_tokens));
                $period_value = isset($pattern_data['period_value']) ? (string) $pattern_data['period_value'] : '';
                $date_machine = isset($pattern_data['date_machine']) ? (string) $pattern_data['date_machine'] : '';
                $timestamp_value = isset($pattern_data['timestamp']) && null !== $pattern_data['timestamp']
                    ? (string) (int) $pattern_data['timestamp']
                    : '';
                $search_haystack = isset($pattern_data['search_haystack']) ? (string) $pattern_data['search_haystack'] : '';
                $title_sort = isset($pattern_data['title_sort']) ? (string) $pattern_data['title_sort'] : '';
                $original_index = isset($pattern_data['original_index']) ? (int) $pattern_data['original_index'] : 0;
                $category_labels = isset($pattern_data['category_labels']) && is_array($pattern_data['category_labels'])
                    ? array_filter($pattern_data['category_labels'], 'is_scalar')
                    : [];
                $excerpt = isset($pattern_data['excerpt']) ? (string) $pattern_data['excerpt'] : '';
                $date_display = isset($pattern_data['date_display']) ? (string) $pattern_data['date_display'] : '';
                ?>
                <div
                    class="pattern-item"
                    data-search="<?php echo esc_attr($search_haystack); ?>"
                    data-categories="<?php echo esc_attr($category_tokens_attr); ?>"
                    data-period="<?php echo esc_attr($period_value); ?>"
                    data-date="<?php echo esc_attr($date_machine); ?>"
                    data-timestamp="<?php echo esc_attr($timestamp_value); ?>"
                    data-title-sort="<?php echo esc_attr($title_sort); ?>"
                    data-original-index="<?php echo esc_attr($original_index); ?>"
                >
                    <?php $preview_live_id = 'pattern-preview-live-' . (int) $pattern_data['index']; ?>
                    <div class="pattern-selector">
                        <label>
                            <input type="checkbox" name="selected_patterns[]" value="<?php echo esc_attr($pattern_data['index']); ?>" checked>
                            <strong><?php echo esc_html($pattern_data['title']); ?></strong>
                        </label>
                        <?php if ('' !== $date_display || !empty($category_labels)): ?>
                            <div class="pattern-import-meta">
                                <?php if ('' !== $date_display): ?>
                                    <span class="pattern-import-meta-date pattern-selection-date"><?php echo esc_html($date_display); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($category_labels)): ?>
                                    <span class="pattern-import-meta-categories" aria-label="<?php echo esc_attr__('Catégories associées', 'theme-export-jlg'); ?>">
                                        <?php foreach ($category_labels as $category_label): ?>
                                            <span class="pattern-import-category pattern-selection-term"><?php echo esc_html($category_label); ?></span>
                                        <?php endforeach; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ('' !== $excerpt): ?>
                            <p class="pattern-import-excerpt"><?php echo esc_html($excerpt); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="pattern-preview-wrapper" data-preview-state="compact">
                        <div class="pattern-preview-compact" data-preview-compact>
                            <div class="pattern-preview-compact-thumbnail" aria-hidden="true"></div>
                            <div class="pattern-preview-compact-details">
                                <?php if ('' !== $date_display || !empty($category_labels)): ?>
                                    <div class="pattern-preview-compact-meta">
                                        <?php if ('' !== $date_display): ?>
                                            <span class="pattern-preview-compact-date"><?php echo esc_html($date_display); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($category_labels)): ?>
                                            <span class="pattern-preview-compact-categories" aria-label="<?php echo esc_attr__('Catégories associées', 'theme-export-jlg'); ?>">
                                                <?php foreach ($category_labels as $category_label): ?>
                                                    <span class="pattern-preview-compact-category"><?php echo esc_html($category_label); ?></span>
                                                <?php endforeach; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <button
                                    type="button"
                                    class="button button-secondary pattern-preview-trigger"
                                    data-preview-trigger="expand"
                                    aria-controls="<?php echo esc_attr($preview_live_id); ?>"
                                    aria-expanded="false"
                                >
                                    <?php esc_html_e('Prévisualiser', 'theme-export-jlg'); ?>
                                </button>
                            </div>
                        </div>
                        <div
                            class="pattern-preview-live"
                            data-preview-live
                            id="<?php echo esc_attr($preview_live_id); ?>"
                            hidden
                        >
                            <button
                                type="button"
                                class="button-link pattern-preview-trigger pattern-preview-trigger--collapse"
                                data-preview-trigger="collapse"
                                aria-controls="<?php echo esc_attr($preview_live_id); ?>"
                                aria-expanded="true"
                            >
                                <?php esc_html_e('Masquer la prévisualisation', 'theme-export-jlg'); ?>
                            </button>
                            <div class="pattern-preview-loading" data-preview-loading role="status" aria-live="polite" hidden>
                                <span class="spinner is-active" aria-hidden="true"></span>
                                <span class="pattern-preview-loading-text"><?php esc_html_e('Chargement de la prévisualisation…', 'theme-export-jlg'); ?></span>
                            </div>
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
        </div>
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

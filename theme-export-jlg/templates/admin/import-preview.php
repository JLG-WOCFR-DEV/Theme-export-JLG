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
<h2><?php esc_html_e('Assistant d’import des compositions', 'theme-export-jlg'); ?></h2>
<p><?php esc_html_e('Suivez les étapes pour sélectionner, confirmer puis importer les compositions. Un résumé téléchargeable est proposé avant l’envoi.', 'theme-export-jlg'); ?></p>
<form method="post" action="<?php echo esc_url($import_tab_url); ?>" data-assistant-form>
    <?php wp_nonce_field('tejlg_import_patterns_step2_action', 'tejlg_import_patterns_step2_nonce'); ?>
    <div
        class="tejlg-assistant"
        data-assistant
        data-assistant-id="import-preview"
        data-assistant-storage-key="import-preview"
        data-assistant-transient-id="<?php echo esc_attr($transient_id); ?>"
        data-assistant-has-global-styles="<?php echo $has_global_styles ? '1' : '0'; ?>"
    >
        <noscript>
            <div class="notice notice-warning tejlg-nojs-notice">
                <p><?php esc_html_e('JavaScript est désactivé : toutes les étapes sont affichées en continu. Passez en revue la sélection puis validez l’import en bas de page.', 'theme-export-jlg'); ?></p>
            </div>
        </noscript>
        <header class="tejlg-assistant__progress">
            <ol class="tejlg-stepper" data-assistant-stepper>
                <li
                    class="tejlg-stepper__step"
                    data-assistant-stepper-item
                    data-assistant-step-target="selection"
                >
                    <span class="tejlg-stepper__index" aria-hidden="true">1</span>
                    <span class="tejlg-stepper__label"><?php esc_html_e('Sélection', 'theme-export-jlg'); ?></span>
                </li>
                <li
                    class="tejlg-stepper__step"
                    data-assistant-stepper-item
                    data-assistant-step-target="confirmation"
                >
                    <span class="tejlg-stepper__index" aria-hidden="true">2</span>
                    <span class="tejlg-stepper__label"><?php esc_html_e('Confirmation', 'theme-export-jlg'); ?></span>
                </li>
                <li
                    class="tejlg-stepper__step"
                    data-assistant-stepper-item
                    data-assistant-step-target="summary"
                >
                    <span class="tejlg-stepper__index" aria-hidden="true">3</span>
                    <span class="tejlg-stepper__label"><?php esc_html_e('Résumé', 'theme-export-jlg'); ?></span>
                </li>
            </ol>
        </header>
        <div
            class="tejlg-assistant__hint notice notice-info"
            data-assistant-hint
            hidden
            role="status"
            aria-live="polite"
        ></div>
        <div class="tejlg-steps" data-assistant-panels>
            <section
                class="tejlg-step is-active"
                data-step="0"
                data-assistant-step="selection"
                data-assistant-hint-key="selection"
                aria-labelledby="tejlg-import-assistant-selection"
            >
                <h4 id="tejlg-import-assistant-selection" class="tejlg-step__title" tabindex="-1"><?php esc_html_e('Étape 1 : Sélectionner les compositions', 'theme-export-jlg'); ?></h4>
                <p class="description"><?php esc_html_e('Cochez les compositions à importer. Filtrez la liste, prévisualisez le rendu et inspectez le code si nécessaire.', 'theme-export-jlg'); ?></p>
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
                                <?php foreach ($date_filter_options as $value => $label): ?>
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
                        <div
                            class="pattern-import-control pattern-import-control--preview-width"
                            data-preview-width-control
                        >
                            <span class="pattern-import-control-label"><?php esc_html_e('Largeur de la prévisualisation', 'theme-export-jlg'); ?></span>
                            <div class="pattern-preview-width-buttons" role="group" aria-label="<?php esc_attr_e('Options de largeur de prévisualisation', 'theme-export-jlg'); ?>">
                                <button
                                    type="button"
                                    class="button button-secondary pattern-preview-width-button"
                                    data-preview-width-option="editor"
                                    data-preview-width
                                    aria-pressed="false"
                                >
                                    <?php esc_html_e('Éditeur', 'theme-export-jlg'); ?>
                                </button>
                                <button
                                    type="button"
                                    class="button button-secondary pattern-preview-width-button"
                                    data-preview-width-option="full"
                                    data-preview-width
                                    aria-pressed="false"
                                >
                                    <?php esc_html_e('Pleine largeur', 'theme-export-jlg'); ?>
                                </button>
                                <button
                                    type="button"
                                    class="button button-secondary pattern-preview-width-button"
                                    data-preview-width-option="custom"
                                    data-preview-width
                                    aria-pressed="false"
                                    aria-controls="tejlg-preview-width-custom-panel"
                                    aria-expanded="false"
                                >
                                    <?php esc_html_e('Largeur personnalisée…', 'theme-export-jlg'); ?>
                                </button>
                            </div>
                            <div
                                class="pattern-preview-width-custom"
                                id="tejlg-preview-width-custom-panel"
                                data-preview-width-custom
                                hidden
                            >
                                <span class="pattern-preview-width-custom-label" id="tejlg-preview-width-custom-label"><?php esc_html_e('Largeur personnalisée (px)', 'theme-export-jlg'); ?></span>
                                <div class="pattern-preview-width-custom-controls" role="group" aria-labelledby="tejlg-preview-width-custom-label">
                                    <input
                                        type="range"
                                        id="tejlg-preview-width-range"
                                        class="pattern-preview-width-range"
                                        min="320"
                                        max="1600"
                                        step="10"
                                        value="1024"
                                        data-preview-width-range
                                        aria-label="<?php esc_attr_e('Définir la largeur personnalisée en pixels', 'theme-export-jlg'); ?>"
                                    >
                                    <label class="screen-reader-text" for="tejlg-preview-width-number"><?php esc_html_e('Définir la largeur personnalisée en pixels', 'theme-export-jlg'); ?></label>
                                    <input
                                        type="number"
                                        id="tejlg-preview-width-number"
                                        class="pattern-preview-width-number"
                                        min="320"
                                        max="1600"
                                        step="10"
                                        value="1024"
                                        data-preview-width-number
                                    >
                                    <output
                                        class="pattern-preview-width-value"
                                        for="tejlg-preview-width-range tejlg-preview-width-number"
                                        data-preview-width-value
                                        data-value-template="<?php echo esc_attr__('Largeur : %s px', 'theme-export-jlg'); ?>"
                                        aria-live="polite"
                                    ><?php echo esc_html__('Largeur : 1024 px', 'theme-export-jlg'); ?></output>
                                </div>
                                <p class="description pattern-preview-width-description"><?php esc_html_e('Ajustez la largeur pour simuler différents écrans.', 'theme-export-jlg'); ?></p>
                            </div>
                        </div>
                    </div>
                    <p id="pattern-import-status" class="pattern-import-status" aria-live="polite" aria-atomic="true"></p>
                    <div
                        class="pattern-selection-feedback"
                        role="status"
                        aria-live="polite"
                        aria-atomic="true"
                    >
                        <p
                            id="pattern-import-selection-count"
                            class="pattern-selection-count"
                            data-pattern-selection-count
                        ></p>
                        <p class="pattern-selection-count" data-pattern-selection-description></p>
                    </div>
                    <div class="pattern-import-items" id="patterns-preview-items" data-searchable="true" data-assistant-selection-list>
                        <?php foreach ($patterns as $pattern_data): ?>
                            <?php
                            $category_tokens = isset($pattern_data['categories']) && is_array($pattern_data['categories'])
                                ? array_filter($pattern_data['categories'], 'is_scalar')
                                : [];
                            $category_tokens_attr = implode(' ', array_map('sanitize_title', $category_tokens));
                            $period_value = isset($pattern_data['period_value']) ? (string) $pattern_data['period_value'] : '';
                            $period_label = isset($pattern_data['period_label']) ? (string) $pattern_data['period_label'] : '';
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
                            $category_labels_json = wp_json_encode($category_labels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                            if (!is_string($category_labels_json)) {
                                $category_labels_json = '[]';
                            }
                            $excerpt = isset($pattern_data['excerpt']) ? (string) $pattern_data['excerpt'] : '';
                            $date_display = isset($pattern_data['date_display']) ? (string) $pattern_data['date_display'] : '';
                            $pattern_title = isset($pattern_data['title']) ? (string) $pattern_data['title'] : '';
                            ?>
                            <div
                                class="pattern-item"
                                data-search="<?php echo esc_attr($search_haystack); ?>"
                                data-categories="<?php echo esc_attr($category_tokens_attr); ?>"
                                data-period="<?php echo esc_attr($period_value); ?>"
                                data-period-label="<?php echo esc_attr($period_label); ?>"
                                data-date="<?php echo esc_attr($date_machine); ?>"
                                data-date-display="<?php echo esc_attr($date_display); ?>"
                                data-timestamp="<?php echo esc_attr($timestamp_value); ?>"
                                data-title-sort="<?php echo esc_attr($title_sort); ?>"
                                data-original-index="<?php echo esc_attr($original_index); ?>"
                                data-title="<?php echo esc_attr($pattern_title); ?>"
                                data-category-labels="<?php echo esc_attr($category_labels_json); ?>"
                                data-assistant-selectable
                            >
                                <?php $preview_live_id = 'pattern-preview-live-' . (int) $pattern_data['index']; ?>
                                <div class="pattern-selector">
                                    <label>
                                        <input type="checkbox" name="selected_patterns[]" value="<?php echo esc_attr($pattern_data['index']); ?>" checked>
                                        <strong><?php echo esc_html($pattern_title); ?></strong>
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
                                            data-tejlg-stylesheet-links-html="<?php echo esc_attr(isset($pattern_data['iframe_stylesheet_links_json']) ? $pattern_data['iframe_stylesheet_links_json'] : ''); ?>"
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
                </div>
                <?php if ($has_global_styles): ?>
                    <div class="global-css-container">
                        <details
                            class="css-accordion"
                            id="<?php echo esc_attr($global_css_section_id); ?>"
                            data-tejlg-global-css="true"
                            data-tejlg-persist="panel"
                        >
                            <summary><?php esc_html_e('Afficher le CSS global du thème', 'theme-export-jlg'); ?></summary>
                            <pre><code><?php echo esc_html($global_styles); ?></code></pre>
                        </details>
                    </div>
                <?php endif; ?>
                <div class="tejlg-step__actions">
                    <button type="button" class="button button-primary wp-ui-primary" data-assistant-next data-assistant-requires-selection><?php esc_html_e('Confirmer la sélection', 'theme-export-jlg'); ?></button>
                </div>
            </section>
            <section
                class="tejlg-step"
                data-step="1"
                data-assistant-step="confirmation"
                data-assistant-hint-key="confirmation"
                aria-labelledby="tejlg-import-assistant-confirmation"
            >
                <h4 id="tejlg-import-assistant-confirmation" class="tejlg-step__title" tabindex="-1"><?php esc_html_e('Étape 2 : Confirmer la sélection', 'theme-export-jlg'); ?></h4>
                <p class="description"><?php esc_html_e('Revoyez la sélection, vérifiez les avertissements éventuels puis poursuivez vers le résumé.', 'theme-export-jlg'); ?></p>
                <p class="description" data-assistant-selection-count><?php esc_html_e('Calcul de la sélection…', 'theme-export-jlg'); ?></p>
                <ol
                    class="tejlg-step-summary"
                    data-assistant-selection-preview
                    data-assistant-preview-limit="5"
                    data-assistant-preview-empty="<?php echo esc_attr__('Aucune composition sélectionnée.', 'theme-export-jlg'); ?>"
                ></ol>
                <p class="description" data-assistant-preview-hint hidden></p>
                <?php if (!empty($encoding_failures)): ?>
                    <div class="notice notice-warning" data-assistant-encoding>
                        <?php foreach ($encoding_failures as $failure_message): ?>
                            <p><?php echo esc_html($failure_message); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($warnings)): ?>
                    <div class="notice notice-warning" data-assistant-warning>
                        <?php foreach ($warnings as $warning_message): ?>
                            <p><?php echo esc_html($warning_message); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="tejlg-step__actions">
                    <button type="button" class="button button-secondary wp-ui-secondary" data-assistant-prev><?php esc_html_e('Retour', 'theme-export-jlg'); ?></button>
                    <button type="button" class="button button-primary wp-ui-primary" data-assistant-next data-assistant-requires-selection><?php esc_html_e('Accéder au résumé', 'theme-export-jlg'); ?></button>
                </div>
            </section>
            <section
                class="tejlg-step"
                data-step="2"
                data-assistant-step="summary"
                data-assistant-hint-key="summary"
                aria-labelledby="tejlg-import-assistant-summary"
            >
                <h4 id="tejlg-import-assistant-summary" class="tejlg-step__title" tabindex="-1"><?php esc_html_e('Étape 3 : Résumé et import', 'theme-export-jlg'); ?></h4>
                <p class="description"><?php esc_html_e('Téléchargez le résumé pour archivage puis lancez l’import des compositions sélectionnées.', 'theme-export-jlg'); ?></p>
                <ul class="tejlg-step-summary">
                    <li>
                        <strong><?php esc_html_e('Compositions sélectionnées', 'theme-export-jlg'); ?> :</strong>
                        <span
                            data-assistant-summary-count
                            data-assistant-summary-empty="<?php echo esc_attr__('Aucune composition sélectionnée.', 'theme-export-jlg'); ?>"
                        >—</span>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Filtres actifs', 'theme-export-jlg'); ?> :</strong>
                        <span
                            data-assistant-summary-filters
                            data-assistant-summary-empty="<?php echo esc_attr__('Aucun filtre actif.', 'theme-export-jlg'); ?>"
                        >—</span>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Avertissements', 'theme-export-jlg'); ?> :</strong>
                        <span
                            data-assistant-summary-warnings
                            data-assistant-summary-empty="<?php echo esc_attr__('Aucun avertissement.', 'theme-export-jlg'); ?>"
                        >—</span>
                    </li>
                </ul>
                <div class="tejlg-step__actions">
                    <button type="button" class="button button-secondary wp-ui-secondary" data-assistant-prev><?php esc_html_e('Retour', 'theme-export-jlg'); ?></button>
                    <div class="tejlg-step__cta">
                        <button type="button" class="button button-secondary wp-ui-secondary" data-assistant-download-summary data-assistant-requires-selection><?php esc_html_e('Télécharger le résumé JSON', 'theme-export-jlg'); ?></button>
                        <button type="submit" name="tejlg_import_patterns_step2" class="button button-primary button-hero" data-pattern-submit="true"><?php esc_html_e('Importer la sélection', 'theme-export-jlg'); ?></button>
                    </div>
                </div>
            </section>
        </div>
    </div>
</form>

<?php include __DIR__ . '/quick-actions.php'; ?>

<?php
/** @var string    $page_slug */
/** @var WP_Query  $patterns_query */
/** @var int       $per_page */
/** @var int       $current_page */
/** @var int       $total_pages */
/** @var string    $pagination_base */

$back_url = add_query_arg([
    'page' => $page_slug,
    'tab'  => 'export',
], admin_url('admin.php'));
?>
<p><a href="<?php echo esc_url($back_url); ?>">&larr; <?php esc_html_e('Retour aux outils principaux', 'theme-export-jlg'); ?></a></p>
<h2><?php esc_html_e('Exporter une sélection de compositions', 'theme-export-jlg'); ?></h2>
<p><?php echo wp_kses_post(__('Cochez les compositions que vous souhaitez inclure dans votre fichier d\'exportation <code>.json</code>.', 'theme-export-jlg')); ?></p>

<?php if (!$patterns_query->have_posts()): ?>
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
            <ul class="pattern-selection-items" id="pattern-selection-items" aria-live="polite" data-searchable="true">
                <?php
                $pattern_counter = $per_page > 0 ? (($current_page - 1) * $per_page) : 0;
                while ($patterns_query->have_posts()):
                    $patterns_query->the_post();
                    $pattern_counter++;
                    $pattern_id = get_the_ID();

                    $raw_title = get_the_title();
                    if (!is_scalar($raw_title)) {
                        $raw_title = '';
                    }
                    $pattern_title = trim((string) $raw_title);
                    if ('' === $pattern_title) {
                        $pattern_title = sprintf(
                            esc_html__('Composition sans titre #%d', 'theme-export-jlg'),
                            (int) $pattern_counter
                        );
                    }

                    $raw_excerpt = get_the_excerpt($pattern_id);
                    if (!is_string($raw_excerpt)) {
                        $raw_excerpt = '';
                    }
                    $excerpt = trim(wp_strip_all_tags($raw_excerpt));
                    if ('' === $excerpt) {
                        $raw_content = get_the_content(null, false, $pattern_id);
                        if (!is_string($raw_content)) {
                            $raw_content = '';
                        }
                        $excerpt = trim(wp_strip_all_tags($raw_content));
                    }
                    if ('' !== $excerpt) {
                        $excerpt = wp_trim_words($excerpt, 30, '…');
                    }

                    $terms = get_the_terms($pattern_id, 'wp_pattern_category');
                    $term_badges = [];
                    $term_tokens = [];
                    if (is_array($terms) && !is_wp_error($terms)) {
                        foreach ($terms as $term) {
                            if (!is_object($term) || !isset($term->name)) {
                                continue;
                            }
                            $term_name = is_scalar($term->name) ? trim((string) $term->name) : '';
                            $term_slug = isset($term->slug) && is_scalar($term->slug) ? trim((string) $term->slug) : '';
                            if ('' === $term_name) {
                                continue;
                            }
                            $term_badges[] = sprintf('<span class="pattern-selection-term">%s</span>', esc_html($term_name));
                            $term_tokens[] = $term_name;
                            if ('' !== $term_slug) {
                                $term_tokens[] = $term_slug;
                            }
                        }
                    }
                    $terms_display = implode('', $term_badges);
                    $terms_for_data = implode(' ', array_unique($term_tokens));

                    $display_date = get_the_date(get_option('date_format'));
                    if (!is_string($display_date)) {
                        $display_date = '';
                    }
                    $machine_date = get_the_date('Y-m-d');
                    if (!is_string($machine_date)) {
                        $machine_date = '';
                    }
                ?>
                    <li
                        class="pattern-selection-item"
                        data-label="<?php echo esc_attr($pattern_title); ?>"
                        data-excerpt="<?php echo esc_attr($excerpt); ?>"
                        data-terms="<?php echo esc_attr($terms_for_data); ?>"
                        data-date="<?php echo esc_attr($machine_date); ?>"
                    >
                        <label class="pattern-selection-label">
                            <input type="checkbox" name="selected_patterns[]" value="<?php echo esc_attr($pattern_id); ?>">
                            <span class="pattern-selection-content">
                                <span class="pattern-selection-title"><?php echo esc_html($pattern_title); ?></span>
                                <?php if ('' !== $display_date || '' !== $terms_display): ?>
                                    <span class="pattern-selection-meta">
                                        <?php if ('' !== $display_date): ?>
                                            <span class="pattern-selection-date"><?php echo esc_html($display_date); ?></span>
                                        <?php endif; ?>
                                        <?php if ('' !== $terms_display): ?>
                                            <span class="pattern-selection-terms" aria-label="<?php echo esc_attr__('Catégories', 'theme-export-jlg'); ?>">
                                                <?php echo wp_kses_post($terms_display); ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ('' !== $excerpt): ?>
                                    <span class="pattern-selection-excerpt"><?php echo esc_html($excerpt); ?></span>
                                <?php endif; ?>
                            </span>
                        </label>
                    </li>
                <?php endwhile; ?>
            </ul>
            <?php wp_reset_postdata(); ?>
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
        <p><label><input type="checkbox" name="export_portable" value="1" checked> <strong><?php esc_html_e('Générer un export "portable"', 'theme-export-jlg'); ?></strong> <?php esc_html_e('(Recommandé pour migrer vers un autre site)', 'theme-export-jlg'); ?></label></p>

        <p><button type="submit" name="tejlg_export_selected_patterns" class="button button-primary button-hero"><?php esc_html_e('Exporter la sélection', 'theme-export-jlg'); ?></button></p>
    </form>
<?php endif; ?>

<?php
require_once __DIR__ . '/class-tejlg-export-history.php';

if (!class_exists('TEJLG_Redirect_Exception')) {
    class TEJLG_Redirect_Exception extends RuntimeException {
        private $redirect_url;

        public function __construct($redirect_url) {
            parent::__construct('tejlg_redirect');
            $this->redirect_url = (string) $redirect_url;
        }

        public function get_redirect_url() {
            return $this->redirect_url;
        }
    }
}

class TEJLG_Admin_Export_Page extends TEJLG_Admin_Page {
    const EXCLUSION_PATTERNS_OPTION = 'tejlg_export_exclusion_patterns';
    const PORTABLE_MODE_OPTION      = 'tejlg_export_portable_mode';
    private $page_slug;

    public function __construct($template_dir, $page_slug) {
        parent::__construct($template_dir);
        $this->page_slug = $page_slug;
    }

    public function handle_request() {
        $this->handle_schedule_settings_submission();

        $theme_export_result = $this->handle_theme_export_form_submission();

        if (null !== $theme_export_result) {
            return;
        }

        if (isset($_POST['tejlg_nonce']) && wp_verify_nonce($_POST['tejlg_nonce'], 'tejlg_export_action')) {
            $is_portable = isset($_POST['export_portable']);
            $this->store_portable_mode_preference($is_portable);
            TEJLG_Export::export_patterns_json([], $is_portable);
        }

        $this->handle_global_styles_export_request();
        $this->handle_selected_patterns_export_request();
        $this->handle_child_theme_request();
    }

    private function handle_schedule_settings_submission() {
        if (!isset($_POST['tejlg_schedule_settings_nonce']) || !wp_verify_nonce($_POST['tejlg_schedule_settings_nonce'], 'tejlg_schedule_settings_action')) {
            return;
        }

        if (!TEJLG_Capabilities::current_user_can('settings')) {
            add_settings_error(
                'tejlg_admin_messages',
                'schedule_settings_permissions',
                esc_html__("Erreur : vous n'avez pas l'autorisation de modifier la planification des exports.", 'theme-export-jlg'),
                'error'
            );

            return;
        }

        $raw_frequency  = isset($_POST['tejlg_schedule_frequency']) ? sanitize_key((string) $_POST['tejlg_schedule_frequency']) : 'disabled';
        $raw_exclusions = isset($_POST['tejlg_schedule_exclusions']) ? wp_unslash((string) $_POST['tejlg_schedule_exclusions']) : '';
        $raw_retention  = isset($_POST['tejlg_schedule_retention']) ? wp_unslash((string) $_POST['tejlg_schedule_retention']) : '';
        $raw_run_time   = isset($_POST['tejlg_schedule_run_time']) ? wp_unslash((string) $_POST['tejlg_schedule_run_time']) : '';

        $retention = is_numeric($raw_retention) ? (int) $raw_retention : 0;
        $retention = $retention < 0 ? 0 : $retention;

        $run_time = '';

        if (is_string($raw_run_time)) {
            $raw_run_time = trim($raw_run_time);

            if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $raw_run_time, $matches)) {
                $run_time = sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
            }
        }

        $settings = [
            'frequency'      => $raw_frequency,
            'exclusions'     => $raw_exclusions,
            'retention_days' => $retention,
            'run_time'       => $run_time,
        ];

        $normalized = TEJLG_Export::update_schedule_settings($settings);

        TEJLG_Export::reschedule_theme_export_event();
        TEJLG_Export::ensure_cleanup_event_scheduled();
        TEJLG_Export::cleanup_persisted_archives($normalized['retention_days']);

        add_settings_error(
            'tejlg_admin_messages',
            'schedule_settings_saved',
            esc_html__('Les réglages de planification ont été enregistrés.', 'theme-export-jlg'),
            'updated'
        );
    }

    public function render() {
        $action_param = filter_input(INPUT_GET, 'action', FILTER_DEFAULT);

        if (null === $action_param && isset($_GET['action'])) {
            $action_param = $_GET['action'];
        }

        $action = is_string($action_param) ? sanitize_key($action_param) : '';

        if ('select_patterns' === $action) {
            $this->render_pattern_selection_page();
            return;
        }

        $this->render_export_default_page();
    }

    private function render_export_default_page() {
        settings_errors('tejlg_admin_messages');

        $child_theme_value = '';

        if (isset($_POST['child_theme_name'])) {
            $raw_child_theme = $_POST['child_theme_name'];

            if (is_scalar($raw_child_theme)) {
                $child_theme_value = sanitize_text_field(wp_unslash($raw_child_theme));
            }
        }

        $exclusion_patterns_value = '';

        if (isset($_POST['tejlg_exclusion_patterns']) && is_string($_POST['tejlg_exclusion_patterns'])) {
            $exclusion_patterns_value = wp_unslash($_POST['tejlg_exclusion_patterns']);
        } else {
            $exclusion_patterns_value = $this->get_saved_exclusion_patterns();
        }

        $portable_mode_enabled = $this->get_portable_mode_preference(false);

        if (isset($_POST['tejlg_nonce']) && wp_verify_nonce($_POST['tejlg_nonce'], 'tejlg_export_action')) {
            $portable_mode_enabled = isset($_POST['export_portable']);
        }

        $schedule_settings   = TEJLG_Export::get_schedule_settings();
        $schedule_frequencies = TEJLG_Export::get_available_schedule_frequencies();
        $schedule_next_run    = TEJLG_Export::get_next_scheduled_export_timestamp();

        $history_per_page = (int) apply_filters('tejlg_export_history_per_page', 10);
        $history_per_page = $history_per_page > 0 ? $history_per_page : 10;

        $history_page = isset($_GET['history_page']) ? absint($_GET['history_page']) : 0;
        $history_page = $history_page > 0 ? $history_page : 1;

        $history = TEJLG_Export_History::get_entries([
            'per_page' => $history_per_page,
            'paged'    => $history_page,
        ]);

        $history_total_pages = isset($history['total_pages']) ? (int) $history['total_pages'] : 1;
        $history_total_pages = $history_total_pages > 0 ? $history_total_pages : 1;

        $history_base_url = add_query_arg([
            'page' => $this->page_slug,
            'tab'  => 'export',
        ], admin_url('admin.php'));

        $history_pagination_links = paginate_links([
            'base'      => add_query_arg('history_page', '%#%', $history_base_url),
            'format'    => '',
            'current'   => isset($history['current_page']) ? (int) $history['current_page'] : 1,
            'total'     => $history_total_pages,
            'type'      => 'array',
            'add_args'  => false,
        ]);

        $this->render_template('export.php', [
            'page_slug'                 => $this->page_slug,
            'child_theme_value'         => $child_theme_value,
            'exclusion_patterns_value'  => $exclusion_patterns_value,
            'portable_mode_enabled'     => $portable_mode_enabled,
            'schedule_settings'         => $schedule_settings,
            'schedule_frequencies'      => $schedule_frequencies,
            'schedule_next_run'         => $schedule_next_run,
            'history_entries'           => isset($history['entries']) ? (array) $history['entries'] : [],
            'history_total'             => isset($history['total']) ? (int) $history['total'] : 0,
            'history_pagination_links'  => is_array($history_pagination_links) ? $history_pagination_links : [],
            'history_current_page'      => isset($history['current_page']) ? (int) $history['current_page'] : 1,
            'history_total_pages'       => $history_total_pages,
            'history_per_page'          => $history_per_page,
        ]);
    }

    private function render_pattern_selection_page() {
        settings_errors('tejlg_admin_messages');

        $per_page     = (int) apply_filters('tejlg_patterns_selection_per_page', 100);
        $per_page     = $per_page < 1 ? -1 : $per_page;
        $current_page = isset($_GET['pattern_page']) ? max(1, absint($_GET['pattern_page'])) : 1;

        $query_args = [
            'post_type'              => 'wp_block',
            'posts_per_page'         => $per_page,
            'post_status'            => 'publish',
            'orderby'                => 'title',
            'order'                  => 'ASC',
            'paged'                  => $per_page < 1 ? 1 : $current_page,
            'no_found_rows'          => $per_page < 1,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];

        $patterns_query  = new WP_Query($query_args);
        $total_pages     = $per_page < 1 ? 1 : (int) $patterns_query->max_num_pages;
        $pattern_entries = [];

        $global_styles = function_exists('wp_get_global_stylesheet') ? wp_get_global_stylesheet() : '';
        if (!is_string($global_styles)) {
            $global_styles = '';
        }

        $preview_stylesheets = $this->get_preview_stylesheet_urls();

        $json_options = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $json_options |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        $stylesheet_links_markup = '';
        foreach ($preview_stylesheets as $stylesheet_url) {
            $stylesheet_links_markup .= '<link rel="stylesheet" href="' . esc_url($stylesheet_url) . '" />';
        }

        $stylesheets_json = wp_json_encode($preview_stylesheets, $json_options);
        if (false === $stylesheets_json) {
            $stylesheets_json = '[]';
        }

        $stylesheet_links_json = wp_json_encode($stylesheet_links_markup, $json_options);
        if (false === $stylesheet_links_json) {
            $stylesheet_links_json = '""';
        }

        $pattern_counter = $per_page > 0 ? (($current_page - 1) * $per_page) : 0;

        if ($patterns_query->have_posts()) {
            while ($patterns_query->have_posts()) {
                $patterns_query->the_post();
                $pattern_counter++;

                $post_object = get_post();

                if (!$post_object instanceof WP_Post) {
                    continue;
                }

                $prepared_entry = $this->prepare_pattern_preview_entry(
                    $post_object,
                    $pattern_counter,
                    $global_styles,
                    $stylesheet_links_markup,
                    $stylesheets_json,
                    $stylesheet_links_json,
                    $json_options
                );

                if (!empty($prepared_entry)) {
                    $pattern_entries[] = $prepared_entry;
                }
            }
        }

        $pagination_base = add_query_arg(
            [
                'page'   => $this->page_slug,
                'tab'    => 'export',
                'action' => 'select_patterns',
            ],
            admin_url('admin.php')
        );
        $pagination_base = add_query_arg('pattern_page', '%#%', $pagination_base);

        $portable_mode_enabled = $this->get_portable_mode_preference(true);

        $this->render_template('export-pattern-selection.php', [
            'page_slug'         => $this->page_slug,
            'patterns_query'    => $patterns_query,
            'pattern_entries'   => $pattern_entries,
            'per_page'          => $per_page,
            'current_page'      => $current_page,
            'total_pages'       => $total_pages,
            'pagination_base'   => $pagination_base,
            'portable_mode_enabled' => $portable_mode_enabled,
        ]);

        wp_reset_postdata();
    }

    private function get_preview_stylesheet_urls() {
        $preview_stylesheets = [];

        $primary_stylesheet_uri = get_stylesheet_uri();
        if (is_string($primary_stylesheet_uri)) {
            $preview_stylesheets[] = $primary_stylesheet_uri;
        }

        $normalized = [];

        foreach ($preview_stylesheets as $candidate_url) {
            if (!is_string($candidate_url)) {
                continue;
            }

            $candidate_url = trim($candidate_url);

            if ('' === $candidate_url) {
                continue;
            }

            $validated_url = wp_http_validate_url($candidate_url);

            if (false === $validated_url) {
                if (0 === strpos($candidate_url, '//')) {
                    $https_url = 'https:' . $candidate_url;
                    $validated_url = wp_http_validate_url($https_url);

                    if (false === $validated_url) {
                        $http_url = 'http:' . $candidate_url;
                        $validated_url = wp_http_validate_url($http_url);
                    }
                }

                if (false === $validated_url) {
                    $candidate_path = '/' . ltrim($candidate_url, '/');
                    $home_based_url = home_url($candidate_path);
                    $validated_url   = wp_http_validate_url($home_based_url);
                }
            }

            if (false !== $validated_url && !in_array($validated_url, $normalized, true)) {
                $normalized[] = $validated_url;
            }
        }

        return $normalized;
    }

    private function prepare_pattern_preview_entry(
        WP_Post $post,
        $display_index,
        $global_styles,
        $stylesheet_links_markup,
        $stylesheets_json,
        $stylesheet_links_json,
        $json_options
    ) {
        $pattern_id = (int) $post->ID;

        $raw_title = get_the_title($post);
        if (!is_scalar($raw_title)) {
            $raw_title = '';
        }
        $title = trim((string) $raw_title);

        if ('' === $title) {
            $title = sprintf(__('Composition sans titre #%d', 'theme-export-jlg'), (int) $display_index);
        }

        $raw_content = get_post_field('post_content', $pattern_id);
        if (!is_string($raw_content)) {
            $raw_content = '';
        }

        $parsed_blocks = '' !== $raw_content ? parse_blocks($raw_content) : [];
        $rendered_pattern = '';

        if (!empty($parsed_blocks)) {
            $rendered_pattern = $this->render_blocks_preview($parsed_blocks);
        }

        if ('' === $rendered_pattern) {
            $rendered_pattern = $raw_content;
        }

        $sanitized_rendered = wp_kses_post($rendered_pattern);

        $iframe_content = '<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . $stylesheet_links_markup
            . '<style>' . $global_styles . '</style>'
            . '</head><body class="block-editor-writing-flow">'
            . $sanitized_rendered
            . '</body></html>';

        $iframe_json = wp_json_encode($iframe_content, $json_options);

        if (false === $iframe_json) {
            $iframe_json = '""';
        }

        $iframe_title = sprintf(__('Aperçu : %s', 'theme-export-jlg'), $title);

        $raw_excerpt = get_the_excerpt($post);
        if (!is_string($raw_excerpt)) {
            $raw_excerpt = '';
        }

        $excerpt = trim(wp_strip_all_tags($raw_excerpt));

        if ('' === $excerpt) {
            $excerpt = trim(wp_strip_all_tags($raw_content));
        }

        if ('' !== $excerpt) {
            $excerpt = wp_trim_words($excerpt, 30, '…');
        }

        $terms = get_the_terms($pattern_id, 'wp_pattern_category');
        $term_labels = [];
        $term_tokens = [];

        if (is_array($terms) && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                if (!is_object($term)) {
                    continue;
                }

                $term_name = isset($term->name) && is_scalar($term->name)
                    ? trim((string) $term->name)
                    : '';

                $term_slug = isset($term->slug) && is_scalar($term->slug)
                    ? trim((string) $term->slug)
                    : '';

                if ('' === $term_name) {
                    continue;
                }

                $term_labels[] = $term_name;
                $term_tokens[] = $term_name;

                if ('' !== $term_slug) {
                    $term_tokens[] = $term_slug;
                }
            }
        }

        if (!empty($term_tokens)) {
            $term_tokens = array_values(array_unique($term_tokens));
        }

        $date_display = get_the_date(get_option('date_format'), $post);
        if (!is_string($date_display)) {
            $date_display = '';
        }

        $date_machine = get_the_date('Y-m-d', $post);
        if (!is_string($date_machine)) {
            $date_machine = '';
        }

        $timestamp = get_post_time('U', true, $post);
        $timestamp_value = is_numeric($timestamp) ? (string) (int) $timestamp : '';

        $search_components = array_filter(
            [
                $title,
                implode(' ', $term_tokens),
                $excerpt,
                $date_display,
                $date_machine,
            ],
            static function ($component) {
                return '' !== trim((string) $component);
            }
        );

        $search_haystack = '';

        if (!empty($search_components)) {
            $haystack = implode(' ', $search_components);
            if (function_exists('mb_strtolower')) {
                $search_haystack = mb_strtolower($haystack, 'UTF-8');
            } else {
                $search_haystack = strtolower($haystack);
            }
        }

        $preview_live_id = 'pattern-preview-live-export-' . $pattern_id;

        return [
            'id'                     => $pattern_id,
            'title'                  => $title,
            'excerpt'                => $excerpt,
            'date_display'           => $date_display,
            'date_machine'           => $date_machine,
            'timestamp'              => $timestamp_value,
            'term_labels'            => $term_labels,
            'term_tokens'            => $term_tokens,
            'iframe_json'            => $iframe_json,
            'iframe_title'           => $iframe_title,
            'stylesheets_json'       => $stylesheets_json,
            'stylesheet_links_json'  => $stylesheet_links_json,
            'search_haystack'        => $search_haystack,
            'preview_live_id'        => $preview_live_id,
            'display_index'          => (int) $display_index,
        ];
    }

    private function render_blocks_preview(array $blocks) {
        $output = '';

        foreach ($blocks as $block) {
            $output .= $this->render_block_preview($block);
        }

        return $output;
    }

    private function render_block_preview(array $block) {
        if (empty($block['blockName'])) {
            return isset($block['innerHTML']) ? $block['innerHTML'] : '';
        }

        $block_name = $block['blockName'];
        $block_type = WP_Block_Type_Registry::get_instance()->get_registered($block_name);

        $is_dynamic = ('core/shortcode' === $block_name);

        if ($block_type instanceof WP_Block_Type && !empty($block_type->render_callback)) {
            $is_dynamic = true;
        } elseif (!$block_type) {
            $is_dynamic = true;
        }

        $rendered_inner_blocks = [];
        if (!empty($block['innerBlocks'])) {
            foreach ($block['innerBlocks'] as $inner_block) {
                $rendered_inner_blocks[] = $this->render_block_preview($inner_block);
            }
        }

        if (!empty($block['innerContent'])) {
            $content = '';
            $inner_index = 0;

            foreach ($block['innerContent'] as $chunk) {
                if (null === $chunk) {
                    $content .= isset($rendered_inner_blocks[$inner_index]) ? $rendered_inner_blocks[$inner_index] : '';
                    $inner_index++;
                } else {
                    $content .= $chunk;
                }
            }

            if ('' !== trim($content) || !empty($rendered_inner_blocks)) {
                return $content;
            }
        }

        if (isset($block['innerHTML'])) {
            if ('' !== trim($block['innerHTML'])) {
                return $block['innerHTML'];
            }
        }

        if (!empty($rendered_inner_blocks)) {
            return implode('', $rendered_inner_blocks);
        }

        if ($is_dynamic) {
            return $this->get_dynamic_block_placeholder($block_name);
        }

        return '';
    }

    private function get_dynamic_block_placeholder($block_name) {
        $block_label = $block_name ? $block_name : __('bloc inconnu', 'theme-export-jlg');
        $placeholder_text = sprintf(
            /* translators: %s: dynamic block name. */
            esc_html__('Bloc dynamique "%s" non rendu dans cet aperçu.', 'theme-export-jlg'),
            esc_html($block_label)
        );

        $icon_svg = '<svg class="tejlg-block-placeholder__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" role="img">'
            . '<path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10.011 10.011 0 0 0 12 2Zm0 15.25a1.25 1.25 0 1 1 1.25-1.25A1.251 1.251 0 0 1 12 17.25Zm1.5-5.75a1.5 1.5 0 0 1-3 0V7.75a1.5 1.5 0 0 1 3 0Z"/></svg>';

        return '<div class="tejlg-block-placeholder" role="note">' . $icon_svg . '<p class="tejlg-block-placeholder__message">'
            . $placeholder_text . '</p></div>';
    }

    private function handle_global_styles_export_request() {
        if (!isset($_POST['tejlg_export_global_styles_nonce']) || !wp_verify_nonce($_POST['tejlg_export_global_styles_nonce'], 'tejlg_export_global_styles_action')) {
            return;
        }

        TEJLG_Export::export_global_styles();
    }

    private function handle_selected_patterns_export_request() {
        if (!isset($_POST['tejlg_export_selected_nonce']) || !wp_verify_nonce($_POST['tejlg_export_selected_nonce'], 'tejlg_export_selected_action')) {
            return;
        }

        $selected_patterns = isset($_POST['selected_patterns'])
            ? $this->sanitize_pattern_ids($_POST['selected_patterns'])
            : [];

        $is_portable = isset($_POST['export_portable']);
        $this->store_portable_mode_preference($is_portable);

        if (!empty($selected_patterns)) {
            TEJLG_Export::export_selected_patterns_json($selected_patterns, $is_portable);
            return;
        }

        add_settings_error(
            'tejlg_admin_messages',
            'patterns_export_no_selection',
            esc_html__("Erreur : Veuillez sélectionner au moins une composition avant de lancer l'export.", 'theme-export-jlg'),
            'error'
        );
    }

    private function handle_child_theme_request() {
        if (!isset($_POST['tejlg_create_child_nonce']) || !wp_verify_nonce($_POST['tejlg_create_child_nonce'], 'tejlg_create_child_action')) {
            return;
        }

        if (!current_user_can('install_themes')) {
            add_settings_error(
                'tejlg_admin_messages',
                'child_theme_capabilities',
                esc_html__("Erreur : Vous n'avez pas les autorisations nécessaires pour créer un thème enfant.", 'theme-export-jlg'),
                'error'
            );

            return;
        }

        if (!isset($_POST['child_theme_name'])) {
            return;
        }

        TEJLG_Theme_Tools::create_child_theme(sanitize_text_field(wp_unslash($_POST['child_theme_name'])));
    }

    public function handle_theme_export_form_submission() {
        if (!isset($_POST['tejlg_theme_export_nonce']) || !wp_verify_nonce($_POST['tejlg_theme_export_nonce'], 'tejlg_theme_export_action')) {
            return null;
        }

        $raw_exclusions = isset($_POST['tejlg_exclusion_patterns'])
            ? wp_unslash((string) $_POST['tejlg_exclusion_patterns'])
            : '';

        $this->store_exclusion_patterns($raw_exclusions);

        $exclusions = TEJLG_Export::sanitize_exclusion_patterns($raw_exclusions);

        add_filter('tejlg_export_run_jobs_immediately', '__return_true');

        try {
            $result = TEJLG_Export::export_theme($exclusions);
        } finally {
            remove_filter('tejlg_export_run_jobs_immediately', '__return_true');
        }

        if (is_wp_error($result)) {
            $this->notify_and_redirect(
                'error',
                'theme_export_error',
                esc_html($result->get_error_message())
            );
        }

        $job_id = (string) $result;

        TEJLG_Export::run_pending_export_jobs();

        $job = TEJLG_Export::get_job($job_id);

        if (!is_array($job)) {
            $this->notify_and_redirect(
                'error',
                'theme_export_job_missing',
                esc_html__("Erreur : la tâche d'export générée est introuvable.", 'theme-export-jlg')
            );
        }

        if (!isset($job['status']) || 'completed' !== $job['status']) {
            $message = isset($job['message']) && is_string($job['message']) && '' !== $job['message']
                ? $job['message']
                : esc_html__("L'export du thème n'a pas pu être finalisé.", 'theme-export-jlg');
            $this->notify_and_redirect(
                'error',
                'theme_export_job_incomplete',
                esc_html($message)
            );
        }

        $zip_path = isset($job['zip_path']) ? (string) $job['zip_path'] : '';

        if ('' === $zip_path || !file_exists($zip_path)) {
            TEJLG_Export::delete_job($job_id, [
                'origin' => 'admin',
                'reason' => 'missing_zip',
            ]);
            $this->notify_and_redirect(
                'error',
                'theme_export_zip_missing',
                esc_html__('Le fichier ZIP généré est introuvable.', 'theme-export-jlg')
            );
        }

        $zip_file_name = isset($job['zip_file_name']) && '' !== $job['zip_file_name']
            ? $job['zip_file_name']
            : basename($zip_path);

        $zip_file_size = isset($job['zip_file_size'])
            ? (int) $job['zip_file_size']
            : (int) filesize($zip_path);

        $should_stream = apply_filters('tejlg_export_stream_zip_archive', true, $zip_path, $zip_file_name, $zip_file_size);

        if (!$should_stream) {
            $message = sprintf(
                /* translators: %s: generated ZIP file path. */
                esc_html__("Export du thème réussi. Archive générée : %s", 'theme-export-jlg'),
                esc_html($zip_path)
            );

            $this->notify_and_redirect(
                'success',
                'theme_export_success',
                $message
            );
        }

        nocache_headers();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zip_file_name . '"');

        if ($zip_file_size > 0) {
            header('Content-Length: ' . (string) $zip_file_size);
        }

        $handle = fopen($zip_path, 'rb');

        if (false === $handle) {
            TEJLG_Export::delete_job($job_id, [
                'origin' => 'admin',
                'reason' => 'unreadable_zip',
            ]);
            $this->notify_and_redirect(
                'error',
                'theme_export_zip_unreadable',
                esc_html__("Impossible de lire l'archive ZIP générée.", 'theme-export-jlg')
            );
        }

        while (!feof($handle)) {
            echo fread($handle, 8192);
        }

        fclose($handle);

        TEJLG_Export::delete_job($job_id, [
            'origin' => 'admin',
            'reason' => 'downloaded',
        ]);

        flush();
        exit;
    }

    private function store_exclusion_patterns($patterns) {
        $sanitized = TEJLG_Export::sanitize_exclusion_patterns_string($patterns);

        update_option(self::EXCLUSION_PATTERNS_OPTION, $sanitized, false);
    }

    private function get_saved_exclusion_patterns() {
        $stored = get_option(self::EXCLUSION_PATTERNS_OPTION, '');

        if (!is_string($stored)) {
            $stored = '';
        }

        $sanitized = TEJLG_Export::sanitize_exclusion_patterns_string($stored);

        if ($sanitized !== $stored) {
            update_option(self::EXCLUSION_PATTERNS_OPTION, $sanitized, false);
        }

        return $sanitized;
    }

    private function store_portable_mode_preference($is_portable) {
        update_option(self::PORTABLE_MODE_OPTION, $is_portable ? '1' : '0');
    }

    private function get_portable_mode_preference($default) {
        $stored = get_option(self::PORTABLE_MODE_OPTION, null);

        if (null === $stored) {
            return (bool) $default;
        }

        return '1' === (string) $stored;
    }

    private function notify_and_redirect($type, $code, $message) {
        add_settings_error(
            'tejlg_admin_messages',
            $code,
            $message,
            $type
        );

        $errors = get_settings_errors('tejlg_admin_messages');

        if (!empty($errors)) {
            set_transient('settings_errors', $errors, 30);
        }

        $redirect_url = add_query_arg(
            [
                'page' => $this->page_slug,
                'tab'  => 'export',
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect_url);

        if (defined('TEJLG_BYPASS_REDIRECT_EXIT') && TEJLG_BYPASS_REDIRECT_EXIT) {
            throw new TEJLG_Redirect_Exception($redirect_url);
        }

        exit;
    }

    private function sanitize_pattern_ids($pattern_ids) {
        $sanitized = [];

        foreach ((array) $pattern_ids as $pattern_id) {
            if (!is_scalar($pattern_id) || !is_numeric($pattern_id)) {
                continue;
            }

            $pattern_id = (int) $pattern_id;

            if ($pattern_id <= 0) {
                continue;
            }

            $sanitized[$pattern_id] = $pattern_id;
        }

        return array_values($sanitized);
    }

    public static function ajax_preview_exclusion_patterns() {
        if (!TEJLG_Capabilities::current_user_can('exports')) {
            wp_send_json_error([
                'message' => esc_html__("Erreur : vous n'avez pas l'autorisation d'effectuer cette action.", 'theme-export-jlg'),
            ], 403);
        }

        check_ajax_referer('tejlg_preview_exclusion_patterns', 'nonce');

        $raw_patterns = isset($_POST['patterns'])
            ? wp_unslash((string) $_POST['patterns'])
            : '';

        $preview = TEJLG_Export::preview_theme_export_files($raw_patterns);

        if (is_wp_error($preview)) {
            $data = [
                'message' => $preview->get_error_message(),
            ];

            $error_data = $preview->get_error_data();

            if (is_array($error_data)) {
                if (isset($error_data['invalid_patterns'])) {
                    $invalid_patterns = array_map('strval', (array) $error_data['invalid_patterns']);
                    $data['invalid_patterns'] = array_values(array_unique($invalid_patterns));
                }

                if (isset($error_data['message']) && !empty($error_data['message']) && is_string($error_data['message'])) {
                    $data['message'] = $error_data['message'];
                }
            }

            $status = is_array($error_data) && isset($error_data['status']) ? (int) $error_data['status'] : 400;

            if ($status < 200 || $status >= 600) {
                $status = 400;
            }

            wp_send_json_error($data, $status);
        }

        wp_send_json_success($preview);
    }
}

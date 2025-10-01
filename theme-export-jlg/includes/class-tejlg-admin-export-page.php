<?php

class TEJLG_Admin_Export_Page {

    /**
     * Process form submissions for the export tab.
     */
    public function handle_request() {
        $theme_export_result = $this->handle_theme_export_form_submission();

        if (null !== $theme_export_result) {
            return;
        }

        if ($this->is_nonce_valid('tejlg_nonce', 'tejlg_export_action')) {
            if (isset($_POST['tejlg_export_patterns'])) {
                $is_portable = isset($_POST['export_portable']);
                TEJLG_Export::export_patterns_json([], $is_portable);
            }
        }

        if ($this->is_nonce_valid('tejlg_export_global_styles_nonce', 'tejlg_export_global_styles_action')) {
            TEJLG_Export::export_global_styles();
        }

        if ($this->is_nonce_valid('tejlg_export_selected_nonce', 'tejlg_export_selected_action')) {
            $selected_patterns = isset($_POST['selected_patterns'])
                ? $this->sanitize_pattern_ids($_POST['selected_patterns'])
                : [];

            if (!empty($selected_patterns)) {
                $is_portable = isset($_POST['export_portable']);
                TEJLG_Export::export_selected_patterns_json($selected_patterns, $is_portable);
            } else {
                add_settings_error(
                    'tejlg_admin_messages',
                    'patterns_export_no_selection',
                    esc_html__("Erreur : Veuillez sélectionner au moins une composition avant de lancer l'export.", 'theme-export-jlg'),
                    'error'
                );
            }
        }

        if ($this->is_nonce_valid('tejlg_create_child_nonce', 'tejlg_create_child_action')) {
            $this->handle_child_theme_request();
        }
    }

    /**
     * Render the export tab.
     */
    public function render() {
        $action_param = filter_input(INPUT_GET, 'action', FILTER_DEFAULT);

        if (null === $action_param && isset($_GET['action'])) {
            $action_param = $_GET['action'];
        }

        $action = is_string($action_param) ? sanitize_key($action_param) : '';

        if ('select_patterns' === $action) {
            $this->render_pattern_selection_page();
        } else {
            $this->render_default_page();
        }
    }

    /**
     * Handle the main theme export form submission.
     *
     * @return array|null
     */
    public function handle_theme_export_form_submission() {
        if (!$this->is_nonce_valid('tejlg_theme_export_nonce', 'tejlg_theme_export_action')) {
            return null;
        }

        $raw_exclusions = isset($_POST['tejlg_exclusion_patterns'])
            ? wp_unslash((string) $_POST['tejlg_exclusion_patterns'])
            : '';

        $exclusions = [];

        if ('' !== $raw_exclusions) {
            $split = preg_split('/[,\n]+/', $raw_exclusions);

            if (false !== $split) {
                $exclusions = array_values(
                    array_filter(
                        array_map(
                            static function ($pattern) {
                                return trim((string) $pattern);
                            },
                            $split
                        ),
                        static function ($pattern) {
                            return '' !== $pattern;
                        }
                    )
                );
            }
        }

        add_filter('tejlg_export_run_jobs_immediately', '__return_true');

        try {
            $result = TEJLG_Export::export_theme($exclusions);
        } finally {
            remove_filter('tejlg_export_run_jobs_immediately', '__return_true');
        }

        if (is_wp_error($result)) {
            wp_die(esc_html($result->get_error_message()));
        }

        $job_id = (string) $result;

        TEJLG_Export::run_pending_export_jobs();

        $job = TEJLG_Export::get_job($job_id);

        if (!is_array($job)) {
            wp_die(esc_html__("Erreur : la tâche d'export générée est introuvable.", 'theme-export-jlg'));
        }

        if (!isset($job['status']) || 'completed' !== $job['status']) {
            $message = isset($job['message']) && is_string($job['message']) && '' !== $job['message']
                ? $job['message']
                : esc_html__("L'export du thème n'a pas pu être finalisé.", 'theme-export-jlg');
            wp_die(esc_html($message));
        }

        $zip_path = isset($job['zip_path']) ? (string) $job['zip_path'] : '';

        if ('' === $zip_path || !file_exists($zip_path)) {
            TEJLG_Export::delete_job($job_id);
            wp_die(esc_html__('Le fichier ZIP généré est introuvable.', 'theme-export-jlg'));
        }

        $zip_file_name = isset($job['zip_file_name']) && '' !== $job['zip_file_name']
            ? $job['zip_file_name']
            : basename($zip_path);

        $zip_file_size = isset($job['zip_file_size'])
            ? (int) $job['zip_file_size']
            : (int) filesize($zip_path);

        $should_stream = apply_filters('tejlg_export_stream_zip_archive', true, $zip_path, $zip_file_name, $zip_file_size);

        if (!$should_stream) {
            return [
                'job_id'   => $job_id,
                'path'     => $zip_path,
                'filename' => $zip_file_name,
                'size'     => $zip_file_size,
            ];
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
            TEJLG_Export::delete_job($job_id);
            wp_die(esc_html__("Impossible de lire l'archive ZIP générée.", 'theme-export-jlg'));
        }

        while (!feof($handle)) {
            echo fread($handle, 8192);
        }

        fclose($handle);

        TEJLG_Export::delete_job($job_id);

        flush();
        exit;
    }

    private function render_default_page() {
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
        }

        $export_tab_url      = $this->get_export_tab_url();
        $select_patterns_url = add_query_arg(
            [
                'page'   => 'theme-export-jlg',
                'tab'    => 'export',
                'action' => 'select_patterns',
            ],
            admin_url('admin.php')
        );

        $this->render_template('export', [
            'export_tab_url'            => $export_tab_url,
            'select_patterns_url'       => $select_patterns_url,
            'child_theme_value'         => $child_theme_value,
            'exclusion_patterns_value'  => $exclusion_patterns_value,
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
        $pattern_counter = $per_page > 0 ? (($current_page - 1) * $per_page) : 0;

        $patterns = [];

        while ($patterns_query->have_posts()) {
            $patterns_query->the_post();
            $pattern_counter++;
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

            $patterns[] = [
                'id'    => get_the_ID(),
                'title' => $pattern_title,
            ];
        }

        wp_reset_postdata();

        $pagination_base = add_query_arg(
            [
                'page'   => 'theme-export-jlg',
                'tab'    => 'export',
                'action' => 'select_patterns',
            ],
            admin_url('admin.php')
        );
        $pagination_base = add_query_arg('pattern_page', '%#%', $pagination_base);

        $pagination_links = '';

        if ($per_page > 0 && $total_pages > 1) {
            $pagination_links = paginate_links([
                'base'      => $pagination_base,
                'format'    => '',
                'current'   => $current_page,
                'total'     => $total_pages,
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
            ]);
        }

        $this->render_template('export-pattern-selection', [
            'export_tab_url'    => $this->get_export_tab_url(),
            'patterns'          => $patterns,
            'has_patterns'      => !empty($patterns),
            'pagination_links'  => $pagination_links,
        ]);
    }

    private function handle_child_theme_request() {
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
            add_settings_error(
                'tejlg_admin_messages',
                'child_theme_missing_name',
                esc_html__("Erreur : Veuillez fournir un nom pour le thème enfant.", 'theme-export-jlg'),
                'error'
            );
            return;
        }

        $raw_child_name = wp_unslash($_POST['child_theme_name']);
        $child_name     = sanitize_text_field($raw_child_name);

        if ('' === $child_name) {
            add_settings_error(
                'tejlg_admin_messages',
                'child_theme_empty_name',
                esc_html__("Erreur : Le nom du thème enfant ne peut pas être vide.", 'theme-export-jlg'),
                'error'
            );
            return;
        }

        $result = TEJLG_Theme_Tools::create_child_theme($child_name);

        if (is_wp_error($result)) {
            add_settings_error(
                'tejlg_admin_messages',
                'child_theme_error',
                esc_html($result->get_error_message()),
                'error'
            );
            return;
        }

        add_settings_error(
            'tejlg_admin_messages',
            'child_theme_created',
            esc_html__("Succès : Le thème enfant a été créé dans le dossier wp-content/themes.", 'theme-export-jlg'),
            'updated'
        );
    }

    private function sanitize_pattern_ids($pattern_ids) {
        if (!is_array($pattern_ids)) {
            return [];
        }

        $sanitized = [];

        foreach ($pattern_ids as $pattern_id) {
            if (is_numeric($pattern_id)) {
                $sanitized[] = (int) $pattern_id;
            }
        }

        return array_values(array_unique($sanitized));
    }

    private function is_nonce_valid($nonce_key, $action) {
        return isset($_POST[$nonce_key]) && wp_verify_nonce($_POST[$nonce_key], $action);
    }

    private function get_export_tab_url() {
        return add_query_arg(
            [
                'page' => 'theme-export-jlg',
                'tab'  => 'export',
            ],
            admin_url('admin.php')
        );
    }

    private function render_template($template, array $context = []) {
        $template_path = $this->locate_template($template);

        if (!$template_path) {
            return;
        }

        extract($context);
        include $template_path;
    }

    private function locate_template($template) {
        $template_directory = defined('TEJLG_PATH')
            ? trailingslashit(TEJLG_PATH) . 'templates/admin/'
            : trailingslashit(dirname(__DIR__)) . 'templates/admin/';

        $file = $template_directory . $template . '.php';

        if (file_exists($file)) {
            return $file;
        }

        return false;
    }
}

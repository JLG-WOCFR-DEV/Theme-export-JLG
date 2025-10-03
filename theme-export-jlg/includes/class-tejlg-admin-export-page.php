<?php

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

    public static function get_exclusion_presets_catalog() {
        return [
            'node_modules'      => [
                'label'       => esc_html__('Dépendances Node.js', 'theme-export-jlg'),
                'description' => esc_html__("Ignore les dossiers et sous-dossiers node_modules générés par npm ou yarn.", 'theme-export-jlg'),
                'patterns'    => [
                    'node_modules',
                    '*/node_modules',
                    '**/node_modules/**',
                ],
            ],
            'temporary_files'  => [
                'label'       => esc_html__('Fichiers temporaires', 'theme-export-jlg'),
                'description' => esc_html__("Exclut les fichiers temporaires et caches fréquemment créés localement.", 'theme-export-jlg'),
                'patterns'    => [
                    '*.log',
                    '*.tmp',
                    '.DS_Store',
                    'Thumbs.db',
                    '.cache',
                    '**/.cache/**',
                ],
            ],
            'uncompiled_assets' => [
                'label'       => esc_html__('Sources non compilées', 'theme-export-jlg'),
                'description' => esc_html__("Omet les sources front-end brutes (SCSS, TS, JSX…) pour un package plus léger.", 'theme-export-jlg'),
                'patterns'    => [
                    'assets/**/*.scss',
                    'assets/**/*.sass',
                    'assets/**/*.less',
                    'assets/**/*.ts',
                    'assets/**/*.tsx',
                    'assets/**/*.jsx',
                ],
            ],
        ];
    }

    public static function normalize_exclusion_option($stored) {
        if (is_array($stored)) {
            $presets = [];

            if (isset($stored['presets']) && is_array($stored['presets'])) {
                $presets = array_values(array_filter(array_map('sanitize_key', $stored['presets'])));
            }

            $custom = '';

            if (isset($stored['custom']) && is_string($stored['custom'])) {
                $custom = $stored['custom'];
            }

            return [
                'presets' => $presets,
                'custom'  => $custom,
            ];
        }

        if (is_string($stored)) {
            return [
                'presets' => [],
                'custom'  => $stored,
            ];
        }

        return [
            'presets' => [],
            'custom'  => '',
        ];
    }

    public static function extract_exclusion_selection_from_request($source) {
        $presets = [];
        $custom  = '';

        if (isset($source['tejlg_exclusion_presets'])) {
            $presets = $source['tejlg_exclusion_presets'];
        }

        if (array_key_exists('tejlg_exclusion_custom', $source)) {
            $custom = $source['tejlg_exclusion_custom'];
        }

        if ('' === $custom && isset($source['tejlg_exclusion_patterns']) && is_string($source['tejlg_exclusion_patterns'])) {
            $custom = $source['tejlg_exclusion_patterns'];
        }

        if (empty($presets) && '' === $custom && isset($source['exclusions']) && is_string($source['exclusions'])) {
            $custom = $source['exclusions'];
        }

        return self::sanitize_exclusion_selection($presets, $custom);
    }

    public static function sanitize_exclusion_selection($raw_presets, $raw_custom) {
        $catalog = self::get_exclusion_presets_catalog();
        $presets = [];

        if (is_array($raw_presets)) {
            foreach ($raw_presets as $preset) {
                $key = sanitize_key($preset);

                if (!isset($catalog[$key])) {
                    continue;
                }

                if (in_array($key, $presets, true)) {
                    continue;
                }

                $presets[] = $key;
            }
        }

        $custom = '';

        if (is_string($raw_custom)) {
            $custom = wp_unslash($raw_custom);
        }

        return [
            'presets' => $presets,
            'custom'  => $custom,
        ];
    }

    public static function store_exclusion_preferences(array $selection) {
        $normalized = self::normalize_exclusion_option($selection);
        update_option(self::EXCLUSION_PATTERNS_OPTION, $normalized);
    }

    public static function get_saved_exclusion_preferences() {
        $stored = get_option(self::EXCLUSION_PATTERNS_OPTION, [
            'presets' => [],
            'custom'  => '',
        ]);

        $normalized = self::normalize_exclusion_option($stored);

        $catalog = self::get_exclusion_presets_catalog();
        $normalized['presets'] = array_values(array_filter(
            $normalized['presets'],
            static function ($preset_key) use ($catalog) {
                return isset($catalog[$preset_key]);
            }
        ));

        return $normalized;
    }

    public static function build_exclusion_list(array $selection) {
        $catalog   = self::get_exclusion_presets_catalog();
        $patterns  = [];
        $processed = [];

        foreach ($selection['presets'] as $preset_key) {
            if (!isset($catalog[$preset_key]) || !isset($catalog[$preset_key]['patterns'])) {
                continue;
            }

            foreach ((array) $catalog[$preset_key]['patterns'] as $pattern) {
                $pattern = (string) $pattern;

                if ('' === $pattern || isset($processed[$pattern])) {
                    continue;
                }

                $patterns[]           = $pattern;
                $processed[$pattern]   = true;
            }
        }

        if (isset($selection['custom']) && is_string($selection['custom'])) {
            $custom_raw = $selection['custom'];

            if ('' !== $custom_raw) {
                $split = preg_split('/[,\r\n]+/', $custom_raw);

                if (false !== $split) {
                    foreach ($split as $pattern) {
                        $pattern = trim((string) $pattern);

                        if ('' === $pattern || isset($processed[$pattern])) {
                            continue;
                        }

                        $patterns[]         = $pattern;
                        $processed[$pattern] = true;
                    }
                }
            }
        }

        return $patterns;
    }

    public function __construct($template_dir, $page_slug) {
        parent::__construct($template_dir);
        $this->page_slug = $page_slug;
    }

    public function handle_request() {
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

        $exclusion_selection = self::get_saved_exclusion_preferences();

        if (isset($_POST['tejlg_exclusion_presets']) || array_key_exists('tejlg_exclusion_custom', $_POST)) {
            $exclusion_selection = self::extract_exclusion_selection_from_request($_POST);
        }

        $exclusion_summary = self::build_exclusion_list($exclusion_selection);

        $portable_mode_enabled = $this->get_portable_mode_preference(false);

        if (isset($_POST['tejlg_nonce']) && wp_verify_nonce($_POST['tejlg_nonce'], 'tejlg_export_action')) {
            $portable_mode_enabled = isset($_POST['export_portable']);
        }

        $this->render_template('export.php', [
            'page_slug'                 => $this->page_slug,
            'child_theme_value'         => $child_theme_value,
            'exclusion_presets'         => self::get_exclusion_presets_catalog(),
            'selected_exclusion_presets'=> $exclusion_selection['presets'],
            'exclusion_custom_value'    => $exclusion_selection['custom'],
            'exclusion_summary'         => $exclusion_summary,
            'portable_mode_enabled'     => $portable_mode_enabled,
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
            'page_slug'        => $this->page_slug,
            'patterns_query'   => $patterns_query,
            'per_page'         => $per_page,
            'current_page'     => $current_page,
            'total_pages'      => $total_pages,
            'pagination_base'  => $pagination_base,
            'portable_mode_enabled' => $portable_mode_enabled,
        ]);

        wp_reset_postdata();
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

        $selection  = self::extract_exclusion_selection_from_request($_POST);
        self::store_exclusion_preferences($selection);
        $exclusions = self::build_exclusion_list($selection);

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
            TEJLG_Export::delete_job($job_id);
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
            TEJLG_Export::delete_job($job_id);
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

        TEJLG_Export::delete_job($job_id);

        flush();
        exit;
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
}

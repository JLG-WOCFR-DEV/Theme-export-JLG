<?php

class TEJLG_Admin_Import_Page extends TEJLG_Admin_Page {
    private $page_slug;

    public function __construct($template_dir, $page_slug) {
        parent::__construct($template_dir);
        $this->page_slug = $page_slug;
    }

    public function handle_request() {
        $this->handle_theme_import_request();
        $this->handle_patterns_import_step1_request();
        $this->handle_patterns_import_step2_request();
        $this->handle_global_styles_import_request();
    }

    public function render() {
        settings_errors('tejlg_import_messages');

        if (isset($_GET['action']) && 'preview_patterns' === sanitize_key($_GET['action']) && isset($_GET['transient_id'])) {
            $this->render_patterns_preview_page(sanitize_key($_GET['transient_id']));
            return;
        }

        $this->render_import_default_page();
    }

    private function render_import_default_page() {
        $this->render_template('import.php', [
            'page_slug'              => $this->page_slug,
            'theme_file_info'        => $this->get_import_file_info('theme'),
            'patterns_file_info'     => $this->get_import_file_info('patterns'),
            'global_styles_file_info' => $this->get_import_file_info('global_styles'),
        ]);
    }

    private function render_patterns_preview_page($transient_id) {
        $transient_id = (string) $transient_id;

        if ('' === $transient_id || 0 !== strpos($transient_id, 'tejlg_')) {
            echo '<div class="error"><p>' . esc_html__("Erreur : L'identifiant de session est invalide. Veuillez téléverser à nouveau votre fichier.", 'theme-export-jlg') . '</p></div>';
            return;
        }

        $storage = get_transient($transient_id);

        if (false === $storage) {
            echo '<div class="error"><p>' . esc_html__("La session d'importation a expiré ou est invalide. Veuillez téléverser à nouveau votre fichier.", 'theme-export-jlg') . '</p></div>';
            return;
        }

        $patterns = TEJLG_Import::retrieve_patterns_from_storage($storage);

        if (!is_array($patterns) || empty($patterns)) {
            TEJLG_Import::delete_patterns_storage($transient_id, $storage);
            echo '<div class="error"><p>' . esc_html__("Erreur : Aucune composition valide n'a pu être prévisualisée. Veuillez vérifier le fichier importé.", 'theme-export-jlg') . '</p></div>';
            echo '<p><a href="' . esc_url(add_query_arg(['page' => $this->page_slug, 'tab' => 'import'], admin_url('admin.php'))) . '">&larr; ' . esc_html__("Retour au formulaire d'import", 'theme-export-jlg') . '</a></p>';
            return;
        }

        $global_styles = function_exists('wp_get_global_stylesheet') ? wp_get_global_stylesheet() : '';
        if (!is_string($global_styles)) {
            $global_styles = '';
        }

        $preview_stylesheets = [];
        $candidate_stylesheets = [];

        $primary_stylesheet_uri = get_stylesheet_uri();
        if (is_string($primary_stylesheet_uri)) {
            $candidate_stylesheets[] = $primary_stylesheet_uri;
        }

        foreach ($candidate_stylesheets as $candidate_url) {
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

            if (false !== $validated_url && !in_array($validated_url, $preview_stylesheets, true)) {
                $preview_stylesheets[] = $validated_url;
            }
        }

        $invalid_patterns       = [];
        $prepared_patterns      = [];
        $has_renderable_pattern = false;
        $category_filters       = [];
        $date_filters           = [];
        $has_uncategorized      = false;
        $has_undated            = false;

        $lowercase = static function ($value) {
            $value = (string) $value;

            if (function_exists('mb_strtolower')) {
                return mb_strtolower($value, 'UTF-8');
            }

            return strtolower($value);
        };

        foreach ($patterns as $index => $pattern) {
            if (!is_array($pattern) || !array_key_exists('title', $pattern) || !array_key_exists('content', $pattern)) {
                $invalid_patterns[] = (int) $index;
                continue;
            }

            $raw_title = $pattern['title'];
            if (!is_scalar($raw_title)) {
                $raw_title = '';
            }
            $title = trim((string) $raw_title);
            if ('' === $title) {
                $title = sprintf(__('Composition sans titre #%d', 'theme-export-jlg'), ((int) $index) + 1);
            }

            $raw_content = isset($pattern['content']) ? $pattern['content'] : '';
            $pattern_content = TEJLG_Import::extract_pattern_content_value($raw_content);

            $parsed_blocks = '' !== $pattern_content ? parse_blocks($pattern_content) : [];
            $rendered_pattern = '';

            if (!empty($parsed_blocks)) {
                $rendered_pattern = $this->render_blocks_preview($parsed_blocks);
            }

            if ('' === $rendered_pattern) {
                $rendered_pattern = $pattern_content;
            }

            $sanitized_rendered_pattern = wp_kses_post($rendered_pattern);
            if ('' !== trim($sanitized_rendered_pattern) || '' !== trim($pattern_content)) {
                $has_renderable_pattern = true;
            }

            $raw_description = '';

            if (isset($pattern['description'])) {
                $raw_description = $pattern['description'];

                if (is_array($raw_description)) {
                    if (isset($raw_description['rendered']) && is_scalar($raw_description['rendered'])) {
                        $raw_description = (string) $raw_description['rendered'];
                    } elseif (isset($raw_description['raw']) && is_scalar($raw_description['raw'])) {
                        $raw_description = (string) $raw_description['raw'];
                    } else {
                        $raw_description = '';
                    }
                } elseif (!is_scalar($raw_description)) {
                    $raw_description = '';
                }
            }

            $raw_description = trim((string) $raw_description);

            $excerpt_source = '' !== $raw_description
                ? $raw_description
                : wp_strip_all_tags($pattern_content);

            $excerpt = '' !== $excerpt_source
                ? wp_trim_words($excerpt_source, 36, '…')
                : '';

            $category_entries = [];

            if (isset($pattern['taxonomies']) && is_array($pattern['taxonomies'])) {
                if (isset($pattern['taxonomies']['wp_pattern_category']) && is_array($pattern['taxonomies']['wp_pattern_category'])) {
                    $category_entries = array_merge($category_entries, $pattern['taxonomies']['wp_pattern_category']);
                }
            }

            if (isset($pattern['categories']) && is_array($pattern['categories'])) {
                $category_entries = array_merge($category_entries, $pattern['categories']);
            }

            $normalized_category_tokens = [];
            $category_labels            = [];

            foreach ($category_entries as $category_entry) {
                $slug_candidate  = '';
                $label_candidate = '';

                if (is_array($category_entry)) {
                    if (isset($category_entry['slug']) && is_scalar($category_entry['slug'])) {
                        $slug_candidate = (string) $category_entry['slug'];
                    }

                    if (isset($category_entry['name']) && is_scalar($category_entry['name'])) {
                        $label_candidate = (string) $category_entry['name'];
                    } elseif (isset($category_entry['label']) && is_scalar($category_entry['label'])) {
                        $label_candidate = (string) $category_entry['label'];
                    } elseif (isset($category_entry['title']) && is_scalar($category_entry['title'])) {
                        $label_candidate = (string) $category_entry['title'];
                    }
                } elseif (is_scalar($category_entry)) {
                    $slug_candidate  = (string) $category_entry;
                    $label_candidate = (string) $category_entry;
                }

                $slug_candidate  = sanitize_title($slug_candidate);
                $label_candidate = trim((string) $label_candidate);

                if ('' === $slug_candidate && '' !== $label_candidate) {
                    $slug_candidate = sanitize_title($label_candidate);
                }

                if ('' === $label_candidate && '' !== $slug_candidate) {
                    $label_candidate = ucwords(str_replace(['-', '_'], ' ', $slug_candidate));
                }

                if ('' === $slug_candidate) {
                    continue;
                }

                if ('' === $label_candidate) {
                    $label_candidate = $slug_candidate;
                }

                if (!in_array($slug_candidate, $normalized_category_tokens, true)) {
                    $normalized_category_tokens[] = $slug_candidate;
                }

                $category_labels[$slug_candidate] = $label_candidate;
                $category_filters[$slug_candidate] = $label_candidate;
            }

            if (empty($normalized_category_tokens)) {
                $has_uncategorized = true;
            }

            $keywords = [];

            if (isset($pattern['keywords']) && is_array($pattern['keywords'])) {
                foreach ($pattern['keywords'] as $keyword) {
                    if (is_array($keyword)) {
                        if (isset($keyword['name']) && is_scalar($keyword['name'])) {
                            $keyword = (string) $keyword['name'];
                        } elseif (isset($keyword['slug']) && is_scalar($keyword['slug'])) {
                            $keyword = (string) $keyword['slug'];
                        } else {
                            $keyword = '';
                        }
                    } elseif (!is_scalar($keyword)) {
                        $keyword = '';
                    }

                    $keyword = trim((string) $keyword);

                    if ('' !== $keyword) {
                        $keywords[] = $keyword;
                    }
                }
            }

            $date_timestamp = null;
            $date_display   = '';
            $date_machine   = '';
            $period_value   = '';
            $period_label   = '';

            foreach (['modified', 'modified_gmt', 'date', 'date_gmt'] as $date_key) {
                if (!isset($pattern[$date_key])) {
                    continue;
                }

                $raw_date = $pattern[$date_key];

                if (is_array($raw_date)) {
                    if (isset($raw_date['raw']) && is_scalar($raw_date['raw'])) {
                        $raw_date = (string) $raw_date['raw'];
                    } elseif (isset($raw_date['rendered']) && is_scalar($raw_date['rendered'])) {
                        $raw_date = (string) $raw_date['rendered'];
                    } else {
                        $raw_date = '';
                    }
                } elseif (!is_scalar($raw_date)) {
                    $raw_date = '';
                }

                $raw_date = trim((string) $raw_date);

                if ('' === $raw_date) {
                    continue;
                }

                $timestamp = strtotime($raw_date);

                if (false === $timestamp) {
                    continue;
                }

                $date_timestamp = $timestamp;
                break;
            }

            if (null !== $date_timestamp) {
                $date_display = wp_date(get_option('date_format'), $date_timestamp);
                $date_machine = wp_date('Y-m-d', $date_timestamp);
                $period_value = wp_date('Y-m', $date_timestamp);
                $period_label = wp_date('F Y', $date_timestamp);

                if ('' !== $period_value && !isset($date_filters[$period_value])) {
                    $date_filters[$period_value] = [
                        'label'     => $period_label,
                        'timestamp' => $date_timestamp,
                    ];
                }
            } else {
                $has_undated = true;
            }

            $category_label_values = [];

            foreach ($normalized_category_tokens as $token) {
                if (isset($category_labels[$token])) {
                    $category_label_values[] = $category_labels[$token];
                }
            }

            $search_components = [
                $title,
                implode(' ', $normalized_category_tokens),
                implode(' ', $category_label_values),
                implode(' ', $keywords),
                $excerpt,
                $date_display,
                $date_machine,
                $period_label,
            ];

            if (isset($pattern['slug']) && is_scalar($pattern['slug'])) {
                $search_components[] = (string) $pattern['slug'];
            }

            $search_components = array_filter($search_components, static function ($component) {
                return '' !== trim((string) $component);
            });

            $search_haystack = '';

            if (!empty($search_components)) {
                $search_haystack = $lowercase(implode(' ', $search_components));
            }

            $prepared_patterns[] = [
                'index'             => (int) $index,
                'title'             => $title,
                'title_sort'        => $lowercase($title),
                'content'           => $pattern_content,
                'rendered'          => $sanitized_rendered_pattern,
                'excerpt'           => $excerpt,
                'categories'        => $normalized_category_tokens,
                'category_labels'   => $category_label_values,
                'date_display'      => $date_display,
                'date_machine'      => $date_machine,
                'period_value'      => $period_value,
                'period_label'      => $period_label,
                'timestamp'         => $date_timestamp,
                'search_haystack'   => $search_haystack,
                'original_index'    => (int) $index,
            ];
        }

        $warnings = [];

        if (!empty($invalid_patterns)) {
            sort($invalid_patterns, SORT_NUMERIC);

            $display_indexes = array_map(
                static function ($index) {
                    return sprintf('#%d', ((int) $index) + 1);
                },
                $invalid_patterns
            );

            $invalid_count = count($display_indexes);
            if (1 === $invalid_count) {
                $warnings[] = sprintf(
                    __('Une entrée a été ignorée car elle ne possède pas de titre et un contenu valides (%s).', 'theme-export-jlg'),
                    implode(', ', $display_indexes)
                );
            } else {
                $warnings[] = sprintf(
                    __('%d entrées ont été ignorées car elles ne possèdent pas de titre et un contenu valides (%s).', 'theme-export-jlg'),
                    $invalid_count,
                    implode(', ', $display_indexes)
                );
            }
        }

        if (empty($prepared_patterns) || !$has_renderable_pattern) {
            TEJLG_Import::delete_patterns_storage($transient_id, $storage);
            echo '<div class="error"><p>' . esc_html__("Erreur : Aucune composition valide n'a pu être prévisualisée. Veuillez vérifier le fichier importé.", 'theme-export-jlg') . '</p></div>';
            echo '<p><a href="' . esc_url(add_query_arg(['page' => $this->page_slug, 'tab' => 'import'], admin_url('admin.php'))) . '">&larr; ' . esc_html__("Retour au formulaire d'import", 'theme-export-jlg') . '</a></p>';
            return;
        }

        if (!empty($category_filters)) {
            natcasesort($category_filters);
        }

        $category_filter_options = $category_filters;

        if (!empty($date_filters)) {
            uasort(
                $date_filters,
                static function ($a, $b) {
                    $a_time = isset($a['timestamp']) ? (int) $a['timestamp'] : 0;
                    $b_time = isset($b['timestamp']) ? (int) $b['timestamp'] : 0;

                    if ($a_time === $b_time) {
                        return 0;
                    }

                    return ($a_time > $b_time) ? -1 : 1;
                }
            );
        }

        $date_filter_options = [];

        foreach ($date_filters as $value => $data) {
            $label = '';

            if (isset($data['label']) && is_scalar($data['label'])) {
                $label = (string) $data['label'];
            }

            if ('' === $label) {
                $label = (string) $value;
            }

            $date_filter_options[$value] = $label;
        }

        $default_sort = 'title-asc';

        $encoding_failures = [];

        foreach ($prepared_patterns as &$pattern_data) {
            $stylesheet_links_markup = '';

            foreach ($preview_stylesheets as $stylesheet_url) {
                $stylesheet_links_markup .= '<link rel="stylesheet" href="' . esc_url($stylesheet_url) . '" />';
            }

            $iframe_content = '<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width, initial-scale=1.0">' . $stylesheet_links_markup . '<style>' . $global_styles . '</style></head><body class="block-editor-writing-flow">' . $pattern_data['rendered'] . '</body></html>';
            $json_options = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

            if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
                $json_options |= JSON_INVALID_UTF8_SUBSTITUTE;
            }

            $iframe_json = wp_json_encode($iframe_content, $json_options);

            $stylesheets_json = wp_json_encode($preview_stylesheets, $json_options);
            $stylesheet_links_json = wp_json_encode($stylesheet_links_markup, $json_options);

            if (false === $iframe_json) {
                $iframe_json = wp_json_encode('', $json_options);

                if (false === $iframe_json) {
                    $iframe_json = '""';

                    $encoding_failures[] = sprintf(
                        /* translators: %s: pattern title. */
                        __('Impossible d\'encoder l\'aperçu JSON pour « %s ». Le contenu a été remplacé par une valeur vide.', 'theme-export-jlg'),
                        $pattern_data['title']
                    );
                }
            }

            $pattern_title = isset($pattern_data['title']) ? (string) $pattern_data['title'] : '';

            if ('' !== $pattern_title) {
                $iframe_title_text = sprintf(
                    __('Aperçu : %s', 'theme-export-jlg'),
                    $pattern_title
                );
            } else {
                $iframe_title_text = __('Aperçu de la composition', 'theme-export-jlg');
            }

            if (false === $stylesheets_json) {
                $stylesheets_json = '[]';
            }

            if (false === $stylesheet_links_json) {
                $stylesheet_links_json = '""';
            }

            $pattern_data['iframe_json']             = $iframe_json;
            $pattern_data['iframe_title']            = $iframe_title_text;
            $pattern_data['iframe_stylesheets_json'] = $stylesheets_json;
            $pattern_data['iframe_stylesheet_links_json'] = $stylesheet_links_json;
        }
        unset($pattern_data);

        $this->render_template('import-preview.php', [
            'page_slug'          => $this->page_slug,
            'transient_id'       => $transient_id,
            'patterns'           => $prepared_patterns,
            'encoding_failures'  => $encoding_failures,
            'warnings'           => $warnings,
            'global_styles'      => $global_styles,
            'category_filters'   => $category_filter_options,
            'date_filters'       => $date_filter_options,
            'has_uncategorized'  => $has_uncategorized,
            'has_undated'        => $has_undated,
            'default_sort'       => $default_sort,
        ]);
    }

    private function handle_theme_import_request() {
        if (!isset($_POST['tejlg_import_theme_nonce']) || !wp_verify_nonce($_POST['tejlg_import_theme_nonce'], 'tejlg_import_theme_action')) {
            return;
        }

        $theme_file = isset($_FILES['theme_zip']) ? $_FILES['theme_zip'] : [ 'error' => UPLOAD_ERR_NO_FILE ];

        if (!current_user_can('install_themes')) {
            add_settings_error(
                'tejlg_import_messages',
                'theme_import_cap_missing',
                esc_html__("Vous n'avez pas l'autorisation d'installer des thèmes sur ce site.", 'theme-export-jlg'),
                'error'
            );
            return;
        }

        if ((int) $theme_file['error'] === UPLOAD_ERR_OK) {
            TEJLG_Import::import_theme($theme_file);
            return;
        }

        if (!empty($theme_file['tmp_name']) && is_string($theme_file['tmp_name']) && file_exists($theme_file['tmp_name'])) {
            @unlink($theme_file['tmp_name']);
        }

        add_settings_error(
            'tejlg_import_messages',
            'theme_import_upload_error_' . (int) $theme_file['error'],
            $this->get_upload_error_message((int) $theme_file['error'], esc_html__("du thème", 'theme-export-jlg')),
            'error'
        );
    }

    private function handle_patterns_import_step1_request() {
        if (!isset($_POST['tejlg_import_patterns_step1_nonce']) || !wp_verify_nonce($_POST['tejlg_import_patterns_step1_nonce'], 'tejlg_import_patterns_step1_action')) {
            return;
        }

        $patterns_file = isset($_FILES['patterns_json']) ? $_FILES['patterns_json'] : [ 'error' => UPLOAD_ERR_NO_FILE ];

        if ((int) $patterns_file['error'] === UPLOAD_ERR_OK) {
            TEJLG_Import::handle_patterns_upload_step1($patterns_file);
            return;
        }

        if (!empty($patterns_file['tmp_name']) && is_string($patterns_file['tmp_name']) && file_exists($patterns_file['tmp_name'])) {
            @unlink($patterns_file['tmp_name']);
        }

        add_settings_error(
            'tejlg_import_messages',
            'patterns_import_upload_error_' . (int) $patterns_file['error'],
            $this->get_upload_error_message((int) $patterns_file['error'], esc_html__("des compositions", 'theme-export-jlg')),
            'error'
        );
    }

    private function handle_patterns_import_step2_request() {
        if (!isset($_POST['tejlg_import_patterns_step2_nonce']) || !wp_verify_nonce($_POST['tejlg_import_patterns_step2_nonce'], 'tejlg_import_patterns_step2_action')) {
            return;
        }

        $transient_id = isset($_POST['transient_id']) ? sanitize_key($_POST['transient_id']) : '';

        if ('' === $transient_id || 0 !== strpos($transient_id, 'tejlg_')) {
            add_settings_error(
                'tejlg_import_messages',
                'patterns_import_status',
                esc_html__("Erreur : L'identifiant de session est invalide. Veuillez réessayer.", 'theme-export-jlg'),
                'error'
            );
            return;
        }

        $selected_patterns = isset($_POST['selected_patterns'])
            ? $this->sanitize_pattern_indices($_POST['selected_patterns'])
            : [];

        if (!empty($selected_patterns)) {
            TEJLG_Import::handle_patterns_import_step2($transient_id, $selected_patterns);

            $errors = get_settings_errors('tejlg_import_messages');
            set_transient('settings_errors', $errors, 30);

            $has_error = false;
            foreach ($errors as $error) {
                if (isset($error['type']) && 'error' === $error['type']) {
                    $has_error = true;
                    break;
                }
            }

            $redirect_args = [
                'page'             => $this->page_slug,
                'tab'              => 'import',
                'settings-updated' => $has_error ? 'false' : 'true',
            ];

            $remaining_patterns = get_transient($transient_id);
            if (!empty($remaining_patterns)) {
                $redirect_args['action']       = 'preview_patterns';
                $redirect_args['transient_id'] = $transient_id;
            }

            $redirect_url = add_query_arg($redirect_args, admin_url('admin.php'));

            $fallback_url = admin_url('admin.php?page=' . $this->page_slug . '&tab=import');
            $redirect_url = wp_validate_redirect($redirect_url, $fallback_url);

            wp_safe_redirect($redirect_url);
            exit;
        }

        add_settings_error(
            'tejlg_import_messages',
            'patterns_import_no_selection',
            esc_html__("Erreur : Veuillez sélectionner au moins une composition avant de lancer l'import.", 'theme-export-jlg'),
            'error'
        );

        $errors = get_settings_errors('tejlg_import_messages');
        set_transient('settings_errors', $errors, 30);

        $redirect_url = add_query_arg(
            [
                'page'             => $this->page_slug,
                'tab'              => 'import',
                'action'           => 'preview_patterns',
                'transient_id'     => $transient_id,
                'settings-updated' => 'false',
            ],
            admin_url('admin.php')
        );

        $fallback_url = admin_url('admin.php?page=' . $this->page_slug . '&tab=import');
        $redirect_url = wp_validate_redirect($redirect_url, $fallback_url);

        wp_safe_redirect($redirect_url);
        exit;
    }

    private function handle_global_styles_import_request() {
        if (!isset($_POST['tejlg_import_global_styles_nonce']) || !wp_verify_nonce($_POST['tejlg_import_global_styles_nonce'], 'tejlg_import_global_styles_action')) {
            return;
        }

        if (!isset($_FILES['global_styles_json'])) {
            add_settings_error(
                'tejlg_import_messages',
                'global_styles_import_status',
                esc_html__("Erreur : Aucun fichier n'a été téléchargé.", 'theme-export-jlg'),
                'error'
            );

            return;
        }

        TEJLG_Import::import_global_styles($_FILES['global_styles_json']);
    }

    private function sanitize_pattern_indices($pattern_ids) {
        $sanitized = [];

        foreach ((array) $pattern_ids as $pattern_id) {
            if (!is_scalar($pattern_id) || !is_numeric($pattern_id)) {
                continue;
            }

            $sanitized[] = (int) $pattern_id;
        }

        return $sanitized;
    }

    private function get_import_file_info($type) {
        $config = TEJLG_Import::get_import_file_type($type);

        $extensions = [];

        if (isset($config['extensions']) && is_array($config['extensions'])) {
            foreach ($config['extensions'] as $extension) {
                $extension = '.' . ltrim(strtolower((string) $extension), '.');

                if ('.' === $extension) {
                    continue;
                }

                $extensions[] = $extension;
            }
        }

        $extensions = array_values(array_unique($extensions));

        $code_extensions = array_map(
            static function ($extension) {
                return '<code>' . esc_html($extension) . '</code>';
            },
            $extensions
        );

        return [
            'display' => implode(', ', $extensions),
            'code'    => implode(', ', $code_extensions),
            'accept'  => implode(',', $extensions),
        ];
    }

    private function get_upload_error_message($error_code, $file_label) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return sprintf(
                    esc_html__('Le fichier %1$s dépasse la taille maximale autorisée (%2$s).', 'theme-export-jlg'),
                    $file_label,
                    esc_html(ini_get('upload_max_filesize'))
                );
            case UPLOAD_ERR_PARTIAL:
                return esc_html__('Le fichier n\'a pas été entièrement téléversé. Veuillez réessayer.', 'theme-export-jlg');
            case UPLOAD_ERR_NO_FILE:
                return sprintf(
                    esc_html__('Aucun fichier %s n\'a été téléversé. Veuillez sélectionner un fichier avant de recommencer.', 'theme-export-jlg'),
                    $file_label
                );
            case UPLOAD_ERR_NO_TMP_DIR:
                return esc_html__('Le dossier temporaire du serveur est manquant. Contactez votre hébergeur.', 'theme-export-jlg');
            case UPLOAD_ERR_CANT_WRITE:
                return esc_html__('Impossible d\'écrire le fichier sur le disque. Vérifiez les permissions de votre serveur.', 'theme-export-jlg');
            case UPLOAD_ERR_EXTENSION:
                return esc_html__('Une extension PHP a interrompu le téléversement. Vérifiez la configuration de votre serveur.', 'theme-export-jlg');
            default:
                return sprintf(
                    esc_html__('Une erreur inconnue est survenue lors du téléversement du fichier %1$s (code %2$d).', 'theme-export-jlg'),
                    $file_label,
                    $error_code
                );
        }
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

        return '<div class="tejlg-block-placeholder" role="note">' . $icon_svg . '<p class="tejlg-block-placeholder__message">' . $placeholder_text . '</p></div>';
    }
}

<?php

class TEJLG_Admin_Import_Page {

    /**
     * Handle import-related form submissions.
     */
    public function handle_request() {
        if ($this->is_nonce_valid('tejlg_import_global_styles_nonce', 'tejlg_import_global_styles_action')) {
            $this->handle_global_styles_import_request();
        }

        if ($this->is_nonce_valid('tejlg_import_theme_nonce', 'tejlg_import_theme_action')) {
            $this->handle_theme_import_request();
        }

        if ($this->is_nonce_valid('tejlg_import_patterns_step1_nonce', 'tejlg_import_patterns_step1_action')) {
            $this->handle_patterns_import_step1_request();
        }

        if ($this->is_nonce_valid('tejlg_import_patterns_step2_nonce', 'tejlg_import_patterns_step2_action')) {
            $this->handle_patterns_import_step2_request();
        }
    }

    /**
     * Render the import tab.
     */
    public function render() {
        settings_errors('tejlg_import_messages');

        if (isset($_GET['action']) && 'preview_patterns' === $_GET['action'] && isset($_GET['transient_id'])) {
            $this->render_patterns_preview_page(sanitize_key($_GET['transient_id']));
            return;
        }

        $theme_file_info         = $this->format_import_type('theme');
        $patterns_file_info      = $this->format_import_type('patterns');
        $global_styles_file_info = $this->format_import_type('global_styles');

        $this->render_template('import', [
            'theme_file_info'         => $theme_file_info,
            'patterns_file_info'      => $patterns_file_info,
            'global_styles_file_info' => $global_styles_file_info,
            'import_tab_url'          => $this->get_import_tab_url(),
        ]);
    }

    private function handle_global_styles_import_request() {
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

    private function handle_theme_import_request() {
        $theme_file = isset($_FILES['theme_zip']) ? $_FILES['theme_zip'] : [ 'error' => UPLOAD_ERR_NO_FILE ];

        if (!current_user_can('install_themes')) {
            add_settings_error(
                'tejlg_import_messages',
                'theme_import_cap_missing',
                esc_html__('Vous n\'avez pas l\'autorisation d\'installer des thèmes sur ce site.', 'theme-export-jlg'),
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
            $this->get_upload_error_message((int) $theme_file['error'], esc_html__('du thème', 'theme-export-jlg')),
            'error'
        );
    }

    private function handle_patterns_import_step1_request() {
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
            $this->get_upload_error_message((int) $patterns_file['error'], esc_html__('des compositions', 'theme-export-jlg')),
            'error'
        );
    }

    private function handle_patterns_import_step2_request() {
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
                'page'             => 'theme-export-jlg',
                'tab'              => 'import',
                'settings-updated' => $has_error ? 'false' : 'true',
            ];

            $remaining_patterns = get_transient($transient_id);
            if (!empty($remaining_patterns)) {
                $redirect_args['action']       = 'preview_patterns';
                $redirect_args['transient_id'] = $transient_id;
            }

            $redirect_url = add_query_arg($redirect_args, admin_url('admin.php'));
            $fallback_url = admin_url('admin.php?page=theme-export-jlg&tab=import');
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
                'page'             => 'theme-export-jlg',
                'tab'              => 'import',
                'action'           => 'preview_patterns',
                'transient_id'     => $transient_id,
                'settings-updated' => 'false',
            ],
            admin_url('admin.php')
        );

        $fallback_url = admin_url('admin.php?page=theme-export-jlg&tab=import');
        $redirect_url = wp_validate_redirect($redirect_url, $fallback_url);

        wp_safe_redirect($redirect_url);
        exit;
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
            echo '<div class="error"><p>' . esc_html__("Erreur : Aucune composition valide n'a été trouvée dans le fichier importé.", 'theme-export-jlg') . '</p></div>';
            echo '<p><a href="' . esc_url($this->get_import_tab_url()) . '">&larr; ' . esc_html__("Retour au formulaire d'import", 'theme-export-jlg') . '</a></p>';
            return;
        }

        $global_styles = function_exists('wp_get_global_stylesheet') ? wp_get_global_stylesheet() : '';
        if (!is_string($global_styles)) {
            $global_styles = '';
        }

        $invalid_patterns       = [];
        $prepared_patterns      = [];
        $has_renderable_pattern = false;

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

            $raw_content     = isset($pattern['content']) ? $pattern['content'] : '';
            $pattern_content = TEJLG_Import::extract_pattern_content_value($raw_content);

            $parsed_blocks     = '' !== $pattern_content ? parse_blocks($pattern_content) : [];
            $rendered_pattern  = '';

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

            $prepared_patterns[] = [
                'index'    => (int) $index,
                'title'    => $title,
                'content'  => $pattern_content,
                'rendered' => $sanitized_rendered_pattern,
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
            echo '<p><a href="' . esc_url($this->get_import_tab_url()) . '">&larr; ' . esc_html__("Retour au formulaire d'import", 'theme-export-jlg') . '</a></p>';
            return;
        }

        $patterns_for_template = [];
        $encoding_failures     = [];

        foreach ($prepared_patterns as $pattern_data) {
            $iframe_content = '<!DOCTYPE html><html><head><meta name="viewport" content="width=device-width, initial-scale=1.0"><style>' . $global_styles . '</style></head><body class="block-editor-writing-flow">' . $pattern_data['rendered'] . '</body></html>';
            $json_options   = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

            if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
                $json_options |= JSON_INVALID_UTF8_SUBSTITUTE;
            }

            $iframe_json = wp_json_encode($iframe_content, $json_options);

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

            $patterns_for_template[] = [
                'index'          => $pattern_data['index'],
                'title'          => $pattern_data['title'],
                'content'        => $pattern_data['content'],
                'iframe_json'    => $iframe_json,
                'iframe_title'   => $iframe_title_text,
            ];
        }

        $this->render_template('import-preview', [
            'import_tab_url'      => $this->get_import_tab_url(),
            'transient_id'        => $transient_id,
            'patterns'            => $patterns_for_template,
            'global_styles'       => $global_styles,
            'warnings'            => $warnings,
            'encoding_failures'   => $encoding_failures,
        ]);
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
            $content     = '';
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

        if (isset($block['innerHTML']) && '' !== trim($block['innerHTML'])) {
            return $block['innerHTML'];
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
        $block_label      = $block_name ? $block_name : __('bloc inconnu', 'theme-export-jlg');
        $placeholder_text = sprintf(
            /* translators: %s: dynamic block name. */
            esc_html__('Bloc dynamique "%s" non rendu dans cet aperçu.', 'theme-export-jlg'),
            esc_html($block_label)
        );

        return '<div class="tejlg-block-placeholder"><p>' . $placeholder_text . '</p></div>';
    }

    private function sanitize_pattern_indices($pattern_ids) {
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
                return sprintf(
                    esc_html__('Le fichier %s n\'a été que partiellement téléversé. Veuillez réessayer.', 'theme-export-jlg'),
                    $file_label
                );
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

    private function format_import_type($type) {
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

    private function get_import_tab_url() {
        return add_query_arg(
            [
                'page' => 'theme-export-jlg',
                'tab'  => 'import',
            ],
            admin_url('admin.php')
        );
    }

    private function is_nonce_valid($nonce_key, $action) {
        return isset($_POST[$nonce_key]) && wp_verify_nonce($_POST[$nonce_key], $action);
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

<?php
class TEJLG_Import {

    public static function import_theme($file) {
        if (!current_user_can('install_themes')) {
            if (isset($file['tmp_name'])) {
                @unlink($file['tmp_name']);
            }

            add_settings_error(
                'tejlg_import_messages',
                'theme_import_cap_missing',
                esc_html__('Vous n\'avez pas l\'autorisation d\'installer des thèmes sur ce site.', 'theme-export-jlg'),
                'error'
            );

            return;
        }

        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';

        $package_param = 'tejlg_package';
        $file_upload   = new File_Upload_Upgrader('theme_zip', $package_param);

        $page_url   = admin_url('admin.php?page=theme-export-jlg&tab=import');
        $theme_root = get_theme_root();

        if ($file_upload->id) {
            $page_url = add_query_arg($package_param, (int) $file_upload->id, $page_url);
        }

        $filesystem_credentials = request_filesystem_credentials(
            $page_url,
            '',
            false,
            $theme_root,
            [$package_param]
        );

        if (false === $filesystem_credentials) {
            if ($file_upload instanceof File_Upload_Upgrader) {
                $file_upload->cleanup();
            }

            return;
        }

        if (!WP_Filesystem($filesystem_credentials, $theme_root)) {
            if ($file_upload instanceof File_Upload_Upgrader) {
                $file_upload->cleanup();
            }

            request_filesystem_credentials($page_url, '', true, $theme_root, [$package_param]);

            return;
        }

        $skin_args = [
            'type'  => 'upload',
            'title' => esc_html__('Installation du thème', 'theme-export-jlg'),
            'url'   => $page_url,
            'nonce' => 'tejlg-import-theme',
        ];

        $upgrader = new Theme_Upgrader(new TEJLG_Silent_Theme_Installer_Skin($skin_args));
        $result = $upgrader->install($file_upload->package, ['overwrite_package' => true]);

        if ($file_upload instanceof File_Upload_Upgrader) {
            $file_upload->cleanup();
        }

        $message_type = 'info';
        $message_text = esc_html__('Le processus d\'installation du thème a renvoyé un résultat inattendu.', 'theme-export-jlg');

        if (is_wp_error($result)) {
            $message_type = 'error';
            $message_text = $result->get_error_message();
        } elseif (false === $result) {
            $message_type = 'error';
            $message_text = esc_html__('L\'installation du thème a échoué.', 'theme-export-jlg');
        } elseif (false !== $result) {
            $message_type = 'success';
            $message_text = esc_html__('Le thème a été installé avec succès !', 'theme-export-jlg');
        }

        add_settings_error('tejlg_import_messages', 'theme_import_status', $message_text, $message_type);
    }

    public static function handle_patterns_upload_step1($file) {
        if (!isset($file['tmp_name']) || '' === $file['tmp_name']) {
            add_settings_error(
                'tejlg_import_messages',
                'patterns_import_status',
                esc_html__("Erreur : Aucun fichier n'a été téléchargé.", 'theme-export-jlg'),
                'error'
            );

            return;
        }

        if (isset($file['error']) && UPLOAD_ERR_OK !== (int) $file['error']) {
            @unlink($file['tmp_name']);
            add_settings_error(
                'tejlg_import_messages',
                'patterns_import_status',
                esc_html__("Erreur : Le téléchargement du fichier a échoué. Veuillez réessayer.", 'theme-export-jlg'),
                'error'
            );

            return;
        }

        $max_size = (int) apply_filters('tejlg_import_patterns_max_filesize', 5 * 1024 * 1024);
        if ($max_size < 1) {
            $max_size = 5 * 1024 * 1024;
        }

        $file_size = isset($file['size']) ? (int) $file['size'] : 0;
        if ((0 === $file_size || $file_size < 0) && @is_file($file['tmp_name'])) {
            $file_size = (int) @filesize($file['tmp_name']);
        }

        if ($file_size > $max_size) {
            @unlink($file['tmp_name']);
            add_settings_error(
                'tejlg_import_messages',
                'patterns_import_status',
                sprintf(
                    esc_html__("Erreur : Le fichier est trop volumineux. La taille maximale autorisée est de %s Mo.", 'theme-export-jlg'),
                    number_format_i18n($max_size / (1024 * 1024), 2)
                ),
                'error'
            );

            return;
        }

        if (!is_readable($file['tmp_name'])) {
            @unlink($file['tmp_name']);
            add_settings_error(
                'tejlg_import_messages',
                'patterns_import_status',
                esc_html__("Erreur : Impossible d'accéder au fichier téléchargé.", 'theme-export-jlg'),
                'error'
            );

            return;
        }

        $json_content = file_get_contents($file['tmp_name']);

        if (false === $json_content) {
            @unlink($file['tmp_name']);
            add_settings_error(
                'tejlg_import_messages',
                'patterns_import_status',
                esc_html__("Erreur : Impossible de lire le fichier téléchargé.", 'theme-export-jlg'),
                'error'
            );

            return;
        }

        @unlink($file['tmp_name']);

        $patterns = json_decode($json_content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($patterns)) {
            add_settings_error('tejlg_import_messages', 'patterns_import_status', esc_html__('Erreur : Le fichier n\'est pas un fichier JSON valide.', 'theme-export-jlg'), 'error');
            return;
        }

        $patterns = array_values(
            array_filter(
                $patterns,
                static function ($pattern) {
                    return is_array($pattern)
                        && array_key_exists('title', $pattern)
                        && array_key_exists('content', $pattern);
                }
            )
        );

        $transient_id = 'tejlg_' . md5(uniqid('', true));

        if (empty($patterns)) {
            delete_transient($transient_id);
            add_settings_error(
                'tejlg_import_messages',
                'patterns_import_status',
                esc_html__('Erreur : Aucune composition valide (titre + contenu) n\'a été trouvée dans le fichier fourni.', 'theme-export-jlg'),
                'error'
            );

            $errors = get_settings_errors('tejlg_import_messages');
            set_transient('settings_errors', $errors, 30);

            $redirect_url = add_query_arg(
                'settings-updated',
                'false',
                admin_url('admin.php?page=theme-export-jlg&tab=import')
            );

            $fallback_url = admin_url('admin.php?page=theme-export-jlg&tab=import');
            $redirect_url = wp_validate_redirect($redirect_url, $fallback_url);

            wp_safe_redirect($redirect_url);
            exit;
        }
        set_transient($transient_id, $patterns, 15 * MINUTE_IN_SECONDS);

        $redirect_url = add_query_arg(
            [
                'page'         => 'theme-export-jlg',
                'tab'          => 'import',
                'action'       => 'preview_patterns',
                'transient_id' => $transient_id,
            ],
            admin_url('admin.php')
        );

        $fallback_url = admin_url('admin.php?page=theme-export-jlg&tab=import');
        $redirect_url = wp_validate_redirect($redirect_url, $fallback_url);

        wp_safe_redirect($redirect_url);
        exit;
    }

    public static function handle_patterns_import_step2($transient_id, $selected_indices) {
        $transient_id = (string) $transient_id;

        if ('' === $transient_id || 0 !== strpos($transient_id, 'tejlg_')) {
            add_settings_error(
                'tejlg_import_messages',
                'patterns_import_status',
                esc_html__("Erreur : L'identifiant de session est invalide. Veuillez réessayer.", 'theme-export-jlg'),
                'error'
            );

            return;
        }

        $all_patterns = get_transient($transient_id);

        if (false === $all_patterns) {
            add_settings_error('tejlg_import_messages', 'patterns_import_status', esc_html__('Erreur : La session d\'importation a expiré. Veuillez réessayer.', 'theme-export-jlg'), 'error');
            return;
        }

        if (!is_array($selected_indices)) {
            add_settings_error('tejlg_import_messages', 'patterns_import_status', esc_html__('Erreur : La sélection des compositions est invalide.', 'theme-export-jlg'), 'error');
            return;
        }

        $imported_count = 0;
        $errors = [];
        $failed_patterns = [];

        foreach ($selected_indices as $index) {
            $index = intval($index);
            if (!isset($all_patterns[$index]) || !is_array($all_patterns[$index])) {
                continue;
            }

            $pattern = $all_patterns[$index];

            $raw_slug = isset($pattern['slug']) ? (string) $pattern['slug'] : '';
            $raw_slug = trim($raw_slug);

            if (0 === strpos($raw_slug, 'custom-patterns/')) {
                $raw_slug = substr($raw_slug, strlen('custom-patterns/'));
            }

            $original_slug = $raw_slug;

            $slug = sanitize_title($raw_slug);
            if ('' === $slug) {
                $errors[] = sprintf(__('La composition à l\'index %d ne possède pas de slug valide.', 'theme-export-jlg'), $index);
                $failed_patterns[$index] = $pattern;
                continue;
            }

            $title = isset($pattern['title']) ? sanitize_text_field($pattern['title']) : '';
            $content = '';

            if (isset($pattern['content'])) {
                $content = self::sanitize_pattern_content_for_current_user((string) $pattern['content']);
            }

            $candidate_slugs = array_unique([
                $slug,
                $original_slug,
                '' !== $slug ? 'custom-patterns/' . $slug : '',
            ]);

            $candidate_slugs = array_values(
                array_filter(
                    $candidate_slugs,
                    static function ($value) {
                        return '' !== $value;
                    }
                )
            );

            $existing_block = null;

            if (!empty($candidate_slugs)) {
                global $wpdb;

                $allowed_statuses = ['publish', 'draft', 'pending', 'future', 'private', 'trash'];

                $slug_placeholders      = implode(', ', array_fill(0, count($candidate_slugs), '%s'));
                $status_placeholders    = implode(', ', array_fill(0, count($allowed_statuses), '%s'));
                $order_slug_placeholders = $slug_placeholders;

                $sql = "SELECT ID FROM {$wpdb->posts}
                    WHERE post_type = %s
                        AND post_name IN ($slug_placeholders)
                        AND post_status IN ($status_placeholders)
                    ORDER BY FIELD(post_name, $order_slug_placeholders)
                    LIMIT 1";

                $prepare_args = array_merge(
                    ['wp_block'],
                    $candidate_slugs,
                    $allowed_statuses,
                    $candidate_slugs
                );

                $existing_block_id = $wpdb->get_var($wpdb->prepare($sql, $prepare_args));

                if (!empty($existing_block_id)) {
                    $existing_block = get_post((int) $existing_block_id);
                }
            }

            // Always store the sanitized slug without the legacy "custom-patterns/" prefix.
            $post_data = [
                'post_title'   => $title,
                'post_content' => $content,
                'post_name'    => $slug,
            ];

            $action = 'create';

            if ($existing_block instanceof WP_Post) {
                $action = 'update';
                $post_status = isset($existing_block->post_status) ? $existing_block->post_status : get_post_status($existing_block);

                if ('publish' !== $post_status) {
                    if ('trash' === $post_status) {
                        $untrash_result = wp_untrash_post($existing_block->ID);

                        if (is_wp_error($untrash_result)) {
                            $errors[] = $untrash_result->get_error_message();
                            $failed_patterns[$index] = $pattern;
                            continue;
                        }

                        if (false === $untrash_result) {
                            $errors[] = sprintf(__('Impossible de restaurer la composition "%s".', 'theme-export-jlg'), $title);
                            $failed_patterns[$index] = $pattern;
                            continue;
                        }

                        $existing_block = get_post($existing_block->ID);
                        $post_status    = isset($existing_block->post_status) ? $existing_block->post_status : get_post_status($existing_block);
                    }

                    $post_data['post_status'] = 'publish';
                }

                $post_data['ID'] = $existing_block->ID;

                $result = wp_update_post(wp_slash($post_data), true);
            } else {
                $post_data['post_status'] = 'publish';
                $post_data['post_type']   = 'wp_block';
                $post_data['post_author'] = get_current_user_id();

                $result = wp_insert_post(wp_slash($post_data), true);
            }

            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
                $failed_patterns[$index] = $pattern;
                continue;
            }

            if (!empty($result)) {
                $imported_count++;
                continue;
            }

            $failed_patterns[$index] = $pattern;

            if ('update' === $action) {
                $errors[] = sprintf(
                    __('La composition "%1$s" n\'a pas pu être mise à jour (ID %2$d).', 'theme-export-jlg'),
                    $title,
                    $existing_block instanceof WP_Post ? (int) $existing_block->ID : 0
                );
            } else {
                $errors[] = sprintf(
                    __('La composition "%s" n\'a pas pu être créée.', 'theme-export-jlg'),
                    $title
                );
            }
        }

        if (!empty($errors)) {
            add_settings_error('tejlg_import_messages', 'patterns_import_errors', esc_html(implode(' ', array_unique($errors))), 'error');

            if (!empty($failed_patterns)) {
                set_transient($transient_id, $failed_patterns, 15 * MINUTE_IN_SECONDS);
            } else {
                set_transient($transient_id, $all_patterns, 15 * MINUTE_IN_SECONDS);
            }
        } else {
            delete_transient($transient_id);
        }

        if ($imported_count > 0) {
            $success_message = sprintf(
                _n('%d composition a été enregistrée avec succès.', '%d compositions ont été enregistrées avec succès.', $imported_count, 'theme-export-jlg'),
                $imported_count
            );

            add_settings_error('tejlg_import_messages', 'patterns_import_status', esc_html($success_message), 'success');
        } else {
            add_settings_error('tejlg_import_messages', 'patterns_import_status', esc_html__('Aucune composition n\'a pu être enregistrée (elles existent peut-être déjà ou des erreurs sont survenues).', 'theme-export-jlg'), 'info');
        }
    }

    /**
     * Sanitize pattern content for users without the unfiltered_html capability while preserving block structure.
     */
    private static function sanitize_pattern_content_for_current_user($raw_content) {
        if (current_user_can('unfiltered_html')) {
            return $raw_content;
        }

        $block_comment_tokens = [];

        $tokenized_content = preg_replace_callback(
            '/<!--\s*(\/?.*?wp:[^>]*?)\s*-->/',
            function ($matches) use (&$block_comment_tokens) {
                $token = '[[TEJLG_WP_COMMENT_' . count($block_comment_tokens) . ']]';
                $block_comment_tokens[$token] = $matches[0];

                return $token;
            },
            $raw_content
        );

        $allowed_html      = wp_kses_allowed_html('post');
        $allowed_protocols = wp_allowed_protocols();
        $sanitized_content = wp_kses($tokenized_content, $allowed_html, $allowed_protocols);

        if (!empty($block_comment_tokens)) {
            $sanitized_content = strtr($sanitized_content, $block_comment_tokens);
        }

        return $sanitized_content;
    }
}

if (!class_exists('TEJLG_Silent_Theme_Installer_Skin')) {
    if (!class_exists('Theme_Installer_Skin')) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    }

    class TEJLG_Silent_Theme_Installer_Skin extends Theme_Installer_Skin {
        public function header() {
            $this->done_header = true;
        }

        public function footer() {}

        public function before() {}

        public function after() {}

        public function feedback($string, ...$args) {}
    }
}

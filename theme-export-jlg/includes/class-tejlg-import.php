<?php
class TEJLG_Import {

    private const IMPORT_FILE_TYPES = [
        'theme' => [
            'extensions' => ['zip'],
            'mime_types' => [
                'zip' => 'application/zip',
            ],
        ],
        'patterns' => [
            'extensions' => ['json'],
            'mime_types' => [
                'json' => 'application/json',
            ],
        ],
        'global_styles' => [
            'extensions' => ['json'],
            'mime_types' => [
                'json' => 'application/json',
            ],
        ],
    ];

    public static function get_import_file_types() {
        $types = apply_filters('tejlg_import_file_types', self::IMPORT_FILE_TYPES);

        if (!is_array($types)) {
            return self::IMPORT_FILE_TYPES;
        }

        foreach ($types as $key => $config) {
            $types[$key] = self::normalize_file_type_config($config);
        }

        return $types;
    }

    public static function get_import_file_type($type) {
        $types = self::get_import_file_types();

        if (!isset($types[$type]) || !is_array($types[$type])) {
            return [
                'extensions' => [],
                'mime_types' => [],
            ];
        }

        return $types[$type];
    }

    public static function get_extensions_display_string($type, $glue = ', ') {
        $config     = self::get_import_file_type($type);
        $extensions = self::prefix_extensions($config['extensions']);

        return implode($glue, $extensions);
    }

    public static function get_accept_attribute_value($type) {
        $config     = self::get_import_file_type($type);
        $extensions = self::prefix_extensions($config['extensions']);

        return implode(',', $extensions);
    }

    private static function prefix_extensions($extensions) {
        $prefixed = [];

        foreach ($extensions as $extension) {
            $extension = ltrim((string) $extension, '.');

            if ('' === $extension) {
                continue;
            }

            $prefixed[] = '.' . strtolower($extension);
        }

        return array_values(array_unique($prefixed));
    }

    private static function normalize_file_type_config($config) {
        $extensions = [];
        $mime_types = [];

        if (isset($config['extensions']) && is_array($config['extensions'])) {
            foreach ($config['extensions'] as $extension) {
                $extension = ltrim(strtolower(trim((string) $extension)), '.');

                if ('' === $extension) {
                    continue;
                }

                $extensions[] = $extension;
            }
        }

        if (isset($config['mime_types']) && is_array($config['mime_types'])) {
            foreach ($config['mime_types'] as $extension => $mime) {
                $extension = ltrim(strtolower(trim((string) $extension)), '.');
                $mime      = trim((string) $mime);

                if ('' === $extension || '' === $mime) {
                    continue;
                }

                $mime_types[$extension] = $mime;
            }
        }

        $extensions = array_values(array_unique($extensions));

        return [
            'extensions' => $extensions,
            'mime_types' => $mime_types,
        ];
    }

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

        if (
            (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS) ||
            (defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT)
        ) {
            if (isset($file['tmp_name'])) {
                @unlink($file['tmp_name']);
            }

            add_settings_error(
                'tejlg_import_messages',
                'theme_import_file_mods_disabled',
                esc_html__('Erreur : Les modifications de fichiers sont désactivées sur ce site.', 'theme-export-jlg'),
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

    public static function import_global_styles($file) {
        if (!isset($file['tmp_name']) || '' === $file['tmp_name']) {
            add_settings_error(
                'tejlg_import_messages',
                'global_styles_import_status',
                esc_html__("Erreur : Aucun fichier n'a été téléchargé.", 'theme-export-jlg'),
                'error'
            );

            return;
        }

        if (isset($file['error']) && UPLOAD_ERR_OK !== (int) $file['error']) {
            @unlink($file['tmp_name']);
            add_settings_error(
                'tejlg_import_messages',
                'global_styles_import_status',
                esc_html__("Erreur : Le téléchargement du fichier a échoué. Veuillez réessayer.", 'theme-export-jlg'),
                'error'
            );

            return;
        }

        $max_size = (int) apply_filters('tejlg_import_global_styles_max_filesize', 1024 * 1024);

        if ($max_size < 1) {
            $max_size = 1024 * 1024;
        }

        $file_size = isset($file['size']) ? (int) $file['size'] : 0;

        if ((0 === $file_size || $file_size < 0) && @is_file($file['tmp_name'])) {
            $file_size = (int) @filesize($file['tmp_name']);
        }

        if ($file_size > $max_size) {
            @unlink($file['tmp_name']);
            add_settings_error(
                'tejlg_import_messages',
                'global_styles_import_status',
                sprintf(
                    esc_html__("Erreur : Le fichier est trop volumineux. La taille maximale autorisée est de %s Mo.", 'theme-export-jlg'),
                    number_format_i18n($max_size / (1024 * 1024), 2)
                ),
                'error'
            );

            return;
        }

        $file_type_config = self::get_import_file_type('global_styles');
        $allowed_mime_map = $file_type_config['mime_types'];

        $filetype = wp_check_filetype_and_ext(
            $file['tmp_name'],
            isset($file['name']) ? (string) $file['name'] : '',
            $allowed_mime_map
        );

        $ext  = isset($filetype['ext']) ? (string) $filetype['ext'] : '';
        $type = isset($filetype['type']) ? (string) $filetype['type'] : '';

        $expected_mime = isset($allowed_mime_map[$ext]) ? (string) $allowed_mime_map[$ext] : '';

        if ('' === $ext || '' === $type || '' === $expected_mime || $expected_mime !== $type) {
            @unlink($file['tmp_name']);
            add_settings_error(
                'tejlg_import_messages',
                'global_styles_import_invalid_type',
                esc_html__("Erreur : Le fichier téléchargé doit être un fichier JSON valide.", 'theme-export-jlg'),
                'error'
            );

            return;
        }

        if (!is_readable($file['tmp_name'])) {
            @unlink($file['tmp_name']);
            add_settings_error(
                'tejlg_import_messages',
                'global_styles_import_status',
                esc_html__("Erreur : Impossible d'accéder au fichier téléchargé.", 'theme-export-jlg'),
                'error'
            );

            return;
        }

        $json_content = file_get_contents($file['tmp_name']);

        @unlink($file['tmp_name']);

        if (false === $json_content) {
            add_settings_error(
                'tejlg_import_messages',
                'global_styles_import_status',
                esc_html__("Erreur : Impossible de lire le fichier téléchargé.", 'theme-export-jlg'),
                'error'
            );

            return;
        }

        $data = json_decode($json_content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            add_settings_error(
                'tejlg_import_messages',
                'global_styles_import_status',
                esc_html__("Erreur : Le fichier n'est pas un fichier JSON valide.", 'theme-export-jlg'),
                'error'
            );

            return;
        }

        $settings   = [];
        $stylesheet = '';

        if (isset($data['data']) && is_array($data['data'])) {
            if (isset($data['data']['settings'])) {
                $settings = $data['data']['settings'];
            }

            if (isset($data['data']['stylesheet']) && is_string($data['data']['stylesheet'])) {
                $stylesheet = $data['data']['stylesheet'];
            }
        }

        if (empty($settings) && isset($data['settings'])) {
            $settings = $data['settings'];
        }

        if ('' === $stylesheet && isset($data['stylesheet']) && is_string($data['stylesheet'])) {
            $stylesheet = $data['stylesheet'];
        }

        if (!is_array($settings)) {
            add_settings_error(
                'tejlg_import_messages',
                'global_styles_import_status',
                esc_html__("Erreur : La structure du fichier de styles globaux est invalide.", 'theme-export-jlg'),
                'error'
            );

            return;
        }

        $applied = false;
        $errors  = [];

        if (function_exists('wp_update_global_settings')) {
            $result = wp_update_global_settings($settings);

            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
            } elseif (false === $result) {
                $errors[] = esc_html__("WordPress a renvoyé une réponse vide lors de la mise à jour des réglages globaux.", 'theme-export-jlg');
            } else {
                $applied = true;
            }
        }

        if (!$applied) {
            $persist_result = self::persist_global_styles_post($settings, $stylesheet);

            if (is_wp_error($persist_result)) {
                $errors[] = $persist_result->get_error_message();
            } elseif (false === $persist_result) {
                $errors[] = esc_html__("Erreur inconnue lors de l'enregistrement des styles globaux.", 'theme-export-jlg');
            } else {
                $applied = true;
            }
        }

        if (!$applied) {
            $errors = array_filter(array_map([__CLASS__, 'sanitize_error_message'], $errors));

            if (empty($errors)) {
                $errors[] = esc_html__("Erreur : Les styles globaux n'ont pas pu être importés.", 'theme-export-jlg');
            }

            add_settings_error(
                'tejlg_import_messages',
                'global_styles_import_status',
                esc_html(implode(' ', array_unique($errors))),
                'error'
            );

            return;
        }

        add_settings_error(
            'tejlg_import_messages',
            'global_styles_import_status',
            esc_html__('Les styles globaux ont été importés avec succès.', 'theme-export-jlg'),
            'success'
        );
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

        $file_type_config = self::get_import_file_type('patterns');
        $allowed_mime_map = $file_type_config['mime_types'];

        $filetype = wp_check_filetype_and_ext(
            $file['tmp_name'],
            isset($file['name']) ? (string) $file['name'] : '',
            $allowed_mime_map
        );

        $ext  = isset($filetype['ext']) ? (string) $filetype['ext'] : '';
        $type = isset($filetype['type']) ? (string) $filetype['type'] : '';

        $expected_mime = isset($allowed_mime_map[$ext]) ? (string) $allowed_mime_map[$ext] : '';

        if ('' === $ext || '' === $type || '' === $expected_mime || $expected_mime !== $type) {
            @unlink($file['tmp_name']);
            add_settings_error(
                'tejlg_import_messages',
                'patterns_import_invalid_type',
                esc_html__("Erreur : Le fichier téléchargé doit être un fichier JSON valide.", 'theme-export-jlg'),
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

        $storage_result = self::persist_patterns_session($transient_id, $patterns);

        if (is_wp_error($storage_result)) {
            $message = $storage_result->get_error_message();
            $message = '' !== $message ? $message : __('Erreur : Impossible de préparer la session d\'importation.', 'theme-export-jlg');

            add_settings_error(
                'tejlg_import_messages',
                'patterns_import_status',
                esc_html(self::sanitize_error_message($message)),
                'error'
            );

            return;
        }

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

        $storage = get_transient($transient_id);

        if (false === $storage) {
            add_settings_error('tejlg_import_messages', 'patterns_import_status', esc_html__('Erreur : La session d\'importation a expiré. Veuillez réessayer.', 'theme-export-jlg'), 'error');
            return;
        }

        $all_patterns = self::retrieve_patterns_from_storage($storage);

        if (is_wp_error($all_patterns)) {
            $message = $all_patterns->get_error_message();
            $message = '' !== $message ? $message : __('Erreur : Impossible de récupérer les données de la session d\'importation.', 'theme-export-jlg');

            add_settings_error(
                'tejlg_import_messages',
                'patterns_import_status',
                esc_html(self::sanitize_error_message($message)),
                'error'
            );

            self::delete_patterns_storage($transient_id, $storage);

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

            $raw_slug_sources = [];

            foreach (['slug', 'name'] as $slug_field) {
                if (!isset($pattern[$slug_field])) {
                    continue;
                }

                $value = $pattern[$slug_field];

                if (is_scalar($value)) {
                    $value = (string) $value;
                } elseif (is_array($value) && isset($value['rendered']) && is_scalar($value['rendered'])) {
                    $value = (string) $value['rendered'];
                } else {
                    $value = '';
                }

                $value = trim($value);

                if ('' !== $value) {
                    $raw_slug_sources[] = $value;
                }
            }

            $title_value = '';

            if (isset($pattern['title'])) {
                $title_value = $pattern['title'];

                if (is_array($title_value)) {
                    if (isset($title_value['rendered']) && is_scalar($title_value['rendered'])) {
                        $title_value = (string) $title_value['rendered'];
                    } elseif (isset($title_value['raw']) && is_scalar($title_value['raw'])) {
                        $title_value = (string) $title_value['raw'];
                    } else {
                        $title_value = '';
                    }
                } elseif (!is_scalar($title_value)) {
                    $title_value = '';
                }
            }

            $title = sanitize_text_field((string) $title_value);

            $title_slug_source = trim(wp_strip_all_tags((string) $title_value));
            if ('' !== $title_slug_source) {
                $raw_slug_sources[] = $title_slug_source;
            }

            $raw_slug_sources = array_values(
                array_filter(
                    array_unique($raw_slug_sources),
                    static function ($value) {
                        return '' !== $value;
                    }
                )
            );

            $slug = '';
            $primary_slug_source = '';
            $normalized_slug_sources = [];

            foreach ($raw_slug_sources as $candidate) {
                if (0 === strpos($candidate, 'custom-patterns/')) {
                    $candidate = substr($candidate, strlen('custom-patterns/'));
                }

                $candidate = trim($candidate);

                if ('' === $candidate) {
                    continue;
                }

                $normalized_slug_sources[] = $candidate;

                if ('' === $slug) {
                    $sanitized_candidate = sanitize_title($candidate);

                    if ('' !== $sanitized_candidate) {
                        $slug = $sanitized_candidate;
                        $primary_slug_source = $candidate;
                    }
                }
            }

            if ('' === $slug) {
                $errors[] = self::sanitize_error_message(
                    sprintf(__('La composition à l\'index %d ne possède pas de slug valide.', 'theme-export-jlg'), $index)
                );
                $failed_patterns[$index] = $pattern;
                continue;
            }

            $candidate_slugs = [];

            foreach ($normalized_slug_sources as $candidate) {
                $candidate_slugs[] = $candidate;

                $sanitized_candidate = sanitize_title($candidate);

                if ('' !== $sanitized_candidate) {
                    $candidate_slugs[] = $sanitized_candidate;
                    $candidate_slugs[] = 'custom-patterns/' . $sanitized_candidate;
                }
            }

            $candidate_slugs[] = $slug;
            $candidate_slugs[] = 'custom-patterns/' . $slug;

            if ('' !== $primary_slug_source) {
                $candidate_slugs[] = $primary_slug_source;
            }

            $candidate_slugs = array_values(
                array_filter(
                    array_unique($candidate_slugs),
                    static function ($value) {
                        return '' !== $value;
                    }
                )
            );

            $content_source = isset($pattern['content'])
                ? self::extract_pattern_content_value($pattern['content'])
                : '';

            $content = self::sanitize_pattern_content_for_current_user($content_source);

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
                            $failed_patterns[$index] = $pattern;
                            $errors[]              = self::build_pattern_error_message(
                                'update',
                                $title,
                                $slug,
                                $existing_block instanceof WP_Post ? (int) $existing_block->ID : 0,
                                [$untrash_result->get_error_message()]
                            );

                            continue;
                        }

                        if (false === $untrash_result) {
                            $failed_patterns[$index] = $pattern;
                            $errors[]              = self::build_pattern_error_message(
                                'update',
                                $title,
                                $slug,
                                $existing_block instanceof WP_Post ? (int) $existing_block->ID : 0,
                                [__('Impossible de restaurer la composition depuis la corbeille.', 'theme-export-jlg')]
                            );

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

            $failure_reasons = [];

            if (is_wp_error($result)) {
                $failure_reasons[] = $result->get_error_message();
            }

            if (empty($result)) {
                $failure_reasons[] = __('WordPress a renvoyé une réponse vide.', 'theme-export-jlg');
            }

            if (!empty($failure_reasons)) {
                $failed_patterns[$index] = $pattern;

                $errors[] = self::build_pattern_error_message(
                    $action,
                    $title,
                    $slug,
                    $existing_block instanceof WP_Post ? (int) $existing_block->ID : 0,
                    $failure_reasons
                );

                continue;
            }

            $imported_count++;
        }

        if (!empty($errors)) {
            add_settings_error('tejlg_import_messages', 'patterns_import_errors', esc_html(implode(' ', array_unique($errors))), 'error');

            $patterns_to_store = !empty($failed_patterns) ? $failed_patterns : $all_patterns;
            $persist_result    = self::persist_patterns_session($transient_id, $patterns_to_store, $storage);

            if (is_wp_error($persist_result)) {
                $message = $persist_result->get_error_message();
                $message = '' !== $message ? $message : __('Erreur : Impossible de conserver les données de la session d\'importation.', 'theme-export-jlg');

                add_settings_error(
                    'tejlg_import_messages',
                    'patterns_import_status',
                    esc_html(self::sanitize_error_message($message)),
                    'error'
                );
            }
        } else {
            self::delete_patterns_storage($transient_id, $storage);
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

    private static function build_pattern_error_message($action, $title, $slug, $existing_id, array $details = []) {
        $safe_title = '' !== $title ? $title : __('(Titre manquant)', 'theme-export-jlg');
        $safe_slug  = '' !== $slug ? $slug : __('(slug manquant)', 'theme-export-jlg');

        $details = array_filter(
            array_map(
                [__CLASS__, 'sanitize_error_message'],
                $details
            )
        );

        if ('update' === $action) {
            $message = sprintf(
                __('Échec de la mise à jour de la composition "%1$s" (ID %2$d).', 'theme-export-jlg'),
                $safe_title,
                (int) $existing_id
            );
        } else {
            $message = sprintf(
                __('Échec de la création de la composition "%1$s" (slug "%2$s").', 'theme-export-jlg'),
                $safe_title,
                $safe_slug
            );
        }

        if (!empty($details)) {
            $message .= ' ' . sprintf(
                __('Raison : %s.', 'theme-export-jlg'),
                implode(' ', $details)
            );
        }

        return self::sanitize_error_message($message);
    }

    private static function persist_global_styles_post($settings, $stylesheet) {
        if (!post_type_exists('wp_global_styles')) {
            return new WP_Error(
                'global_styles_post_type_missing',
                esc_html__("Erreur : Cette installation de WordPress ne prend pas en charge l'enregistrement des styles globaux.", 'theme-export-jlg')
            );
        }

        $theme = wp_get_theme();

        if (!is_object($theme)) {
            return new WP_Error(
                'global_styles_theme_missing',
                esc_html__("Erreur : Impossible de déterminer le thème actif pour appliquer les styles globaux.", 'theme-export-jlg')
            );
        }

        $theme_slug = (string) $theme->get_stylesheet();

        if ('' === $theme_slug) {
            return new WP_Error(
                'global_styles_theme_slug_missing',
                esc_html__("Erreur : Le thème actif ne dispose pas d'identifiant valide pour stocker les styles globaux.", 'theme-export-jlg')
            );
        }

        $json_options = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $json_options |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        $content = wp_json_encode(['settings' => $settings], $json_options);

        if (false === $content || (function_exists('json_last_error') && json_last_error() !== JSON_ERROR_NONE)) {
            return new WP_Error(
                'global_styles_encode_error',
                esc_html__("Erreur : Impossible de préparer les données de styles globaux.", 'theme-export-jlg')
            );
        }

        $existing_id = self::find_existing_global_styles_post($theme_slug);
        $post_title  = sprintf(__('Styles globaux pour %s', 'theme-export-jlg'), $theme->get('Name'));
        $post_name   = 'wp-global-styles-' . sanitize_key($theme_slug);

        $post_data = [
            'post_type'            => 'wp_global_styles',
            'post_status'          => 'publish',
            'post_title'           => $post_title,
            'post_name'            => $post_name,
            'post_content'         => $content,
            'post_content_filtered'=> is_string($stylesheet) ? $stylesheet : '',
        ];

        if ($existing_id > 0) {
            $post_data['ID'] = $existing_id;
            $result          = wp_update_post(wp_slash($post_data), true);
            $post_id         = $existing_id;
        } else {
            $post_data['post_author'] = get_current_user_id();
            $result                   = wp_insert_post(wp_slash($post_data), true);
            $post_id                  = is_wp_error($result) ? 0 : (int) $result;
        }

        if (is_wp_error($result) || empty($result)) {
            $message = is_wp_error($result)
                ? $result->get_error_message()
                : esc_html__("Erreur inconnue lors de l'enregistrement des styles globaux.", 'theme-export-jlg');

            return new WP_Error('global_styles_persist_error', self::sanitize_error_message($message));
        }

        if ($post_id > 0 && taxonomy_exists('wp_theme')) {
            wp_set_post_terms($post_id, [$theme_slug], 'wp_theme', false);
        }

        return true;
    }

    private static function find_existing_global_styles_post($theme_slug) {
        $theme_slug = (string) $theme_slug;

        if ('' === $theme_slug || !post_type_exists('wp_global_styles')) {
            return 0;
        }

        $query_args = [
            'post_type'      => 'wp_global_styles',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'no_found_rows'  => true,
            'fields'         => 'ids',
        ];

        $sanitized_slug = sanitize_key($theme_slug);

        if (taxonomy_exists('wp_theme')) {
            $query_args['tax_query'] = [
                [
                    'taxonomy' => 'wp_theme',
                    'field'    => 'name',
                    'terms'    => [$theme_slug],
                ],
            ];
        } else {
            $query_args['name'] = 'wp-global-styles-' . $sanitized_slug;
        }

        $query   = new WP_Query($query_args);
        $post_id = 0;

        if (!empty($query->posts)) {
            $post_id = (int) $query->posts[0];
        }

        wp_reset_postdata();

        if ($post_id > 0) {
            return $post_id;
        }

        $post = get_page_by_path('wp-global-styles-' . $sanitized_slug, OBJECT, 'wp_global_styles');

        if ($post instanceof WP_Post) {
            return (int) $post->ID;
        }

        return 0;
    }

    private static function sanitize_error_message($message) {
        if (!is_scalar($message)) {
            return '';
        }

        $sanitized = wp_strip_all_tags((string) $message, true);
        $sanitized = preg_replace('/\s+/', ' ', $sanitized);

        return trim((string) $sanitized);
    }

    private static function persist_patterns_session($transient_id, array $patterns, $previous_storage = null) {
        $payload = self::create_patterns_storage_payload($patterns);

        if (is_wp_error($payload)) {
            return $payload;
        }

        $expiration    = self::get_patterns_storage_expiration();
        $transient_set = set_transient($transient_id, $payload, $expiration);

        if (!$transient_set) {
            self::cleanup_patterns_storage($payload);

            return new WP_Error(
                'tejlg_import_transient_error',
                __('Erreur : Impossible d\'enregistrer la session d\'importation.', 'theme-export-jlg')
            );
        }

        if (null !== $previous_storage) {
            self::cleanup_patterns_storage($previous_storage);
        }

        return true;
    }

    private static function create_patterns_storage_payload(array $patterns) {
        self::cleanup_expired_patterns_storage();

        $json_options = JSON_UNESCAPED_UNICODE;

        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $json_options |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        $encoded = wp_json_encode($patterns, $json_options);

        if (false === $encoded || (function_exists('json_last_error') && json_last_error() !== JSON_ERROR_NONE)) {
            return new WP_Error(
                'tejlg_import_json_encode_error',
                __('Erreur : Impossible de préparer les données des compositions pour l\'import.', 'theme-export-jlg')
            );
        }

        $temp_file = wp_tempnam('tejlg-patterns');

        if (empty($temp_file)) {
            return new WP_Error(
                'tejlg_import_temp_file_error',
                __('Erreur : Impossible de créer un fichier temporaire pour l\'import.', 'theme-export-jlg')
            );
        }

        $bytes_written = file_put_contents($temp_file, $encoded);

        if (false === $bytes_written) {
            @unlink($temp_file);

            return new WP_Error(
                'tejlg_import_temp_file_write_error',
                __('Erreur : Impossible d\'écrire les données d\'importation sur le disque.', 'theme-export-jlg')
            );
        }

        return [
            'type'      => 'file',
            'path'      => $temp_file,
            'count'     => count($patterns),
            'created'   => time(),
            'checksum'  => md5($encoded),
            'size'      => strlen($encoded),
        ];
    }

    public static function retrieve_patterns_from_storage($storage) {
        if (is_array($storage) && isset($storage['type']) && 'file' === $storage['type']) {
            $path = isset($storage['path']) ? (string) $storage['path'] : '';

            if ('' === $path || !is_readable($path)) {
                return new WP_Error(
                    'tejlg_import_storage_missing',
                    __('Erreur : Le fichier temporaire de la session d\'importation est introuvable.', 'theme-export-jlg')
                );
            }

            $contents = file_get_contents($path);

            if (false === $contents) {
                return new WP_Error(
                    'tejlg_import_storage_unreadable',
                    __('Erreur : Impossible de lire le fichier temporaire de la session d\'importation.', 'theme-export-jlg')
                );
            }

            $expected_size     = isset($storage['size']) ? (int) $storage['size'] : null;
            $expected_checksum = isset($storage['checksum']) ? (string) $storage['checksum'] : '';

            if (null !== $expected_size && $expected_size >= 0 && strlen($contents) !== $expected_size) {
                self::cleanup_patterns_storage($storage);

                return new WP_Error(
                    'tejlg_import_storage_size_mismatch',
                    __('Erreur : Les données temporaires de la session d\'importation sont incomplètes ou corrompues.', 'theme-export-jlg')
                );
            }

            if ('' !== $expected_checksum && md5($contents) !== $expected_checksum) {
                self::cleanup_patterns_storage($storage);

                return new WP_Error(
                    'tejlg_import_storage_checksum_mismatch',
                    __('Erreur : Les données temporaires de la session d\'importation ont été modifiées.', 'theme-export-jlg')
                );
            }

            $data = json_decode($contents, true);

            if (JSON_ERROR_NONE !== json_last_error() || !is_array($data)) {
                return new WP_Error(
                    'tejlg_import_storage_corrupted',
                    __('Erreur : Les données temporaires de la session d\'importation sont corrompues.', 'theme-export-jlg')
                );
            }

            if (isset($storage['count']) && (int) $storage['count'] !== count($data)) {
                self::cleanup_patterns_storage($storage);

                return new WP_Error(
                    'tejlg_import_storage_count_mismatch',
                    __('Erreur : Le contenu de la session d\'importation ne correspond pas aux données attendues.', 'theme-export-jlg')
                );
            }

            return $data;
        }

        if (is_array($storage)) {
            return $storage;
        }

        return new WP_Error(
            'tejlg_import_storage_invalid',
            __('Erreur : Les données de la session d\'importation sont invalides.', 'theme-export-jlg')
        );
    }

    private static function cleanup_patterns_storage($storage) {
        if (is_array($storage) && isset($storage['type']) && 'file' === $storage['type']) {
            $path = isset($storage['path']) ? (string) $storage['path'] : '';

            if ('' !== $path && @file_exists($path)) {
                @unlink($path);
            }
        }
    }

    public static function cleanup_expired_patterns_storage() {
        $expiration = self::get_patterns_storage_expiration();

        $max_age = (int) apply_filters(
            'tejlg_import_patterns_storage_cleanup_max_age',
            $expiration * 2
        );

        if ($max_age < $expiration) {
            $max_age = $expiration;
        }

        self::cleanup_stale_pattern_files($max_age);
    }

    private static function get_patterns_storage_expiration() {
        $expiration = (int) apply_filters('tejlg_import_patterns_storage_expiration', 15 * MINUTE_IN_SECONDS);

        if ($expiration < MINUTE_IN_SECONDS) {
            $expiration = MINUTE_IN_SECONDS;
        }

        return $expiration;
    }

    private static function cleanup_stale_pattern_files($max_age) {
        if ($max_age <= 0 || !function_exists('get_temp_dir')) {
            return;
        }

        $temp_dir = get_temp_dir();

        if (!is_string($temp_dir) || '' === $temp_dir) {
            return;
        }

        $temp_dir = trailingslashit($temp_dir);

        if (!@is_dir($temp_dir) || !@is_readable($temp_dir)) {
            return;
        }

        $files = @glob($temp_dir . 'tejlg-patterns*');

        if (!is_array($files) || empty($files)) {
            return;
        }

        $threshold = time() - (int) $max_age;

        foreach ($files as $file) {
            if (!@is_file($file)) {
                continue;
            }

            $file_mtime = @filemtime($file);
            $file_ctime = @filectime($file);

            if ((false !== $file_mtime && $file_mtime > $threshold) || (false !== $file_ctime && $file_ctime > $threshold)) {
                continue;
            }

            @unlink($file);
        }
    }

    public static function delete_patterns_storage($transient_id, $storage) {
        self::cleanup_patterns_storage($storage);
        delete_transient($transient_id);
    }

    private static function generate_block_comment_token(array $existing_tokens) {
        $attempts = 0;

        do {
            $random_segment = '';

            if (function_exists('random_bytes')) {
                try {
                    $random_segment = bin2hex(random_bytes(12));
                } catch (Exception $e) {
                    $random_segment = '';
                }
            }

            if ('' === $random_segment) {
                if (function_exists('wp_unique_id')) {
                    $random_segment = wp_unique_id('tejlg_wp_comment_');
                } else {
                    $random_segment = uniqid('tejlg_wp_comment_', true);
                }
            } else {
                $random_segment = 'tejlg_wp_comment_' . $random_segment;
            }

            $token = '__' . $random_segment . '__';
            $attempts++;
        } while (isset($existing_tokens[$token]) && $attempts < 5);

        if (isset($existing_tokens[$token])) {
            $fallback_segment = function_exists('wp_unique_id')
                ? wp_unique_id('tejlg_wp_comment_')
                : uniqid('tejlg_wp_comment_', true);

            $token = '__' . $fallback_segment . '__';
        }

        return $token;
    }

    /**
     * Extract a usable pattern content string from various content formats.
     *
     * @param mixed $raw_content Content field from the imported pattern.
     * @return string Pattern content suitable for parsing or sanitizing.
     */
    public static function extract_pattern_content_value($raw_content) {
        if (is_array($raw_content)) {
            foreach (['raw', 'rendered'] as $content_key) {
                if (!array_key_exists($content_key, $raw_content)) {
                    continue;
                }

                $candidate = $raw_content[$content_key];

                if (!is_scalar($candidate)) {
                    continue;
                }

                $candidate_string = (string) $candidate;

                if ('' !== trim($candidate_string)) {
                    return $candidate_string;
                }
            }

            $raw_content = '';
        }

        if (!is_scalar($raw_content)) {
            return '';
        }

        return (string) $raw_content;
    }

    /**
     * Sanitize pattern content for users without the unfiltered_html capability while preserving block structure.
     */
    private static function sanitize_pattern_content_for_current_user($raw_content) {
        if (current_user_can('unfiltered_html')) {
            return $raw_content;
        }

        $block_comment_tokens = [];
        $tokenized_content    = '';
        $offset               = 0;
        $length               = strlen($raw_content);

        while (false !== ($start = strpos($raw_content, '<!--', $offset))) {
            $tokenized_content .= substr($raw_content, $offset, $start - $offset);

            $comment_candidate = substr($raw_content, $start);

            if (!preg_match('/^<!--\s*\/?wp:/i', $comment_candidate)) {
                $tokenized_content .= '<!--';
                $offset = $start + 4;
                continue;
            }

            $in_single_quote = false;
            $in_double_quote = false;
            $comment_end     = false;

            for ($i = $start + 4; $i < $length; $i++) {
                $char = $raw_content[$i];

                $backslash_count = 0;
                for ($j = $i - 1; $j >= $start + 4 && '\\' === $raw_content[$j]; $j--) {
                    $backslash_count++;
                }

                $is_escaped = ($backslash_count % 2) === 1;

                if (!$in_single_quote && '"' === $char && !$is_escaped) {
                    $in_double_quote = !$in_double_quote;
                } elseif (!$in_double_quote && "'" === $char && !$is_escaped) {
                    $in_single_quote = !$in_single_quote;
                }

                if (!$in_single_quote && !$in_double_quote && '-' === $char) {
                    if (substr($raw_content, $i, 3) === '-->') {
                        $comment_end = $i + 3;
                        break;
                    }
                }
            }

            if (false === $comment_end) {
                $tokenized_content .= substr($raw_content, $start);
                $offset = $length;
                break;
            }

            $comment = substr($raw_content, $start, $comment_end - $start);
            $token   = self::generate_block_comment_token($block_comment_tokens);

            $block_comment_tokens[$token] = $comment;
            $tokenized_content           .= $token;

            $offset = $comment_end;
        }

        if ($offset < $length) {
            $tokenized_content .= substr($raw_content, $offset);
        }

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

<?php
class TEJLG_Export {

    /**
     * Crée et télécharge l'archive ZIP du thème actif.
     */
    public static function export_theme($exclusions = []) {
        if (!class_exists('ZipArchive')) {
            wp_die(esc_html__('La classe ZipArchive n\'est pas disponible.', 'theme-export-jlg'));
        }

        $exclusions = array_values(array_filter(
            array_map(
                static function ($pattern) {
                    if (!is_scalar($pattern)) {
                        return '';
                    }

                    $pattern = trim((string) $pattern);

                    if ('' === $pattern) {
                        return '';
                    }

                    return ltrim($pattern, "\\/");
                },
                (array) $exclusions
            ),
            static function ($pattern) {
                return '' !== $pattern;
            }
        ));

        $theme = wp_get_theme();
        $theme_dir_path = $theme->get_stylesheet_directory();
        $theme_slug = $theme->get_stylesheet();
        $zip_file_name = $theme_slug . '.zip';
        $zip_file_path = wp_tempnam($zip_file_name);

        if (!$zip_file_path) {
            wp_die(esc_html__('Impossible de créer le fichier temporaire pour l\'archive ZIP.', 'theme-export-jlg'));
        }

        if (file_exists($zip_file_path) && !self::delete_temp_file($zip_file_path)) {
            wp_die(esc_html__('Impossible de préparer le fichier temporaire pour l\'archive ZIP.', 'theme-export-jlg'));
        }

        $zip = new ZipArchive();
        if ($zip->open($zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            wp_die(esc_html__('Impossible de créer l\'archive ZIP.', 'theme-export-jlg'));
        }

        $normalized_theme_dir = self::normalize_path($theme_dir_path);
        $files_added = 0;

        $directory_iterator = new RecursiveDirectoryIterator(
            $theme_dir_path,
            FilesystemIterator::SKIP_DOTS
        );

        $filter_iterator = new RecursiveCallbackFilterIterator(
            $directory_iterator,
            static function (SplFileInfo $file) use ($normalized_theme_dir, $exclusions) {
                if ($file->isLink()) {
                    return false;
                }

                $real_path = $file->getRealPath();

                if (false === $real_path) {
                    return false;
                }

                $normalized_file_path = self::normalize_path($real_path);

                if (!self::is_path_within_base($normalized_file_path, $normalized_theme_dir)) {
                    return false;
                }

                $relative_path = self::get_relative_path($normalized_file_path, $normalized_theme_dir);

                if ($file->isDir()) {
                    return '' === $relative_path || !self::should_exclude_file($relative_path, $exclusions);
                }

                if ('' === $relative_path) {
                    return false;
                }

                return !self::should_exclude_file($relative_path, $exclusions);
            }
        );

        $iterator = new RecursiveIteratorIterator(
            $filter_iterator,
            RecursiveIteratorIterator::SELF_FIRST,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );

        $zip_root_directory = rtrim($theme_slug, '/') . '/';

        if (true !== $zip->addEmptyDir($zip_root_directory)) {
            self::abort_zip_export(
                $zip,
                $zip_file_path,
                sprintf(
                    /* translators: %s: slug of the theme used as the root directory of the ZIP archive. */
                    esc_html__("Impossible d'ajouter le dossier racine « %s » à l'archive ZIP.", 'theme-export-jlg'),
                    esc_html($zip_root_directory)
                )
            );
        }

        $directories_added = [
            $zip_root_directory => true,
        ];

        foreach ($iterator as $file) {
            $real_path = $file->getRealPath();

            if (false === $real_path) {
                continue;
            }

            $normalized_file_path = self::normalize_path($real_path);

            if (!self::is_path_within_base($normalized_file_path, $normalized_theme_dir)) {
                continue;
            }

            $relative_path = self::get_relative_path($normalized_file_path, $normalized_theme_dir);

            if ('' === $relative_path) {
                continue;
            }

            $relative_path_in_zip = $zip_root_directory . ltrim($relative_path, '/');

            if ($file->isDir()) {
                $zip_path = rtrim($relative_path_in_zip, '/') . '/';

                if (!isset($directories_added[$zip_path])) {
                    if (true !== $zip->addEmptyDir($zip_path)) {
                        self::abort_zip_export(
                            $zip,
                            $zip_file_path,
                            sprintf(
                                /* translators: %s: relative path of the directory that failed to be added to the ZIP archive. */
                                esc_html__('Impossible d\'ajouter le dossier « %s » à l\'archive ZIP.', 'theme-export-jlg'),
                                esc_html($zip_path)
                            )
                        );
                    }

                    $directories_added[$zip_path] = true;
                }

                continue;
            }

            if (true !== $zip->addFile($real_path, $relative_path_in_zip)) {
                self::abort_zip_export(
                    $zip,
                    $zip_file_path,
                    sprintf(
                        /* translators: %s: relative path of the file that failed to be added to the ZIP archive. */
                        esc_html__('Impossible d\'ajouter le fichier « %s » à l\'archive ZIP.', 'theme-export-jlg'),
                        esc_html($relative_path_in_zip)
                    )
                );
            }
            $files_added++;
        }

        if (0 === $files_added) {
            $zip->close();

            if (file_exists($zip_file_path)) {
                self::delete_temp_file($zip_file_path);
            }

            add_settings_error(
                'tejlg_admin_messages',
                'theme_export_all_excluded',
                esc_html__("Erreur : tous les fichiers ont été exclus de l'export. Vérifiez vos motifs.", 'theme-export-jlg'),
                'error'
            );

            return;
        }
        $zip->close();

        nocache_headers();
        self::clear_output_buffers();

        $zip_file_size = filesize($zip_file_path);
        /**
         * Filters the computed ZIP file size before streaming the archive.
         *
         * This allows testing utilities or custom integrations to override the
         * detected size when required.
         *
         * @since 1.0.0
         *
         * @param int|false $zip_file_size  The detected ZIP file size or false on failure.
         * @param string    $zip_file_path  Absolute path to the ZIP file being exported.
         */
        $zip_file_size = apply_filters('tejlg_export_zip_file_size', $zip_file_size, $zip_file_path);

        if (!is_numeric($zip_file_size)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[Theme Export JLG] Unable to determine ZIP size for download: %s (value: %s)', $zip_file_path, var_export($zip_file_size, true)));
            }

            self::delete_temp_file($zip_file_path);

            wp_die(esc_html__("Impossible de déterminer la taille de l'archive ZIP à télécharger.", 'theme-export-jlg'));
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zip_file_name . '"');
        header('Content-Length: ' . (string) (int) $zip_file_size);
        readfile($zip_file_path);
        flush();

        self::delete_temp_file($zip_file_path);
        exit;
    }

    /**
     * Aborts the ZIP export, cleans up temporary files and stops execution.
     *
     * @param ZipArchive $zip           Archive instance to close.
     * @param string     $zip_file_path Path to the temporary ZIP file.
     * @param string     $message       Sanitized error message displayed to the user.
     */
    private static function abort_zip_export(ZipArchive $zip, $zip_file_path, $message) {
        $zip->close();

        if (file_exists($zip_file_path)) {
            self::delete_temp_file($zip_file_path);
        }

        wp_die($message);
    }

    /**
     * Exporte toutes les compositions en JSON.
     *
     * @param array $pattern_ids Liste optionnelle d'identifiants de compositions à exporter.
     * @param bool  $is_portable Active le nettoyage « portable » du contenu.
     */
    public static function export_patterns_json($pattern_ids = [], $is_portable = false) {
        $sanitized_ids = array_filter(array_map('intval', (array) $pattern_ids));
        $batch_size    = (int) apply_filters('tejlg_export_patterns_batch_size', 100);

        if ($batch_size < 1) {
            $batch_size = 100;
        }

        $args = [
            'post_type'              => 'wp_block',
            'posts_per_page'         => $batch_size,
            'post_status'            => 'publish',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'lazy_load_term_meta'    => false,
        ];

        if (!empty($sanitized_ids)) {
            $args['post__in'] = $sanitized_ids;
            $args['orderby']  = 'post__in';
        }

        $temp_file = wp_tempnam('tejlg-patterns-export');

        if (empty($temp_file)) {
            wp_die(esc_html__("Une erreur critique est survenue lors de la préparation du fichier JSON d'export.", 'theme-export-jlg'));
        }

        $handle = fopen($temp_file, 'w');

        if (false === $handle) {
            @unlink($temp_file);
            wp_die(esc_html__("Impossible de créer le flux de téléchargement pour l'export JSON.", 'theme-export-jlg'));
        }

        $has_written_items = false;
        fwrite($handle, "[\n");

        $page = 1;

        while (true) {
            $args['paged'] = $page;
            $patterns_query = new WP_Query($args);

            if (!$patterns_query->have_posts()) {
                wp_reset_postdata();
                break;
            }

            $current_batch_count = count($patterns_query->posts);

            while ($patterns_query->have_posts()) {
                $patterns_query->the_post();

                $content = self::get_sanitized_content();
                if ($is_portable) {
                    $content = self::clean_pattern_content($content);
                }

                $slug = get_post_field('post_name', get_the_ID());
                if ('' === $slug) {
                    $slug = sanitize_title(get_the_title());
                }

                $pattern_data = [
                    'title'   => get_the_title(),
                    'slug'    => $slug,
                    'content' => $content,
                ];

                $encoded_pattern = wp_json_encode($pattern_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

                if (false === $encoded_pattern || (function_exists('json_last_error') && json_last_error() !== JSON_ERROR_NONE)) {
                    fclose($handle);
                    @unlink($temp_file);

                    $json_error_message = function_exists('json_last_error_msg')
                        ? json_last_error_msg()
                        : esc_html__('Erreur JSON inconnue.', 'theme-export-jlg');

                    wp_die(
                        esc_html(
                            sprintf(
                                __('Une erreur critique est survenue lors de la création du fichier JSON : %s. Cela peut être dû à des caractères invalides dans une de vos compositions.', 'theme-export-jlg'),
                                $json_error_message
                            )
                        )
                    );
                }

                $formatted_pattern = self::indent_json_fragment($encoded_pattern);

                if ($has_written_items) {
                    fwrite($handle, ",\n" . $formatted_pattern);
                } else {
                    fwrite($handle, $formatted_pattern);
                    $has_written_items = true;
                }
            }

            wp_reset_postdata();

            if ($current_batch_count < $batch_size) {
                break;
            }

            $page++;
        }

        fwrite($handle, $has_written_items ? "\n]\n" : "]\n");
        fclose($handle);

        $filename = empty($sanitized_ids) ? 'exported-patterns.json' : 'selected-patterns.json';
        return self::stream_json_file($temp_file, $filename);
    }

    private static function normalize_path($path) {
        $normalized = function_exists('wp_normalize_path')
            ? wp_normalize_path($path)
            : str_replace('\\', '/', $path);

        return rtrim($normalized, '/');
    }

    private static function is_path_within_base($path, $base) {
        if ('' === $base) {
            return true;
        }

        return $path === $base || 0 === strpos($path, $base . '/');
    }

    private static function get_relative_path($path, $base) {
        if ($path === $base) {
            return '';
        }

        $relative = substr($path, strlen($base));
        $relative = ltrim($relative, '/');

        return $relative;
    }

    private static function should_exclude_file($relative_path, $patterns) {
        if (empty($patterns)) {
            return false;
        }

        foreach ($patterns as $pattern) {
            $normalized_pattern = function_exists('wp_normalize_path')
                ? wp_normalize_path($pattern)
                : str_replace('\\', '/', $pattern);

            if ('' === $normalized_pattern) {
                continue;
            }

            $normalized_pattern = ltrim($normalized_pattern, '/');

            if (function_exists('wp_match_path_pattern')) {
                if (wp_match_path_pattern($normalized_pattern, $relative_path)) {
                    return true;
                }
            } elseif (function_exists('fnmatch')) {
                if (fnmatch($normalized_pattern, $relative_path)) {
                    return true;
                }
            } else {
                $regex = '#^' . str_replace('\\*', '.*', preg_quote($normalized_pattern, '#')) . '$#i';
                if (preg_match($regex, $relative_path)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Safely removes a temporary file, logging failures when possible.
     *
     * @param string $file_path Absolute path to the file to delete.
     * @return bool True on success or if the file is absent, false otherwise.
     */
    private static function delete_temp_file($file_path) {
        if (empty($file_path) || !file_exists($file_path)) {
            return true;
        }

        if (@unlink($file_path)) {
            return true;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[Theme Export JLG] Unable to delete temporary file: %s', $file_path));
        }

        return false;
    }

    /**
     * Exporte uniquement les compositions dont les IDs sont fournis.
     *
     * @param array $pattern_ids Liste d'identifiants de compositions à exporter.
     * @param bool  $is_portable Active le nettoyage « portable » du contenu.
     */
    public static function export_selected_patterns_json($pattern_ids, $is_portable = false) {
        return self::export_patterns_json($pattern_ids, $is_portable);
    }

    public static function export_global_styles() {
        if (!function_exists('wp_get_global_settings')) {
            add_settings_error(
                'tejlg_admin_messages',
                'global_styles_export_unsupported',
                esc_html__("Erreur : Cette version de WordPress ne permet pas l'export des réglages globaux.", 'theme-export-jlg'),
                'error'
            );

            return;
        }

        $settings = wp_get_global_settings();

        if (!is_array($settings)) {
            $settings = [];
        }

        $stylesheet = '';

        if (function_exists('wp_get_global_stylesheet')) {
            $raw_stylesheet = wp_get_global_stylesheet();
            $stylesheet     = is_string($raw_stylesheet) ? $raw_stylesheet : '';
        }

        $theme     = wp_get_theme();
        $theme_name = is_object($theme) ? $theme->get('Name') : '';
        $theme_slug = is_object($theme) ? $theme->get_stylesheet() : '';

        $payload = [
            'meta' => [
                'generated_at' => gmdate('c'),
                'site_url'     => home_url('/'),
                'wp_version'   => get_bloginfo('version'),
                'tejlg_version' => defined('TEJLG_VERSION') ? TEJLG_VERSION : null,
                'theme'        => [
                    'name'       => $theme_name,
                    'stylesheet' => $theme_slug,
                ],
            ],
            'data' => [
                'settings'   => $settings,
                'stylesheet' => $stylesheet,
            ],
        ];

        if (null === $payload['meta']['tejlg_version']) {
            unset($payload['meta']['tejlg_version']);
        }

        $json_options = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $json_options |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        $json_data = wp_json_encode($payload, $json_options);

        if (false === $json_data || (function_exists('json_last_error') && json_last_error() !== JSON_ERROR_NONE)) {
            add_settings_error(
                'tejlg_admin_messages',
                'global_styles_export_json_error',
                esc_html__("Erreur : Impossible de générer le fichier JSON des styles globaux.", 'theme-export-jlg'),
                'error'
            );

            return;
        }

        $temp_file = wp_tempnam('tejlg-global-styles');

        if (empty($temp_file)) {
            add_settings_error(
                'tejlg_admin_messages',
                'global_styles_export_tmp_error',
                esc_html__("Erreur : Impossible de préparer le fichier d'export des styles globaux.", 'theme-export-jlg'),
                'error'
            );

            return;
        }

        $bytes = file_put_contents($temp_file, $json_data);

        if (false === $bytes) {
            @unlink($temp_file);

            add_settings_error(
                'tejlg_admin_messages',
                'global_styles_export_write_error',
                esc_html__("Erreur : Impossible d'écrire le fichier d'export des styles globaux.", 'theme-export-jlg'),
                'error'
            );

            return;
        }

        $filename = sanitize_key($theme_slug);
        $filename = '' !== $filename ? 'global-styles-' . $filename . '.json' : 'global-styles.json';

        self::stream_json_file($temp_file, $filename);
    }
    
    /**
     * Récupère le contenu et garantit qu'il est en UTF-8 valide.
     */
    private static function get_sanitized_content() {
        $content = get_post_field('post_content', get_the_ID());
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        }

        if (function_exists('wp_check_invalid_utf8')) {
            return wp_check_invalid_utf8($content, true);
        }

        return (string) $content;
    }

    /**
     * Nettoie le contenu d'une composition pour la rendre portable.
     */
    private static function clean_pattern_content($content) {
        $blocks = function_exists('parse_blocks') ? parse_blocks($content) : [];

        if (!empty($blocks)) {
            $blocks = array_map([__CLASS__, 'clean_block_recursive'], $blocks);

            if (function_exists('serialize_block')) {
                $content = implode('', array_map('serialize_block', $blocks));
            } elseif (function_exists('render_block')) {
                $content = implode('', array_map('render_block', $blocks));
            }
        }

        // 1. Remplace les URLs absolues du site par des URLs relatives
        $home_url = get_home_url();
        $home_parts = wp_parse_url($home_url);
        $home_path  = '';
        $allowed_ports = [];

        if (!empty($home_parts['path'])) {
            $trimmed_path = trim($home_parts['path'], '/');

            if ('' !== $trimmed_path) {
                $home_path = '/' . $trimmed_path;
            }
        }

        if (!empty($home_parts['host'])) {
            $host_pattern = preg_quote($home_parts['host'], '#');
            $port_pattern = '';

            if (!empty($home_parts['port'])) {
                $allowed_ports[] = (string) $home_parts['port'];
            } else {
                $scheme = isset($home_parts['scheme']) ? strtolower($home_parts['scheme']) : '';

                if ('http' === $scheme) {
                    $allowed_ports[] = '80';
                } elseif ('https' === $scheme) {
                    $allowed_ports[] = '443';
                }
            }

            if (!empty($allowed_ports)) {
                $escaped_ports = array_map(
                    static function ($port) {
                        return preg_quote($port, '#');
                    },
                    $allowed_ports
                );
                $port_pattern = '(?::(?:' . implode('|', $escaped_ports) . '))?';
            }

            $pattern = '#https?:\/\/' . $host_pattern . $port_pattern . '(?=[\/\?#]|$)([\/\?#][^\s"\'>]*)?#i';
            $relative_content = preg_replace_callback(
                $pattern,
                static function ($matches) use ($home_path) {
                    $relative = wp_make_link_relative($matches[0]);

                    if ('' !== $relative && preg_match('#^https?://#i', $relative)) {
                        $parsed_url = wp_parse_url($matches[0]);

                        $path      = isset($parsed_url['path']) ? $parsed_url['path'] : '';
                        $query     = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
                        $fragment  = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
                        $relative  = $path . $query . $fragment;
                    }

                    if ('' !== $home_path && 0 === strpos($relative, $home_path)) {
                        $remaining = substr($relative, strlen($home_path));

                        if ($remaining === '' || in_array($remaining[0], ['/', '?', '#'], true)) {
                            $has_duplicate_prefix = 0 === strpos($remaining, $home_path)
                                && ('' === substr($remaining, strlen($home_path))
                                    || in_array(substr($remaining, strlen($home_path), 1), ['/', '?', '#'], true));

                            if ($has_duplicate_prefix) {
                                $relative = $home_path . substr($remaining, strlen($home_path));
                            }

                            if ($relative === '' || '/' !== $relative[0]) {
                                $relative = '/' . ltrim($relative, '/');
                            }
                        }
                    }

                    if ('' === $relative) {
                        return '/';
                    }

                    if ('/' !== $relative[0]) {
                        $relative = '/' . ltrim($relative, '/');
                    }

                    return $relative;
                },
                $content
            );

            if (null !== $relative_content) {
                $content = $relative_content;
            }
        }

        return $content;
    }

    /**
     * Supprime récursivement les métadonnées des blocs.
     */
    private static function clean_block_recursive($block) {
        if (isset($block['attrs']) && is_array($block['attrs'])) {
            $block['attrs'] = self::clean_metadata_recursive($block['attrs']);
            $block['attrs'] = self::reset_block_ids_recursive($block['attrs']);
        }

        if (!empty($block['innerBlocks'])) {
            $block['innerBlocks'] = array_map([__CLASS__, 'clean_block_recursive'], $block['innerBlocks']);
        }

        return $block;
    }

    /**
     * Parcourt récursivement une structure de données pour supprimer la clé "metadata".
     */
    private static function clean_metadata_recursive($data) {
        if (!is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if ('metadata' === $key) {
                unset($data[$key]);
                continue;
            }

            $data[$key] = self::clean_metadata_recursive($value);
        }

        return $data;
    }

    /**
     * Réinitialise récursivement les identifiants présents dans les attributs de blocs.
     */
    private static function reset_block_ids_recursive($data) {
        if (!is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if ('id' === $key) {
                $data[$key] = self::neutralize_single_id_value($value);
                continue;
            }

            if ('ids' === $key) {
                $data[$key] = self::neutralize_ids_collection($value);
                continue;
            }

            $data[$key] = self::reset_block_ids_recursive($value);
        }

        return $data;
    }

    /**
     * Normalise une valeur d'identifiant simple.
     */
    private static function neutralize_single_id_value($value) {
        if (is_array($value)) {
            return self::reset_block_ids_recursive($value);
        }

        return 0;
    }

    /**
     * Normalise une collection d'identifiants.
     */
    private static function neutralize_ids_collection($value) {
        if (!is_array($value)) {
            return [];
        }

        return array_map([__CLASS__, 'neutralize_single_id_value'], $value);
    }

    /**
     * Gère la création et le téléchargement du fichier JSON.
     */
    private static function download_json( $data, $filename = 'exported-patterns.json' ) {
        $json_options = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE;

        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $json_options |= JSON_INVALID_UTF8_SUBSTITUTE;
        }

        $json_data = wp_json_encode($data, $json_options);

        if (false === $json_data || (function_exists('json_last_error') && json_last_error() !== JSON_ERROR_NONE)) {
            $json_error_message = function_exists('json_last_error_msg') ? json_last_error_msg() : __('Erreur JSON inconnue.', 'theme-export-jlg');

            wp_die(
                esc_html(
                    sprintf(
                        __('Une erreur critique est survenue lors de la création du fichier JSON : %s. Cela peut être dû à des caractères invalides dans une de vos compositions.', 'theme-export-jlg'),
                        $json_error_message
                    )
                )
            );
        }

        $temp_file = wp_tempnam('tejlg-patterns-export');

        if (empty($temp_file)) {
            wp_die(esc_html__("Une erreur critique est survenue lors de la préparation du fichier JSON d'export.", 'theme-export-jlg'));
        }

        $bytes = file_put_contents($temp_file, $json_data);

        if (false === $bytes) {
            @unlink($temp_file);
            wp_die(esc_html__("Impossible d'écrire le fichier d'export JSON sur le disque.", 'theme-export-jlg'));
        }

        self::stream_json_file($temp_file, $filename);
    }

    private static function clear_output_buffers() {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    private static function indent_json_fragment($json, $depth = 1) {
        $json  = trim((string) $json);
        $lines = explode("\n", $json);
        $indent = str_repeat('    ', max(0, (int) $depth));

        $lines = array_map(
            static function ($line) use ($indent) {
                return $indent . rtrim($line, "\r");
            },
            $lines
        );

        return implode("\n", $lines);
    }

    private static function stream_json_file($file_path, $filename) {
        if (!@file_exists($file_path) || !is_readable($file_path)) {
            @unlink($file_path);
            wp_die(esc_html__("Le fichier d'export JSON est introuvable ou illisible.", 'theme-export-jlg'));
        }

        $should_stream = apply_filters('tejlg_export_stream_json_file', true, $file_path, $filename);

        if (!$should_stream) {
            $contents = @file_get_contents($file_path);
            @unlink($file_path);

            return false === $contents ? '' : $contents;
        }

        $file_size = filesize($file_path);

        nocache_headers();
        self::clear_output_buffers();

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        if (false !== $file_size) {
            header('Content-Length: ' . $file_size);
        }

        $handle = fopen($file_path, 'rb');

        if (false === $handle) {
            @unlink($file_path);
            wp_die(esc_html__("Impossible de lire le fichier d'export JSON.", 'theme-export-jlg'));
        }

        while (!feof($handle)) {
            echo fread($handle, 8192);
        }

        fclose($handle);
        @unlink($file_path);
        flush();
        exit;
    }
}
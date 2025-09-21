<?php
class TEJLG_Export {

    /**
     * Crée et télécharge l'archive ZIP du thème actif.
     */
    public static function export_theme() {
        if (!class_exists('ZipArchive')) {
            wp_die(esc_html__('La classe ZipArchive n\'est pas disponible.', 'theme-export-jlg'));
        }

        $theme = wp_get_theme();
        $theme_dir_path = $theme->get_stylesheet_directory();
        $theme_slug = $theme->get_stylesheet();
        $zip_file_name = $theme_slug . '.zip';
        $zip_file_path = wp_tempnam($zip_file_name);

        if (!$zip_file_path) {
            wp_die(esc_html__('Impossible de créer le fichier temporaire pour l\'archive ZIP.', 'theme-export-jlg'));
        }

        unlink($zip_file_path);

        $zip = new ZipArchive();
        if ($zip->open($zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            wp_die(esc_html__('Impossible de créer l\'archive ZIP.', 'theme-export-jlg'));
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($theme_dir_path), RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $file_path = $file->getRealPath();
                $relative_path = substr($file_path, strlen($theme_dir_path) + 1);
                $zip->addFile($file_path, $relative_path);
            }
        }
        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zip_file_name . '"');
        header('Content-Length: ' . filesize($zip_file_path));
        readfile($zip_file_path);

        unlink($zip_file_path);
        exit;
    }

    /**
     * Exporte toutes les compositions en JSON.
     */
    public static function export_patterns_json($pattern_ids = []) {
        $is_portable = isset($_POST['export_portable']);
        $sanitized_ids = array_filter(array_map('intval', (array) $pattern_ids));
        $exported_patterns = [];
        $args = [
            'post_type'      => 'wp_block',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
        ];

        if (!empty($sanitized_ids)) {
            $args['post__in'] = $sanitized_ids;
            $args['orderby']  = 'post__in';
        }

        $patterns_query = new WP_Query($args);

        if ($patterns_query->have_posts()) {
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

                $exported_patterns[] = [
                    'title'   => get_the_title(),
                    'slug'    => $slug,
                    'content' => $content,
                ];
            }
        }

        wp_reset_postdata();

        $filename = empty($sanitized_ids) ? 'exported-patterns.json' : 'selected-patterns.json';
        self::download_json($exported_patterns, $filename);
    }

    /**
     * Exporte uniquement les compositions dont les IDs sont fournis.
     */
    public static function export_selected_patterns_json($pattern_ids) {
        self::export_patterns_json($pattern_ids);
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
            $content = implode('', array_map('serialize_block', $blocks));
        }

        // 1. Remplace les URLs absolues du site par des URLs relatives
        $home_url = get_home_url();
        $home_parts = wp_parse_url($home_url);
        $home_path  = '';

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
                $port_pattern = '(?::' . preg_quote((string) $home_parts['port'], '#') . ')?';
            } else {
                $port_pattern = '(?::\d+)?';
            }

            $pattern = '#https?:\/\/' . $host_pattern . $port_pattern . '(?=[\/\?#]|$)([\/\?#][^\s"\'>]*)?#i';
            $relative_content = preg_replace_callback(
                $pattern,
                static function ($matches) use ($home_path) {
                    $relative = wp_make_link_relative($matches[0]);

                    if ('' !== $home_path && 0 === strpos($relative, $home_path)) {
                        $relative = substr($relative, strlen($home_path));

                        if ($relative === '' || '/' !== $relative[0]) {
                            $relative = '/' . ltrim($relative, '/');
                        }
                    }

                    return '' !== $relative ? $relative : '/';
                },
                $content
            );

            if (null !== $relative_content) {
                $content = $relative_content;
            }
        }

        // 2. Neutralise les IDs des médias pour éviter les dépendances
        $content = preg_replace('/("id"\s*:\s*)\d+/', '${1}0', $content);

        return $content;
    }

    /**
     * Supprime récursivement les métadonnées des blocs.
     */
    private static function clean_block_recursive($block) {
        if (isset($block['attrs'])) {
            $block['attrs'] = self::clean_metadata_recursive($block['attrs']);
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

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($json_data));
        echo $json_data;
        exit;
    }
}
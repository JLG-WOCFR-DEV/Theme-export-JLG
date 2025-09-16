<?php
class TEJLG_Export {

    /**
     * Crée et télécharge l'archive ZIP du thème actif.
     */
    public static function export_theme() {
        if (!class_exists('ZipArchive')) wp_die('La classe ZipArchive n\'est pas disponible.');

        $theme = wp_get_theme();
        $theme_dir_path = $theme->get_stylesheet_directory();
        $theme_slug = $theme->get_stylesheet();
        $zip_file_name = $theme_slug . '.zip';
        $zip_file_path = sys_get_temp_dir() . '/' . $zip_file_name;

        if (file_exists($zip_file_path)) unlink($zip_file_path);

        $zip = new ZipArchive();
        if ($zip->open($zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) wp_die('Impossible de créer l\'archive ZIP.');

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
    public static function export_patterns_json() {
        $is_portable = isset($_POST['export_portable']);
        $all_patterns = [];
        $args = ['post_type' => 'wp_block', 'posts_per_page' => -1, 'post_status' => 'publish'];
        $patterns_query = new WP_Query($args);

        if ($patterns_query->have_posts()) {
            while ($patterns_query->have_posts()) {
                $patterns_query->the_post();
                $content = self::get_sanitized_content();
                if ($is_portable) {
                    $content = self::clean_pattern_content($content);
                }
                $all_patterns[] = [
                    'title'   => get_the_title(),
                    'slug'    => 'custom-patterns/' . sanitize_title(get_the_title()),
                    'content' => $content,
                ];
            }
        }
        wp_reset_postdata();

        self::download_json($all_patterns);
    }

    /**
     * Exporte uniquement les compositions dont les IDs sont fournis.
     */
    public static function export_selected_patterns_json( $pattern_ids ) {
        $is_portable = isset($_POST['export_portable']);
        $sanitized_ids = array_map('intval', $pattern_ids);
        $selected_patterns = [];
        $args = [
            'post_type' => 'wp_block',
            'post__in'  => $sanitized_ids,
            'posts_per_page' => -1,
            'orderby' => 'post__in',
        ];
        $patterns_query = new WP_Query($args);

        if ($patterns_query->have_posts()) {
            while ($patterns_query->have_posts()) {
                $patterns_query->the_post();
                $content = self::get_sanitized_content();
                if ($is_portable) {
                    $content = self::clean_pattern_content($content);
                }
                $selected_patterns[] = [
                    'title'   => get_the_title(),
                    'slug'    => 'custom-patterns/' . sanitize_title(get_the_title()),
                    'content' => $content,
                ];
            }
        }
        wp_reset_postdata();

        self::download_json($selected_patterns, 'selected-patterns.json');
    }
    
    /**
     * Récupère le contenu et garantit qu'il est en UTF-8 valide.
     */
    private static function get_sanitized_content() {
        $content = get_the_content();
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
        // 1. Remplace les URLs absolues du site par des URLs relatives
        $content = str_replace(get_home_url(), '', $content);

        // 2. Neutralise les IDs des médias pour éviter les dépendances
        $content = preg_replace('/("id"\s*:\s*)\d+/', '$1' . '0', $content);

        // 3. Supprime les métadonnées potentiellement incompatibles
        $content = preg_replace('/,\s*"metadata"\s*:\s*\{[^{}]*?\}/s', '', $content);
        $content = preg_replace('/"metadata"\s*:\s*\{[^{}]*?\},\s*/s', '', $content);

        return $content;
    }

    /**
     * Gère la création et le téléchargement du fichier JSON.
     */
    private static function download_json( $data, $filename = 'exported-patterns.json' ) {
        $json_data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_die('Une erreur critique est survenue lors de la création du fichier JSON : ' . json_last_error_msg() . '. Cela peut être dû à des caractères invalides dans une de vos compositions.');
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($json_data));
        echo $json_data;
        exit;
    }
}
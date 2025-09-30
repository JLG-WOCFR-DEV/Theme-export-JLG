<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('TEJLG_CLI_WPDie_Exception')) {
    class TEJLG_CLI_WPDie_Exception extends RuntimeException {
    }
}

class TEJLG_CLI {

    public function __invoke($args, $assoc_args) {
        WP_CLI::log(__('Commandes disponibles :', 'theme-export-jlg'));
        WP_CLI::log('  wp theme-export-jlg theme [--exclusions=<motifs>] [--output=<chemin>]');
        WP_CLI::log('  wp theme-export-jlg patterns [--portable] [--output=<chemin>]');
    }

    public function theme($args, $assoc_args) {
        $exclusions = $this->parse_exclusions($assoc_args);
        $default_filename = $this->get_theme_slug() . '.zip';
        $output_path = $this->resolve_output_path($assoc_args, $default_filename);

        $job_id = TEJLG_Export::export_theme($exclusions, ['dispatch' => false]);

        if (is_wp_error($job_id)) {
            WP_CLI::error($this->normalize_cli_message($job_id->get_error_message()));
            return;
        }

        while (true) {
            $result = TEJLG_Export::process_theme_export_job($job_id);

            if (false === $result) {
                break;
            }
        }

        $job = TEJLG_Export::get_theme_export_job($job_id);

        if (null === $job) {
            WP_CLI::error(__('Impossible de récupérer le statut du job d\'export.', 'theme-export-jlg'));
            return;
        }

        if ('error' === $job['status']) {
            $message = isset($job['message']) && '' !== $job['message'] ? $job['message'] : __('Une erreur inconnue est survenue pendant l\'export.', 'theme-export-jlg');
            TEJLG_Export::cleanup_theme_export_job($job_id);
            WP_CLI::error($this->normalize_cli_message($message));
            return;
        }

        if ('completed' !== $job['status']) {
            TEJLG_Export::cleanup_theme_export_job($job_id);
            WP_CLI::error(__('Le job d\'export ne s\'est pas terminé correctement.', 'theme-export-jlg'));
            return;
        }

        $source_path = isset($job['zip_path']) ? $job['zip_path'] : '';

        if ('' === $source_path || !file_exists($source_path)) {
            TEJLG_Export::cleanup_theme_export_job($job_id);
            WP_CLI::error(__('Le fichier ZIP généré est introuvable.', 'theme-export-jlg'));
            return;
        }

        if (!$this->copy_file($source_path, $output_path)) {
            TEJLG_Export::cleanup_theme_export_job($job_id);
            WP_CLI::error(sprintf(__('Impossible de copier le fichier ZIP vers %s.', 'theme-export-jlg'), $output_path));
            return;
        }

        TEJLG_Export::cleanup_theme_export_job($job_id);

        WP_CLI::success(sprintf(__('Archive du thème exportée vers %s', 'theme-export-jlg'), $output_path));
    }

    public function patterns($args, $assoc_args) {
        $is_portable = $this->get_bool_flag($assoc_args, 'portable');
        $default_filename = $this->get_theme_slug() . '-patterns.json';
        $output_path = $this->resolve_output_path($assoc_args, $default_filename);

        $stream_filter = static function () {
            return false;
        };

        add_filter('tejlg_export_stream_json_file', $stream_filter, 10, 3);

        try {
            $contents = $this->capture_wp_die(static function () use ($is_portable) {
                return TEJLG_Export::export_patterns_json([], $is_portable);
            });
        } catch (TEJLG_CLI_WPDie_Exception $exception) {
            remove_filter('tejlg_export_stream_json_file', $stream_filter, 10);
            WP_CLI::error($this->normalize_cli_message($exception->getMessage()));
            return;
        }

        remove_filter('tejlg_export_stream_json_file', $stream_filter, 10);

        if (!is_string($contents)) {
            WP_CLI::error(__('Le contenu JSON généré est invalide.', 'theme-export-jlg'));
            return;
        }

        if (false === file_put_contents($output_path, $contents)) {
            WP_CLI::error(sprintf(__('Impossible d\'écrire le fichier JSON vers %s.', 'theme-export-jlg'), $output_path));
            return;
        }

        WP_CLI::success(sprintf(__('Fichier JSON des compositions exporté vers %s', 'theme-export-jlg'), $output_path));
    }

    private function parse_exclusions($assoc_args) {
        if (!isset($assoc_args['exclusions'])) {
            return [];
        }

        $raw_exclusions = $assoc_args['exclusions'];

        if (is_array($raw_exclusions)) {
            $raw_exclusions = implode(',', $raw_exclusions);
        }

        $split = preg_split('/[,\n]+/', (string) $raw_exclusions);

        if (false === $split) {
            return [];
        }

        $exclusions = array_filter(
            array_map(
                static function ($pattern) {
                    return trim((string) $pattern);
                },
                $split
            ),
            static function ($pattern) {
                return '' !== $pattern;
            }
        );

        return array_values($exclusions);
    }

    private function resolve_output_path($assoc_args, $default_filename) {
        $provided = isset($assoc_args['output']) ? (string) $assoc_args['output'] : '';
        $provided = trim($provided);

        if ('' === $provided) {
            $directory = $this->normalize_path(getcwd());
            $this->ensure_directory_exists($directory);

            return trailingslashit($directory) . $default_filename;
        }

        $normalized = $this->normalize_path($provided);

        if (!path_is_absolute($normalized)) {
            $base = $this->normalize_path(getcwd());
            $normalized = trailingslashit($base) . ltrim($normalized, '/');
        }

        $looks_like_directory = $this->path_ends_with_directory_separator($provided) || is_dir($normalized);

        if ($looks_like_directory) {
            $directory = untrailingslashit($normalized);
            if ('' === $directory) {
                $directory = $this->normalize_path(getcwd());
            }
            $this->ensure_directory_exists($directory);

            return trailingslashit($directory) . $default_filename;
        }

        $directory = $this->normalize_path(dirname($normalized));
        $this->ensure_directory_exists($directory);

        return $normalized;
    }

    private function ensure_directory_exists($directory) {
        if ('' === $directory) {
            $directory = $this->normalize_path(getcwd());
        }

        if (!file_exists($directory)) {
            if (!wp_mkdir_p($directory)) {
                WP_CLI::error(sprintf(__('Impossible de créer le dossier cible : %s', 'theme-export-jlg'), $directory));
            }
        }

        if (!is_dir($directory)) {
            WP_CLI::error(sprintf(__('Le chemin cible n\'est pas un dossier valide : %s', 'theme-export-jlg'), $directory));
        }

        if (!is_writable($directory)) {
            WP_CLI::error(sprintf(__('Le dossier cible n\'est pas accessible en écriture : %s', 'theme-export-jlg'), $directory));
        }
    }

    private function copy_file($source, $destination) {
        $bytes = @copy($source, $destination);

        if (!$bytes) {
            return false;
        }

        return true;
    }

    private function capture_wp_die(callable $callback) {
        $handler = static function () {
            return static function ($message) {
                throw new TEJLG_CLI_WPDie_Exception($message);
            };
        };

        add_filter('wp_die_handler', $handler);

        try {
            return $callback();
        } finally {
            remove_filter('wp_die_handler', $handler);
        }
    }

    private function normalize_cli_message($message) {
        if ($message instanceof WP_Error) {
            $message = $message->get_error_message();
        }

        return trim(wp_strip_all_tags((string) $message));
    }

    private function get_bool_flag($assoc_args, $key) {
        if (!isset($assoc_args[$key])) {
            return false;
        }

        $value = $assoc_args[$key];

        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower((string) $value);

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    private function path_ends_with_directory_separator($path) {
        if ('' === $path) {
            return false;
        }

        $last_character = substr($path, -1);

        return '/' === $last_character || '\\' === $last_character;
    }

    private function normalize_path($path) {
        return wp_normalize_path($path);
    }

    private function get_theme_slug() {
        $theme = wp_get_theme();
        $slug = $theme->get_stylesheet();

        if ('' === $slug) {
            $slug = 'theme-export';
        }

        return sanitize_key($slug);
    }
}

if (class_exists('WP_CLI')) {
    WP_CLI::add_command('theme-export-jlg', 'TEJLG_CLI');
}

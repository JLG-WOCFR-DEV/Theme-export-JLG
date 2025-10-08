<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-tejlg-export-history.php';
require_once __DIR__ . '/class-tejlg-import.php';
require_once __DIR__ . '/class-tejlg-settings.php';

if (!class_exists('TEJLG_CLI_WPDie_Exception')) {
    class TEJLG_CLI_WPDie_Exception extends RuntimeException {
    }
}

class TEJLG_CLI {

    public function __invoke($args, $assoc_args) {
        WP_CLI::log(__('Commandes disponibles :', 'theme-export-jlg'));
        WP_CLI::log('  wp theme-export-jlg theme [--exclusions=<motifs>] [--output=<chemin>]');
        WP_CLI::log('  wp theme-export-jlg patterns [--portable] [--output=<chemin>]');
        WP_CLI::log('  wp theme-export-jlg import theme <chemin_zip> [--overwrite]');
        WP_CLI::log('  wp theme-export-jlg import patterns <chemin_json>');
        WP_CLI::log('  wp theme-export-jlg history [--per-page=<nombre>] [--page=<nombre>] [--result=<statut>] [--origin=<origine>]');
        WP_CLI::log('  wp theme-export-jlg history report [--window=<jours>] [--result=<statut>] [--origin=<origine>] [--format=<table|json>] [--limit=<nombre>]');
        WP_CLI::log('  wp theme-export-jlg settings export [--output=<chemin>]');
        WP_CLI::log('  wp theme-export-jlg settings import <chemin_json>');
    }

    public function theme($args, $assoc_args) {
        $exclusions = $this->parse_exclusions($assoc_args);
        $default_filename = $this->get_theme_slug() . '.zip';
        $output_path = $this->resolve_output_path($assoc_args, $default_filename);

        $job_id = TEJLG_Export::export_theme($exclusions);

        if (is_wp_error($job_id)) {
            WP_CLI::error($this->normalize_cli_message($job_id->get_error_message()));
            return;
        }

        TEJLG_Export::run_pending_export_jobs();

        $job = TEJLG_Export::get_export_job_status($job_id);

        if (null === $job) {
            WP_CLI::error(__('Impossible de récupérer le statut de la tâche d\'export.', 'theme-export-jlg'));
            return;
        }

        if (isset($job['status']) && 'failed' === $job['status']) {
            $message = isset($job['message']) && is_string($job['message']) && $job['message'] !== ''
                ? $job['message']
                : __('Une erreur est survenue lors de l\'export du thème.', 'theme-export-jlg');
            WP_CLI::error($this->normalize_cli_message($message));
            TEJLG_Export::delete_job($job_id, [
                'origin' => 'cli',
                'reason' => 'failure',
            ]);
            return;
        }

        if (!isset($job['status']) || 'completed' !== $job['status']) {
            WP_CLI::error(__('La génération de l\'archive du thème est incomplète.', 'theme-export-jlg'));
            TEJLG_Export::delete_job($job_id, [
                'origin' => 'cli',
                'reason' => 'incomplete',
            ]);
            return;
        }

        $source_path = isset($job['zip_path']) ? (string) $job['zip_path'] : '';

        if ('' === $source_path || !file_exists($source_path)) {
            WP_CLI::error(__('Le fichier ZIP généré est introuvable.', 'theme-export-jlg'));
            TEJLG_Export::delete_job($job_id, [
                'origin' => 'cli',
                'reason' => 'missing_zip',
            ]);
            return;
        }

        if (!$this->copy_file($source_path, $output_path)) {
            TEJLG_Export::delete_job($job_id, [
                'origin' => 'cli',
                'reason' => 'copy_failed',
            ]);
            WP_CLI::error(sprintf(__('Impossible de copier le fichier ZIP vers %s.', 'theme-export-jlg'), $output_path));
            return;
        }

        $persistence = TEJLG_Export::persist_export_archive($job);

        $delete_context = [
            'origin' => 'cli',
            'reason' => 'exported',
        ];

        if (!empty($persistence['path'])) {
            $delete_context['persistent_path'] = $persistence['path'];
        }

        if (!empty($persistence['url'])) {
            $delete_context['download_url'] = $persistence['url'];
        }

        TEJLG_Export::delete_job($job_id, $delete_context);

        WP_CLI::success(sprintf(__('Archive du thème exportée vers %s', 'theme-export-jlg'), $output_path));
    }

    public function history($args, $assoc_args) {
        if (!empty($args)) {
            $subcommand = array_shift($args);
            $subcommand = is_string($subcommand) ? strtolower($subcommand) : '';

            if ('report' === $subcommand) {
                $this->run_history_report($assoc_args);

                return;
            }

            WP_CLI::error(__('Sous-commande inconnue. Utilisez "report" ou aucun argument.', 'theme-export-jlg'));
        }

        $per_page = isset($assoc_args['per-page']) ? (int) $assoc_args['per-page'] : 10;
        $per_page = $per_page > 0 ? $per_page : 10;

        $page = isset($assoc_args['page']) ? (int) $assoc_args['page'] : 1;
        $page = $page > 0 ? $page : 1;

        $result_filter = isset($assoc_args['result']) ? sanitize_key((string) $assoc_args['result']) : '';
        $origin_filter = isset($assoc_args['origin']) ? sanitize_key((string) $assoc_args['origin']) : '';

        $history = TEJLG_Export_History::get_entries([
            'per_page' => $per_page,
            'paged'    => $page,
            'result'   => $result_filter,
            'origin'   => $origin_filter,
        ]);

        $entries = isset($history['entries']) ? (array) $history['entries'] : [];
        $total   = isset($history['total']) ? (int) $history['total'] : 0;
        $total_pages = isset($history['total_pages']) ? (int) $history['total_pages'] : 1;
        $total_pages = $total_pages > 0 ? $total_pages : 1;

        if (empty($entries)) {
            if ('' !== $result_filter || '' !== $origin_filter) {
                WP_CLI::log(__('Aucun export ne correspond aux filtres fournis.', 'theme-export-jlg'));
            } else {
                WP_CLI::log(__('Aucun export n\'a encore été enregistré.', 'theme-export-jlg'));
            }
            return;
        }

        $date_format = get_option('date_format', 'Y-m-d');
        $time_format = get_option('time_format', 'H:i');
        $datetime_format = trim($date_format . ' ' . $time_format);

        WP_CLI::log(sprintf(
            /* translators: 1: current page number, 2: total pages, 3: total entries. */
            __('Historique des exports – page %1$d sur %2$d (%3$d entrées)', 'theme-export-jlg'),
            isset($history['current_page']) ? (int) $history['current_page'] : 1,
            $total_pages,
            $total
        ));

        foreach ($entries as $entry) {
            $job_id = isset($entry['job_id']) ? (string) $entry['job_id'] : '';

            $timestamp = isset($entry['timestamp']) ? (int) $entry['timestamp'] : 0;
            $formatted_date = '';

            if ($timestamp > 0) {
                if (function_exists('wp_date')) {
                    $formatted_date = wp_date($datetime_format, $timestamp);
                } else {
                    $formatted_date = date_i18n($datetime_format, $timestamp);
                }
            }

            $user_name = isset($entry['user_name']) ? (string) $entry['user_name'] : '';
            $user_id   = isset($entry['user_id']) ? (int) $entry['user_id'] : 0;

            if ('' === $user_name) {
                $user_name = $user_id > 0
                    ? sprintf(__('Utilisateur #%d', 'theme-export-jlg'), $user_id)
                    : __('Système', 'theme-export-jlg');
            }

            $size_bytes = isset($entry['zip_file_size']) ? (int) $entry['zip_file_size'] : 0;
            $size_label = $size_bytes > 0 ? size_format($size_bytes, 2) : __('Inconnue', 'theme-export-jlg');

            $status = isset($entry['status']) && '' !== $entry['status']
                ? (string) $entry['status']
                : __('Inconnu', 'theme-export-jlg');

            $exclusions = isset($entry['exclusions']) ? (array) $entry['exclusions'] : [];
            $exclusions = array_map('sanitize_text_field', $exclusions);
            $exclusions_label = !empty($exclusions)
                ? implode(', ', $exclusions)
                : __('Aucune exclusion', 'theme-export-jlg');

            $download_url = isset($entry['persistent_url']) ? (string) $entry['persistent_url'] : '';

            $line = sprintf(
                '[%1$s] %2$s | %3$s | %4$s | %5$s | %6$s',
                $job_id,
                $formatted_date,
                $user_name,
                $size_label,
                $status,
                $exclusions_label
            );

            if ('' !== $download_url) {
                $line .= ' | ' . sprintf(__('Téléchargement : %s', 'theme-export-jlg'), $download_url);
            }

            WP_CLI::log($line);
        }
    }

    private function run_history_report($assoc_args) {
        $window_days = isset($assoc_args['window']) ? (int) $assoc_args['window'] : 7;
        $window_days = $window_days >= 0 ? $window_days : 7;

        $result_filter = isset($assoc_args['result']) ? sanitize_key((string) $assoc_args['result']) : '';
        $origin_filter = isset($assoc_args['origin']) ? sanitize_key((string) $assoc_args['origin']) : '';

        $format = isset($assoc_args['format']) ? strtolower((string) $assoc_args['format']) : 'table';
        $limit  = isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : 10;
        $limit  = $limit >= 0 ? $limit : 10;

        $report = TEJLG_Export_History::generate_report([
            'window_days'     => $window_days,
            'result'          => $result_filter,
            'origin'          => $origin_filter,
            'limit'           => $limit,
            'include_entries' => true,
        ]);

        if ('json' === $format) {
            WP_CLI::print_value($report, [
                'format' => 'json',
            ]);

            return;
        }

        $date_format = get_option('date_format', 'Y-m-d');
        $time_format = get_option('time_format', 'H:i');
        $datetime    = trim($date_format . ' ' . $time_format);
        $generated   = isset($report['generated_at']) ? (int) $report['generated_at'] : time();

        if (function_exists('wp_date')) {
            $generated_label = wp_date($datetime, $generated);
        } else {
            $generated_label = date_i18n($datetime, $generated);
        }

        $total_entries = isset($report['totals']['entries']) ? (int) $report['totals']['entries'] : 0;
        $total_duration = isset($report['totals']['duration_seconds']) ? (int) $report['totals']['duration_seconds'] : 0;
        $total_size     = isset($report['totals']['archive_size_bytes']) ? (int) $report['totals']['archive_size_bytes'] : 0;
        $counts         = isset($report['counts']['results']) && is_array($report['counts']['results'])
            ? $report['counts']['results']
            : [];
        $uptime_rate = isset($report['uptime_rate']) ? $report['uptime_rate'] : null;

        WP_CLI::log(sprintf(
            /* translators: %s: report generation datetime. */
            __('Rapport d’export généré le %s', 'theme-export-jlg'),
            $generated_label
        ));

        WP_CLI::log(sprintf(
            /* translators: %d: analysis window in days. */
            __('Fenêtre analysée : %d jours', 'theme-export-jlg'),
            isset($report['filters']['window_days']) ? (int) $report['filters']['window_days'] : $window_days
        ));

        WP_CLI::log(sprintf(
            /* translators: %d: total number of exports. */
            __('Exports analysés : %d', 'theme-export-jlg'),
            $total_entries
        ));

        WP_CLI::log(sprintf(
            /* translators: 1: success count, 2: warning count, 3: error count, 4: info count. */
            __('Répartition – Succès : %1$d | Avertissements : %2$d | Erreurs : %3$d | Informations : %4$d', 'theme-export-jlg'),
            isset($counts[TEJLG_Export_History::RESULT_SUCCESS]) ? (int) $counts[TEJLG_Export_History::RESULT_SUCCESS] : 0,
            isset($counts[TEJLG_Export_History::RESULT_WARNING]) ? (int) $counts[TEJLG_Export_History::RESULT_WARNING] : 0,
            isset($counts[TEJLG_Export_History::RESULT_ERROR]) ? (int) $counts[TEJLG_Export_History::RESULT_ERROR] : 0,
            isset($counts[TEJLG_Export_History::RESULT_INFO]) ? (int) $counts[TEJLG_Export_History::RESULT_INFO] : 0
        ));

        if (null !== $uptime_rate) {
            WP_CLI::log(sprintf(
                /* translators: %s: uptime rate percentage. */
                __('Taux de réussite sur la période : %s%%', 'theme-export-jlg'),
                function_exists('number_format_i18n')
                    ? number_format_i18n($uptime_rate, 1)
                    : number_format($uptime_rate, 1)
            ));
        }

        if ($total_duration > 0) {
            WP_CLI::log(sprintf(
                /* translators: %s: total duration. */
                __('Durée cumulée : %s', 'theme-export-jlg'),
                human_readable_duration($total_duration)
            ));
        }

        if ($total_size > 0) {
            WP_CLI::log(sprintf(
                /* translators: %s: total archive size. */
                __('Poids total des archives : %s', 'theme-export-jlg'),
                size_format($total_size)
            ));
        }

        $entries = isset($report['entries']) && is_array($report['entries']) ? $report['entries'] : [];

        if (empty($entries)) {
            WP_CLI::log(__('Aucun export récent ne correspond aux critères.', 'theme-export-jlg'));

            return;
        }

        WP_CLI::log(__('Exports récents :', 'theme-export-jlg'));

        foreach ($entries as $entry) {
            $timestamp = isset($entry['timestamp']) ? (int) $entry['timestamp'] : 0;

            if ($timestamp > 0) {
                if (function_exists('wp_date')) {
                    $date_label = wp_date($datetime, $timestamp);
                } else {
                    $date_label = date_i18n($datetime, $timestamp);
                }
            } else {
                $date_label = __('Date inconnue', 'theme-export-jlg');
            }

            $job_id = isset($entry['job_id']) ? (string) $entry['job_id'] : '';
            $result = isset($entry['result']) ? (string) $entry['result'] : TEJLG_Export_History::RESULT_INFO;
            $result_label = $this->get_history_result_label($result);
            $size   = isset($entry['zip_file_size']) ? (int) $entry['zip_file_size'] : 0;
            $size_label = $size > 0 ? size_format($size, 2) : __('Inconnue', 'theme-export-jlg');
            $duration = isset($entry['duration']) ? (int) $entry['duration'] : 0;
            $duration_label = $duration > 0 ? human_readable_duration($duration) : __('Non renseignée', 'theme-export-jlg');

            WP_CLI::log(sprintf(
                '[%1$s] %2$s | %3$s | %4$s | %5$s',
                $job_id,
                $date_label,
                $result_label,
                $size_label,
                $duration_label
            ));
        }
    }

    private function get_history_result_label($result) {
        switch ($result) {
            case TEJLG_Export_History::RESULT_SUCCESS:
                return __('Succès', 'theme-export-jlg');
            case TEJLG_Export_History::RESULT_WARNING:
                return __('Avertissement', 'theme-export-jlg');
            case TEJLG_Export_History::RESULT_ERROR:
                return __('Erreur', 'theme-export-jlg');
            default:
                return __('Information', 'theme-export-jlg');
        }
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

    public function import($args, $assoc_args) {
        if (empty($args)) {
            WP_CLI::error(__('Veuillez préciser la sous-commande d\'import (theme ou patterns).', 'theme-export-jlg'));
        }

        $subcommand = array_shift($args);
        $subcommand = is_string($subcommand) ? strtolower($subcommand) : '';

        if ('theme' === $subcommand) {
            $this->run_theme_import($args, $assoc_args);
            return;
        }

        if ('patterns' === $subcommand) {
            $this->run_patterns_import($args, $assoc_args);
            return;
        }

        WP_CLI::error(__('Sous-commande inconnue. Utilisez "theme" ou "patterns".', 'theme-export-jlg'));
    }

    public function settings($args, $assoc_args) {
        if (empty($args)) {
            WP_CLI::error(__('Veuillez préciser la sous-commande d\'export/import (export ou import).', 'theme-export-jlg'));
        }

        $subcommand = array_shift($args);
        $subcommand = is_string($subcommand) ? strtolower($subcommand) : '';

        if ('export' === $subcommand) {
            $this->run_settings_export($assoc_args);
            return;
        }

        if ('import' === $subcommand) {
            $this->run_settings_import($args, $assoc_args);
            return;
        }

        WP_CLI::error(__('Sous-commande inconnue. Utilisez "export" ou "import".', 'theme-export-jlg'));
    }

    private function run_theme_import($args, $assoc_args) {
        if (empty($args)) {
            WP_CLI::error(__('Veuillez fournir le chemin du fichier ZIP du thème à importer.', 'theme-export-jlg'));
        }

        $source_path = $this->validate_import_file_path(array_shift($args), 'theme');
        $allow_overwrite = $this->get_bool_flag($assoc_args, 'overwrite');

        $temporary_file = wp_tempnam('tejlg-cli-theme-import');

        if (false === $temporary_file || !@copy($source_path, $temporary_file)) {
            if (false !== $temporary_file && file_exists($temporary_file)) {
                @unlink($temporary_file);
            }

            WP_CLI::error(sprintf(__('Impossible de préparer le fichier %s pour l\'import.', 'theme-export-jlg'), $source_path));
        }

        $file_size = @filesize($source_path);
        $file_size = false === $file_size ? 0 : (int) $file_size;

        $file_array = [
            'name'     => basename($source_path),
            'type'     => 'application/zip',
            'tmp_name' => $temporary_file,
            'error'    => UPLOAD_ERR_OK,
            'size'     => $file_size,
        ];

        $previous_files = isset($_FILES['theme_zip']) ? $_FILES['theme_zip'] : null;
        $_FILES['theme_zip'] = $file_array;

        try {
            $result = TEJLG_Import::import_theme($file_array, $allow_overwrite);
        } finally {
            if (null === $previous_files) {
                unset($_FILES['theme_zip']);
            } else {
                $_FILES['theme_zip'] = $previous_files;
            }

            if (file_exists($temporary_file)) {
                @unlink($temporary_file);
            }
        }

        if (is_wp_error($result)) {
            WP_CLI::error($this->normalize_cli_message($result->get_error_message()));
        }

        if (false === $result || null === $result) {
            WP_CLI::error(__('L\'installation du thème a échoué.', 'theme-export-jlg'));
        }

        WP_CLI::success(__('Le thème a été installé avec succès !', 'theme-export-jlg'));
    }

    private function run_patterns_import($args, $assoc_args) {
        if (empty($args)) {
            WP_CLI::error(__('Veuillez fournir le chemin du fichier JSON des compositions à importer.', 'theme-export-jlg'));
        }

        $source_path = $this->validate_import_file_path(array_shift($args), 'patterns');

        $json_content = file_get_contents($source_path);

        if (false === $json_content) {
            WP_CLI::error(sprintf(__('Impossible de lire le fichier %s.', 'theme-export-jlg'), $source_path));
        }

        $patterns = TEJLG_Import::prepare_patterns_from_json($json_content);

        if (is_wp_error($patterns)) {
            WP_CLI::error($this->normalize_cli_message($patterns->get_error_message()));
        }

        $result = TEJLG_Import::import_patterns_collection($patterns);

        $imported_count = isset($result['imported_count']) ? (int) $result['imported_count'] : 0;
        $errors = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : [];

        if (!empty($errors)) {
            foreach ($errors as $message) {
                WP_CLI::warning($this->normalize_cli_message($message));
            }
        }

        if ($imported_count > 0) {
            WP_CLI::success(sprintf(
                _n('%d composition a été enregistrée avec succès.', '%d compositions ont été enregistrées avec succès.', $imported_count, 'theme-export-jlg'),
                $imported_count
            ));

            return;
        }

        WP_CLI::error(__('Aucune composition n\'a pu être enregistrée (elles existent peut-être déjà ou des erreurs sont survenues).', 'theme-export-jlg'));
    }

    private function run_settings_export($assoc_args) {
        $default_filename = $this->get_theme_slug() . '-settings.json';
        $output_path      = $this->resolve_output_path($assoc_args, $default_filename);

        $package = TEJLG_Settings::build_export_package();
        $json    = TEJLG_Settings::encode_export_package($package);

        if (is_wp_error($json)) {
            WP_CLI::error($json->get_error_message());
        }

        if (false === file_put_contents($output_path, $json)) {
            WP_CLI::error(sprintf(__('Impossible d\'écrire le fichier JSON vers %s.', 'theme-export-jlg'), $output_path));
        }

        $hash = isset($package['signature']['hash']) ? (string) $package['signature']['hash'] : '';

        if ('' !== $hash) {
            WP_CLI::success(sprintf(__('Réglages exportés vers %1$s (empreinte %2$s).', 'theme-export-jlg'), $output_path, $hash));

            return;
        }

        WP_CLI::success(sprintf(__('Réglages exportés vers %s.', 'theme-export-jlg'), $output_path));
    }

    private function run_settings_import($args, $assoc_args) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
        if (empty($args)) {
            WP_CLI::error(__('Veuillez fournir le chemin du fichier JSON des réglages à importer.', 'theme-export-jlg'));
        }

        $source_path = $this->validate_import_file_path(array_shift($args), 'settings');
        $contents    = file_get_contents($source_path);

        if (false === $contents) {
            WP_CLI::error(sprintf(__('Impossible de lire le fichier %s.', 'theme-export-jlg'), $source_path));
        }

        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            WP_CLI::error(__('Le fichier fourni ne contient pas un JSON valide.', 'theme-export-jlg'));
        }

        $schema = isset($decoded['schema']) ? (string) $decoded['schema'] : '';

        if (TEJLG_Settings::EXPORT_SCHEMA !== $schema) {
            WP_CLI::error(__('Ce fichier ne correspond pas au format d\'export des réglages Theme Export JLG.', 'theme-export-jlg'));
        }

        $version = isset($decoded['version']) ? (int) $decoded['version'] : 0;

        if ($version > TEJLG_Settings::EXPORT_VERSION) {
            WP_CLI::warning(__('Attention : ce fichier provient d\'une version plus récente du plugin. Les champs inconnus seront ignorés.', 'theme-export-jlg'));
        }

        if (!isset($decoded['signature']) || !is_array($decoded['signature'])) {
            WP_CLI::warning(__('Signature absente : impossible de vérifier si le fichier a été modifié.', 'theme-export-jlg'));
        } else {
            $verification = TEJLG_Settings::verify_signature($decoded);

            if (!$verification['valid']) {
                WP_CLI::warning(sprintf(
                    /* translators: 1: expected hash, 2: actual hash */
                    __('Attention : la signature ne correspond pas (attendu %1$s, obtenu %2$s).', 'theme-export-jlg'),
                    $verification['expected_hash'],
                    $verification['actual_hash']
                ));
            }
        }

        $snapshot = isset($decoded['settings']) && is_array($decoded['settings']) ? $decoded['settings'] : [];

        $results = TEJLG_Settings::apply_snapshot($snapshot, [
            'origin'      => 'cli',
            'schema_file' => $source_path,
            'version'     => $version,
        ]);

        $updated_sections = [];

        if (!empty($results['schedule_updated'])) {
            $updated_sections[] = __('planification', 'theme-export-jlg');
        }

        if (!empty($results['export_preferences_updated'])) {
            $updated_sections[] = __('préférences d\'export', 'theme-export-jlg');
        }

        if (!empty($results['debug_preferences_updated'])) {
            $updated_sections[] = __('indicateurs de diagnostic', 'theme-export-jlg');
        }

        if (empty($updated_sections)) {
            WP_CLI::success(__('Réglages importés : aucune modification n\'était nécessaire.', 'theme-export-jlg'));

            return;
        }

        WP_CLI::success(sprintf(
            /* translators: %s: comma separated list of sections updated. */
            __('Réglages importés avec succès (%s mis à jour).', 'theme-export-jlg'),
            implode(', ', $updated_sections)
        ));
    }

    private function validate_import_file_path($path, $type) {
        $resolved = $this->resolve_input_file_path($path);

        if (!file_exists($resolved)) {
            WP_CLI::error(sprintf(__('Le fichier %s est introuvable.', 'theme-export-jlg'), $resolved));
        }

        if (!is_file($resolved)) {
            WP_CLI::error(sprintf(__('Le chemin %s ne correspond pas à un fichier.', 'theme-export-jlg'), $resolved));
        }

        if (!is_readable($resolved)) {
            WP_CLI::error(sprintf(__('Le fichier %s n\'est pas lisible.', 'theme-export-jlg'), $resolved));
        }

        if ('patterns' === $type) {
            $max_size = (int) apply_filters('tejlg_import_patterns_max_filesize', 5 * 1024 * 1024);

            if ($max_size < 1) {
                $max_size = 5 * 1024 * 1024;
            }

            $file_size = @filesize($resolved);
            $file_size = false === $file_size ? 0 : (int) $file_size;

            if ($file_size > $max_size) {
                WP_CLI::error(sprintf(
                    __('Erreur : Le fichier est trop volumineux. La taille maximale autorisée est de %s Mo.', 'theme-export-jlg'),
                    number_format_i18n($max_size / (1024 * 1024), 2)
                ));
            }
        } elseif ('settings' === $type) {
            $max_size = (int) apply_filters('tejlg_settings_import_max_filesize', 512 * 1024);

            if ($max_size < 1) {
                $max_size = 512 * 1024;
            }

            $file_size = @filesize($resolved);
            $file_size = false === $file_size ? 0 : (int) $file_size;

            if ($file_size > $max_size) {
                WP_CLI::error(sprintf(
                    __('Erreur : Le fichier de réglages dépasse la taille maximale autorisée de %s Ko.', 'theme-export-jlg'),
                    number_format_i18n($max_size / 1024, 0)
                ));
            }
        }

        $config = TEJLG_Import::get_import_file_type($type);
        $allowed_mime_map = isset($config['mime_types']) && is_array($config['mime_types']) ? $config['mime_types'] : [];

        if (!empty($allowed_mime_map)) {
            $filetype = wp_check_filetype_and_ext($resolved, basename($resolved), $allowed_mime_map);

            $ext  = isset($filetype['ext']) ? (string) $filetype['ext'] : '';
            $mime = isset($filetype['type']) ? (string) $filetype['type'] : '';
            $expected_mime = isset($allowed_mime_map[$ext]) ? (string) $allowed_mime_map[$ext] : '';

            $is_valid_type = '' !== $ext && '' !== $mime && '' !== $expected_mime && $expected_mime === $mime;

            if (!$is_valid_type) {
                if ('patterns' === $type) {
                    WP_CLI::error(__('Erreur : Le fichier téléchargé doit être un fichier JSON valide.', 'theme-export-jlg'));
                } else {
                    WP_CLI::error(__('Erreur : Le fichier fourni doit être une archive ZIP valide.', 'theme-export-jlg'));
                }
            }
        }

        return $resolved;
    }

    private function resolve_input_file_path($path) {
        $provided = is_string($path) ? $path : '';
        $provided = trim($provided);

        if ('' === $provided) {
            WP_CLI::error(__('Veuillez indiquer un chemin de fichier valide.', 'theme-export-jlg'));
        }

        $normalized = $this->normalize_path($provided);

        if (!path_is_absolute($normalized)) {
            $base = $this->normalize_path(getcwd());
            $normalized = trailingslashit($base) . ltrim($normalized, '/');
        }

        return $normalized;
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

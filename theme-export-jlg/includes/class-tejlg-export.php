<?php
class TEJLG_Export {

    /**
     * Crée et télécharge l'archive ZIP du thème actif.
     *
     * Les jobs sont traités immédiatement lorsque WP-Cron est indisponible ou
     * lorsqu'aucun évènement n'a pu être planifié, afin d'imiter le
     * comportement attendu dans les environnements professionnels :
     *
     * - la constante DISABLE_WP_CRON (ou tout autre flag équivalent) force
     *   l'exécution immédiate du job ;
     * - l'absence d'évènement planifié après la tentative de `dispatch`
     *   déclenche également l'exécution immédiate.
     */
    public static function export_theme($exclusions = []) {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('tejlg_ziparchive_missing', esc_html__('La classe ZipArchive n\'est pas disponible.', 'theme-export-jlg'));
        }

        $exclusions = self::sanitize_exclusion_patterns($exclusions);

        $theme = wp_get_theme();
        $theme_dir_path = $theme->get_stylesheet_directory();

        if (!is_dir($theme_dir_path) || !is_readable($theme_dir_path)) {
            return new WP_Error('tejlg_theme_directory_unreadable', esc_html__("Impossible d'accéder au dossier du thème actif.", 'theme-export-jlg'));
        }

        $theme_slug    = $theme->get_stylesheet();
        $zip_file_name = $theme_slug . '.zip';
        $zip_file_path = wp_tempnam($zip_file_name);

        if (!$zip_file_path) {
            return new WP_Error('tejlg_zip_temp_creation_failed', esc_html__("Impossible de créer le fichier temporaire pour l'archive ZIP.", 'theme-export-jlg'));
        }

        if (file_exists($zip_file_path) && !self::delete_temp_file($zip_file_path)) {
            return new WP_Error('tejlg_zip_temp_cleanup_failed', esc_html__("Impossible de préparer le fichier temporaire pour l'archive ZIP.", 'theme-export-jlg'));
        }

        $zip = new ZipArchive();

        if (true !== $zip->open($zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            self::delete_temp_file($zip_file_path);
            return new WP_Error('tejlg_zip_open_failed', esc_html__("Impossible de créer l'archive ZIP.", 'theme-export-jlg'));
        }

        $zip_root_directory = rtrim($theme_slug, '/') . '/';

        if (true !== $zip->addEmptyDir($zip_root_directory)) {
            $zip->close();
            self::delete_temp_file($zip_file_path);

            return new WP_Error(
                'tejlg_zip_root_dir_failed',
                sprintf(
                    /* translators: %s: slug of the theme used as the root directory of the ZIP archive. */
                    esc_html__("Impossible d'ajouter le dossier racine « %s » à l'archive ZIP.", 'theme-export-jlg'),
                    esc_html($zip_root_directory)
                )
            );
        }

        $zip->close();

        $normalized_theme_dir = self::normalize_path($theme_dir_path);

        try {
            $queue = self::collect_theme_export_items(
                $theme_dir_path,
                $normalized_theme_dir,
                $zip_root_directory,
                $exclusions
            );
        } catch (RuntimeException $exception) {
            self::delete_temp_file($zip_file_path);

            return new WP_Error('tejlg_theme_export_queue_failed', $exception->getMessage());
        }

        $queue_items = isset($queue['items']) && is_array($queue['items']) ? $queue['items'] : [];
        $files_count = isset($queue['files_count']) ? (int) $queue['files_count'] : 0;

        if ($files_count < 1) {
            self::delete_temp_file($zip_file_path);

            return new WP_Error(
                'tejlg_theme_export_no_files',
                esc_html__("Erreur : tous les fichiers ont été exclus de l'export. Vérifiez vos motifs.", 'theme-export-jlg')
            );
        }

        $job_id = self::generate_job_id();

        $job = [
            'id'                => $job_id,
            'status'            => 'queued',
            'progress'          => 0,
            'processed_items'   => 0,
            'total_items'       => count($queue_items),
            'zip_path'          => $zip_file_path,
            'zip_file_name'     => $zip_file_name,
            'directories_added' => [
                $zip_root_directory => true,
            ],
            'exclusions'        => $exclusions,
            'created_at'        => time(),
            'updated_at'        => time(),
            'message'           => '',
        ];

        self::persist_job($job);
        self::remember_job_for_current_user($job_id);

        $process = self::get_export_process();

        foreach ($queue_items as $item) {
            $process->push_to_queue(
                [
                    'job_id'               => $job_id,
                    'type'                 => isset($item['type']) ? $item['type'] : 'file',
                    'real_path'            => isset($item['real_path']) ? $item['real_path'] : '',
                    'relative_path_in_zip' => isset($item['relative_path_in_zip']) ? $item['relative_path_in_zip'] : '',
                ]
            );
        }

        $process->save();
        $process->dispatch();

        $should_run_immediately = apply_filters('tejlg_export_run_jobs_immediately', defined('WP_RUNNING_TESTS'));

        $cron_disabled = false;

        if (defined('DISABLE_WP_CRON')) {
            $cron_disabled = function_exists('wp_validate_boolean')
                ? wp_validate_boolean(DISABLE_WP_CRON)
                : (bool) DISABLE_WP_CRON;
        }

        $cron_hook_identifier = method_exists($process, 'get_cron_hook_identifier')
            ? (string) $process->get_cron_hook_identifier()
            : '';

        $event_scheduled = '' !== $cron_hook_identifier
            && function_exists('wp_next_scheduled')
            && false !== wp_next_scheduled($cron_hook_identifier);

        if (!$should_run_immediately && ($cron_disabled || !$event_scheduled)) {
            $should_run_immediately = true;
        }

        if ($should_run_immediately) {
            self::run_pending_export_jobs();
        }

        return $job_id;
    }


    private static function sanitize_exclusion_patterns($exclusions) {
        return array_values(
            array_filter(
                array_map(
                    static function ($pattern) {
                        if (!is_scalar($pattern)) {
                            return '';
                        }

                        $pattern = trim((string) $pattern);

                        if ('' === $pattern) {
                            return '';
                        }

                        return ltrim($pattern, "\/");
                    },
                    (array) $exclusions
                ),
                static function ($pattern) {
                    return '' !== $pattern;
                }
            )
        );
    }

    private static function collect_theme_export_items($theme_dir_path, $normalized_theme_dir, $zip_root_directory, $exclusions) {
        try {
            $directory_iterator = new RecursiveDirectoryIterator(
                $theme_dir_path,
                FilesystemIterator::SKIP_DOTS
            );
        } catch (UnexpectedValueException $exception) {
            throw new RuntimeException(
                esc_html__("Impossible de parcourir les fichiers du thème pour l'export.", 'theme-export-jlg'),
                0,
                $exception
            );
        }

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

                $normalized_file_path = TEJLG_Export::normalize_path($real_path);

                if (!TEJLG_Export::is_path_within_base($normalized_file_path, $normalized_theme_dir)) {
                    return false;
                }

                $relative_path = TEJLG_Export::get_relative_path($normalized_file_path, $normalized_theme_dir);

                if ($file->isDir()) {
                    return '' === $relative_path || !TEJLG_Export::should_exclude_file($relative_path, $exclusions);
                }

                if ('' === $relative_path) {
                    return false;
                }

                return !TEJLG_Export::should_exclude_file($relative_path, $exclusions);
            }
        );

        $iterator = new RecursiveIteratorIterator(
            $filter_iterator,
            RecursiveIteratorIterator::SELF_FIRST,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );

        $items       = [];
        $files_count = 0;

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
                $items[] = [
                    'type'                 => 'dir',
                    'real_path'            => $real_path,
                    'relative_path_in_zip' => rtrim($relative_path_in_zip, '/') . '/',
                ];

                continue;
            }

            $items[] = [
                'type'                 => 'file',
                'real_path'            => $real_path,
                'relative_path_in_zip' => $relative_path_in_zip,
            ];

            $files_count++;
        }

        return [
            'items'       => $items,
            'files_count' => $files_count,
        ];
    }

    private static function generate_job_id() {
        if (function_exists('wp_generate_uuid4')) {
            $raw_id = wp_generate_uuid4();
        } else {
            $raw_id = uniqid('tejlg_export_', true);
        }

        $sanitized = strtolower(preg_replace('/[^a-z0-9_]/', '', (string) $raw_id));

        if ('' === $sanitized) {
            $sanitized = 'tejlg_export_' . wp_rand(1000, 9999);
        }

        return $sanitized;
    }

    private static function get_export_process() {
        return new TEJLG_Export_Process();
    }

    private static function get_user_job_meta_key() {
        return '_tejlg_last_theme_export_job_id';
    }

    private static function remember_job_for_current_user($job_id) {
        if (!function_exists('get_current_user_id')) {
            return;
        }

        $job_id = sanitize_key((string) $job_id);

        if ('' === $job_id) {
            return;
        }

        $user_id = get_current_user_id();

        if ($user_id <= 0) {
            return;
        }

        update_user_meta($user_id, self::get_user_job_meta_key(), $job_id);
    }

    public static function get_user_job_reference($user_id = 0) {
        if (!function_exists('get_current_user_id')) {
            return '';
        }

        $user_id = (int) $user_id;

        if ($user_id <= 0) {
            $user_id = (int) get_current_user_id();
        }

        if ($user_id <= 0) {
            return '';
        }

        $stored = get_user_meta($user_id, self::get_user_job_meta_key(), true);

        if (!is_string($stored) || '' === $stored) {
            return '';
        }

        return sanitize_key($stored);
    }

    public static function clear_user_job_reference($job_id, $user_id = 0) {
        if (!function_exists('get_current_user_id')) {
            return;
        }

        $job_id = sanitize_key((string) $job_id);

        if ('' === $job_id) {
            return;
        }

        $user_id = (int) $user_id;

        if ($user_id <= 0) {
            $user_id = (int) get_current_user_id();
        }

        if ($user_id <= 0) {
            return;
        }

        $stored = get_user_meta($user_id, self::get_user_job_meta_key(), true);

        if (!is_string($stored) || '' === $stored) {
            return;
        }

        if (sanitize_key($stored) !== $job_id) {
            return;
        }

        delete_user_meta($user_id, self::get_user_job_meta_key());
    }

    public static function get_current_user_job_snapshot() {
        $job_id = self::get_user_job_reference();

        if ('' === $job_id) {
            return null;
        }

        $job = self::get_job($job_id);

        if (null === $job) {
            self::clear_user_job_reference($job_id);

            return null;
        }

        return [
            'job_id' => $job_id,
            'job'    => self::prepare_job_response($job),
            'status' => isset($job['status']) ? (string) $job['status'] : '',
        ];
    }

    private static function get_job_option_name($job_id) {
        $job_id = trim((string) $job_id);

        return 'tejlg_export_job_' . $job_id;
    }

    public static function persist_job($job) {
        if (!is_array($job) || empty($job['id'])) {
            return;
        }

        $job['updated_at'] = isset($job['updated_at']) ? (int) $job['updated_at'] : time();

        update_option(self::get_job_option_name($job['id']), $job, false);
    }

    public static function get_job($job_id) {
        $job = get_option(self::get_job_option_name($job_id), false);

        if (!is_array($job) || empty($job['id'])) {
            return null;
        }

        return $job;
    }

    public static function delete_job($job_id) {
        $job = self::get_job($job_id);

        if (null !== $job && !empty($job['zip_path']) && file_exists($job['zip_path'])) {
            self::delete_temp_file($job['zip_path']);
        }

        delete_option(self::get_job_option_name($job_id));
    }

    public static function cleanup_stale_jobs($max_age = null) {
        global $wpdb;

        $max_age = null === $max_age ? HOUR_IN_SECONDS : (int) $max_age;

        if ($max_age <= 0) {
            $max_age = HOUR_IN_SECONDS;
        }

        $threshold = time() - $max_age;
        $option_prefix = 'tejlg_export_job_';
        $options_table = $wpdb->options;
        $like_pattern  = $wpdb->esc_like($option_prefix) . '%';

        $option_names = (array) $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$options_table} WHERE option_name LIKE %s",
                $like_pattern
            )
        );

        if (empty($option_names)) {
            return;
        }

        foreach ($option_names as $option_name) {
            if (!is_string($option_name) || '' === $option_name) {
                continue;
            }

            $job_id = substr($option_name, strlen($option_prefix));

            if ('' === $job_id) {
                continue;
            }

            $job = self::get_job($job_id);

            if (null === $job) {
                delete_option($option_name);
                continue;
            }

            $status = isset($job['status']) ? (string) $job['status'] : '';

            if (!in_array($status, ['completed', 'failed'], true)) {
                continue;
            }

            $completed_at = isset($job['completed_at']) ? (int) $job['completed_at'] : 0;
            $updated_at   = isset($job['updated_at']) ? (int) $job['updated_at'] : 0;
            $reference    = $completed_at > 0 ? $completed_at : $updated_at;

            if ($reference <= 0 || $reference > $threshold) {
                continue;
            }

            self::delete_job($job_id);
        }
    }

    public static function mark_job_failed($job_id, $message) {
        $job = self::get_job($job_id);

        if (null === $job) {
            return;
        }

        $job['status']   = 'failed';
        $job['message']  = is_string($message) ? $message : '';
        $job['progress'] = isset($job['progress']) ? (int) $job['progress'] : 0;
        $job['updated_at'] = time();

        if (!empty($job['zip_path']) && file_exists($job['zip_path'])) {
            self::delete_temp_file($job['zip_path']);
        }

        self::persist_job($job);
    }

    public static function finalize_job($job) {
        if (!is_array($job) || empty($job['id'])) {
            return;
        }

        $job_id  = $job['id'];
        $zip_path = isset($job['zip_path']) ? (string) $job['zip_path'] : '';

        if ('' === $zip_path || !file_exists($zip_path)) {
            self::mark_job_failed($job_id, esc_html__("Impossible de finaliser l'archive d'export.", 'theme-export-jlg'));
            return;
        }

        $zip_file_size = filesize($zip_path);

        $zip_file_size = apply_filters('tejlg_export_zip_file_size', $zip_file_size, $zip_path);

        if (!is_numeric($zip_file_size)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf('[Theme Export JLG] Unable to determine ZIP size for download: %s (value: %s)', $zip_path, var_export($zip_file_size, true)));
            }

            self::mark_job_failed($job_id, esc_html__("Impossible de déterminer la taille de l'archive ZIP.", 'theme-export-jlg'));
            return;
        }

        $job['status']            = 'completed';
        $job['progress']          = 100;
        $job['zip_file_size']     = (int) $zip_file_size;
        $job['directories_added'] = [];
        $job['completed_at']      = time();
        $job['updated_at']        = time();

        self::persist_job($job);
    }

    public static function run_pending_export_jobs() {
        $process = self::get_export_process();
        $process->handle();
    }

    public static function get_export_job_status($job_id) {
        return self::get_job($job_id);
    }


    private static function prepare_job_response($job) {
        if (!is_array($job)) {
            return null;
        }

        $progress        = isset($job['progress']) ? max(0, min(100, (int) $job['progress'])) : 0;
        $processed_items = isset($job['processed_items']) ? (int) $job['processed_items'] : 0;
        $total_items     = isset($job['total_items']) ? (int) $job['total_items'] : 0;

        return [
            'id'               => isset($job['id']) ? (string) $job['id'] : '',
            'status'           => isset($job['status']) ? (string) $job['status'] : 'queued',
            'progress'         => $progress,
            'processed_items'  => $processed_items,
            'total_items'      => $total_items,
            'message'          => isset($job['message']) && is_string($job['message']) ? $job['message'] : '',
            'zip_file_size'    => isset($job['zip_file_size']) ? (int) $job['zip_file_size'] : 0,
            'zip_file_name'    => isset($job['zip_file_name']) ? (string) $job['zip_file_name'] : '',
            'created_at'       => isset($job['created_at']) ? (int) $job['created_at'] : 0,
            'updated_at'       => isset($job['updated_at']) ? (int) $job['updated_at'] : 0,
        ];
    }

    public static function ajax_start_theme_export() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Accès refusé.', 'theme-export-jlg')], 403);
        }

        check_ajax_referer('tejlg_start_theme_export', 'nonce');

        $selection  = TEJLG_Admin_Export_Page::extract_exclusion_selection_from_request($_POST);
        TEJLG_Admin_Export_Page::store_exclusion_preferences($selection);
        $exclusions = TEJLG_Admin_Export_Page::build_exclusion_list($selection);

        $result = self::export_theme($exclusions);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 400);
        }

        $job_id = (string) $result;
        $job    = self::get_job($job_id);

        wp_send_json_success([
            'job_id'        => $job_id,
            'job'           => self::prepare_job_response($job),
            'downloadNonce' => wp_create_nonce('tejlg_download_theme_export_' . $job_id),
        ]);
    }

    public static function ajax_get_theme_export_status() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Accès refusé.', 'theme-export-jlg')], 403);
        }

        check_ajax_referer('tejlg_theme_export_status', 'nonce');

        $job_id = isset($_REQUEST['job_id']) ? sanitize_key(wp_unslash((string) $_REQUEST['job_id'])) : '';

        if ('' === $job_id) {
            wp_send_json_error(['message' => esc_html__('Identifiant de tâche manquant.', 'theme-export-jlg')], 400);
        }

        self::cleanup_stale_jobs();

        $job = self::get_job($job_id);

        if (null === $job) {
            self::clear_user_job_reference($job_id);
            wp_send_json_error(['message' => esc_html__('Tâche introuvable ou expirée.', 'theme-export-jlg')], 404);
        }

        $response = [
            'job' => self::prepare_job_response($job),
        ];

        $job_status = isset($job['status']) ? (string) $job['status'] : '';

        if (isset($job['status']) && 'completed' === $job['status']) {
            $download_nonce = wp_create_nonce('tejlg_download_theme_export_' . $job_id);
            $response['download_url'] = add_query_arg(
                [
                    'action'  => 'tejlg_download_theme_export',
                    'job_id'  => rawurlencode($job_id),
                    '_wpnonce' => $download_nonce,
                ],
                admin_url('admin-ajax.php')
            );
        }

        if (in_array($job_status, ['completed', 'failed'], true)) {
            self::clear_user_job_reference($job_id);
        }

        wp_send_json_success($response);
    }

    public static function ajax_download_theme_export() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Accès refusé.', 'theme-export-jlg')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        $job_id = isset($_REQUEST['job_id']) ? sanitize_key(wp_unslash((string) $_REQUEST['job_id'])) : '';

        if ('' === $job_id) {
            wp_die(esc_html__('Identifiant de tâche manquant.', 'theme-export-jlg'));
        }

        check_ajax_referer('tejlg_download_theme_export_' . $job_id);

        $job = self::get_job($job_id);

        if (null === $job || !isset($job['status']) || 'completed' !== $job['status']) {
            wp_die(esc_html__("Cette archive n'est pas disponible.", 'theme-export-jlg'));
        }

        $zip_path = isset($job['zip_path']) ? (string) $job['zip_path'] : '';

        if ('' === $zip_path || !file_exists($zip_path)) {
            self::delete_job($job_id);
            wp_die(esc_html__('Le fichier ZIP généré est introuvable.', 'theme-export-jlg'));
        }

        $zip_file_name = isset($job['zip_file_name']) && '' !== $job['zip_file_name']
            ? $job['zip_file_name']
            : basename($zip_path);
        $zip_file_size = isset($job['zip_file_size']) ? (int) $job['zip_file_size'] : (int) filesize($zip_path);

        $should_stream = apply_filters('tejlg_export_stream_zip_archive', true, $zip_path, $zip_file_name, $zip_file_size);

        if (!$should_stream) {
            wp_send_json_success([
                'path'     => $zip_path,
                'filename' => $zip_file_name,
                'size'     => $zip_file_size,
            ]);
        }

        nocache_headers();
        self::clear_output_buffers();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zip_file_name . '"');
        header('Content-Length: ' . (string) $zip_file_size);

        readfile($zip_path);
        flush();

        self::delete_job($job_id);
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

        if (false !== $handle) {
            /**
             * Filters the file handle used to write the exported patterns JSON file.
             *
             * This filter can be used to provide a custom stream resource when generating
             * the export, for instance in tests.
             *
             * @param resource|false $handle    File handle returned by `fopen()`.
             * @param string         $temp_file Absolute path to the temporary JSON file.
             */
            $handle = apply_filters('tejlg_export_patterns_file_handle', $handle, $temp_file);
        }

        if (false === $handle || !is_resource($handle)) {
            @unlink($temp_file);
            wp_die(esc_html__("Impossible de créer le flux de téléchargement pour l'export JSON.", 'theme-export-jlg'));
        }

        $has_written_items = false;
        self::write_to_handle_or_fail($handle, $temp_file, "[\n");

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

                $post_id = get_the_ID();

                $pattern_data = [
                    'title'   => get_the_title(),
                    'slug'    => $slug,
                    'content' => $content,
                ];

                $excerpt = get_post_field('post_excerpt', $post_id);

                if (is_string($excerpt)) {
                    $excerpt = trim($excerpt);

                    if ('' !== $excerpt) {
                        $pattern_data['post_excerpt'] = $excerpt;
                    }
                }

                $taxonomies = self::get_pattern_taxonomies_payload($post_id);

                if (!empty($taxonomies)) {
                    $pattern_data['taxonomies'] = $taxonomies;
                }

                $meta = self::get_pattern_meta_payload($post_id);

                if (!empty($meta)) {
                    $pattern_data['meta'] = $meta;
                }

                if (!array_key_exists('viewportWidth', $pattern_data) && isset($meta['viewportWidth'])) {
                    $viewport_width = $meta['viewportWidth'];

                    if (is_numeric($viewport_width)) {
                        $pattern_data['viewportWidth'] = (int) $viewport_width;
                    }
                }

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
                    self::write_to_handle_or_fail($handle, $temp_file, ",\n" . $formatted_pattern);
                } else {
                    self::write_to_handle_or_fail($handle, $temp_file, $formatted_pattern);
                    $has_written_items = true;
                }
            }

            wp_reset_postdata();

            if ($current_batch_count < $batch_size) {
                break;
            }

            $page++;
        }

        self::write_to_handle_or_fail($handle, $temp_file, $has_written_items ? "\n]\n" : "]\n");
        fclose($handle);

        $filename = empty($sanitized_ids) ? 'exported-patterns.json' : 'selected-patterns.json';
        return self::stream_json_file($temp_file, $filename);
    }

    private static function get_pattern_taxonomies_payload($post_id) {
        $default_taxonomies = ['wp_pattern_category', 'wp_pattern_tag'];

        $taxonomies = apply_filters('tejlg_export_patterns_taxonomies', $default_taxonomies, $post_id);

        if (!is_array($taxonomies) || empty($taxonomies)) {
            return [];
        }

        $payload = [];

        foreach ($taxonomies as $taxonomy) {
            if (!is_string($taxonomy) || '' === $taxonomy) {
                continue;
            }

            $taxonomy = trim($taxonomy);

            if ('' === $taxonomy || !taxonomy_exists($taxonomy)) {
                continue;
            }

            $terms = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'slugs']);

            if (is_wp_error($terms) || empty($terms)) {
                continue;
            }

            $sanitized_terms = array_values(
                array_filter(
                    array_unique(
                        array_map(
                            static function ($term) {
                                if (!is_scalar($term)) {
                                    return '';
                                }

                                $term = trim((string) $term);

                                if ('' === $term) {
                                    return '';
                                }

                                $sanitized = sanitize_title($term);

                                return '' === $sanitized ? '' : $sanitized;
                            },
                            (array) $terms
                        )
                    ),
                    static function ($term) {
                        return '' !== $term;
                    }
                )
            );

            if (!empty($sanitized_terms)) {
                $payload[$taxonomy] = $sanitized_terms;
            }
        }

        return $payload;
    }

    private static function get_pattern_meta_payload($post_id) {
        $registered_meta_keys = function_exists('get_registered_meta_keys')
            ? get_registered_meta_keys('post', 'wp_block')
            : [];

        $default_meta_keys = [];

        if (is_array($registered_meta_keys)) {
            foreach ($registered_meta_keys as $meta_key => $meta_args) {
                if (!is_string($meta_key) || '' === $meta_key) {
                    continue;
                }

                $show_in_rest = is_array($meta_args) && isset($meta_args['show_in_rest'])
                    ? $meta_args['show_in_rest']
                    : false;

                if ($show_in_rest) {
                    $default_meta_keys[] = $meta_key;
                }
            }
        }

        $default_meta_keys[] = 'viewportWidth';

        $meta_keys = apply_filters('tejlg_export_patterns_meta_keys', array_unique($default_meta_keys), $post_id);

        if (!is_array($meta_keys) || empty($meta_keys)) {
            return [];
        }

        $payload = [];

        foreach ($meta_keys as $meta_key) {
            if (!is_string($meta_key)) {
                continue;
            }

            $meta_key = trim($meta_key);

            if ('' === $meta_key) {
                continue;
            }

            $value = get_post_meta($post_id, $meta_key, true);

            if (is_string($value)) {
                if ('' === $value && '0' !== $value) {
                    continue;
                }

                $payload[$meta_key] = $value;
                continue;
            }

            if (is_numeric($value) || is_bool($value)) {
                $payload[$meta_key] = $value;
                continue;
            }

            if (is_array($value)) {
                $normalizer = static function ($item) {
                    if (is_string($item)) {
                        return $item;
                    }

                    if (is_numeric($item) || is_bool($item) || null === $item) {
                        return $item;
                    }

                    return is_object($item) ? (array) $item : $item;
                };

                $normalized = function_exists('map_deep')
                    ? map_deep($value, $normalizer)
                    : self::map_deep_compat($value, $normalizer);

                if (!empty($normalized)) {
                    $payload[$meta_key] = $normalized;
                }
            }
        }

        return $payload;
    }

    private static function map_deep_compat($value, callable $callback) {
        if (is_array($value)) {
            foreach ($value as $key => $sub_value) {
                $value[$key] = self::map_deep_compat($sub_value, $callback);
            }

            return $value;
        }

        return $callback($value);
    }

    private static function write_to_handle_or_fail($handle, $temp_file, $data) {
        $bytes_written = fwrite($handle, $data);

        if (false === $bytes_written) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            if (file_exists($temp_file)) {
                @unlink($temp_file);
            }

            wp_die(
                esc_html__(
                    "Une erreur critique est survenue lors de l'écriture du fichier JSON d'export.",
                    'theme-export-jlg'
                )
            );
        }
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
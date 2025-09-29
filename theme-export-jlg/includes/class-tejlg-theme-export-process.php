<?php
class TEJLG_Theme_Export_Process {
    const JOB_TTL = DAY_IN_SECONDS;
    const JOB_OPTION_PREFIX = 'tejlg_theme_export_job_';
    const QUEUE_OPTION_PREFIX = 'tejlg_theme_export_queue_';
    const CRON_HOOK = 'tejlg_theme_export_process_batch';

    private static $hooks_registered = false;

    /**
     * Registers hooks used by the export background processing system.
     */
    public static function register_hooks() {
        if (self::$hooks_registered) {
            return;
        }

        add_action(self::CRON_HOOK, [__CLASS__, 'process_batch']);
        self::$hooks_registered = true;
    }

    /**
     * Creates a new export job and stores it in the persistent queue.
     *
     * @param array $job_data     Initial job data to persist.
     * @param array $file_batches List of file batches to process sequentially.
     * @param array $directories  Directories to add to the ZIP archive.
     * @return string|WP_Error Job identifier on success, WP_Error otherwise.
     */
    public static function register_job(array $job_data, array $file_batches, array $directories) {
        if (empty($job_data['id'])) {
            return new WP_Error('tejlg_export_invalid_job', __('Identifiant de tâche invalide.', 'theme-export-jlg'));
        }

        $job_id = (string) $job_data['id'];
        $job_key = self::get_job_key($job_id);
        $queue_key = self::get_queue_key($job_id);

        $job_data['status'] = 'queued';
        $job_data['processed_files'] = isset($job_data['processed_files']) ? (int) $job_data['processed_files'] : 0;
        $job_data['total_files'] = isset($job_data['total_files']) ? (int) $job_data['total_files'] : 0;
        $job_data['directories_remaining'] = array_values(array_unique($directories));
        $job_data['message'] = __('Tâche en file d\'attente…', 'theme-export-jlg');
        $job_data['last_activity'] = time();
        $job_data['zip_initialized'] = !empty($job_data['zip_initialized']);
        $job_data['is_running'] = false;

        $queue_payload = [
            'batches' => array_values($file_batches),
        ];

        if (!set_transient($job_key, $job_data, self::JOB_TTL)) {
            return new WP_Error('tejlg_export_persist_job_failed', __('Impossible de démarrer la tâche d\'export.', 'theme-export-jlg'));
        }

        if (!set_transient($queue_key, $queue_payload, self::JOB_TTL)) {
            delete_transient($job_key);
            return new WP_Error('tejlg_export_persist_queue_failed', __('Impossible de planifier le traitement des fichiers.', 'theme-export-jlg'));
        }

        return $job_id;
    }

    /**
     * Schedules a background batch for the provided job identifier.
     *
     * @param string $job_id Identifier of the job to process.
     */
    public static function dispatch($job_id) {
        if (empty($job_id)) {
            return;
        }

        if (!wp_next_scheduled(self::CRON_HOOK, [$job_id])) {
            wp_schedule_single_event(time() + 1, self::CRON_HOOK, [$job_id]);
        }
    }

    /**
     * Attempts to process a batch immediately. Primarily used by status polling
     * and automated tests to ensure progress without waiting for WP-Cron.
     *
     * @param string $job_id Identifier of the job to process.
     * @return bool True when additional work remains, false otherwise.
     */
    public static function maybe_process_now($job_id) {
        $result = self::process_batch($job_id);

        if (is_wp_error($result)) {
            return false;
        }

        $job = self::get_job($job_id);

        if (empty($job)) {
            return false;
        }

        return !in_array($job['status'], ['failed', 'completed'], true);
    }

    /**
     * Processes the next batch of files for a given job.
     *
     * @param string $job_id Identifier of the job to process.
     * @return true|WP_Error True on success, WP_Error on failure.
     */
    public static function process_batch($job_id) {
        $job_id = (string) $job_id;
        $job = self::get_job($job_id);

        if (empty($job)) {
            self::clear_queue($job_id);
            return new WP_Error('tejlg_export_missing_job', __('La tâche d\'export demandée est introuvable.', 'theme-export-jlg'));
        }

        if (in_array($job['status'], ['failed', 'completed'], true)) {
            self::refresh_job_ttl($job_id, $job);
            self::refresh_queue_ttl($job_id);
            return true;
        }

        if (!empty($job['is_running'])) {
            // Another process is currently handling this job, reschedule.
            self::dispatch($job_id);
            return true;
        }

        $job['is_running'] = true;
        $job['last_activity'] = time();
        self::store_job($job_id, $job);

        $queue = self::get_queue($job_id);
        $batch = self::shift_next_batch($job_id, $queue);

        $zip_path = isset($job['zip_path']) ? (string) $job['zip_path'] : '';
        $zip_root = isset($job['zip_root']) ? (string) $job['zip_root'] : '';

        $zip = new ZipArchive();
        $zip_flags = ZipArchive::CREATE;

        if (empty($job['zip_initialized'])) {
            $zip_flags |= ZipArchive::OVERWRITE;
        }

        $zip_open_result = $zip->open($zip_path, $zip_flags);

        if (true !== $zip_open_result) {
            $job['is_running'] = false;
            self::fail_job($job_id, $job, esc_html__("Impossible d'ouvrir l'archive ZIP.", 'theme-export-jlg'));
            return new WP_Error('tejlg_export_zip_open_failed', __('Impossible d\'ouvrir le fichier ZIP temporaire.', 'theme-export-jlg'));
        }

        $root_directory = rtrim($zip_root, '/') . '/';

        if (empty($job['zip_initialized'])) {
            if (true !== $zip->addEmptyDir($root_directory)) {
                $zip->close();
                $job['is_running'] = false;
                self::fail_job($job_id, $job, esc_html__("Impossible d'ajouter le dossier racine dans l'archive.", 'theme-export-jlg'));
                return new WP_Error('tejlg_export_zip_root_failed', __('Impossible d\'ajouter le dossier racine dans l\'archive ZIP.', 'theme-export-jlg'));
            }

            $job['zip_initialized'] = true;
        }

        if (!empty($job['directories_remaining'])) {
            foreach ($job['directories_remaining'] as $directory) {
                $directory = is_string($directory) ? trim($directory, '/') : '';

                if ('' === $directory) {
                    continue;
                }

                $zip_directory = rtrim($root_directory . ltrim($directory, '/'), '/') . '/';

                if (true !== $zip->addEmptyDir($zip_directory)) {
                    $zip->close();
                    $job['directories_remaining'] = [];
                    $job['is_running'] = false;
                    self::fail_job(
                        $job_id,
                        $job,
                        sprintf(
                            /* translators: %s: directory path relative to the theme root. */
                            esc_html__("Impossible d'ajouter le dossier « %s » à l'archive.", 'theme-export-jlg'),
                            esc_html($zip_directory)
                        )
                    );

                    return new WP_Error('tejlg_export_add_directory_failed', __('Impossible d\'ajouter un dossier à l\'archive ZIP.', 'theme-export-jlg'));
                }
            }

            $job['directories_remaining'] = [];
        }

        $processed_in_batch = 0;

        foreach ($batch as $file_data) {
            if (!is_array($file_data)) {
                continue;
            }

            $absolute_path = isset($file_data['absolute']) ? (string) $file_data['absolute'] : '';
            $relative_path = isset($file_data['relative']) ? (string) $file_data['relative'] : '';

            if ('' === $absolute_path || '' === $relative_path) {
                continue;
            }

            if (!is_readable($absolute_path)) {
                $zip->close();
                $job['is_running'] = false;
                self::fail_job(
                    $job_id,
                    $job,
                    sprintf(
                        /* translators: %s: file path relative to the theme root. */
                        esc_html__("Impossible de lire le fichier « %s ».", 'theme-export-jlg'),
                        esc_html($relative_path)
                    )
                );

                return new WP_Error('tejlg_export_unreadable_file', __('Un fichier nécessaire est illisible.', 'theme-export-jlg'));
            }

            $zip_path = $root_directory . ltrim($relative_path, '/');

            if (true !== $zip->addFile($absolute_path, $zip_path)) {
                $zip->close();
                $job['is_running'] = false;
                self::fail_job(
                    $job_id,
                    $job,
                    sprintf(
                        /* translators: %s: file path relative to the theme root. */
                        esc_html__("Impossible d'ajouter le fichier « %s » à l'archive.", 'theme-export-jlg'),
                        esc_html($zip_path)
                    )
                );

                return new WP_Error('tejlg_export_add_file_failed', __('Impossible d\'ajouter un fichier à l\'archive ZIP.', 'theme-export-jlg'));
            }

            $processed_in_batch++;
        }

        $zip->close();

        if ($processed_in_batch > 0) {
            $job['processed_files'] += $processed_in_batch;
        }

        if ($job['processed_files'] >= $job['total_files']) {
            $file_size = file_exists($zip_path) ? filesize($zip_path) : false;
            $file_size = apply_filters('tejlg_export_zip_file_size', $file_size, $zip_path);

            if (!is_numeric($file_size)) {
                $job['is_running'] = false;
                self::fail_job(
                    $job_id,
                    $job,
                    esc_html__("Impossible de déterminer la taille de l'archive ZIP à télécharger.", 'theme-export-jlg')
                );
                TEJLG_Export::delete_temp_file($zip_path);

                return new WP_Error('tejlg_export_invalid_zip_size', __('Impossible de déterminer la taille de l\'archive ZIP.', 'theme-export-jlg'));
            }

            $job['status'] = 'completed';
            $job['file_size'] = (int) $file_size;
            $job['completed_at'] = time();
            $job['message'] = __('Archive prête pour le téléchargement.', 'theme-export-jlg');
            $job['is_running'] = false;
            self::store_job($job_id, $job);
            self::clear_queue($job_id);

            return true;
        }

        if (!empty($queue['batches'])) {
            $job['status'] = 'processing';
            $job['message'] = __('Création de l\'archive en cours…', 'theme-export-jlg');
            $job['is_running'] = false;
            self::store_job($job_id, $job);
            self::store_queue($job_id, $queue);
            self::dispatch($job_id);

            return true;
        }

        // No more batches but not enough files processed: mark as failed.
        $job['is_running'] = false;
        self::fail_job(
            $job_id,
            $job,
            esc_html__("Aucun fichier n'a pu être ajouté à l'archive.", 'theme-export-jlg')
        );

        return new WP_Error('tejlg_export_empty_job', __('Aucun fichier n\'a pu être ajouté à l\'archive.', 'theme-export-jlg'));
    }

    /**
     * Retrieves a stored job by its identifier.
     *
     * @param string $job_id Identifier of the job to fetch.
     * @return array|null Job payload or null when absent.
     */
    public static function get_job($job_id) {
        $job_key = self::get_job_key((string) $job_id);
        $job = get_transient($job_key);

        if (!is_array($job)) {
            return null;
        }

        return $job;
    }

    /**
     * Removes the job and associated queue from storage.
     *
     * @param string $job_id Identifier of the job to remove.
     */
    public static function delete_job($job_id) {
        delete_transient(self::get_job_key((string) $job_id));
        delete_transient(self::get_queue_key((string) $job_id));
    }

    /**
     * Persists the provided job payload while refreshing its TTL.
     *
     * @param string $job_id Identifier of the job to store.
     * @param array  $job    Job payload.
     */
    private static function store_job($job_id, array $job) {
        set_transient(self::get_job_key((string) $job_id), $job, self::JOB_TTL);
    }

    /**
     * Retrieves and refreshes the queue payload for a job.
     *
     * @param string $job_id Identifier of the job.
     * @return array
     */
    private static function get_queue($job_id) {
        $queue_key = self::get_queue_key((string) $job_id);
        $queue = get_transient($queue_key);

        if (!is_array($queue)) {
            $queue = [ 'batches' => [] ];
        }

        return $queue;
    }

    /**
     * Persists the queue payload.
     *
     * @param string $job_id Identifier of the job.
     * @param array  $queue  Queue payload.
     */
    private static function store_queue($job_id, array $queue) {
        set_transient(self::get_queue_key((string) $job_id), $queue, self::JOB_TTL);
    }

    /**
     * Removes all queue data associated with a job.
     *
     * @param string $job_id Identifier of the job.
     */
    private static function clear_queue($job_id) {
        delete_transient(self::get_queue_key((string) $job_id));
    }

    /**
     * Pops the next batch to process from the queue.
     *
     * @param string $job_id Identifier of the job.
     * @param array  $queue  Queue payload (passed by reference).
     * @return array
     */
    private static function shift_next_batch($job_id, array &$queue) {
        if (empty($queue['batches']) || !is_array($queue['batches'])) {
            $queue['batches'] = [];
            self::store_queue($job_id, $queue);
            return [];
        }

        $batch = array_shift($queue['batches']);
        self::store_queue($job_id, $queue);

        if (!is_array($batch)) {
            return [];
        }

        return $batch;
    }

    /**
     * Marks the job as failed with the provided message and performs cleanup.
     *
     * @param string $job_id  Identifier of the job.
     * @param array  $job     Job payload.
     * @param string $message Message to store.
     */
    private static function fail_job($job_id, array $job, $message) {
        $job['status'] = 'failed';
        $job['message'] = $message;
        $job['failed_at'] = time();
        $job['is_running'] = false;
        self::store_job($job_id, $job);
        self::clear_queue($job_id);

        if (!empty($job['zip_path']) && file_exists($job['zip_path'])) {
            TEJLG_Export::delete_temp_file($job['zip_path']);
        }
    }

    private static function refresh_job_ttl($job_id, array $job) {
        self::store_job($job_id, $job);
    }

    private static function refresh_queue_ttl($job_id) {
        $queue = self::get_queue($job_id);
        self::store_queue($job_id, $queue);
    }

    private static function get_job_key($job_id) {
        return self::JOB_OPTION_PREFIX . sanitize_key(str_replace([':', ' '], '-', $job_id));
    }

    private static function get_queue_key($job_id) {
        return self::QUEUE_OPTION_PREFIX . sanitize_key(str_replace([':', ' '], '-', $job_id));
    }
}

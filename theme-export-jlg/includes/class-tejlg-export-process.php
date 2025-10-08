<?php

require_once __DIR__ . '/class-tejlg-zip-writer.php';

class TEJLG_Export_Process extends WP_Background_Process {

    /**
     * Background process action name.
     *
     * @var string
     */
    protected $action = 'tejlg_theme_export';

    /**
     * Process a single queue item.
     *
     * @param array $item Queue item containing job and file data.
     *
     * @return false|array
     */
    protected function task($item) {
        if (!is_array($item)) {
            return false;
        }

        $job_id = isset($item['job_id']) ? (string) $item['job_id'] : '';

        if ('' === $job_id) {
            return false;
        }

        $job = TEJLG_Export::get_job($job_id);

        if (null === $job) {
            return false;
        }

        if (!isset($job['status']) || 'failed' === $job['status']) {
            return false;
        }

        if ('cancelled' === $job['status']) {
            return false;
        }

        if (isset($job['failure_code'])) {
            unset($job['failure_code']);
        }

        $job['updated_at'] = time();
        TEJLG_Export::persist_job($job);

        $type               = isset($item['type']) ? $item['type'] : '';
        $real_path          = isset($item['real_path']) ? (string) $item['real_path'] : '';
        $relative_path_in_zip = isset($item['relative_path_in_zip']) ? (string) $item['relative_path_in_zip'] : '';

        if ('' === $relative_path_in_zip) {
            return false;
        }

        $zip_path = isset($job['zip_path']) ? (string) $job['zip_path'] : '';

        if ('' === $zip_path || !file_exists($zip_path)) {
            TEJLG_Export::mark_job_failed(
                $job_id,
                esc_html__("L'archive temporaire est introuvable.", 'theme-export-jlg')
            );
            return false;
        }

        $zip = TEJLG_Zip_Writer::open($zip_path);

        if (is_wp_error($zip)) {
            TEJLG_Export::mark_job_failed(
                $job_id,
                esc_html__("Impossible d'ouvrir l'archive temporaire.", 'theme-export-jlg')
            );
            return false;
        }

        $directories_added = isset($job['directories_added']) && is_array($job['directories_added'])
            ? $job['directories_added']
            : [];

        $result = true;

        if ('dir' === $type) {
            $zip_path_to_add = rtrim($relative_path_in_zip, '/') . '/';

            if (!isset($directories_added[$zip_path_to_add])) {
                $result = $zip->add_directory($zip_path_to_add);

                if (true === $result) {
                    $directories_added[$zip_path_to_add] = true;
                }
            }
        } elseif ('file' === $type) {
            if (!file_exists($real_path)) {
                $zip->close();
                TEJLG_Export::mark_job_failed(
                    $job_id,
                    sprintf(
                        /* translators: %s: relative path of the missing file. */
                        esc_html__("Le fichier « %s » est introuvable.", 'theme-export-jlg'),
                        esc_html($relative_path_in_zip)
                    )
                );
                return false;
            }

            $directory_base = dirname($relative_path_in_zip);
            $directory_base = is_string($directory_base) ? trim($directory_base) : '';

            if ('' !== $directory_base && '.' !== $directory_base && '/' !== $directory_base && '\\' !== $directory_base) {
                $directory_to_ensure = trailingslashit($directory_base);
                $segments = array_filter(
                    explode('/', trim($directory_to_ensure, '/')),
                    static function ($segment) {
                        return '' !== $segment && '.' !== $segment;
                    }
                );

                if (!empty($segments)) {
                    $current = '';

                    foreach ($segments as $segment) {
                        $current .= $segment . '/';

                        if (!isset($directories_added[$current])) {
                            $zip->add_directory($current);
                            $directories_added[$current] = true;
                        }
                    }
                }
            }

            $result = $zip->add_file($real_path, $relative_path_in_zip);
        } else {
            $zip->close();
            return false;
        }

        $zip->close();

        if (true !== $result) {
            TEJLG_Export::mark_job_failed(
                $job_id,
                esc_html__("Une erreur est survenue lors de l'ajout d'un élément à l'archive.", 'theme-export-jlg')
            );

            return false;
        }

        $processed = isset($job['processed_items']) ? (int) $job['processed_items'] : 0;
        $total     = isset($job['total_items']) ? (int) $job['total_items'] : 0;

        $processed++;
        $progress = $total > 0 ? min(100, (int) floor(($processed / $total) * 100)) : 100;

        $job['directories_added'] = $directories_added;
        $job['processed_items']   = $processed;
        $job['progress']          = $progress;
        $job['status']            = 'processing';
        $job['updated_at']        = time();

        if ($processed >= $total && $total > 0) {
            TEJLG_Export::finalize_job($job);
            return false;
        }

        TEJLG_Export::persist_job($job);

        return false;
    }
}


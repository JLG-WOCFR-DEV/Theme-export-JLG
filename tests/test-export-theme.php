<?php

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export.php';

/**
 * @group export-theme
 */
class Test_Export_Theme extends WP_UnitTestCase {

    public function test_export_theme_marks_job_failed_when_filesize_is_unavailable() {
        $filesize_filter = static function () {
            return false;
        };

        add_filter('tejlg_export_zip_file_size', $filesize_filter, 10, 2);

        $job_id = TEJLG_Export::export_theme();

        $this->assertNotWPError($job_id, 'Job creation should succeed even if the export fails later.');
        $this->assertIsString($job_id, 'The job identifier should be a string.');

        TEJLG_Export::run_pending_export_jobs();

        $job = TEJLG_Export::get_export_job_status($job_id);

        $this->assertIsArray($job, 'The job payload should be stored.');
        $this->assertSame('failed', $job['status'], 'The job should be marked as failed when filesize detection fails.');
        $this->assertStringContainsString(
            "Impossible de déterminer la taille de l'archive ZIP.",
            isset($job['message']) ? (string) $job['message'] : '',
            'The failure message should mention the filesize error.'
        );

        TEJLG_Export::delete_job($job_id);

        remove_filter('tejlg_export_zip_file_size', $filesize_filter, 10);
    }

    public function test_export_theme_runs_immediately_when_wp_cron_is_disabled() {
        $immediate_filter = static function () {
            return false;
        };

        add_filter('tejlg_export_run_jobs_immediately', $immediate_filter, 10, 1);

        if (!defined('DISABLE_WP_CRON')) {
            define('DISABLE_WP_CRON', true);
        } elseif (!DISABLE_WP_CRON) {
            $this->markTestSkipped('DISABLE_WP_CRON is already defined and set to false.');
        }

        $job_id = TEJLG_Export::export_theme();

        remove_filter('tejlg_export_run_jobs_immediately', $immediate_filter, 10);

        $this->assertNotWPError($job_id, 'The export job should be created successfully even if WP-Cron is disabled.');

        $job = TEJLG_Export::get_export_job_status($job_id);

        $this->assertIsArray($job, 'The export job payload should be accessible.');
        $this->assertSame('completed', isset($job['status']) ? $job['status'] : null, 'The export should complete immediately when WP-Cron cannot run.');

        TEJLG_Export::delete_job($job_id);
    }

    public function test_cleanup_stale_jobs_removes_completed_job_and_file() {
        $job_id = sanitize_key('test_cleanup_' . wp_generate_uuid4());
        $temp_file = wp_tempnam('tejlg-export-cleanup');

        $this->assertIsString($temp_file, 'Temporary file path should be generated.');
        $this->assertNotEmpty($temp_file, 'Temporary file path should not be empty.');

        file_put_contents($temp_file, 'temporary export data');

        $this->assertTrue(file_exists($temp_file), 'Temporary ZIP file should exist before cleanup.');

        $job_payload = [
            'id'            => $job_id,
            'status'        => 'completed',
            'zip_path'      => $temp_file,
            'zip_file_name' => basename($temp_file),
            'completed_at'  => time() - (2 * HOUR_IN_SECONDS),
            'updated_at'    => time() - (2 * HOUR_IN_SECONDS),
        ];

        TEJLG_Export::persist_job($job_payload);

        $stored_job = TEJLG_Export::get_job($job_id);

        $this->assertIsArray($stored_job, 'The job should be stored before cleanup runs.');

        TEJLG_Export::cleanup_stale_jobs(HOUR_IN_SECONDS);

        $this->assertNull(TEJLG_Export::get_job($job_id), 'The job should be removed once it becomes stale.');
        $this->assertFalse(file_exists($temp_file), 'Cleanup should delete the associated temporary ZIP file.');
    }

    public function test_export_theme_generates_zip_when_ziparchive_is_unavailable() {
        $filter = static function () {
            return false;
        };

        add_filter('tejlg_zip_writer_use_ziparchive', $filter, 10, 1);

        $job_id = TEJLG_Export::export_theme();

        $this->assertNotWPError($job_id, 'The export job should be created successfully without ZipArchive.');

        TEJLG_Export::run_pending_export_jobs();

        $job = TEJLG_Export::get_export_job_status($job_id);

        $this->assertIsArray($job, 'The job payload should be available.');
        $this->assertSame('completed', isset($job['status']) ? $job['status'] : null, 'The export should complete successfully with the fallback.');

        $zip_path = isset($job['zip_path']) ? (string) $job['zip_path'] : '';

        $this->assertNotEmpty($zip_path, 'The fallback export should provide a ZIP path.');
        $this->assertTrue(file_exists($zip_path), 'The fallback export should generate a ZIP file on disk.');

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            $open_result = $zip->open($zip_path);

            $this->assertSame(true, $open_result, 'The generated archive should be readable as a ZIP file.');
            $this->assertGreaterThan(0, $zip->numFiles, 'The generated archive should contain at least one file.');

            $zip->close();
        }

        if (!class_exists('PclZip')) {
            require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
        }

        $pclzip = new PclZip($zip_path);
        $list   = $pclzip->listContent();

        $this->assertIsArray($list, 'PclZip should be able to read the fallback archive.');
        $this->assertNotEmpty($list, 'The fallback archive should contain entries.');

        $entry_names = array_values(
            array_filter(
                array_map(
                    static function ($entry) {
                        if (isset($entry['stored_filename'])) {
                            return (string) $entry['stored_filename'];
                        }

                        if (isset($entry['filename'])) {
                            return (string) $entry['filename'];
                        }

                        return '';
                    },
                    $list
                ),
                static function ($name) {
                    return '' !== $name;
                }
            )
        );

        $theme      = wp_get_theme();
        $theme_slug = $theme->get_stylesheet();
        $root_entry = trailingslashit($theme_slug);

        $this->assertContains($root_entry, $entry_names, 'The archive should contain the theme root directory.');

        TEJLG_Export::delete_job($job_id);

        remove_filter('tejlg_zip_writer_use_ziparchive', $filter, 10);
    }

    public function test_run_scheduled_theme_export_handles_persistence_failure() {
        TEJLG_Export_History::clear_history();

        $original_settings = TEJLG_Export::get_schedule_settings();
        $modified_settings = $original_settings;
        $modified_settings['frequency'] = 'daily';

        TEJLG_Export::update_schedule_settings($modified_settings);

        $original_admin_email = get_option('admin_email');
        update_option('admin_email', 'admin@example.com');

        $uploads_filter = static function ($uploads) {
            return [
                'path'    => '',
                'url'     => '',
                'subdir'  => '',
                'basedir' => '',
                'baseurl' => '',
                'error'   => 'Simulated failure',
            ];
        };

        add_filter('wp_upload_dir', $uploads_filter, 10, 1);

        $sent_emails = [];

        $mail_filter = static function ($args) use (&$sent_emails) {
            $sent_emails[] = $args;

            return $args;
        };

        add_filter('wp_mail', $mail_filter, 10, 1);

        TEJLG_Export::run_scheduled_theme_export();

        remove_filter('wp_upload_dir', $uploads_filter, 10);
        remove_filter('wp_mail', $mail_filter, 10);

        $history = TEJLG_Export_History::get_entries(['per_page' => 1]);

        $this->assertNotEmpty($history['entries'], 'A history entry should be recorded for the failed scheduled export.');

        $entry = $history['entries'][0];

        $this->assertSame('failed', $entry['status'], 'The history entry should record the failure.');
        $this->assertNotEmpty($entry['job_id'], 'The failure entry should keep the job identifier.');

        $job = TEJLG_Export::get_export_job_status($entry['job_id']);

        $this->assertIsArray($job, 'The failed job should remain stored.');
        $this->assertSame('failed', isset($job['status']) ? $job['status'] : null, 'The job should be marked as failed.');
        $this->assertStringContainsString(
            "Impossible de conserver l'archive de l'export planifié :",
            isset($job['message']) ? (string) $job['message'] : '',
            'The failure message should mention the persistence error.'
        );

        $success_notifications = array_filter(
            $sent_emails,
            static function ($mail) {
                if (!is_array($mail) || empty($mail['subject'])) {
                    return false;
                }

                return false !== strpos((string) $mail['subject'], 'réussi');
            }
        );

        $this->assertEmpty($success_notifications, 'No success notification should be sent when persistence fails.');

        $failure_notifications = array_filter(
            $sent_emails,
            static function ($mail) {
                if (!is_array($mail) || empty($mail['subject'])) {
                    return false;
                }

                return false !== strpos((string) $mail['subject'], 'Échec');
            }
        );

        $this->assertNotEmpty($failure_notifications, 'A failure notification should be sent when persistence fails.');

        TEJLG_Export::delete_job($entry['job_id']);
        TEJLG_Export::update_schedule_settings($original_settings);
        update_option('admin_email', $original_admin_email);
        TEJLG_Export_History::clear_history();
    }

    public function test_run_scheduled_theme_export_records_summary_url_in_history() {
        TEJLG_Export_History::clear_history();

        $original_settings = TEJLG_Export::get_schedule_settings();
        $modified_settings = $original_settings;
        $modified_settings['frequency'] = 'daily';

        TEJLG_Export::update_schedule_settings($modified_settings);

        $sent_emails = [];
        $mail_filter = static function ($args) use (&$sent_emails) {
            $sent_emails[] = $args;

            return $args;
        };

        add_filter('wp_mail', $mail_filter, 10, 1);

        try {
            TEJLG_Export::run_scheduled_theme_export();

            $history = TEJLG_Export_History::get_entries(['per_page' => 1]);

            $this->assertNotEmpty($history['entries'], 'A successful scheduled export should be recorded in the history.');

            $entry = $history['entries'][0];

            $this->assertSame('completed', $entry['status'], 'The recorded entry should indicate a successful export.');
            $this->assertArrayHasKey('summary_url', $entry, 'The history entry should expose the persisted summary URL.');
            $this->assertNotEmpty($entry['summary_url'], 'The persisted summary URL should not be empty.');

            if (!empty($entry['job_id'])) {
                $this->assertNull(
                    TEJLG_Export::get_job($entry['job_id']),
                    'Completed scheduled jobs should be removed after persistence.'
                );
            }
        } finally {
            remove_filter('wp_mail', $mail_filter, 10);

            $uploads    = wp_upload_dir();
            $export_dir = isset($uploads['basedir']) ? trailingslashit((string) $uploads['basedir']) . 'theme-export-jlg' : '';

            if ('' !== $export_dir && is_dir($export_dir)) {
                $this->remove_theme_directory($export_dir);
            }

            TEJLG_Export::update_schedule_settings($original_settings);
            TEJLG_Export_History::clear_history();
        }
    }

    public function test_export_theme_includes_parent_theme_files() {
        $theme_root     = trailingslashit(get_theme_root());
        $parent_slug    = sanitize_title('tejlg-parent-' . wp_generate_password(6, false));
        $child_slug     = sanitize_title('tejlg-child-' . wp_generate_password(6, false));
        $parent_dir     = $theme_root . $parent_slug;
        $child_dir      = $theme_root . $child_slug;
        $job_id         = null;
        $previous_theme = get_stylesheet();

        wp_mkdir_p($parent_dir);
        file_put_contents(
            $parent_dir . '/style.css',
            "/*\nTheme Name: Temporary Parent\nVersion: 1.0\n*/"
        );
        file_put_contents($parent_dir . '/index.php', "<?php\n// Parent index\n");
        file_put_contents($parent_dir . '/parent-extra.php', "<?php\n// Parent extra\n");

        wp_mkdir_p($child_dir);
        file_put_contents(
            $child_dir . '/style.css',
            "/*\nTheme Name: Temporary Child\nTemplate: {$parent_slug}\nVersion: 1.0\n*/"
        );
        file_put_contents($child_dir . '/functions.php', "<?php\n// Child functions\n");
        file_put_contents($child_dir . '/child-extra.php', "<?php\n// Child extra\n");

        wp_clean_themes_cache();

        try {
            switch_theme($child_slug);

            $job_id = TEJLG_Export::export_theme();

            $this->assertNotWPError($job_id, 'Exporting the child theme should succeed.');

            TEJLG_Export::run_pending_export_jobs();

            $job = TEJLG_Export::get_export_job_status($job_id);

            $this->assertIsArray($job, 'Export job payload should be stored.');

            $zip_path = isset($job['zip_path']) ? (string) $job['zip_path'] : '';

            $this->assertNotEmpty($zip_path, 'Export should provide a ZIP path.');
            $this->assertFileExists($zip_path, 'Generated ZIP file should exist.');

            $entries = [];

            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();
                $this->assertSame(true, $zip->open($zip_path), 'ZIP archive should open successfully.');

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entries[] = $zip->getNameIndex($i);
                }

                $zip->close();
            } else {
                if (!class_exists('PclZip')) {
                    require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
                }

                $pclzip = new PclZip($zip_path);
                $list   = $pclzip->listContent();

                foreach ((array) $list as $entry) {
                    if (isset($entry['stored_filename'])) {
                        $entries[] = (string) $entry['stored_filename'];
                    } elseif (isset($entry['filename'])) {
                        $entries[] = (string) $entry['filename'];
                    }
                }
            }

            $this->assertContains($child_slug . '/child-extra.php', $entries, 'Child-specific files should be present.');
            $this->assertContains(
                'parent-theme/' . $parent_slug . '/parent-extra.php',
                $entries,
                'Parent theme files should be included in the archive.'
            );
        } finally {
            if (null !== $job_id) {
                TEJLG_Export::delete_job($job_id);
            }

            switch_theme($previous_theme);
            wp_clean_themes_cache();

            $this->remove_theme_directory($child_dir);
            $this->remove_theme_directory($parent_dir);
            wp_clean_themes_cache();
        }
    }

    private function remove_theme_directory($directory) {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($directory);
    }
}

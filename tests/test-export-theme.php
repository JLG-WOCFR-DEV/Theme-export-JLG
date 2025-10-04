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
}

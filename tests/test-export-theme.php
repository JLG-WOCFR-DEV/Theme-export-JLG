<?php

if (!defined('TEJLG_PATH')) {
    define('TEJLG_PATH', dirname(__DIR__) . '/theme-export-jlg/');
}

require_once TEJLG_PATH . 'includes/class-tejlg-theme-export-process.php';
require_once TEJLG_PATH . 'includes/class-tejlg-export.php';

/**
 * @group export-theme
 */
class Test_Export_Theme extends WP_UnitTestCase {

    public function test_export_theme_job_returns_status_after_completion() {
        $job_id = TEJLG_Export::export_theme([], ['dispatch' => false]);

        $this->assertIsString($job_id, 'Export should return a job identifier.');

        while (true) {
            $result = TEJLG_Export::process_theme_export_job($job_id);

            if (false === $result) {
                break;
            }
        }

        $status = TEJLG_Export::get_theme_export_status($job_id);

        $this->assertIsArray($status, 'Status data should be returned as an array.');
        $this->assertSame('completed', $status['status'], 'The job should report a completed status.');
        $this->assertTrue($status['downloadReady'], 'The job should be flagged as ready for download.');
        $this->assertNotEmpty($status['zipFilename'], 'The ZIP filename should be provided.');
        $this->assertGreaterThan(0, $status['zipSize'], 'The ZIP size should be greater than zero.');

        $job = TEJLG_Export::get_theme_export_job($job_id);

        $this->assertTrue(file_exists($job['zip_path']), 'The generated ZIP file should exist.');

        TEJLG_Export::cleanup_theme_export_job($job_id);
    }

    public function test_export_theme_job_fails_when_filesize_is_unavailable() {
        $captured_temp_path = null;

        $filesize_filter = static function ($size, $path) use (&$captured_temp_path) {
            $captured_temp_path = $path;
            return false;
        };

        add_filter('tejlg_export_zip_file_size', $filesize_filter, 10, 2);

        $job_id = TEJLG_Export::export_theme([], ['dispatch' => false]);

        $this->assertIsString($job_id, 'Export should return a job identifier.');

        while (true) {
            $result = TEJLG_Export::process_theme_export_job($job_id);

            if (false === $result) {
                break;
            }
        }

        $job = TEJLG_Export::get_theme_export_job($job_id);

        $this->assertNotNull($job, 'The job data should be available after processing.');
        $this->assertSame('error', $job['status'], 'The job should end in an error state.');
        $this->assertStringContainsString(
            "Impossible de déterminer la taille de l'archive ZIP à télécharger.",
            (string) $job['message']
        );
        $this->assertNotEmpty($captured_temp_path, 'The temporary ZIP path should be captured.');
        $this->assertFileDoesNotExist($captured_temp_path, 'The temporary ZIP file should be cleaned up on failure.');

        TEJLG_Export::cleanup_theme_export_job($job_id, false);

        remove_filter('tejlg_export_zip_file_size', $filesize_filter, 10);
    }
}

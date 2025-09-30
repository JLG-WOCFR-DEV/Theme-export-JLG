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
}

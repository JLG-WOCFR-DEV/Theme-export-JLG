<?php

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-theme-export-process.php';
require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export.php';

/**
 * @group export-theme
 */
class Test_Export_Theme extends WP_UnitTestCase {

    private $created_jobs = [];

    protected function setUp(): void {
        parent::setUp();
        TEJLG_Theme_Export_Process::register_hooks();
    }

    protected function tearDown(): void {
        foreach ($this->created_jobs as $job_id) {
            $job = TEJLG_Theme_Export_Process::get_job($job_id);

            if (is_array($job) && !empty($job['zip_path']) && file_exists($job['zip_path'])) {
                TEJLG_Export::delete_temp_file($job['zip_path']);
            }

            TEJLG_Theme_Export_Process::delete_job($job_id);
        }

        $this->created_jobs = [];

        parent::tearDown();
    }

    public function test_export_theme_creates_background_job() {
        $job_id = TEJLG_Export::export_theme();
        $this->created_jobs[] = $job_id;

        $this->assertIsString($job_id, 'The export should return a job identifier.');

        $job = TEJLG_Theme_Export_Process::get_job($job_id);
        $this->assertIsArray($job, 'The job payload should be stored.');
        $this->assertSame('queued', $job['status']);

        while (TEJLG_Theme_Export_Process::maybe_process_now($job_id)) {
            // Process all batches synchronously for the test environment.
        }

        $job = TEJLG_Theme_Export_Process::get_job($job_id);
        $this->assertSame('completed', $job['status']);
        $this->assertArrayHasKey('file_size', $job);
        $this->assertNotEmpty($job['zip_path']);
        $this->assertFileExists($job['zip_path']);
    }

    public function test_export_theme_marks_job_failed_when_filesize_is_unavailable() {
        $captured_path = null;

        $filesize_filter = static function ($size, $path) use (&$captured_path) {
            $captured_path = $path;
            return false;
        };

        add_filter('tejlg_export_zip_file_size', $filesize_filter, 10, 2);

        $job_id = TEJLG_Export::export_theme();
        $this->created_jobs[] = $job_id;

        $this->assertIsString($job_id);

        while (TEJLG_Theme_Export_Process::maybe_process_now($job_id)) {
            // Iterate over the job until it resolves.
        }

        $job = TEJLG_Theme_Export_Process::get_job($job_id);

        $this->assertIsArray($job);
        $this->assertSame('failed', $job['status']);
        $this->assertStringContainsString(
            "Impossible de déterminer la taille de l'archive ZIP à télécharger.",
            $job['message']
        );
        $this->assertNotEmpty($captured_path);
        $this->assertFileDoesNotExist($captured_path);

        remove_filter('tejlg_export_zip_file_size', $filesize_filter, 10);
    }
}

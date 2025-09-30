<?php

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export.php';

class TEJLG_WPDie_Exception extends Exception {

    private $response;

    public function __construct($response) {
        parent::__construct('WP Die intercepted.');
        $this->response = $response;
    }

    public function get_response() {
        return $this->response;
    }
}

/**
 * @group export-theme
 */
class Test_Export_Theme extends WP_UnitTestCase {

    protected function capture_ajax_response(callable $callback) {
        add_filter('wp_die_handler', [$this, 'filter_wp_die_handler']);

        try {
            $callback();
        } catch (TEJLG_WPDie_Exception $exception) {
            return $exception->get_response();
        } finally {
            remove_filter('wp_die_handler', [$this, 'filter_wp_die_handler']);
        }

        $this->fail('The AJAX handler did not terminate execution as expected.');
    }

    public function filter_wp_die_handler() {
        return [$this, 'handle_wp_die'];
    }

    public function handle_wp_die($response) {
        throw new TEJLG_WPDie_Exception($response);
    }

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

    public function test_ajax_start_theme_export_processes_jobs_without_wp_cron() {
        $admin_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($admin_id);

        $immediate_filter = static function () {
            return false;
        };

        add_filter('tejlg_export_run_jobs_immediately', $immediate_filter, 10, 1);

        if (!defined('DISABLE_WP_CRON')) {
            define('DISABLE_WP_CRON', true);
        } elseif (!DISABLE_WP_CRON) {
            remove_filter('tejlg_export_run_jobs_immediately', $immediate_filter, 10);
            wp_set_current_user(0);
            $this->markTestSkipped('DISABLE_WP_CRON is already defined and set to false.');
        }

        $previous_post    = $_POST;
        $previous_request = $_REQUEST;
        $response         = '';

        try {
            $_POST    = [
                'nonce'      => wp_create_nonce('tejlg_start_theme_export'),
                'exclusions' => '',
            ];
            $_REQUEST = $_POST;

            $response = $this->capture_ajax_response(static function () {
                TEJLG_Export::ajax_start_theme_export();
            });
        } finally {
            remove_filter('tejlg_export_run_jobs_immediately', $immediate_filter, 10);
            $_POST    = $previous_post;
            $_REQUEST = $previous_request;
        }

        $decoded = json_decode($response, true);
        $this->assertIsArray($decoded, 'The AJAX response should be valid JSON.');
        $this->assertArrayHasKey('success', $decoded, 'The response should include the success flag.');
        $this->assertTrue((bool) $decoded['success'], 'The AJAX handler should return a success payload.');
        $this->assertArrayHasKey('data', $decoded, 'The response should include data.');

        $data = $decoded['data'];
        $this->assertIsArray($data, 'The data payload should be an array.');
        $this->assertArrayHasKey('job_id', $data, 'The job identifier should be present.');

        $job_id = $data['job_id'];
        $this->assertIsString($job_id, 'The job identifier should be a string.');
        $this->assertNotEmpty($job_id, 'The job identifier should not be empty.');

        $this->assertArrayHasKey('job', $data, 'The job payload should be included in the response.');
        $this->assertIsArray($data['job'], 'The job payload should be an array.');
        $this->assertSame('completed', $data['job']['status'], 'The export should complete synchronously when WP-Cron is disabled.');

        $job = TEJLG_Export::get_export_job_status($job_id);
        $this->assertIsArray($job, 'The job should still be persisted after the AJAX call.');
        $this->assertSame('completed', $job['status'], 'The job should be marked as completed.');

        TEJLG_Export::delete_job($job_id);
        wp_set_current_user(0);
    }

    public function test_ajax_get_theme_export_status_retries_stalled_job_without_wp_cron() {
        $admin_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($admin_id);

        $immediate_filter = static function () {
            return false;
        };

        add_filter('tejlg_export_run_jobs_immediately', $immediate_filter, 10, 1);

        if (!defined('DISABLE_WP_CRON')) {
            define('DISABLE_WP_CRON', true);
        } elseif (!DISABLE_WP_CRON) {
            remove_filter('tejlg_export_run_jobs_immediately', $immediate_filter, 10);
            wp_set_current_user(0);
            $this->markTestSkipped('DISABLE_WP_CRON is already defined and set to false.');
        }

        $job_id = TEJLG_Export::export_theme();

        remove_filter('tejlg_export_run_jobs_immediately', $immediate_filter, 10);

        $this->assertNotWPError($job_id, 'The job should be created successfully.');

        $job = TEJLG_Export::get_export_job_status($job_id);
        $this->assertIsArray($job, 'The job payload should be available.');

        $threshold = (defined('MINUTE_IN_SECONDS') ? MINUTE_IN_SECONDS : 60) + 5;
        $job['updated_at'] = time() - $threshold;
        TEJLG_Export::persist_job($job);

        $process = new TEJLG_Export_Process();
        wp_clear_scheduled_hook($process->get_cron_hook_identifier());

        $previous_request = $_REQUEST;
        $response         = '';

        try {
            $_REQUEST = [
                'nonce'  => wp_create_nonce('tejlg_theme_export_status'),
                'job_id' => $job_id,
            ];

            $response = $this->capture_ajax_response(static function () {
                TEJLG_Export::ajax_get_theme_export_status();
            });
        } finally {
            $_REQUEST = $previous_request;
        }

        $decoded = json_decode($response, true);
        $this->assertIsArray($decoded, 'The AJAX response should be valid JSON.');
        $this->assertTrue((bool) $decoded['success'], 'The AJAX handler should indicate success.');
        $this->assertArrayHasKey('data', $decoded, 'The response should include data.');
        $this->assertArrayHasKey('job', $decoded['data'], 'The job payload should be provided.');

        $job_data = $decoded['data']['job'];
        $this->assertIsArray($job_data, 'The job data should be an array.');
        $this->assertSame('completed', $job_data['status'], 'The stalled job should be completed synchronously.');
        $this->assertSame($job_id, $job_data['id'], 'The job identifier should match the requested job.');

        TEJLG_Export::delete_job($job_id);
        wp_set_current_user(0);
    }
}

<?php

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export.php';

class Tejlg_WP_Die_Exception extends Exception {

    public $payload;

    public function __construct($payload) {
        $this->payload = $payload;
        parent::__construct('Intercepted wp_die');
    }
}

/**
 * @group export-theme
 */
class Test_Export_Theme extends WP_UnitTestCase {

    private function capture_wp_die_handler() {
        $handler = static function ($message) {
            throw new Tejlg_WP_Die_Exception($message);
        };

        $filter = static function () use ($handler) {
            return $handler;
        };

        add_filter('wp_die_handler', $filter, 10, 0);

        return $filter;
    }

    private function remove_wp_die_handler($filter) {
        if (null !== $filter) {
            remove_filter('wp_die_handler', $filter, 10);
        }
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

    public function test_export_theme_runs_immediately_when_cron_event_cannot_be_scheduled() {
        $hook = 'wp_background_process_tejlg_theme_export_cron';

        $immediate_filter = static function () {
            return false;
        };

        add_filter('tejlg_export_run_jobs_immediately', $immediate_filter, 10, 3);

        $pre_schedule_filter = static function ($pre, $timestamp, $hook_name) use ($hook) {
            if ($hook_name === $hook) {
                return false;
            }

            return $pre;
        };

        add_filter('pre_schedule_event', $pre_schedule_filter, 10, 4);

        $job_id = null;

        try {
            $job_id = TEJLG_Export::export_theme();
        } finally {
            remove_filter('pre_schedule_event', $pre_schedule_filter, 10);
            remove_filter('tejlg_export_run_jobs_immediately', $immediate_filter, 10);
        }

        $this->assertNotWPError($job_id, 'The export job should be created successfully when scheduling fails.');

        $job = TEJLG_Export::get_export_job_status($job_id);

        $this->assertIsArray($job, 'The export job payload should be accessible.');
        $this->assertSame('completed', isset($job['status']) ? $job['status'] : null, 'The export should complete immediately when scheduling fails.');

        if (!empty($job_id)) {
            TEJLG_Export::delete_job($job_id);
        }

        delete_option('wp_background_process_tejlg_theme_export_queue');
    }

    public function test_ajax_get_theme_export_status_retries_when_queue_is_stalled() {
        $user_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);

        $hook = 'wp_background_process_tejlg_theme_export_cron';

        $immediate_filter = static function () {
            return false;
        };

        add_filter('tejlg_export_run_jobs_immediately', $immediate_filter, 10, 3);

        $job_id  = null;
        $payload = null;
        $filter  = null;

        try {
            $job_id = TEJLG_Export::export_theme();

            $this->assertNotWPError($job_id, 'The export job should be created successfully.');

            // Remove the scheduled event to simulate an environment without WP-Cron execution.
            wp_clear_scheduled_hook($hook);

            $job = TEJLG_Export::get_job($job_id);

            $this->assertIsArray($job, 'The export job payload should be stored before retrying.');

            $job['updated_at'] = time() - 60;
            TEJLG_Export::persist_job($job);

            $process = new TEJLG_Export_Process();
            $process->unlock();
            remove_action('shutdown', [$process, 'dispatch']);
            remove_action($hook, [$process, 'handle_cron_healthcheck']);

            $this->assertFalse($process->is_locked(), 'The process lock should be cleared before retrying.');

            $nonce = wp_create_nonce('tejlg_theme_export_status');

            $_REQUEST['nonce']  = $nonce;
            $_REQUEST['job_id'] = $job_id;

            $filter = $this->capture_wp_die_handler();

            try {
                TEJLG_Export::ajax_get_theme_export_status();
            } catch (Tejlg_WP_Die_Exception $exception) {
                $payload = $exception->payload;
            } finally {
                $this->remove_wp_die_handler($filter);
                unset($_REQUEST['nonce'], $_REQUEST['job_id']);
            }
        } finally {
            remove_filter('tejlg_export_run_jobs_immediately', $immediate_filter, 10);
            wp_set_current_user(0);
        }

        $this->assertNotNull($payload, 'The AJAX handler should terminate using wp_die.');

        $response = json_decode($payload, true);

        $this->assertIsArray($response, 'The AJAX response should be valid JSON.');
        $this->assertArrayHasKey('success', $response, 'The response should contain the success flag.');
        $this->assertTrue($response['success'], 'The response should indicate success.');
        $this->assertArrayHasKey('data', $response, 'The response should include data payload.');
        $this->assertArrayHasKey('job', $response['data'], 'The response data should include the job.');
        $this->assertSame('completed', $response['data']['job']['status'], 'The job should be completed after retrying.');

        $job = TEJLG_Export::get_job($job_id);

        $this->assertIsArray($job, 'The job should still be retrievable after retrying.');
        $this->assertSame('completed', isset($job['status']) ? $job['status'] : null, 'The job status should be completed after retrying.');

        if (!empty($job_id)) {
            TEJLG_Export::delete_job($job_id);
        }

        delete_option('wp_background_process_tejlg_theme_export_queue');
    }
}

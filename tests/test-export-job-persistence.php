<?php

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export.php';

/**
 * @group export-theme
 */
class Test_Export_Job_Persistence extends WP_Ajax_UnitTestCase {

    public function set_up() {
        parent::set_up();
        wp_set_current_user(0);
    }

    public function tear_down() {
        wp_set_current_user(0);
        parent::tear_down();
    }

    public function test_job_reference_persisted_and_cleared_after_status_lookup() {
        $user_id = self::factory()->user->create([
            'role' => 'administrator',
        ]);

        wp_set_current_user($user_id);

        $job_id = TEJLG_Export::export_theme();

        $this->assertNotWPError($job_id, 'The export job should be created successfully.');
        $this->assertIsString($job_id, 'The job identifier should be a string.');
        $this->assertSame($job_id, TEJLG_Export::get_user_job_reference($user_id), 'The job ID should be stored for the current user.');

        $job = TEJLG_Export::get_job($job_id);

        $this->assertIsArray($job, 'The job payload should be available.');

        $job['status'] = 'completed';
        $job['progress'] = 100;
        $job['processed_items'] = isset($job['total_items']) ? (int) $job['total_items'] : 0;
        TEJLG_Export::persist_job($job);

        $_POST = [
            'nonce'  => wp_create_nonce('tejlg_theme_export_status'),
            'job_id' => $job_id,
        ];
        $_REQUEST = $_POST;

        try {
            $this->_handleAjax('tejlg_theme_export_status');
        } catch (WPAjaxDieContinueException $exception) {
            // Expected behaviour for AJAX handlers in tests.
        }

        $this->assertNotEmpty($this->_last_response, 'The AJAX handler should return a payload.');

        $response = json_decode($this->_last_response, true);

        $this->assertIsArray($response, 'The JSON response should decode to an array.');
        $this->assertArrayHasKey('success', $response, 'The response should contain a success flag.');
        $this->assertTrue($response['success'], 'The handler should return a success response.');
        $this->assertSame('completed', $response['data']['job']['status'], 'The job status should be completed.');

        $this->assertSame('', TEJLG_Export::get_user_job_reference($user_id), 'The stored job ID should be removed after completion.');

        TEJLG_Export::delete_job($job_id);

        $failed_job_id = TEJLG_Export::export_theme();

        $this->assertNotWPError($failed_job_id, 'The second export job should also be created.');
        $this->assertSame($failed_job_id, TEJLG_Export::get_user_job_reference($user_id), 'The new job ID should be stored.');

        $failed_job = TEJLG_Export::get_job($failed_job_id);
        $this->assertIsArray($failed_job, 'The failed job payload should be available.');

        $failed_job['status'] = 'failed';
        $failed_job['message'] = 'Failure during test.';
        TEJLG_Export::persist_job($failed_job);

        $_POST = [
            'nonce'  => wp_create_nonce('tejlg_theme_export_status'),
            'job_id' => $failed_job_id,
        ];
        $_REQUEST = $_POST;

        try {
            $this->_handleAjax('tejlg_theme_export_status');
        } catch (WPAjaxDieContinueException $exception) {
            // Expected behaviour for AJAX handlers in tests.
        }

        $this->assertNotEmpty($this->_last_response, 'The AJAX handler should return a payload for failed jobs.');

        $failed_response = json_decode($this->_last_response, true);

        $this->assertIsArray($failed_response, 'The failed job response should decode to an array.');
        $this->assertArrayHasKey('success', $failed_response, 'The failed response should contain a success flag.');
        $this->assertTrue($failed_response['success'], 'The handler should return a success response even when the job failed.');
        $this->assertSame('failed', $failed_response['data']['job']['status'], 'The job status should be failed.');

        $this->assertSame('', TEJLG_Export::get_user_job_reference($user_id), 'The stored job ID should be removed after a failure.');

        TEJLG_Export::delete_job($failed_job_id);

        wp_set_current_user(0);
    }
}

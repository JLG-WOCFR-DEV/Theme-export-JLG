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

    public function test_cleanup_marks_stale_processing_jobs_as_failed_and_releases_resources() {
        $user_id = self::factory()->user->create([
            'role' => 'administrator',
        ]);

        wp_set_current_user($user_id);

        $job_id    = sanitize_key('stale_job_' . wp_generate_uuid4());
        $temp_file = wp_tempnam('tejlg-export-stale');

        $this->assertIsString($temp_file, 'Temporary file path should be created.');
        $this->assertNotEmpty($temp_file, 'Temporary file path should not be empty.');

        file_put_contents($temp_file, 'incomplete data');

        $this->assertFileExists($temp_file, 'Temporary export file should exist before cleanup.');

        update_user_meta($user_id, '_tejlg_last_theme_export_job_id', $job_id);

        $stale_timestamp = time() - (2 * HOUR_IN_SECONDS);

        $job_payload = [
            'id'              => $job_id,
            'status'          => 'processing',
            'progress'        => 10,
            'processed_items' => 1,
            'total_items'     => 10,
            'zip_path'        => $temp_file,
            'zip_file_name'   => basename($temp_file),
            'created_at'      => time() - DAY_IN_SECONDS,
            'updated_at'      => $stale_timestamp,
            'created_by'      => $user_id,
        ];

        update_option('tejlg_export_job_' . $job_id, $job_payload, false);

        $queue_option = 'wp_background_process_tejlg_theme_export_queue';

        update_option($queue_option, [
            [
                [
                    'job_id'               => $job_id,
                    'type'                 => 'file',
                    'real_path'            => $temp_file,
                    'relative_path_in_zip' => 'stale/file.txt',
                ],
            ],
        ], false);

        TEJLG_Export::cleanup_stale_jobs(HOUR_IN_SECONDS);

        $job_after_cleanup = TEJLG_Export::get_job($job_id);

        $this->assertIsArray($job_after_cleanup, 'The stale job should still be stored after being marked as failed.');
        $this->assertSame('failed', $job_after_cleanup['status'], 'Stale jobs should be marked as failed.');
        $this->assertSame(
            'timeout',
            isset($job_after_cleanup['failure_code']) ? $job_after_cleanup['failure_code'] : '',
            'The failure code should indicate a timeout.'
        );
        $this->assertStringContainsString(
            'inactive',
            isset($job_after_cleanup['message']) ? strtolower($job_after_cleanup['message']) : '',
            'The failure message should mention inactivity.'
        );

        $this->assertFalse(file_exists($temp_file), 'Cleanup should delete the stale temporary file.');

        $queue_after_cleanup = get_option($queue_option, []);

        if (is_array($queue_after_cleanup)) {
            foreach ($queue_after_cleanup as $batch) {
                if (!is_array($batch)) {
                    continue;
                }

                foreach ($batch as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $this->assertNotSame(
                        $job_id,
                        isset($item['job_id']) ? $item['job_id'] : '',
                        'The background queue should no longer contain items for the stale job.'
                    );
                }
            }
        }

        $this->assertSame(
            '',
            get_user_meta($user_id, '_tejlg_last_theme_export_job_id', true),
            'The stored job reference should be cleared for the job owner.'
        );

        TEJLG_Export::delete_job($job_id);

        wp_set_current_user(0);
    }
}

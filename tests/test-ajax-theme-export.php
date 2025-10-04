<?php

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export.php';

/**
 * @group ajax
 */
class Test_Ajax_Theme_Export extends WP_Ajax_UnitTestCase {

    public function set_up() {
        parent::set_up();
        wp_set_current_user(0);
    }

    public function tear_down() {
        wp_set_current_user(0);
        parent::tear_down();
    }

    public function test_start_theme_export_sanitizes_and_preserves_quoted_patterns() {
        $user_id = self::factory()->user->create([
            'role' => 'administrator',
        ]);

        wp_set_current_user($user_id);

        $raw_exclusions = "  \"first\"\nsecond,\r\n\"third\"\r\n,, \"fourth\"  ";

        $original_post = $_POST;

        $_POST = [
            'nonce'      => wp_create_nonce('tejlg_start_theme_export'),
            'exclusions' => $raw_exclusions,
        ];

        try {
            $this->_handleAjax('tejlg_start_theme_export');
        } catch (WPAjaxDieContinueException $exception) {
            // Expected behaviour for AJAX handlers in tests.
        }

        $this->assertNotEmpty($this->_last_response, 'The AJAX handler should produce a response payload.');

        $response = json_decode($this->_last_response, true);

        $this->assertIsArray($response, 'The response should decode to an array.');
        $this->assertArrayHasKey('success', $response, 'The response should contain a success flag.');
        $this->assertTrue($response['success'], 'The AJAX request should be successful.');
        $this->assertArrayHasKey('data', $response, 'The response should contain a data payload.');
        $this->assertArrayHasKey('job_id', $response['data'], 'The data payload should include the job identifier.');

        $job_id = $response['data']['job_id'];

        $this->assertNotEmpty($job_id, 'The job identifier should not be empty.');

        $job = TEJLG_Export::get_job($job_id);

        $this->assertIsArray($job, 'The job metadata should be stored.');
        $this->assertArrayHasKey('exclusions', $job, 'The job metadata should include the exclusions array.');

        $expected_patterns = ['"first"', 'second', '"third"', '"fourth"'];

        $this->assertSame($expected_patterns, $job['exclusions'], 'The exclusions should preserve quoted patterns and omit empty values.');

        foreach ($job['exclusions'] as $pattern) {
            $this->assertNotSame('', $pattern, 'The exclusions list should not contain empty strings.');
        }

        TEJLG_Export::delete_job($job_id);

        $_POST = $original_post;
    }

    public function test_cancel_theme_export_cancels_job_and_cleans_resources() {
        $user_id = self::factory()->user->create([
            'role' => 'administrator',
        ]);

        wp_set_current_user($user_id);

        $job_id = 'tejlg_test_cancel_job';
        $temp_zip = tempnam(sys_get_temp_dir(), 'tejlg');
        file_put_contents($temp_zip, 'temporary archive');

        $job = [
            'id'                => $job_id,
            'status'            => 'processing',
            'progress'          => 45,
            'processed_items'   => 9,
            'total_items'       => 20,
            'zip_path'          => $temp_zip,
            'zip_file_name'     => 'cancel-test.zip',
            'directories_added' => [],
            'exclusions'        => [],
            'created_at'        => time() - 60,
            'updated_at'        => time() - 30,
        ];

        TEJLG_Export::persist_job($job);

        update_user_meta($user_id, '_tejlg_last_theme_export_job_id', $job_id);

        $queue_option_name = 'wp_background_process_tejlg_theme_export_queue';
        $other_job_id      = 'tejlg_other_job';

        update_option(
            $queue_option_name,
            [
                [
                    [
                        'job_id'               => $job_id,
                        'type'                 => 'file',
                        'real_path'            => __FILE__,
                        'relative_path_in_zip' => 'theme/file.php',
                    ],
                    [
                        'job_id'               => $other_job_id,
                        'type'                 => 'file',
                        'real_path'            => __FILE__,
                        'relative_path_in_zip' => 'other/file.php',
                    ],
                ],
                [
                    [
                        'job_id'               => $job_id,
                        'type'                 => 'dir',
                        'real_path'            => dirname(__FILE__),
                        'relative_path_in_zip' => 'theme/',
                    ],
                ],
            ],
            false
        );

        $original_post = $_POST;

        $_POST = [
            'nonce'  => wp_create_nonce('tejlg_cancel_theme_export'),
            'job_id' => $job_id,
        ];

        try {
            $this->_handleAjax('tejlg_cancel_theme_export');
        } catch (WPAjaxDieContinueException $exception) {
            // Expected behaviour for AJAX handlers in tests.
        }

        $this->assertNotEmpty($this->_last_response, 'The AJAX handler should return a payload.');

        $response = json_decode($this->_last_response, true);

        $this->assertIsArray($response, 'The response should decode to an array.');
        $this->assertArrayHasKey('success', $response, 'The response should contain a success flag.');
        $this->assertTrue($response['success'], 'The AJAX request should be successful.');
        $this->assertArrayHasKey('data', $response, 'The response should contain a data payload.');
        $this->assertArrayHasKey('job', $response['data'], 'The response data should include the job payload.');

        $job_response = $response['data']['job'];

        $this->assertSame('cancelled', $job_response['status'], 'The response job status should be cancelled.');
        $this->assertSame(0, $job_response['progress'], 'The response job progress should be reset.');

        $stored_job = TEJLG_Export::get_job($job_id);

        $this->assertIsArray($stored_job, 'The cancelled job should remain stored.');
        $this->assertSame('cancelled', $stored_job['status'], 'The stored job status should be cancelled.');
        $this->assertSame(0, $stored_job['progress'], 'The stored job progress should be reset.');
        $this->assertSame(0, $stored_job['processed_items'], 'The processed items count should be reset.');
        $this->assertArrayHasKey('cancelled_at', $stored_job, 'The cancelled job should include a cancellation timestamp.');
        $this->assertEmpty($stored_job['zip_path'], 'The temporary ZIP path should be cleared after cancellation.');
        $this->assertSame(0, $stored_job['zip_file_size'], 'The ZIP file size should be reset after cancellation.');

        $this->assertFalse(file_exists($temp_zip), 'The temporary ZIP file should be deleted.');

        $queue = get_option($queue_option_name, []);

        $this->assertIsArray($queue, 'The queue option should remain an array.');

        $remaining_items = [];

        foreach ($queue as $batch) {
            if (!is_array($batch)) {
                continue;
            }
            foreach ($batch as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $remaining_items[] = $item;
            }
        }

        $this->assertNotEmpty($remaining_items, 'Other jobs should remain in the queue.');

        foreach ($remaining_items as $item) {
            if (isset($item['job_id'])) {
                $this->assertNotSame($job_id, sanitize_key((string) $item['job_id']), 'The cancelled job should be removed from the queue.');
            }
        }

        $other_job_found = false;
        foreach ($remaining_items as $item) {
            if (isset($item['job_id']) && sanitize_key((string) $item['job_id']) === sanitize_key($other_job_id)) {
                $other_job_found = true;
                break;
            }
        }

        $this->assertTrue($other_job_found, 'Unrelated jobs should remain queued.');

        $user_meta = get_user_meta($user_id, '_tejlg_last_theme_export_job_id', true);
        $this->assertEmpty($user_meta, 'The user job reference should be cleared.');

        TEJLG_Export::delete_job($job_id);
        delete_option($queue_option_name);

        $_POST = $original_post;
    }
}

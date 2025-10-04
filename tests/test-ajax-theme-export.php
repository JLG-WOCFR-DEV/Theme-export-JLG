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

    public function test_cancel_theme_export_cleans_job_and_queue() {
        $user_id = self::factory()->user->create([
            'role' => 'administrator',
        ]);

        wp_set_current_user($user_id);

        $job_id   = 'tejlg_test_job';
        $temp_zip = wp_tempnam('tejlg-cancel-test');

        $this->assertNotFalse($temp_zip, 'Le fichier temporaire doit être créé.');

        file_put_contents($temp_zip, 'export');

        $job = [
            'id'              => $job_id,
            'status'          => 'processing',
            'progress'        => 50,
            'processed_items' => 5,
            'total_items'     => 10,
            'zip_path'        => $temp_zip,
            'zip_file_name'   => 'test.zip',
            'directories_added' => [],
            'created_at'      => time() - 10,
            'updated_at'      => time() - 5,
        ];

        TEJLG_Export::persist_job($job);

        update_user_meta($user_id, '_tejlg_last_theme_export_job_id', $job_id);

        $queue_option = 'wp_background_process_tejlg_theme_export_queue';

        update_option($queue_option, [
            [
                [
                    'job_id'               => $job_id,
                    'type'                 => 'file',
                    'real_path'            => $temp_zip,
                    'relative_path_in_zip' => 'theme/file.php',
                ],
            ],
        ], false);

        wp_schedule_single_event(time() + 60, 'wp_background_process_tejlg_theme_export_cron');
        set_transient('wp_background_process_tejlg_theme_export_process_lock', microtime(), MINUTE_IN_SECONDS);

        $original_post = $_POST;

        $_POST = [
            'nonce'  => wp_create_nonce('tejlg_cancel_theme_export'),
            'job_id' => $job_id,
        ];

        try {
            $this->_handleAjax('tejlg_cancel_theme_export');
        } catch (WPAjaxDieContinueException $exception) {
            // Attendu pour les appels AJAX en test.
        }

        $_POST = $original_post;

        $this->assertNotEmpty($this->_last_response, 'La réponse AJAX ne doit pas être vide.');

        $response = json_decode($this->_last_response, true);

        $this->assertIsArray($response, 'La réponse doit être un tableau.');
        $this->assertArrayHasKey('success', $response);
        $this->assertTrue($response['success'], 'La requête d\'annulation doit réussir.');

        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('job', $response['data']);

        $job_payload = $response['data']['job'];
        $this->assertIsArray($job_payload, 'Le payload du job doit être un tableau.');
        $this->assertSame('cancelled', $job_payload['status'], 'Le statut retourné doit indiquer une annulation.');

        $stored_job = TEJLG_Export::get_job($job_id);
        $this->assertIsArray($stored_job, 'La tâche doit rester persistée.');
        $this->assertSame('cancelled', $stored_job['status'], 'Le statut de la tâche doit être « cancelled ».');
        $this->assertSame(0, $stored_job['progress'], 'La progression doit être réinitialisée.');
        $this->assertSame(
            esc_html__('Export annulé.', 'theme-export-jlg'),
            $stored_job['message'],
            'Le message du job doit indiquer une annulation.'
        );

        $this->assertFalse(file_exists($temp_zip), 'Le fichier temporaire doit être supprimé.');

        $this->assertEmpty(get_option($queue_option, []), 'La file doit être vidée.');

        $this->assertFalse(
            wp_next_scheduled('wp_background_process_tejlg_theme_export_cron'),
            'L\'évènement planifié doit être déprogrammé.'
        );

        $this->assertFalse(
            get_transient('wp_background_process_tejlg_theme_export_process_lock'),
            'Le verrou du processus doit être supprimé.'
        );

        $this->assertSame(
            '',
            get_user_meta($user_id, '_tejlg_last_theme_export_job_id', true),
            'La référence utilisateur doit être nettoyée.'
        );

        TEJLG_Export::delete_job($job_id);
    }
}

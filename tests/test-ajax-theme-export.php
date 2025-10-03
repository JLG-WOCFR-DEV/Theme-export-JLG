<?php

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-admin-export-page.php';
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
        $selected_presets = ['node_modules', 'temporary_files'];

        $original_post = $_POST;

        $_POST = [
            'nonce'      => wp_create_nonce('tejlg_start_theme_export'),
            'tejlg_exclusion_presets' => $selected_presets,
            'tejlg_exclusion_custom'  => $raw_exclusions,
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

        $expected_patterns = TEJLG_Admin_Export_Page::build_exclusion_list([
            'presets' => $selected_presets,
            'custom'  => wp_unslash($raw_exclusions),
        ]);

        $this->assertSame($expected_patterns, $job['exclusions'], 'The exclusions should preserve quoted patterns and append preset patterns.');

        foreach ($job['exclusions'] as $pattern) {
            $this->assertNotSame('', $pattern, 'The exclusions list should not contain empty strings.');
        }

        $stored_preferences = get_option(TEJLG_Admin_Export_Page::EXCLUSION_PATTERNS_OPTION);

        $this->assertIsArray($stored_preferences, 'The exclusion preferences should be stored as an array.');
        $this->assertSame(
            [
                'presets' => $selected_presets,
                'custom'  => wp_unslash($raw_exclusions),
            ],
            TEJLG_Admin_Export_Page::normalize_exclusion_option($stored_preferences),
            'The saved preferences should match the submitted data.'
        );

        TEJLG_Export::delete_job($job_id);

        $_POST = $original_post;
    }
}

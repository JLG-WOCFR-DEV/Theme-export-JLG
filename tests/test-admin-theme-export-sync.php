<?php

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-admin.php';
require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export.php';

/**
 * @group admin
 */
class Test_Admin_Theme_Export_Sync extends WP_UnitTestCase {

    public function test_theme_export_form_submission_streams_zip_when_nonce_is_valid() {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $template_dir = dirname(__DIR__) . '/theme-export-jlg/templates/admin/';
        $export_page  = new TEJLG_Admin_Export_Page($template_dir, 'theme-export-jlg');

        $original_post   = $_POST;
        $original_method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null;

        $_POST['tejlg_theme_export_nonce']   = wp_create_nonce('tejlg_theme_export_action');
        $_POST['tejlg_exclusion_patterns']   = '';
        $_SERVER['REQUEST_METHOD']           = 'POST';

        $captured = null;

        add_filter(
            'tejlg_export_stream_zip_archive',
            static function ($should_stream, $zip_path, $zip_file_name, $zip_file_size) use (&$captured) {
                $captured = [
                    'path'     => $zip_path,
                    'filename' => $zip_file_name,
                    'size'     => $zip_file_size,
                ];

                return false;
            },
            10,
            4
        );

        if (!defined('TEJLG_BYPASS_REDIRECT_EXIT')) {
            define('TEJLG_BYPASS_REDIRECT_EXIT', true);
        }

        try {
            $export_page->handle_theme_export_form_submission();
            $this->fail('Expected a redirect after synchronous export handling.');
        } catch (TEJLG_Redirect_Exception $exception) {
            $redirect_url = $exception->get_redirect_url();

            $this->assertNotEmpty($redirect_url);
            $this->assertStringContainsString('admin.php', $redirect_url);
            $this->assertStringContainsString('tab=export', $redirect_url);
        }

        remove_all_filters('tejlg_export_stream_zip_archive');

        $_POST = $original_post;

        if (null === $original_method) {
            unset($_SERVER['REQUEST_METHOD']);
        } else {
            $_SERVER['REQUEST_METHOD'] = $original_method;
        }

        $job_id = TEJLG_Export::get_user_job_reference();

        $this->assertNotEmpty($job_id, 'Expected the job id to be stored for the current user.');

        $job = TEJLG_Export::get_job($job_id);

        $this->assertIsArray($job);
        $this->assertSame('completed', isset($job['status']) ? $job['status'] : '', 'Expected the export job to be completed.');

        $zip_path = isset($job['zip_path']) ? $job['zip_path'] : '';
        $zip_file_name = isset($job['zip_file_name']) ? $job['zip_file_name'] : '';
        $zip_file_size = isset($job['zip_file_size']) ? (int) $job['zip_file_size'] : 0;

        $this->assertNotEmpty($zip_path);
        $this->assertNotEmpty($zip_file_name);
        $this->assertGreaterThan(0, $zip_file_size);
        $this->assertFileExists($zip_path);

        $this->assertIsArray($captured);
        $this->assertSame($captured['path'], $zip_path);
        $this->assertSame($captured['filename'], $zip_file_name);
        $this->assertSame($captured['size'], $zip_file_size);

        $notices = get_transient('settings_errors');

        $this->assertIsArray($notices);
        $this->assertNotEmpty($notices);

        $notice = $notices[0];

        $this->assertSame('tejlg_admin_messages', isset($notice['setting']) ? $notice['setting'] : '');
        $this->assertSame('success', isset($notice['type']) ? $notice['type'] : '');
        $this->assertStringContainsString(basename($zip_path), isset($notice['message']) ? $notice['message'] : '');

        TEJLG_Export::delete_job($job_id);

        delete_transient('settings_errors');
    }
}

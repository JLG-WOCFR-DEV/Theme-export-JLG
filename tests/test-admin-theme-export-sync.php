<?php

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-admin.php';
require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-admin-export-page.php';
require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export.php';

/**
 * @group admin
 */
class Test_Admin_Theme_Export_Sync extends WP_UnitTestCase {

    public function test_theme_export_form_submission_streams_zip_when_nonce_is_valid() {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));

        $export_page = new TEJLG_Admin_Export_Page();

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

        $result = $export_page->handle_theme_export_form_submission();

        remove_all_filters('tejlg_export_stream_zip_archive');

        $_POST = $original_post;

        if (null === $original_method) {
            unset($_SERVER['REQUEST_METHOD']);
        } else {
            $_SERVER['REQUEST_METHOD'] = $original_method;
        }

        $this->assertIsArray($result, 'Expected the handler to return zip metadata when streaming is disabled.');

        $this->assertArrayHasKey('job_id', $result);
        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('filename', $result);
        $this->assertArrayHasKey('size', $result);

        $this->assertNotEmpty($result['job_id']);
        $this->assertNotEmpty($result['filename']);
        $this->assertGreaterThan(0, $result['size']);
        $this->assertFileExists($result['path']);

        $this->assertIsArray($captured);
        $this->assertSame($captured['path'], $result['path']);
        $this->assertSame($captured['filename'], $result['filename']);
        $this->assertSame($captured['size'], $result['size']);

        TEJLG_Export::delete_job($result['job_id']);
    }
}

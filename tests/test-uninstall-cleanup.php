<?php

/**
 * @group uninstall
 */
class Test_Uninstall_Cleanup extends WP_UnitTestCase {

    public function test_uninstall_removes_job_zip_and_pattern_files() {
        $zip_file = wp_tempnam('tejlg-uninstall-job');
        $this->assertNotFalse($zip_file, 'Temporary export file could not be created.');
        file_put_contents($zip_file, 'dummy');

        $option_name = 'tejlg_export_job_' . uniqid('test_', true);
        add_option($option_name, [
            'zip_path' => $zip_file,
        ]);

        $temp_dir = get_temp_dir();
        $this->assertNotFalse($temp_dir, 'Temporary directory could not be determined.');

        $pattern_file = tempnam($temp_dir, 'tejlg-patterns');
        $this->assertNotFalse($pattern_file, 'Temporary pattern file could not be created.');
        file_put_contents($pattern_file, 'pattern');

        if (!defined('WP_UNINSTALL_PLUGIN')) {
            define('WP_UNINSTALL_PLUGIN', true);
        }

        require dirname(__DIR__) . '/theme-export-jlg/uninstall.php';

        $this->assertFalse(file_exists($zip_file), 'Export job archive should be deleted.');
        $this->assertFalse(file_exists($pattern_file), 'Temporary pattern file should be deleted.');
        $this->assertFalse(get_option($option_name), 'Export job option should be removed.');
    }
}

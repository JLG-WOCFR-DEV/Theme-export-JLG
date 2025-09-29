<?php

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-files.php';

/**
 * @group files
 */
class Test_TEJLG_Files extends WP_UnitTestCase {

    public function test_delete_logs_failure_when_unlink_fails() {
        $log_file = tempnam(sys_get_temp_dir(), 'tejlg-log-');
        $previous_error_log = ini_set('error_log', $log_file);

        $captured_doing_it_wrong_message = '';
        $listener = static function ($function, $message, $version) use (&$captured_doing_it_wrong_message) {
            if ('TEJLG_Files::delete' === $function) {
                $captured_doing_it_wrong_message = $message;
            }
        };

        add_action('doing_it_wrong_run', $listener, 10, 3);
        $this->setExpectedIncorrectUsage('TEJLG_Files::delete');

        $temp_dir = trailingslashit(sys_get_temp_dir()) . 'tejlg-delete-test-' . wp_generate_uuid4();
        wp_mkdir_p($temp_dir);

        try {
            $result = TEJLG_Files::delete($temp_dir);

            $this->assertFalse($result, 'Deleting a directory with the file helper should fail.');

            $this->assertFileExists($log_file, 'The error log file should exist.');
            $log_contents = file_get_contents($log_file);

            $this->assertNotFalse($log_contents, 'The log file contents should be readable.');
            $this->assertStringContainsString($temp_dir, (string) $log_contents, 'The log should mention the failing path.');
            $this->assertNotEmpty($captured_doing_it_wrong_message, 'doing_it_wrong should capture a message.');
            $this->assertStringContainsString($temp_dir, $captured_doing_it_wrong_message, 'doing_it_wrong should mention the failing path.');
        } finally {
            remove_action('doing_it_wrong_run', $listener, 10);

            if (is_dir($temp_dir)) {
                rmdir($temp_dir);
            }

            if (false !== $previous_error_log) {
                ini_set('error_log', $previous_error_log);
            }

            if (file_exists($log_file)) {
                unlink($log_file);
            }
        }
    }
}

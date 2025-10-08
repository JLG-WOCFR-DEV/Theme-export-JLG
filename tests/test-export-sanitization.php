<?php

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export.php';

class Test_Export_Sanitization extends WP_UnitTestCase {
    public function test_sanitize_exclusion_patterns_discards_parent_directory_segments() {
        $input = [
            '../secret',
            'subdir/../hidden.php',
            '..\\windows\\style.css',
            'valid/file.php',
            'file..backup',
        ];

        $sanitized = TEJLG_Export::sanitize_exclusion_patterns($input);

        $this->assertContains('valid/file.php', $sanitized, 'Expected valid paths to be preserved.');
        $this->assertContains('file..backup', $sanitized, 'File names containing double dots should not be treated as traversal attempts.');
        $this->assertNotContains('../secret', $sanitized, 'Parent directory traversal segments must be removed.');
        $this->assertNotContains('subdir/../hidden.php', $sanitized, 'Nested traversal segments must be removed.');
        $this->assertNotContains('..\\windows\\style.css', $sanitized, 'Windows-style traversal patterns must be removed.');
    }
}

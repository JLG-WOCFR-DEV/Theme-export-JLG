<?php

use PHPUnit\Framework\TestCase;

if (!function_exists('wp_check_invalid_utf8')) {
    function wp_check_invalid_utf8($string, $strip = false) {
        return (string) $string;
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($string) {
        return strip_tags((string) $string);
    }
}

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export.php';

class Test_Export_Sanitization extends TestCase {
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

    public function test_exclusion_patterns_sanitizer_limits_count_and_length() {
        $input = [
            '  leading/slash  ',
            '../should-be-removed',
            str_repeat('a', 400),
        ];

        $sanitized = TEJLG_Exclusion_Patterns_Sanitizer::sanitize_list($input, 1, 10);

        $this->assertSame(['leading/slash'], $sanitized, 'Expected trimming, traversal removal and maximum count enforcement.');

        $sanitized_string = TEJLG_Exclusion_Patterns_Sanitizer::sanitize_string("foo\nbar", 1, 5);

        $this->assertSame('foo', $sanitized_string, 'Expected newline-separated string to respect max patterns.');
    }
}

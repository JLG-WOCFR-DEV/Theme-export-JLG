<?php

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export.php';

/**
 * @group export-theme
 */
class Test_Export_Theme extends WP_UnitTestCase {

    public function test_export_theme_dies_when_filesize_is_unavailable() {
        $captured_temp_path = null;
        $die_message        = null;

        $filesize_filter = static function ($size, $path) use (&$captured_temp_path) {
            $captured_temp_path = $path;
            return false;
        };

        $wp_die_handler = static function () use (&$die_message) {
            return static function ($message) use (&$die_message) {
                $die_message = $message;
                throw new WPDieException($message);
            };
        };

        add_filter('tejlg_export_zip_file_size', $filesize_filter, 10, 2);
        add_filter('wp_die_handler', $wp_die_handler);

        try {
            TEJLG_Export::export_theme();
            $this->fail('Expected WPDieException was not thrown.');
        } catch (WPDieException $exception) {
            $this->assertNotEmpty($die_message, 'The wp_die handler should capture a message.');
            $this->assertStringContainsString(
                "Impossible de déterminer la taille de l'archive ZIP à télécharger.",
                wp_strip_all_tags((string) $die_message)
            );
            $this->assertNotEmpty($captured_temp_path, 'The temporary ZIP path should be captured.');
            $this->assertFileDoesNotExist($captured_temp_path, 'The temporary ZIP file should be cleaned up.');
        } finally {
            remove_filter('tejlg_export_zip_file_size', $filesize_filter, 10);
            remove_filter('wp_die_handler', $wp_die_handler, 10);
        }
    }
}

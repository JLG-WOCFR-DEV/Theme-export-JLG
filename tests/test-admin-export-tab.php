<?php

use RuntimeException;

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-admin.php';
require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export.php';
require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-import.php';
require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-admin-debug-page.php';

/**
 * @group admin
 */
class Test_Admin_Export_Tab extends WP_UnitTestCase {

    public function test_render_export_tab_handles_array_action_without_warning() {
        $template_dir = dirname(__DIR__) . '/theme-export-jlg/templates/admin/';
        $export_page  = new TEJLG_Admin_Export_Page($template_dir, 'theme-export-jlg');

        $_GET['action'] = ['select_patterns'];

        $error_handler = static function ($errno, $errstr) {
            if (E_WARNING === $errno || E_USER_WARNING === $errno) {
                throw new RuntimeException($errstr);
            }

            return false;
        };

        set_error_handler($error_handler);

        $output = '';

        try {
            ob_start();
            $export_page->render();
            $output = ob_get_clean();
        } finally {
            restore_error_handler();

            unset($_GET['action']);
        }

        $this->assertStringContainsString(
            'Exporter le Thème Actif',
            $output,
            'Default export tools should be displayed when action is invalid.'
        );

        $this->assertStringNotContainsString(
            'Exporter une sélection de compositions',
            $output,
            'Pattern selection page should not be rendered when action is invalid.'
        );
    }

    public function test_export_progress_has_accessible_label() {
        $template_dir = dirname(__DIR__) . '/theme-export-jlg/templates/admin/';
        $export_page  = new TEJLG_Admin_Export_Page($template_dir, 'theme-export-jlg');

        ob_start();
        $export_page->render();
        $output = ob_get_clean();

        $this->assertStringContainsString(
            'id="tejlg-theme-export-status"',
            $output,
            'The status element should expose an id for accessibility.'
        );

        $this->assertStringContainsString(
            'aria-labelledby="tejlg-theme-export-status"',
            $output,
            'The progress bar should reference the status text for screen readers.'
        );
    }

    public function test_debug_report_download_requires_manage_options() {
        $template_dir = dirname(__DIR__) . '/theme-export-jlg/templates/admin/';
        $debug_page   = new TEJLG_Admin_Debug_Page($template_dir, 'theme-export-jlg');

        $editor_id = self::factory()->user->create(['role' => 'editor']);
        wp_set_current_user($editor_id);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST[TEJLG_Admin_Debug_Page::DOWNLOAD_REQUEST_FLAG] = '1';
        $_POST[TEJLG_Admin_Debug_Page::DOWNLOAD_NONCE_FIELD]  = wp_create_nonce(TEJLG_Admin_Debug_Page::DOWNLOAD_NONCE_ACTION);

        ob_start();
        $debug_page->handle_request();
        $output = ob_get_clean();

        $headers = headers_list();
        $has_download_header = false;

        foreach ($headers as $header) {
            if (stripos($header, 'Content-Disposition:') === 0) {
                $has_download_header = true;
                break;
            }
        }

        $this->assertSame('', $output, 'Editors must not receive the debug report stream.');
        $this->assertFalse($has_download_header, 'Editors must not receive download headers.');

        foreach ($headers as $header) {
            $parts = explode(':', $header, 2);

            if (!empty($parts[0])) {
                header_remove(trim($parts[0]));
            }
        }

        unset(
            $_SERVER['REQUEST_METHOD'],
            $_POST[TEJLG_Admin_Debug_Page::DOWNLOAD_REQUEST_FLAG],
            $_POST[TEJLG_Admin_Debug_Page::DOWNLOAD_NONCE_FIELD]
        );

        wp_set_current_user(0);
    }
}

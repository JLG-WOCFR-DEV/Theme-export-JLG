<?php

use RuntimeException;

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-admin.php';
require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-admin-export-page.php';
require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export.php';
require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-import.php';

/**
 * @group admin
 */
class Test_Admin_Export_Tab extends WP_UnitTestCase {

    public function test_render_export_tab_handles_array_action_without_warning() {
        $export_page = new TEJLG_Admin_Export_Page();

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
        $export_page = new TEJLG_Admin_Export_Page();

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
}

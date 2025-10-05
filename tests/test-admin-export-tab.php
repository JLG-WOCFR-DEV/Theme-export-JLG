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

    public function test_schedule_settings_accept_valid_run_time() {
        delete_option(TEJLG_Export::SCHEDULE_SETTINGS_OPTION);

        $normalized = TEJLG_Export::update_schedule_settings([
            'frequency' => 'daily',
            'run_time'  => '07:05',
        ]);

        $this->assertArrayHasKey('run_time', $normalized);
        $this->assertSame('07:05', $normalized['run_time']);

        delete_option(TEJLG_Export::SCHEDULE_SETTINGS_OPTION);
    }

    public function test_schedule_settings_reject_invalid_run_time() {
        delete_option(TEJLG_Export::SCHEDULE_SETTINGS_OPTION);

        $normalized = TEJLG_Export::update_schedule_settings([
            'frequency' => 'daily',
            'run_time'  => '26:90',
        ]);

        $this->assertArrayHasKey('run_time', $normalized);
        $this->assertSame('00:00', $normalized['run_time']);

        delete_option(TEJLG_Export::SCHEDULE_SETTINGS_OPTION);
    }

    public function test_calculate_next_schedule_timestamp_respects_run_time() {
        $previous_timezone = get_option('timezone_string');
        update_option('timezone_string', 'UTC');

        try {
            $reference = gmmktime(12, 0, 0, 1, 1, 2024);

            $settings = [
                'frequency' => 'daily',
                'run_time'  => '23:45',
            ];

            $first_run = TEJLG_Export::calculate_next_schedule_timestamp($settings, $reference);
            $this->assertSame(gmmktime(23, 45, 0, 1, 1, 2024), $first_run);

            $settings['run_time'] = '08:15';
            $next_run             = TEJLG_Export::calculate_next_schedule_timestamp($settings, $reference);
            $this->assertSame(gmmktime(8, 15, 0, 1, 2, 2024), $next_run);

            $settings['frequency'] = 'weekly';
            $settings['run_time']  = '07:30';
            $weekly_run            = TEJLG_Export::calculate_next_schedule_timestamp($settings, $reference);
            $this->assertSame(gmmktime(7, 30, 0, 1, 8, 2024), $weekly_run);
        } finally {
            if (false === $previous_timezone || '' === $previous_timezone) {
                delete_option('timezone_string');
            } else {
                update_option('timezone_string', $previous_timezone);
            }
        }
    }

    public function test_maybe_schedule_theme_export_event_applies_timestamp_filter() {
        $previous_timezone = get_option('timezone_string');
        update_option('timezone_string', 'UTC');

        delete_option(TEJLG_Export::SCHEDULE_SETTINGS_OPTION);
        TEJLG_Export::clear_scheduled_theme_export_event();

        $settings = [
            'frequency'      => 'daily',
            'run_time'       => '00:00',
            'retention_days' => 0,
        ];

        TEJLG_Export::update_schedule_settings($settings);

        $captured_timestamp = null;

        $filter = static function ($timestamp) use (&$captured_timestamp) {
            $captured_timestamp = $timestamp + 90;
            return $captured_timestamp;
        };

        add_filter('tejlg_export_schedule_timestamp', $filter);

        try {
            TEJLG_Export::maybe_schedule_theme_export_event();

            $scheduled = wp_next_scheduled(TEJLG_Export::SCHEDULE_EVENT_HOOK);

            $this->assertNotNull($captured_timestamp, 'The timestamp filter should capture a value.');
            $this->assertSame($captured_timestamp, $scheduled, 'The scheduled event should use the filtered timestamp.');
        } finally {
            remove_filter('tejlg_export_schedule_timestamp', $filter);
            TEJLG_Export::clear_scheduled_theme_export_event();

            if (false === $previous_timezone || '' === $previous_timezone) {
                delete_option('timezone_string');
            } else {
                update_option('timezone_string', $previous_timezone);
            }
        }
    }
}

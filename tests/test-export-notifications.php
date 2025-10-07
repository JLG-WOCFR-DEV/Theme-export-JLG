<?php

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export-notifications.php';
require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export-history.php';

class Test_Export_Notifications extends WP_UnitTestCase {
    private $original_settings;

    public function setUp(): void {
        parent::setUp();
        $this->original_settings = TEJLG_Export_Notifications::get_settings();
    }

    public function tearDown(): void {
        TEJLG_Export_Notifications::update_settings($this->original_settings);
        parent::tearDown();
    }

    public function test_update_settings_sanitizes_inputs() {
        $updated = TEJLG_Export_Notifications::update_settings([
            'recipients'      => "alerts@example.com, invalid-email, second@example.org \n duplicate@example.com",
            'enabled_results' => ['error', 'unknown', 'success'],
        ]);

        $this->assertSame("alerts@example.com\nsecond@example.org\nduplicate@example.com", $updated['recipients']);
        $this->assertSame(['error', 'success'], $updated['enabled_results']);
    }

    public function test_history_notification_sends_email_for_enabled_result() {
        TEJLG_Export_Notifications::update_settings([
            'recipients'      => 'alerts@example.com',
            'enabled_results' => ['success'],
        ]);

        $captured = null;
        $filter = function ($short_circuit, $atts) use (&$captured) {
            $captured = $atts;

            return true;
        };

        add_filter('pre_wp_mail', $filter, 10, 2);

        $entry = [
            'result'        => 'success',
            'timestamp'     => time(),
            'origin'        => 'web',
            'duration'      => 90,
            'zip_file_size' => 1500000,
            'job_id'        => 'job-42',
            'status_message'=> 'Archive générée avec exclusions.',
        ];

        $job = [
            'duration' => 90,
        ];

        TEJLG_Export_Notifications::maybe_dispatch_history_notification($entry, $job, []);

        remove_filter('pre_wp_mail', $filter, 10);

        $this->assertNotNull($captured, 'A notification e-mail should be sent for success results when enabled.');
        $this->assertSame(['alerts@example.com'], $captured['to']);
        $this->assertNotEmpty($captured['subject']);
        $this->assertStringContainsString('job-42', $captured['message']);
    }

    public function test_schedule_origin_is_skipped_by_default() {
        TEJLG_Export_Notifications::update_settings([
            'recipients'      => 'alerts@example.com',
            'enabled_results' => ['error'],
        ]);

        $captured = null;
        $filter = function ($short_circuit, $atts) use (&$captured) {
            $captured = $atts;

            return true;
        };

        add_filter('pre_wp_mail', $filter, 10, 2);

        $entry = [
            'result'    => 'error',
            'timestamp' => time(),
            'origin'    => 'schedule',
            'job_id'    => 'job-99',
        ];

        TEJLG_Export_Notifications::maybe_dispatch_history_notification($entry, [], []);

        remove_filter('pre_wp_mail', $filter, 10);

        $this->assertNull($captured, 'Scheduled exports should not trigger notifications without explicit opt-in.');
    }
}

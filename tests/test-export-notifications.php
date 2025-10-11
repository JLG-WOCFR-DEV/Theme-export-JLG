<?php

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export-notifications.php';
require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export-history.php';
require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export.php';

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

    public function test_scheduled_success_uses_custom_recipients() {
        update_option('admin_email', 'admin@example.com');

        TEJLG_Export_Notifications::update_settings([
            'recipients'      => "alerts@example.com, ops@example.com",
            'enabled_results' => ['success'],
        ]);

        $captured = null;
        $filter   = function ($short_circuit, $atts) use (&$captured) {
            $captured = $atts;

            return true;
        };

        add_filter('pre_wp_mail', $filter, 10, 2);

        $job = [
            'completed_at' => time(),
        ];

        $settings = [
            'retention_days' => 5,
        ];

        $persistence = [
            'url' => 'https://example.com/download.zip',
        ];

        $this->invoke_scheduled_notification('notify_scheduled_export_success', [$job, $settings, $persistence, ['*.log']]);

        remove_filter('pre_wp_mail', $filter, 10);

        $this->assertNotNull($captured, 'Scheduled success notification should send an email when enabled.');
        $this->assertSame(
            ['alerts@example.com', 'ops@example.com'],
            TEJLG_Export_Notifications::sanitize_recipient_list($captured['to'])
        );
    }

    public function test_scheduled_failure_respects_enabled_results() {
        TEJLG_Export_Notifications::update_settings([
            'recipients'      => 'errors@example.com',
            'enabled_results' => ['success'],
        ]);

        $captured = null;
        $filter   = function ($short_circuit, $atts) use (&$captured) {
            $captured = $atts;

            return true;
        };

        add_filter('pre_wp_mail', $filter, 10, 2);

        $this->invoke_scheduled_notification('notify_scheduled_export_failure', [
            __('Une erreur est survenue.', 'theme-export-jlg'),
            ['retention_days' => 5],
            ['id' => 'job-1'],
            new WP_Error('boom'),
            [],
        ]);

        remove_filter('pre_wp_mail', $filter, 10);

        $this->assertNull($captured, 'Failure notifications should be suppressed when the result is disabled.');
    }

    public function test_scheduled_failure_falls_back_to_admin_email() {
        update_option('admin_email', 'owner@example.com');

        TEJLG_Export_Notifications::update_settings([
            'recipients'      => '',
            'enabled_results' => ['error'],
        ]);

        $captured = null;
        $filter   = function ($short_circuit, $atts) use (&$captured) {
            $captured = $atts;

            return true;
        };

        add_filter('pre_wp_mail', $filter, 10, 2);

        $this->invoke_scheduled_notification('notify_scheduled_export_failure', [
            __('Une erreur est survenue.', 'theme-export-jlg'),
            ['retention_days' => 5],
            ['id' => 'job-2'],
            null,
            [],
        ]);

        remove_filter('pre_wp_mail', $filter, 10);

        $this->assertNotNull($captured, 'Failure notification should be sent when enabled.');
        $this->assertSame(['owner@example.com'], TEJLG_Export_Notifications::sanitize_recipient_list($captured['to']));
    }

    /**
     * Invokes a private scheduled notification helper on the export class.
     *
     * @param string $method
     * @param array  $arguments
     */
    private function invoke_scheduled_notification($method, array $arguments) {
        $reflection = new ReflectionMethod(TEJLG_Export::class, $method);
        $reflection->setAccessible(true);
        $reflection->invokeArgs(null, $arguments);
    }
}

<?php

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export.php';
require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export-history.php';
require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export-notifications.php';

if (!defined('WP_CLI')) {
    define('WP_CLI', true);
}

if (!class_exists('WP_CLI')) {
    class WP_CLI {
        public static $commands = [];
        public static $success_message = '';
        public static $error_message = '';
        public static $last_log = '';
        public static $last_warning = '';
        public static $logs = [];
        public static $printed_value = null;

        public static function add_command($name, $callable) {
            self::$commands[$name] = $callable;
        }

        public static function success($message) {
            self::$success_message = (string) $message;
        }

        public static function error($message) {
            if ($message instanceof WP_Error) {
                $message = $message->get_error_message();
            }

            self::$error_message = (string) $message;
            throw new RuntimeException(self::$error_message);
        }

        public static function log($message) {
            self::$last_log = (string) $message;
            self::$logs[]   = self::$last_log;
        }

        public static function warning($message) {
            self::$last_warning = (string) $message;
        }

        public static function print_value($value, $args = []) {
            self::$printed_value = $value;
        }
    }
}

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-cli.php';

class Test_TEJLG_CLI_Command extends WP_UnitTestCase {
    private $export_dir;

    protected function setUp(): void {
        parent::setUp();

        $uploads = wp_get_upload_dir();
        $this->export_dir = trailingslashit($uploads['basedir']) . 'tejlg-cli-tests';
        wp_mkdir_p($this->export_dir);

        WP_CLI::$success_message = '';
        WP_CLI::$error_message = '';
        WP_CLI::$last_log       = '';
        WP_CLI::$last_warning   = '';
        WP_CLI::$logs           = [];
        WP_CLI::$printed_value  = null;

        TEJLG_Export_History::clear_history();
    }

    protected function tearDown(): void {
        $this->remove_directory($this->export_dir);
        parent::tearDown();
    }

    public function test_command_is_registered() {
        $this->assertArrayHasKey('theme-export-jlg', WP_CLI::$commands);
    }

    public function test_patterns_command_writes_json_file() {
        $target = trailingslashit($this->export_dir) . 'patterns-cli.json';

        $cli = new TEJLG_CLI();
        $cli->patterns([], ['output' => $target, 'portable' => true]);

        $this->assertFileExists($target);
        $contents = file_get_contents($target);
        $this->assertNotFalse($contents);
        $decoded = json_decode($contents, true);
        $this->assertNotNull($decoded, 'JSON should decode even if empty array.');
        $this->assertNotSame('', WP_CLI::$success_message, 'CLI should return a success message.');
        $this->assertStringContainsString($target, WP_CLI::$success_message);
    }

    public function test_history_command_outputs_empty_state() {
        $cli = new TEJLG_CLI();

        WP_CLI::$last_log = '';

        $cli->history([], []);

        $this->assertStringContainsString(
            'Aucun export',
            WP_CLI::$last_log,
            'History command should warn when no entries exist.'
        );
    }

    public function test_history_command_lists_recorded_entry() {
        $temp_file = wp_tempnam('cli-history.zip');
        $this->assertNotFalse($temp_file);

        file_put_contents($temp_file, 'cli');

        TEJLG_Export_History::record_job([
            'id'            => 'cli-history-job',
            'status'        => 'completed',
            'zip_path'      => $temp_file,
            'zip_file_name' => 'cli-history.zip',
            'zip_file_size' => filesize($temp_file),
            'exclusions'    => [],
            'created_at'    => time(),
            'updated_at'    => time(),
            'completed_at'  => time(),
            'created_via'   => 'cli',
        ]);

        $cli = new TEJLG_CLI();
        $cli->history([], []);

        $this->assertStringContainsString(
            'cli-history-job',
            WP_CLI::$last_log,
            'History command should list the recorded entry.'
        );

        if (file_exists($temp_file)) {
            @unlink($temp_file);
        }
    }

    public function test_history_command_supports_result_and_origin_filters() {
        $first_file = wp_tempnam('cli-history-success.zip');
        $second_file = wp_tempnam('cli-history-error.zip');

        $this->assertNotFalse($first_file);
        $this->assertNotFalse($second_file);

        file_put_contents($first_file, 'success');
        file_put_contents($second_file, 'error');

        TEJLG_Export_History::record_job([
            'id'            => 'cli-history-success',
            'status'        => 'completed',
            'zip_path'      => $first_file,
            'zip_file_name' => 'cli-history-success.zip',
            'zip_file_size' => filesize($first_file),
            'created_at'    => time(),
            'updated_at'    => time(),
            'completed_at'  => time(),
        ], [
            'origin' => 'schedule',
        ]);

        TEJLG_Export_History::record_job([
            'id'            => 'cli-history-error',
            'status'        => 'failed',
            'zip_path'      => $second_file,
            'zip_file_name' => 'cli-history-error.zip',
            'zip_file_size' => filesize($second_file),
            'created_at'    => time(),
            'updated_at'    => time(),
            'completed_at'  => time(),
        ], [
            'origin' => 'cli',
        ]);

        $cli = new TEJLG_CLI();

        WP_CLI::$logs = [];

        $cli->history([], ['result' => 'success', 'origin' => 'schedule']);

        $this->assertNotEmpty(WP_CLI::$logs, 'History command should output logs.');
        $last_log = end(WP_CLI::$logs);

        $this->assertIsString($last_log);
        $this->assertStringContainsString('cli-history-success', $last_log, 'Filtered history should include the matching job.');

        foreach (WP_CLI::$logs as $log_line) {
            $this->assertStringNotContainsString('cli-history-error', $log_line, 'Filtered history should not include non-matching jobs.');
        }

        if (file_exists($first_file)) {
            @unlink($first_file);
        }

        if (file_exists($second_file)) {
            @unlink($second_file);
        }
    }

    public function test_history_report_command_outputs_summary() {
        $now = time();

        TEJLG_Export_History::record_job([
            'id'            => 'cli-report-success',
            'status'        => 'completed',
            'zip_file_name' => 'cli-report-success.zip',
            'zip_file_size' => 5120,
            'created_at'    => $now - 120,
            'updated_at'    => $now - 60,
            'completed_at'  => $now - 30,
        ], [
            'origin' => 'web',
        ]);

        TEJLG_Export_History::record_job([
            'id'            => 'cli-report-error',
            'status'        => 'failed',
            'zip_file_name' => 'cli-report-error.zip',
            'zip_file_size' => 1024,
            'created_at'    => $now - 90,
            'updated_at'    => $now - 45,
            'completed_at'  => $now - 10,
        ], [
            'origin' => 'cli',
        ]);

        $cli = new TEJLG_CLI();

        WP_CLI::$logs = [];

        $cli->history(['report'], ['window' => 1, 'limit' => 2]);

        $this->assertNotEmpty(WP_CLI::$logs, 'Report command should output summary lines.');
        $this->assertStringContainsString('Rapport d’export généré', WP_CLI::$logs[0]);

        $found_entry = false;

        foreach (WP_CLI::$logs as $log_line) {
            if (false !== strpos($log_line, 'cli-report-error')) {
                $found_entry = true;
                break;
            }
        }

        $this->assertTrue($found_entry, 'Report should include recent entries in the output.');
    }

    public function test_history_report_supports_json_format() {
        $now = time();

        TEJLG_Export_History::record_job([
            'id'            => 'cli-report-json',
            'status'        => 'completed',
            'zip_file_name' => 'cli-report-json.zip',
            'zip_file_size' => 2048,
            'created_at'    => $now - 60,
            'updated_at'    => $now - 30,
            'completed_at'  => $now - 10,
        ], [
            'origin' => 'web',
        ]);

        $cli = new TEJLG_CLI();

        $cli->history(['report'], ['format' => 'json']);

        $this->assertIsArray(WP_CLI::$printed_value, 'JSON format should capture a structured report.');
        $this->assertSame(1, WP_CLI::$printed_value['totals']['entries']);
    }

    public function test_settings_command_exports_signed_package() {
        $target = trailingslashit($this->export_dir) . 'settings-export.json';

        TEJLG_Export::update_schedule_settings([
            'frequency'      => 'daily',
            'exclusions'     => "vendor\nnode_modules",
            'retention_days' => 14,
            'run_time'       => '06:45',
        ]);

        update_option(TEJLG_Admin_Export_Page::EXCLUSION_PATTERNS_OPTION, "vendor\nnode_modules");
        update_option(TEJLG_Admin_Export_Page::PORTABLE_MODE_OPTION, '1');
        update_option(TEJLG_Admin_Debug_Page::METRICS_ICON_OPTION, 48);
        TEJLG_Export_Notifications::update_settings([
            'recipients'      => "ops@example.com\nsupport@example.com",
            'enabled_results' => ['error', 'warning', 'success'],
        ]);

        $cli = new TEJLG_CLI();
        $cli->settings(['export'], ['output' => $target]);

        $this->assertFileExists($target);

        $contents = file_get_contents($target);
        $this->assertNotFalse($contents);

        $decoded = json_decode($contents, true);
        $this->assertIsArray($decoded);
        $this->assertSame(TEJLG_Settings::EXPORT_SCHEMA, $decoded['schema']);

        $signature = TEJLG_Settings::verify_signature($decoded);
        $this->assertTrue($signature['valid']);

        $this->assertStringContainsString('Réglages exportés', WP_CLI::$success_message);
    }

    public function test_settings_command_imports_snapshot_and_warns_on_signature_mismatch() {
        $target = trailingslashit($this->export_dir) . 'settings-import.json';

        TEJLG_Export::update_schedule_settings([
            'frequency'      => 'disabled',
            'exclusions'     => '',
            'retention_days' => 30,
            'run_time'       => '00:00',
        ]);

        update_option(TEJLG_Admin_Export_Page::EXCLUSION_PATTERNS_OPTION, '');
        update_option(TEJLG_Admin_Export_Page::PORTABLE_MODE_OPTION, '0');
        update_option(TEJLG_Admin_Debug_Page::METRICS_ICON_OPTION, TEJLG_Admin_Debug_Page::METRICS_ICON_DEFAULT);

        $snapshot = [
            'schedule' => [
                'frequency'      => 'hourly',
                'exclusions'     => "foo\nbar",
                'retention_days' => 5,
                'run_time'       => '02:10',
            ],
            'export_preferences' => [
                'exclusion_patterns' => "foo\nbar",
                'portable_mode'      => true,
            ],
            'debug_preferences' => [
                'metrics_icon_size' => 72,
            ],
            'notifications' => [
                'recipients'      => "ops@example.com\nsupport@example.com",
                'enabled_results' => ['error', 'success'],
            ],
        ];

        $package = TEJLG_Settings::build_export_package($snapshot);
        $json    = TEJLG_Settings::encode_export_package($package);
        $this->assertNotWPError($json);

        file_put_contents($target, $json);

        $cli = new TEJLG_CLI();

        WP_CLI::$last_warning = '';
        $cli->settings(['import', $target], []);

        $settings = TEJLG_Export::get_schedule_settings();
        $this->assertSame('hourly', $settings['frequency']);
        $this->assertSame("foo\nbar", get_option(TEJLG_Admin_Export_Page::EXCLUSION_PATTERNS_OPTION));
        $this->assertSame('1', get_option(TEJLG_Admin_Export_Page::PORTABLE_MODE_OPTION));
        $this->assertSame(72, (int) get_option(TEJLG_Admin_Debug_Page::METRICS_ICON_OPTION));
        $notifications = TEJLG_Export_Notifications::get_settings();
        $this->assertSame("ops@example.com\nsupport@example.com", $notifications['recipients']);
        $this->assertSame(['error', 'success'], $notifications['enabled_results']);
        $this->assertSame('', WP_CLI::$last_warning);

        $tampered = json_decode($json, true);
        $this->assertIsArray($tampered);
        $tampered['settings']['schedule']['frequency'] = 'weekly';
        $tampered_json = wp_json_encode($tampered, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->assertNotFalse($tampered_json);
        file_put_contents($target, $tampered_json);

        WP_CLI::$last_warning = '';
        $cli->settings(['import', $target], []);

        $this->assertStringContainsString('signature ne correspond pas', WP_CLI::$last_warning);
        $settings = TEJLG_Export::get_schedule_settings();
        $this->assertSame('weekly', $settings['frequency']);
    }

    private function remove_directory($directory) {
        if (!is_dir($directory)) {
            return;
        }

        $entries = array_diff(scandir($directory), ['.', '..']);

        foreach ($entries as $entry) {
            $path = $directory . '/' . $entry;

            if (is_dir($path)) {
                $this->remove_directory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}

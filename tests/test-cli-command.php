<?php

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export.php';
require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export-history.php';

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
        }

        public static function warning($message) {
            self::$last_warning = (string) $message;
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

<?php

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export.php';

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

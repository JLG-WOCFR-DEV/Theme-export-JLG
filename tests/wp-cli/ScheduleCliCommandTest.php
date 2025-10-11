<?php

use PHPUnit\Framework\TestCase;

if (!class_exists('WP_Error')) {
    class WP_Error {
        /** @var array<string,array<int,string>> */
        private $errors = [];

        public function __construct($code = '', $message = '') {
            if ('' !== $code) {
                $this->add($code, $message);
            }
        }

        public function add($code, $message) {
            $code = (string) $code;
            if (!isset($this->errors[$code])) {
                $this->errors[$code] = [];
            }
            $this->errors[$code][] = (string) $message;
        }

        public function get_error_message($code = '') {
            if ('' === $code) {
                $code = $this->get_error_code();
            }

            if ('' === $code || !isset($this->errors[$code])) {
                return '';
            }

            $messages = $this->errors[$code];

            return empty($messages) ? '' : (string) $messages[0];
        }

        public function get_error_code() {
            if (empty($this->errors)) {
                return '';
            }

            $keys = array_keys($this->errors);

            return (string) reset($keys);
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = null) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
        return (string) $text;
    }
}

if (!function_exists('_n')) {
    function _n($single, $plural, $number, $domain = null) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
        return 1 === (int) $number ? (string) $single : (string) $plural;
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        $key = strtolower((string) $key);

        return preg_replace('/[^a-z0-9_\-]/', '', $key);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) {
        return trim((string) $value);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('wp_check_invalid_utf8')) {
    function wp_check_invalid_utf8($string, $strip = false) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
        return (string) $string;
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($string) {
        return strip_tags((string) $string);
    }
}

if (!function_exists('wp_specialchars_decode')) {
    function wp_specialchars_decode($text, $quote_style = ENT_NOQUOTES) {
        return html_entity_decode((string) $text, $quote_style, 'UTF-8');
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
        return $value;
    }
}

if (!function_exists('get_option')) {
    $GLOBALS['wp_options'] = [];

    function get_option($name, $default = false) {
        $name = (string) $name;

        return array_key_exists($name, $GLOBALS['wp_options']) ? $GLOBALS['wp_options'][$name] : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($name, $value, $autoload = false) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
        $GLOBALS['wp_options'][(string) $name] = $value;

        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($name) {
        unset($GLOBALS['wp_options'][(string) $name]);

        return true;
    }
}

if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (!function_exists('wp_timezone')) {
    function wp_timezone() {
        return new DateTimeZone('UTC');
    }
}

if (!function_exists('wp_date')) {
    function wp_date($format, $timestamp) {
        return date($format, $timestamp);
    }
}

if (!function_exists('date_i18n')) {
    function date_i18n($format, $timestamp) {
        return date($format, $timestamp);
    }
}

if (!function_exists('wp_next_scheduled')) {
    $GLOBALS['tejlg_cron_events'] = [];

    function wp_next_scheduled($hook) {
        $hook = (string) $hook;

        if (!isset($GLOBALS['tejlg_cron_events'][$hook])) {
            return false;
        }

        return (int) $GLOBALS['tejlg_cron_events'][$hook]['timestamp'];
    }
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook) {
        $GLOBALS['tejlg_cron_events'][(string) $hook] = [
            'timestamp'  => (int) $timestamp,
            'recurrence' => $recurrence,
        ];

        return true;
    }
}

if (!function_exists('wp_schedule_single_event')) {
    function wp_schedule_single_event($timestamp, $hook) {
        return wp_schedule_event($timestamp, 'single', $hook);
    }
}

if (!function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook($hook) {
        unset($GLOBALS['tejlg_cron_events'][(string) $hook]);
    }
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

        public static function print_value($value, $args = []) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
            self::$printed_value = $value;
        }
    }
}

if (!function_exists('getcwd')) {
    function getcwd() {
        return sys_get_temp_dir();
    }
}

if (!function_exists('path_is_absolute')) {
    function path_is_absolute($path) {
        return preg_match('#^(/|[a-zA-Z]:\\\\)#', (string) $path) === 1;
    }
}

if (!function_exists('wp_normalize_path')) {
    function wp_normalize_path($path) {
        $path = str_replace('\\', '/', (string) $path);

        return preg_replace('#/+#', '/', $path);
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit($path) {
        $path = (string) $path;

        return rtrim($path, "\\/") . '/';
    }
}

if (!function_exists('untrailingslashit')) {
    function untrailingslashit($path) {
        return rtrim((string) $path, "\\/");
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($dir) {
        if (is_dir($dir)) {
            return true;
        }

        return mkdir($dir, 0777, true);
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        $base = sys_get_temp_dir() . '/tejlg-tests-uploads';
        wp_mkdir_p($base);

        return [
            'basedir' => $base,
            'baseurl' => 'https://example.com/uploads',
        ];
    }
}

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../../');
}

if (!class_exists('Theme_Installer_Skin')) {
    class Theme_Installer_Skin {
        public $done_header = false;

        public function header() {}

        public function footer() {}

        public function before() {}

        public function after() {}

        public function feedback($string, ...$args) {} // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
    }
}

if (!function_exists('wp_get_theme')) {
    function wp_get_theme() {
        return new class {
            public function get_stylesheet() {
                return 'theme-export-jlg-tests';
            }
        };
    }
}

require_once dirname(__DIR__, 2) . '/theme-export-jlg/includes/class-tejlg-export.php';
require_once dirname(__DIR__, 2) . '/theme-export-jlg/includes/class-tejlg-export-history.php';
require_once dirname(__DIR__, 2) . '/theme-export-jlg/includes/class-tejlg-import.php';
require_once dirname(__DIR__, 2) . '/theme-export-jlg/includes/class-tejlg-settings.php';
require_once dirname(__DIR__, 2) . '/theme-export-jlg/includes/class-tejlg-cli.php';

class ScheduleCliCommandTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();

        $GLOBALS['wp_options'] = [];
        $GLOBALS['tejlg_cron_events'] = [];

        WP_CLI::$success_message = '';
        WP_CLI::$error_message   = '';
        WP_CLI::$last_log        = '';
        WP_CLI::$last_warning    = '';
        WP_CLI::$logs            = [];
        WP_CLI::$printed_value   = null;

        update_option('date_format', 'Y-m-d');
        update_option('time_format', 'H:i');
        update_option('timezone_string', 'UTC');
        update_option('gmt_offset', 0);
    }

    public function test_schedule_set_rejects_unknown_frequency(): void {
        $cli = new TEJLG_CLI();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Fréquence inconnue');

        $cli->schedule(['set'], ['frequency' => 'everyminute']);
    }

    public function test_schedule_set_rejects_invalid_time(): void {
        $cli = new TEJLG_CLI();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Le format de l’option --time');

        $cli->schedule(['set'], ['time' => '25:99']);
    }

    public function test_schedule_set_updates_settings_and_reschedules(): void {
        $cli = new TEJLG_CLI();

        $cli->schedule(['set'], [
            'frequency'  => 'daily',
            'time'       => '06:45',
            'retention'  => '5',
            'exclusions' => 'node_modules,*.log',
        ]);

        $stored = get_option(TEJLG_Export::SCHEDULE_SETTINGS_OPTION, []);

        $this->assertIsArray($stored, 'Schedule settings should be stored as an array.');
        $this->assertSame('daily', $stored['frequency']);
        $this->assertSame('06:45', $stored['run_time']);
        $this->assertSame(5, $stored['retention_days']);
        $this->assertSame("node_modules\n*.log", $stored['exclusions']);
        $this->assertNotSame('', WP_CLI::$success_message, 'A success message should be emitted.');
        $this->assertArrayHasKey(TEJLG_Export::SCHEDULE_EVENT_HOOK, $GLOBALS['tejlg_cron_events'], 'A cron event should be scheduled after updating settings.');
    }

    public function test_schedule_run_schedules_event_when_missing(): void {
        TEJLG_Export::update_schedule_settings([
            'frequency'      => 'daily',
            'run_time'       => '07:30',
            'retention_days' => 10,
            'exclusions'     => '',
        ]);

        $cli = new TEJLG_CLI();

        $cli->schedule(['run'], []);

        $this->assertNotSame('', WP_CLI::$success_message, 'Schedule run should report a status.');
        $this->assertArrayHasKey(TEJLG_Export::SCHEDULE_EVENT_HOOK, $GLOBALS['tejlg_cron_events'], 'A cron event should be present after running the command.');
        $this->assertGreaterThan(time(), $GLOBALS['tejlg_cron_events'][TEJLG_Export::SCHEDULE_EVENT_HOOK]['timestamp']);
    }

    public function test_schedule_report_outputs_json_snapshot(): void {
        TEJLG_Export::update_schedule_settings([
            'frequency'      => 'weekly',
            'run_time'       => '22:15',
            'retention_days' => 14,
            'exclusions'     => "vendor\nnode_modules",
        ]);

        $next_run = time() + DAY_IN_SECONDS;
        wp_schedule_event($next_run, 'weekly', TEJLG_Export::SCHEDULE_EVENT_HOOK);

        $cli = new TEJLG_CLI();
        $cli->schedule(['report'], ['format' => 'json']);

        $this->assertIsArray(WP_CLI::$printed_value, 'The JSON report should be printed as an array.');
        $this->assertSame('weekly', WP_CLI::$printed_value['frequency']);
        $this->assertSame('22:15', WP_CLI::$printed_value['run_time']);
        $this->assertSame(14, WP_CLI::$printed_value['retention_days']);
        $this->assertContains('vendor', WP_CLI::$printed_value['exclusions']);
        $this->assertContains('node_modules', WP_CLI::$printed_value['exclusions']);
        $this->assertSame((int) $next_run, WP_CLI::$printed_value['next_run_timestamp']);
    }
}

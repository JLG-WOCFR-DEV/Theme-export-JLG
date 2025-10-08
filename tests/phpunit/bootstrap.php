<?php
use PHPUnit\Framework\TestCase;

if (!class_exists('WP_Error')) {
    class WP_Error {
        /** @var array<string,array<int,string>> */
        private $errors = [];

        public function __construct($code = '', $message = '') {
            if ($code) {
                $this->errors[(string) $code][] = (string) $message;
            }
        }

        public function add($code, $message) {
            $this->errors[(string) $code][] = (string) $message;
        }

        public function get_error_code() {
            if (empty($this->errors)) {
                return '';
            }

            $keys = array_keys($this->errors);

            return (string) reset($keys);
        }

        public function get_error_message($code = '') {
            if ('' === $code) {
                $code = $this->get_error_code();
            }

            if ('' === $code) {
                return '';
            }

            $messages = isset($this->errors[$code]) ? $this->errors[$code] : [];

            return empty($messages) ? '' : (string) $messages[0];
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value) {
        return $value;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = null) {
        return (string) $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return (string) $text;
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        $key = strtolower((string) $key);

        return preg_replace('/[^a-z0-9_\-]/', '', $key);
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit($value) {
        $value = (string) $value;

        if ('' === $value) {
            return '/';
        }

        return rtrim($value, "\\/") . '/';
    }
}

if (!function_exists('wp_tempnam')) {
    function wp_tempnam($filename) {
        $filename = '' !== $filename ? $filename : 'wp';

        return tempnam(sys_get_temp_dir(), preg_replace('/[^a-zA-Z0-9]/', '', (string) $filename));
    }
}

if (!function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4() {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

if (!function_exists('update_option')) {
    /** @var array<string,mixed> $GLOBALS['wp_options'] */
    $GLOBALS['wp_options'] = [];

    function update_option($name, $value, $autoload = false) {
        $GLOBALS['wp_options'][(string) $name] = $value;

        return true;
    }
}

if (!function_exists('get_option')) {
    function get_option($name, $default = false) {
        return isset($GLOBALS['wp_options'][(string) $name]) ? $GLOBALS['wp_options'][(string) $name] : $default;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($name) {
        unset($GLOBALS['wp_options'][(string) $name]);

        return true;
    }
}

if (!class_exists('WP_Background_Process')) {
    abstract class WP_Background_Process {
        /** @var string */
        protected $action = '';
    }
}

if (!class_exists('TEJLG_Export')) {
    class TEJLG_Export {
        /** @var array<string,array<string,mixed>> */
        private static $jobs = [];

        public static function persist_job($job) {
            if (!is_array($job) || empty($job['id'])) {
                return;
            }

            self::$jobs[(string) $job['id']] = $job;
        }

        public static function get_job($job_id) {
            $job_id = (string) $job_id;

            return isset(self::$jobs[$job_id]) ? self::$jobs[$job_id] : null;
        }

        public static function mark_job_failed($job_id, $message, $context = []) {
            $job_id = (string) $job_id;

            if (!isset(self::$jobs[$job_id])) {
                return;
            }

            self::$jobs[$job_id]['status']       = 'failed';
            self::$jobs[$job_id]['message']      = (string) $message;
            self::$jobs[$job_id]['failure_code'] = isset($context['failure_code']) ? (string) $context['failure_code'] : '';
            self::$jobs[$job_id]['updated_at']   = time();
            self::$jobs[$job_id]['completed_at'] = time();
        }

        public static function finalize_job($job) {
            if (!is_array($job) || empty($job['id'])) {
                return;
            }

            $job['status']       = 'completed';
            $job['completed_at'] = time();
            $job['updated_at']   = time();

            self::$jobs[(string) $job['id']] = $job;
        }

        public static function delete_job($job_id, array $context = []) {
            unset(self::$jobs[(string) $job_id]);
        }

        public static function reset_jobs() {
            self::$jobs = [];
        }
    }
}

if (!class_exists('WP_UnitTestCase')) {
    abstract class WP_UnitTestCase extends TestCase {
        protected function setUp(): void {
            parent::setUp();

            $this->markTestSkipped('The WordPress test suite is not available in this environment.');
        }
    }
}

require_once dirname(__DIR__, 2) . '/theme-export-jlg/includes/class-tejlg-zip-writer.php';
require_once dirname(__DIR__, 2) . '/theme-export-jlg/includes/class-tejlg-export-process.php';

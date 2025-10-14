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

if (!isset($GLOBALS['wp_filter']) || !is_array($GLOBALS['wp_filter'])) {
    $GLOBALS['wp_filter'] = [];
}

if (!function_exists('add_filter')) {
    function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1) {
        $hook_name    = (string) $hook_name;
        $priority     = (int) $priority;
        $accepted_args = (int) $accepted_args;

        if (!isset($GLOBALS['wp_filter'][$hook_name])) {
            $GLOBALS['wp_filter'][$hook_name] = [];
        }

        if (!isset($GLOBALS['wp_filter'][$hook_name][$priority])) {
            $GLOBALS['wp_filter'][$hook_name][$priority] = [];
        }

        $GLOBALS['wp_filter'][$hook_name][$priority][] = [
            'function'      => $callback,
            'accepted_args' => $accepted_args,
        ];

        return true;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook_name, $callback, $priority = 10, $accepted_args = 1) {
        return add_filter($hook_name, $callback, $priority, $accepted_args);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook_name, $value, ...$args) {
        $hook_name = (string) $hook_name;

        if (empty($GLOBALS['wp_filter'][$hook_name])) {
            return $value;
        }

        ksort($GLOBALS['wp_filter'][$hook_name]);

        foreach ($GLOBALS['wp_filter'][$hook_name] as $priority => $callbacks) {
            foreach ($callbacks as $entry) {
                $params = [$value];

                if ($entry['accepted_args'] > 1) {
                    $params = array_merge(
                        $params,
                        array_slice($args, 0, $entry['accepted_args'] - 1)
                    );
                }

                $value = call_user_func_array($entry['function'], $params);
            }
        }

        return $value;
    }
}

if (!function_exists('do_action')) {
    function do_action($hook_name, ...$args) {
        $hook_name = (string) $hook_name;

        if (empty($GLOBALS['wp_filter'][$hook_name])) {
            return;
        }

        ksort($GLOBALS['wp_filter'][$hook_name]);

        foreach ($GLOBALS['wp_filter'][$hook_name] as $priority => $callbacks) {
            foreach ($callbacks as $entry) {
                $params = array_slice($args, 0, $entry['accepted_args']);
                call_user_func_array($entry['function'], $params);
            }
        }
    }
}

if (!function_exists('remove_filter')) {
    function remove_filter($hook_name, $callback, $priority = 10) {
        $hook_name = (string) $hook_name;
        $priority  = (int) $priority;

        if (empty($GLOBALS['wp_filter'][$hook_name][$priority])) {
            return false;
        }

        foreach ($GLOBALS['wp_filter'][$hook_name][$priority] as $index => $entry) {
            if ($entry['function'] === $callback) {
                unset($GLOBALS['wp_filter'][$hook_name][$priority][$index]);

                if (empty($GLOBALS['wp_filter'][$hook_name][$priority])) {
                    unset($GLOBALS['wp_filter'][$hook_name][$priority]);
                }

                if (empty($GLOBALS['wp_filter'][$hook_name])) {
                    unset($GLOBALS['wp_filter'][$hook_name]);
                }

                return true;
            }
        }

        return false;
    }
}

if (!function_exists('remove_action')) {
    function remove_action($hook_name, $callback, $priority = 10) {
        return remove_filter($hook_name, $callback, $priority);
    }
}

if (!function_exists('remove_all_filters')) {
    function remove_all_filters($hook_name, $priority = false) {
        $hook_name = (string) $hook_name;

        if (!isset($GLOBALS['wp_filter'][$hook_name])) {
            return;
        }

        if (false === $priority) {
            unset($GLOBALS['wp_filter'][$hook_name]);

            return;
        }

        $priority = (int) $priority;
        unset($GLOBALS['wp_filter'][$hook_name][$priority]);

        if (empty($GLOBALS['wp_filter'][$hook_name])) {
            unset($GLOBALS['wp_filter'][$hook_name]);
        }
    }
}

if (!function_exists('remove_all_actions')) {
    function remove_all_actions($hook_name, $priority = false) {
        remove_all_filters($hook_name, $priority);
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = null) {
        return (string) $text;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = null) {
        return (string) $text;
    }
}

if (!function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = null) {
        echo esc_html__($text, $domain);
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return (string) $text;
    }
}

if (!function_exists('esc_attr_e')) {
    function esc_attr_e($text, $domain = null) {
        echo esc_attr($text);
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return (string) $url;
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        return (string) $url;
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

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($dir) {
        if ('' === $dir) {
            return false;
        }

        if (is_dir($dir)) {
            return true;
        }

        return mkdir($dir, 0777, true);
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        if (isset($GLOBALS['_wp_upload_dir_override']) && is_callable($GLOBALS['_wp_upload_dir_override'])) {
            return (array) call_user_func($GLOBALS['_wp_upload_dir_override']);
        }

        $base = sys_get_temp_dir() . '/tejlg-tests-uploads';
        wp_mkdir_p($base);

        return [
            'basedir' => $base,
            'baseurl' => 'https://example.com/wp-content/uploads',
        ];
    }
}

if (!function_exists('wp_unique_filename')) {
    function wp_unique_filename($dir, $filename) {
        $dir      = trailingslashit($dir);
        $filename = (string) $filename;

        if ('' === $filename) {
            $filename = 'file';
        }

        $info = pathinfo($filename);
        $ext  = isset($info['extension']) && '' !== $info['extension'] ? '.' . $info['extension'] : '';
        $name = isset($info['filename']) ? $info['filename'] : basename($filename, $ext);

        $candidate = $name;
        $number    = 1;

        while (file_exists($dir . $candidate . $ext)) {
            $candidate = $name . '-' . $number;
            $number++;
        }

        return $candidate . $ext;
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'http://example.com/wp-admin/' . ltrim((string) $path, '/');
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url = '') {
        if (!is_array($args)) {
            $args = [];
        }

        $url = (string) $url;
        $parsed = parse_url($url);
        $base   = isset($parsed['scheme']) ? substr($url, 0, strpos($url, '?') ?: strlen($url)) : $url;

        $existing = [];

        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $existing);
        }

        $query = array_merge($existing, $args);

        return $base . '?' . http_build_query($query);
    }
}

if (!function_exists('wp_validate_redirect')) {
    function wp_validate_redirect($location, $fallback = '') {
        $location = (string) $location;

        if ('' === $location) {
            return (string) $fallback;
        }

        return $location;
    }
}

if (!function_exists('wp_safe_redirect')) {
    function wp_safe_redirect($location, $status = 302) {
        $GLOBALS['wp_safe_redirect_last'] = [
            'location' => (string) $location,
            'status'   => (int) $status,
        ];
    }
}

if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = []) {
        throw new RuntimeException(is_string($message) ? $message : 'wp_die');
    }
}

if (!function_exists('wp_tempnam')) {
    function wp_tempnam($filename) {
        $filename = '' !== $filename ? $filename : 'wp';

        return tempnam(sys_get_temp_dir(), preg_replace('/[^a-zA-Z0-9]/', '', (string) $filename));
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return 'nonce-' . (string) $action;
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        return $nonce === 'nonce-' . (string) $action;
    }
}

if (!function_exists('check_admin_referer')) {
    function check_admin_referer($action = -1, $query_arg = '_wpnonce') {
        $nonce = isset($_REQUEST[$query_arg]) ? $_REQUEST[$query_arg] : '';

        if (!wp_verify_nonce($nonce, $action)) {
            wp_die('check_admin_referer_failed');
        }
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $echo = true) {
        unset($referer); // unused in tests
        $field = '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr(wp_create_nonce($action)) . '">';

        if ($echo) {
            echo $field;
        }

        return $field;
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

if (!function_exists('add_settings_error')) {
    $GLOBALS['settings_errors'] = [];

    function add_settings_error($setting, $code, $message, $type = 'error') {
        $setting = (string) $setting;

        if (!isset($GLOBALS['settings_errors'][$setting])) {
            $GLOBALS['settings_errors'][$setting] = [];
        }

        $GLOBALS['settings_errors'][$setting][] = [
            'code'    => (string) $code,
            'message' => (string) $message,
            'type'    => (string) $type,
        ];
    }
}

if (!function_exists('get_settings_errors')) {
    function get_settings_errors($setting = '', $sanitize = false) {
        unset($sanitize);

        if ('' === $setting) {
            return $GLOBALS['settings_errors'];
        }

        return isset($GLOBALS['settings_errors'][$setting]) ? $GLOBALS['settings_errors'][$setting] : [];
    }
}

if (!function_exists('settings_errors')) {
    function settings_errors($setting = '') {
        return get_settings_errors($setting);
    }
}

if (!function_exists('set_transient')) {
    $GLOBALS['wp_transients'] = [];

    function set_transient($name, $value, $expiration = 0) {
        $GLOBALS['wp_transients'][(string) $name] = $value;

        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($name) {
        $name = (string) $name;

        return isset($GLOBALS['wp_transients'][$name]) ? $GLOBALS['wp_transients'][$name] : false;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($name) {
        unset($GLOBALS['wp_transients'][(string) $name]);

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

if (!function_exists('current_user_can')) {
    $GLOBALS['current_user_caps'] = [];

    function current_user_can($cap) {
        return in_array((string) $cap, $GLOBALS['current_user_caps'], true);
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '') {
        return 'https://example.com' . ('' !== $path ? '/' . ltrim((string) $path, '/') : '');
    }
}

if (!function_exists('site_url')) {
    function site_url($path = '') {
        return 'https://example.com' . ('' !== $path ? '/' . ltrim((string) $path, '/') : '');
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }

        return is_string($value) ? stripslashes($value) : $value;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) {
        $value = is_scalar($value) ? (string) $value : '';

        return trim(strip_tags($value));
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        return filter_var((string) $email, FILTER_SANITIZE_EMAIL);
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($value) {
        return sanitize_text_field($value);
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = []) {
        return array_merge($defaults, is_array($args) ? $args : []);
    }
}

if (!function_exists('nocache_headers')) {
    function nocache_headers() {
        // no-op in tests
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
        /** @var array<string,mixed> */
        private static $schedule_settings = [
            'frequency'      => 'disabled',
            'exclusions'     => '',
            'retention_days' => 30,
            'run_time'       => '00:00',
        ];

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

        public static function get_default_schedule_settings() {
            return self::$schedule_settings;
        }

        public static function get_available_schedule_frequencies() {
            return [
                'disabled'   => 'Disabled',
                'hourly'     => 'Hourly',
                'twicedaily' => 'Twice Daily',
                'daily'      => 'Daily',
                'weekly'     => 'Weekly',
            ];
        }

        public static function get_schedule_settings() {
            return self::$schedule_settings;
        }

        public static function update_schedule_settings($settings) {
            if (!is_array($settings)) {
                $settings = [];
            }

            $defaults  = self::get_default_schedule_settings();
            $frequency = isset($settings['frequency']) ? sanitize_key((string) $settings['frequency']) : $defaults['frequency'];

            if (!isset(self::get_available_schedule_frequencies()[$frequency])) {
                $frequency = $defaults['frequency'];
            }

            $exclusions = isset($settings['exclusions']) ? (string) $settings['exclusions'] : $defaults['exclusions'];
            $retention = isset($settings['retention_days']) ? (int) $settings['retention_days'] : $defaults['retention_days'];
            $retention = $retention < 0 ? 0 : $retention;
            $run_time  = isset($settings['run_time']) ? (string) $settings['run_time'] : $defaults['run_time'];

            if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $run_time)) {
                $run_time = $defaults['run_time'];
            }

            self::$schedule_settings = [
                'frequency'      => $frequency,
                'exclusions'     => $exclusions,
                'retention_days' => $retention,
                'run_time'       => $run_time,
            ];

            return self::$schedule_settings;
        }

        public static function sanitize_exclusion_patterns_string($patterns) {
            if (is_array($patterns)) {
                $patterns = implode("\n", $patterns);
            }

            return trim((string) $patterns);
        }

        public static function reschedule_theme_export_event() {
            // no-op in tests
        }

        public static function ensure_cleanup_event_scheduled() {
            // no-op in tests
        }

        public static function cleanup_persisted_archives($retention_days) {
            unset($retention_days); // no-op in tests
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

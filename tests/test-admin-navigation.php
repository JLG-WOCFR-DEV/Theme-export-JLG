<?php

use PHPUnit\Framework\TestCase;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

if (!defined('TEJLG_PATH')) {
    define('TEJLG_PATH', __DIR__ . '/../theme-export-jlg/');
}

if (!defined('TEJLG_URL')) {
    define('TEJLG_URL', 'https://example.com/wp-content/plugins/theme-export-jlg/');
}

if (!defined('TEJLG_VERSION')) {
    define('TEJLG_VERSION', 'test');
}

if (!function_exists('get_admin_page_title')) {
    function get_admin_page_title() {
        return 'Theme Export - JLG';
    }
}

if (!function_exists('checked')) {
    function checked($checked, $current = true, $echo = true) {
        $result = ($checked == $current) ? 'checked="checked"' : '';

        if ($echo) {
            echo $result;
        }

        return $result;
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in() {
        return !empty($GLOBALS['current_user_caps']);
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return 1;
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key, $single = false) {
        $user_id = (int) $user_id;
        $key     = (string) $key;

        if (!isset($GLOBALS['user_meta_store'][$user_id])) {
            return $single ? '' : [];
        }

        if (!isset($GLOBALS['user_meta_store'][$user_id][$key])) {
            return $single ? '' : [];
        }

        return $GLOBALS['user_meta_store'][$user_id][$key];
    }
}

if (!function_exists('update_user_meta')) {
    function update_user_meta($user_id, $key, $value) {
        $user_id = (int) $user_id;
        $key     = (string) $key;
        $GLOBALS['user_meta_store'][$user_id][$key] = $value;

        return true;
    }
}

if (!function_exists('delete_user_meta')) {
    function delete_user_meta($user_id, $key) {
        $user_id = (int) $user_id;
        $key     = (string) $key;

        if (isset($GLOBALS['user_meta_store'][$user_id][$key])) {
            unset($GLOBALS['user_meta_store'][$user_id][$key]);
        }

        return true;
    }
}

if (!function_exists('wp_date')) {
    function wp_date($format, $timestamp) {
        return date($format, (int) $timestamp);
    }
}

if (!function_exists('size_format')) {
    function size_format($bytes, $decimals = 0) {
        $bytes = (int) $bytes;
        $decimals = (int) $decimals;

        return number_format($bytes, $decimals) . ' B';
    }
}

require_once TEJLG_PATH . 'includes/class-tejlg-capabilities.php';
require_once TEJLG_PATH . 'includes/class-tejlg-admin.php';
require_once TEJLG_PATH . 'includes/class-tejlg-export-history.php';

/**
 * @group admin
 */
class Test_Admin_Navigation extends TestCase {
    /** @var array<int,string> */
    private $previous_caps = [];

    /** @var array<string,mixed> */
    private $previous_get = [];

    protected function setUp(): void {
        parent::setUp();

        $this->previous_caps = isset($GLOBALS['current_user_caps']) ? $GLOBALS['current_user_caps'] : [];
        $this->previous_get  = isset($_GET) ? $_GET : [];

        TEJLG_Capabilities::init();
        TEJLG_Export_History::clear_history();

        $GLOBALS['current_user_caps'] = [
            TEJLG_Capabilities::MANAGE_PLUGIN,
            TEJLG_Capabilities::MANAGE_EXPORTS,
            TEJLG_Capabilities::MANAGE_IMPORTS,
            TEJLG_Capabilities::MANAGE_SETTINGS,
            TEJLG_Capabilities::MANAGE_DEBUG,
            'manage_options',
        ];
    }

    protected function tearDown(): void {
        $GLOBALS['current_user_caps'] = $this->previous_caps;
        $_GET = $this->previous_get;

        parent::tearDown();
    }

    public function test_render_admin_page_outputs_single_navigation_toolbar() {
        $_GET['page'] = 'theme-export-jlg';

        $admin = new TEJLG_Admin();

        ob_start();
        $admin->render_admin_page();
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertStringContainsString('Guide de Migration', $output, 'Full navigation should expose every available tab.');
        $this->assertSame(1, substr_count($output, 'tejlg-admin-toolbar__nav'), 'Only one navigation toolbar should be rendered.');
        $this->assertStringNotContainsString('tejlg-section-summary', $output, 'Section summary navigation should not be rendered.');
    }
}

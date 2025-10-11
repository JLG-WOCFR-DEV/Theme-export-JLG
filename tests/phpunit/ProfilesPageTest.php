<?php

use PHPUnit\Framework\TestCase;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!defined('TEJLG_PATH')) {
    define('TEJLG_PATH', dirname(__DIR__, 2) . '/theme-export-jlg/');
}

if (!defined('TEJLG_URL')) {
    define('TEJLG_URL', 'https://example.com/wp-content/plugins/theme-export-jlg/');
}

if (!defined('TEJLG_VERSION')) {
    define('TEJLG_VERSION', 'test');
}

require_once TEJLG_PATH . 'includes/class-tejlg-capabilities.php';
require_once TEJLG_PATH . 'includes/class-tejlg-admin-page.php';
require_once TEJLG_PATH . 'includes/class-tejlg-admin-export-page.php';
require_once TEJLG_PATH . 'includes/class-tejlg-admin-import-page.php';
require_once TEJLG_PATH . 'includes/class-tejlg-admin-debug-page.php';
require_once TEJLG_PATH . 'includes/class-tejlg-admin-profiles-page.php';
require_once TEJLG_PATH . 'includes/class-tejlg-export-notifications.php';
require_once TEJLG_PATH . 'includes/class-tejlg-export-history.php';
require_once TEJLG_PATH . 'includes/class-tejlg-settings.php';
require_once TEJLG_PATH . 'includes/class-tejlg-admin.php';

class ProfilesPageTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();

        $GLOBALS['settings_errors']   = [];
        $GLOBALS['wp_transients']     = [];
        $GLOBALS['current_user_caps'] = [];
        $_REQUEST = [];
        $_FILES   = [];
    }

    public function test_profiles_tab_requires_settings_capability() {
        global $current_user_caps;

        $current_user_caps = [];
        $admin = new TEJLG_Admin();

        $tabs = $this->getAccessibleTabs($admin);
        $this->assertArrayNotHasKey('profiles', $tabs);

        $current_user_caps = [
            'tejlg_manage_plugin',
            'tejlg_manage_exports',
            'tejlg_manage_imports',
            'tejlg_manage_debug',
            'tejlg_manage_settings',
        ];

        $tabs = $this->getAccessibleTabs($admin);
        $this->assertArrayHasKey('profiles', $tabs);
    }

    public function test_export_returns_signed_package() {
        $_REQUEST['action'] = 'tejlg_profiles_export';

        $page = $this->createProfilesPage();
        $result = $page->handle_request();

        $this->assertIsArray($result);
        $this->assertSame('export', $result['type']);
        $this->assertArrayHasKey('json', $result);

        $package = json_decode($result['json'], true);
        $this->assertIsArray($package);
        $this->assertSame(TEJLG_Settings::EXPORT_SCHEMA, $package['schema']);

        $signature = TEJLG_Settings::verify_signature($package);
        $this->assertTrue($signature['valid']);
    }

    public function test_import_applies_snapshot_and_reports_errors() {
        $page = $this->createProfilesPage();

        $package = TEJLG_Settings::build_export_package();
        $json    = TEJLG_Settings::encode_export_package($package);
        $this->assertIsString($json);

        $tmp = tempnam(sys_get_temp_dir(), 'tejlg');
        file_put_contents($tmp, $json);

        $_REQUEST['action'] = 'tejlg_profiles_import';
        $_FILES['tejlg_profiles_file'] = [
            'tmp_name' => $tmp,
            'error'    => UPLOAD_ERR_OK,
            'name'     => 'profiles.json',
        ];

        $result = $page->handle_request();

        $this->assertIsArray($result);
        $this->assertSame('success', $result['status']);

        $messages = get_settings_errors('tejlg_profiles_messages');
        $this->assertNotEmpty($messages);
        $types = array_column($messages, 'type');
        $this->assertTrue(in_array('success', $types, true) || in_array('info', $types, true));
    }

    public function test_import_rejects_invalid_signature() {
        $page = $this->createProfilesPage();

        $package = TEJLG_Settings::build_export_package();
        $package['signature']['hash'] = 'invalid';
        $json = TEJLG_Settings::encode_export_package($package);
        $this->assertIsString($json);

        $tmp = tempnam(sys_get_temp_dir(), 'tejlg');
        file_put_contents($tmp, $json);

        $_REQUEST['action'] = 'tejlg_profiles_import';
        $_FILES['tejlg_profiles_file'] = [
            'tmp_name' => $tmp,
            'error'    => UPLOAD_ERR_OK,
            'name'     => 'profiles.json',
        ];

        $result = $page->handle_request();

        $this->assertInstanceOf(WP_Error::class, $result);

        $messages = get_settings_errors('tejlg_profiles_messages');
        $this->assertNotEmpty($messages);
        $this->assertSame('error', $messages[0]['type']);
    }

    private function createProfilesPage() {
        return new TEJLG_Admin_Profiles_Page(TEJLG_PATH . 'templates/admin/', 'theme-export-jlg');
    }

    private function getAccessibleTabs(TEJLG_Admin $admin) {
        $method = new ReflectionMethod(TEJLG_Admin::class, 'get_accessible_tabs');
        $method->setAccessible(true);

        return $method->invoke($admin);
    }
}

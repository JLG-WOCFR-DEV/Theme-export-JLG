<?php

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-admin-page.php';
require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-admin-import-page.php';
require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-import.php';

/**
 * @group import-theme
 */
class Test_Import_Theme extends WP_UnitTestCase {

    protected function tearDown(): void {
        parent::tearDown();

        global $wp_settings_errors;
        $wp_settings_errors = [];
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_import_theme_aborts_when_file_mods_disallowed() {
        define('DISALLOW_FILE_MODS', true);

        $admin_id = self::factory()->user->create([
            'role' => 'administrator',
        ]);

        wp_set_current_user($admin_id);

        $temp_file = tempnam(sys_get_temp_dir(), 'tejlg_import_');
        file_put_contents($temp_file, 'dummy');

        $this->assertFileExists($temp_file);

        $file_upload_upgrader_loaded_before = class_exists('File_Upload_Upgrader', false);

        TEJLG_Import::import_theme([
            'tmp_name' => $temp_file,
            'name'     => 'dummy-theme.zip',
        ]);

        $this->assertFileDoesNotExist($temp_file);

        $messages = get_settings_errors('tejlg_import_messages');

        $this->assertNotEmpty($messages);
        $this->assertSame('theme_import_file_mods_disabled', $messages[0]['code']);
        $this->assertSame('error', $messages[0]['type']);
        $this->assertStringContainsString(
            "Erreur : Les modifications de fichiers sont désactivées sur ce site.",
            $messages[0]['message']
        );

        if (! $file_upload_upgrader_loaded_before) {
            $this->assertFalse(class_exists('File_Upload_Upgrader', false));
        }
    }

    public function test_finalize_theme_install_result_requires_confirmation_before_overwriting_existing_theme() {
        $error = new WP_Error('folder_exists', 'Destination folder already exists.');

        TEJLG_Import::finalize_theme_install_result($error, false);

        $messages = get_settings_errors('tejlg_import_messages');

        $this->assertNotEmpty($messages);
        $this->assertSame('theme_import_status', $messages[0]['code']);
        $this->assertSame('error', $messages[0]['type']);
        $this->assertStringContainsString(
            "Veuillez relancer l'import en confirmant le remplacement explicite.",
            $messages[0]['message']
        );
    }

    public function test_finalize_theme_install_result_reports_success_when_overwrite_allowed() {
        TEJLG_Import::finalize_theme_install_result(true, true);

        $messages = get_settings_errors('tejlg_import_messages');

        $this->assertNotEmpty($messages);
        $this->assertSame('theme_import_status', $messages[0]['code']);
        $this->assertSame('success', $messages[0]['type']);
        $this->assertStringContainsString(
            "Le thème a été installé avec succès !",
            $messages[0]['message']
        );
    }

    public function test_finalize_theme_install_result_does_not_request_confirmation_when_overwrite_allowed_but_install_fails() {
        $error = new WP_Error('folder_exists', 'Destination folder already exists.');

        TEJLG_Import::finalize_theme_install_result($error, true);

        $messages = get_settings_errors('tejlg_import_messages');

        $this->assertNotEmpty($messages);
        $this->assertSame('theme_import_status', $messages[0]['code']);
        $this->assertSame('error', $messages[0]['type']);
        $this->assertStringNotContainsString(
            "Veuillez relancer l'import en confirmant le remplacement explicite.",
            $messages[0]['message']
        );
    }

    public function test_normalize_overwrite_flag_uses_wp_validate_boolean_when_available() {
        $page = new TEJLG_Admin_Import_Page(
            dirname(__DIR__) . '/theme-export-jlg/templates/admin',
            'theme-export-jlg-import'
        );

        $method = new ReflectionMethod(TEJLG_Admin_Import_Page::class, 'normalize_overwrite_flag');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($page, true));
        $this->assertTrue($method->invoke($page, '1'));
        $this->assertTrue($method->invoke($page, 'YeS'));
        $this->assertTrue($method->invoke($page, 'on'));

        $this->assertFalse($method->invoke($page, false));
        $this->assertFalse($method->invoke($page, '0'));
        $this->assertFalse($method->invoke($page, 'off'));
        $this->assertFalse($method->invoke($page, 'random-value'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_normalize_overwrite_flag_falls_back_when_wp_validate_boolean_disabled() {
        define('TEJLG_FORCE_FALLBACK_BOOLEAN_VALIDATION', true);

        $page = new TEJLG_Admin_Import_Page(
            dirname(__DIR__) . '/theme-export-jlg/templates/admin',
            'theme-export-jlg-import'
        );

        $method = new ReflectionMethod(TEJLG_Admin_Import_Page::class, 'normalize_overwrite_flag');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($page, 'TRUE'));
        $this->assertTrue($method->invoke($page, 'yes'));
        $this->assertTrue($method->invoke($page, 'On'));

        $this->assertFalse($method->invoke($page, 'no'));
        $this->assertFalse($method->invoke($page, 'maybe'));
        $this->assertFalse($method->invoke($page, []));
    }
}

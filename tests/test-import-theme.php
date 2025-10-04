<?php

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

    public function test_import_theme_requires_confirmation_before_overwriting_existing_theme() {
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

    public function test_import_theme_reports_success_when_overwrite_allowed() {
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
}

<?php

use Exception;

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-import.php';

/**
 * @group import-validation
 */
class Test_Import_File_Validation extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();

        global $wp_settings_errors;
        $wp_settings_errors = null;
    }

    public function tearDown(): void {
        global $wp_settings_errors;
        $wp_settings_errors = null;

        parent::tearDown();
    }

    public function test_import_global_styles_rejects_non_json_files(): void {
        $temp_file = tempnam(sys_get_temp_dir(), 'tejlg_import_');
        $this->assertIsString($temp_file);

        $json_path = $temp_file . '.txt';
        $this->assertTrue(rename($temp_file, $json_path));
        $this->assertNotFalse(file_put_contents($json_path, '{"data": {}}'));

        TEJLG_Import::import_global_styles([
            'tmp_name' => $json_path,
            'name'     => 'styles.txt',
            'type'     => 'text/plain',
            'size'     => filesize($json_path),
        ]);

        $this->assertFileDoesNotExist($json_path);

        $messages = get_settings_errors('tejlg_import_messages');

        $this->assertNotEmpty($messages);
        $this->assertSame('global_styles_import_invalid_type', $messages[0]['code']);
        $this->assertSame('error', $messages[0]['type']);
    }

    public function test_import_global_styles_accepts_json_files(): void {
        $temp_file = tempnam(sys_get_temp_dir(), 'tejlg_import_');
        $this->assertIsString($temp_file);

        $json_path = $temp_file . '.json';
        $this->assertTrue(rename($temp_file, $json_path));
        $this->assertNotFalse(file_put_contents($json_path, wp_json_encode([
            'data' => [
                'settings'   => [],
                'stylesheet' => 'body { color: red; }',
            ],
        ])));

        TEJLG_Import::import_global_styles([
            'tmp_name' => $json_path,
            'name'     => 'styles.json',
            'type'     => 'application/json',
            'size'     => filesize($json_path),
        ]);

        $this->assertFileDoesNotExist($json_path);

        $messages = get_settings_errors('tejlg_import_messages');

        foreach ($messages as $message) {
            $this->assertNotSame('global_styles_import_invalid_type', $message['code']);
        }
    }

    public function test_handle_patterns_upload_step1_rejects_non_json_files(): void {
        $temp_file = tempnam(sys_get_temp_dir(), 'tejlg_patterns_');
        $this->assertIsString($temp_file);

        $json_path = $temp_file . '.txt';
        $this->assertTrue(rename($temp_file, $json_path));
        $this->assertNotFalse(file_put_contents($json_path, '[{"title":"One"}]'));

        TEJLG_Import::handle_patterns_upload_step1([
            'tmp_name' => $json_path,
            'name'     => 'patterns.txt',
            'type'     => 'text/plain',
            'size'     => filesize($json_path),
        ]);

        $this->assertFileDoesNotExist($json_path);

        $messages = get_settings_errors('tejlg_import_messages');

        $this->assertNotEmpty($messages);
        $this->assertSame('patterns_import_invalid_type', $messages[0]['code']);
        $this->assertSame('error', $messages[0]['type']);
    }

    public function test_handle_patterns_upload_step1_accepts_json_files(): void {
        $temp_file = tempnam(sys_get_temp_dir(), 'tejlg_patterns_');
        $this->assertIsString($temp_file);

        $json_path = $temp_file . '.json';
        $this->assertTrue(rename($temp_file, $json_path));
        $this->assertNotFalse(file_put_contents($json_path, wp_json_encode([
            [
                'title'   => 'Pattern',
                'content' => '<!-- wp:paragraph --><p>Example</p><!-- /wp:paragraph -->',
            ],
        ])));

        $redirect_exception = function ($location) {
            throw new Exception('redirected');
        };

        add_filter('wp_redirect', $redirect_exception, 10, 2);

        try {
            TEJLG_Import::handle_patterns_upload_step1([
                'tmp_name' => $json_path,
                'name'     => 'patterns.json',
                'type'     => 'application/json',
                'size'     => filesize($json_path),
            ]);
            $this->fail('Expected redirect exception was not thrown.');
        } catch (Exception $e) {
            $this->assertSame('redirected', $e->getMessage());
        } finally {
            remove_filter('wp_redirect', $redirect_exception, 10);
        }

        $this->assertFileDoesNotExist($json_path);

        $messages = get_settings_errors('tejlg_import_messages');

        foreach ($messages as $message) {
            $this->assertNotSame('patterns_import_invalid_type', $message['code']);
        }
    }
}

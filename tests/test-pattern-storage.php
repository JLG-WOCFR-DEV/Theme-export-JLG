<?php

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-import.php';

/**
 * @group pattern-storage
 */
class Test_Pattern_Storage extends WP_UnitTestCase {

    /** @var ReflectionMethod */
    private $persist_method;

    /** @var ReflectionMethod */
    private $cleanup_method;

    public function setUp(): void {
        parent::setUp();

        $this->persist_method = new ReflectionMethod(TEJLG_Import::class, 'persist_patterns_session');
        $this->persist_method->setAccessible(true);

        $this->cleanup_method = new ReflectionMethod(TEJLG_Import::class, 'cleanup_patterns_storage');
        $this->cleanup_method->setAccessible(true);
    }

    public function tearDown(): void {
        $this->persist_method = null;
        $this->cleanup_method = null;

        parent::tearDown();
    }

    public function test_persist_and_retrieve_patterns_success(): void {
        $patterns = [
            [
                'title'   => 'Example pattern',
                'content' => '<!-- wp:paragraph -->Example<!-- /wp:paragraph -->',
                'slug'    => 'example-pattern',
            ],
        ];

        $transient_id = 'tejlg_test_transient_' . wp_generate_password(8, false, false);

        $result = $this->persist_method->invoke(null, $transient_id, $patterns);

        $this->assertTrue($result);

        $storage = get_transient($transient_id);

        $this->assertIsArray($storage);
        $this->assertSame('file', $storage['type']);
        $this->assertArrayHasKey('path', $storage);
        $this->assertFileExists($storage['path']);

        $retrieved = TEJLG_Import::retrieve_patterns_from_storage($storage);

        $this->assertSame($patterns, $retrieved);

        $this->cleanup_method->invoke(null, $storage);
        delete_transient($transient_id);
    }

    public function test_persist_patterns_session_cleans_previous_storage(): void {
        $patterns = [
            [
                'title'   => 'Replacement pattern',
                'content' => '<!-- wp:heading --><h2>Replacement</h2><!-- /wp:heading -->',
                'slug'    => 'replacement-pattern',
            ],
        ];

        $previous_file = wp_tempnam('tejlg-previous-patterns');
        file_put_contents($previous_file, '{"replaced":true}');

        $previous_storage = [
            'type' => 'file',
            'path' => $previous_file,
        ];

        $transient_id = 'tejlg_test_transient_' . wp_generate_password(8, false, false);

        $result = $this->persist_method->invoke(null, $transient_id, $patterns, $previous_storage);

        $this->assertTrue($result);
        clearstatcache();
        $this->assertFileDoesNotExist($previous_file, 'Previous storage should be removed.');

        $storage = get_transient($transient_id);

        $this->assertIsArray($storage);
        $this->assertFileExists($storage['path']);

        $this->cleanup_method->invoke(null, $storage);
        delete_transient($transient_id);
    }

    public function test_retrieve_patterns_from_storage_detects_tampered_file(): void {
        $patterns = [
            [
                'title'   => 'Tampered pattern',
                'content' => '<!-- wp:paragraph -->Original<!-- /wp:paragraph -->',
                'slug'    => 'tampered-pattern',
            ],
        ];

        $transient_id = 'tejlg_test_transient_' . wp_generate_password(8, false, false);

        $this->persist_method->invoke(null, $transient_id, $patterns);

        $storage = get_transient($transient_id);
        $this->assertIsArray($storage);
        $this->assertFileExists($storage['path']);

        // Tamper with the payload to trigger the checksum/size validation.
        file_put_contents($storage['path'], '[]');

        $error = TEJLG_Import::retrieve_patterns_from_storage($storage);

        $this->assertInstanceOf(WP_Error::class, $error);
        $this->assertSame('tejlg_import_storage_size_mismatch', $error->get_error_code());

        clearstatcache();
        $this->assertFileDoesNotExist($storage['path'], 'Tampered storage should be cleaned up.');

        delete_transient($transient_id);
    }

    public function test_cleanup_patterns_storage_removes_unreadable_file(): void {
        $temp_file = wp_tempnam('tejlg-patterns-cleanup');
        file_put_contents($temp_file, 'temporary data');
        chmod($temp_file, 0000);

        $storage = [
            'type' => 'file',
            'path' => $temp_file,
        ];

        $this->cleanup_method->invoke(null, $storage);

        clearstatcache();
        $this->assertFileDoesNotExist($temp_file);
    }

    public function test_cleanup_patterns_storage_ignores_missing_file(): void {
        $temp_file = wp_tempnam('tejlg-patterns-missing');
        file_put_contents($temp_file, 'temporary data');
        unlink($temp_file);

        $storage = [
            'type' => 'file',
            'path' => $temp_file,
        ];

        $this->cleanup_method->invoke(null, $storage);

        clearstatcache();
        $this->assertFileDoesNotExist($temp_file);
    }
}

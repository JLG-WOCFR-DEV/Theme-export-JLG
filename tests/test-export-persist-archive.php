<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export.php';

class Test_Export_Persist_Archive extends TestCase {
    /** @var array<int,string> */
    private $paths_to_cleanup = [];

    protected function setUp(): void {
        parent::setUp();

        if (function_exists('remove_all_actions')) {
            remove_all_actions('tejlg_export_persist_archive_failed');
        }
    }

    protected function tearDown(): void {
        if (function_exists('remove_all_actions')) {
            remove_all_actions('tejlg_export_persist_archive_failed');
        }

        if (isset($GLOBALS['_wp_upload_dir_override'])) {
            unset($GLOBALS['_wp_upload_dir_override']);
        }

        foreach (array_reverse($this->paths_to_cleanup) as $path) {
            $this->remove_path($path);
        }

        $this->paths_to_cleanup = [];

        parent::tearDown();
    }

    public function test_fires_hook_when_zip_source_is_missing(): void {
        $captured = [];

        add_action(
            'tejlg_export_persist_archive_failed',
            static function ($job, $context) use (&$captured) {
                $captured[] = [
                    'job'     => $job,
                    'context' => $context,
                ];
            },
            10,
            2
        );

        $job = [
            'id'       => 'job-123',
            'zip_path' => '/path/to/missing.zip',
        ];

        $result = TEJLG_Export::persist_export_archive($job);

        $this->assertSame(
            [
                'path' => '',
                'url'  => '',
            ],
            $result,
            'The method should return empty paths when the source file is missing.'
        );

        $this->assertNotEmpty($captured, 'The failure hook should be triggered.');
        $this->assertSame('source_missing', $captured[0]['context']['reason']);
        $this->assertSame('job-123', $captured[0]['context']['job_id']);
    }

    public function test_log_filter_allows_disabling_error_logging(): void {
        $logs = [];

        $log_filter = static function ($should_log, $reason, $job, $payload) use (&$logs) {
            $logs[] = [
                'should_log' => $should_log,
                'reason'     => $reason,
                'payload'    => $payload,
            ];

            return false;
        };

        add_filter('tejlg_export_persist_archive_log_errors', $log_filter, 10, 4);

        $job = [
            'id'       => 'job-456',
            'zip_path' => '',
        ];

        TEJLG_Export::persist_export_archive($job);

        $this->assertNotEmpty($logs, 'The logging filter should be invoked.');
        $this->assertSame('source_missing', $logs[0]['reason']);
        $this->assertSame('job-456', $logs[0]['payload']['job_id']);

        remove_filter('tejlg_export_persist_archive_log_errors', $log_filter, 10);
    }

    public function test_creates_guard_files_only_once(): void {
        $root = sys_get_temp_dir() . '/tejlg-guard-' . uniqid('', true);
        $uploads_basedir = $root . '/uploads';
        $this->register_cleanup_path($root);

        $GLOBALS['_wp_upload_dir_override'] = static function () use ($uploads_basedir) {
            return [
                'basedir' => $uploads_basedir,
                'baseurl' => 'https://example.com/uploads',
            ];
        };

        $zip_source = $root . '/source.zip';
        wp_mkdir_p(dirname($zip_source));
        file_put_contents($zip_source, 'zip-content');
        $this->register_cleanup_path($zip_source);

        $job = [
            'id'            => 'guard-test',
            'zip_path'      => $zip_source,
            'zip_file_name' => 'archive.zip',
        ];

        $result = TEJLG_Export::persist_export_archive($job);

        $this->assertNotSame('', $result['path'], 'The archive should be persisted.');

        $guard_directory = $uploads_basedir . '/theme-export-jlg';
        $this->assertDirectoryExists($guard_directory);

        $guard_files = [
            'index.html',
            '.htaccess',
            'web.config',
        ];

        foreach ($guard_files as $filename) {
            $this->assertFileExists($guard_directory . '/' . $filename);
        }

        $index_path = $guard_directory . '/index.html';
        file_put_contents($index_path, 'custom-landing');

        $second_job = [
            'id'            => 'guard-test-2',
            'zip_path'      => $zip_source,
            'zip_file_name' => 'archive.zip',
        ];

        TEJLG_Export::persist_export_archive($second_job);

        $this->assertSame('custom-landing', file_get_contents($index_path), 'Existing guard files should not be overwritten.');
    }

    private function register_cleanup_path($path): void {
        if (is_string($path) && '' !== $path) {
            $this->paths_to_cleanup[] = $path;
        }
    }

    private function remove_path($path): void {
        if (is_dir($path)) {
            try {
                $items = new FilesystemIterator($path, FilesystemIterator::CURRENT_AS_PATHNAME | FilesystemIterator::SKIP_DOTS);

                foreach ($items as $item) {
                    $this->remove_path($item);
                }
            } catch (UnexpectedValueException $exception) {
                // Directory is not readable, ignore silently for test cleanup.
            }

            @rmdir($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

            return;
        }

        if (file_exists($path)) {
            @unlink($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        }
    }
}

<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export.php';

class Test_Export_Persist_Archive extends TestCase {
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
}

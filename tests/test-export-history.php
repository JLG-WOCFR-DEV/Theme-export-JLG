<?php

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export-history.php';
require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export.php';
require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-admin-export-page.php';

class Test_Export_History extends WP_UnitTestCase {
    protected function setUp(): void {
        parent::setUp();
        TEJLG_Export_History::clear_history();
    }

    protected function tearDown(): void {
        TEJLG_Export_History::clear_history();
        parent::tearDown();
    }

    public function test_record_job_persists_summary() {
        $temp_file = wp_tempnam('history-test.zip');
        $this->assertNotFalse($temp_file, 'Temporary file should be created.');

        file_put_contents($temp_file, 'demo');

        $start_time     = time() - 120;
        $update_time    = $start_time + 30;
        $completed_time = $start_time + 45;

        $job = [
            'id'              => 'history-job-1',
            'status'          => 'completed',
            'zip_path'        => $temp_file,
            'zip_file_name'   => 'history-job-1.zip',
            'zip_file_size'   => filesize($temp_file),
            'exclusions'      => ['node_modules', '*.log'],
            'created_at'      => $start_time,
            'updated_at'      => $update_time,
            'completed_at'    => $completed_time,
            'created_by'      => 123,
            'created_by_name' => 'History Tester',
            'created_via'     => 'test',
        ];

        TEJLG_Export_History::record_job($job, ['origin' => 'unit-test']);

        $history = TEJLG_Export_History::get_entries([
            'per_page' => 5,
            'paged'    => 1,
        ]);

        $this->assertNotEmpty($history['entries'], 'History should contain the recorded job.');
        $entry = $history['entries'][0];

        $this->assertSame('history-job-1', $entry['job_id']);
        $this->assertSame('History Tester', $entry['user_name']);
        $this->assertSame(['node_modules', '*.log'], $entry['exclusions']);
        $this->assertSame(filesize($temp_file), $entry['zip_file_size']);
        $this->assertSame(45, $entry['duration']);

        global $wpdb;
        $autoload = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s",
                TEJLG_Export_History::OPTION_NAME
            )
        );

        $this->assertSame('no', $autoload, 'History option should not be autoloaded.');
    }

    public function test_history_survives_job_deletion_and_renders_in_admin_template() {
        $user_id = self::factory()->user->create([
            'role'         => 'administrator',
            'display_name' => 'History User',
        ]);

        wp_set_current_user($user_id);

        $temp_file = wp_tempnam('history-job.zip');
        $this->assertNotFalse($temp_file, 'Temporary file should be created.');

        file_put_contents($temp_file, str_repeat('a', 1024));

        $start_time     = time() - 300;
        $updated_time   = $start_time + 60;
        $completed_time = $start_time + 75;

        $job_id = 'history-job-2';
        $job = [
            'id'              => $job_id,
            'status'          => 'completed',
            'zip_path'        => $temp_file,
            'zip_file_name'   => 'history-job-2.zip',
            'zip_file_size'   => filesize($temp_file),
            'exclusions'      => ['vendor'],
            'created_at'      => $start_time,
            'updated_at'      => $updated_time,
            'completed_at'    => $completed_time,
            'created_by'      => $user_id,
            'created_by_name' => 'History User',
            'created_via'     => 'admin',
        ];

        TEJLG_Export::persist_job($job);

        $this->assertFileExists($temp_file, 'Temporary export file should exist before deletion.');

        TEJLG_Export::delete_job($job_id, ['origin' => 'unit-test']);

        $this->assertFileDoesNotExist($temp_file, 'Temporary export file should be deleted by delete_job.');

        $history = TEJLG_Export_History::get_entries([
            'per_page' => 5,
            'paged'    => 1,
        ]);

        $this->assertNotEmpty($history['entries'], 'History should contain the deleted job.');
        $this->assertSame($job_id, $history['entries'][0]['job_id']);

        $template_dir = dirname(__DIR__) . '/theme-export-jlg/templates/admin/';
        $export_page  = new TEJLG_Admin_Export_Page($template_dir, 'theme-export-jlg');

        ob_start();
        $export_page->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('Historique des exports', $output);
        $this->assertStringContainsString('history-job-2.zip', $output);
        $this->assertStringContainsString('History User', $output);
        $this->assertStringContainsString('vendor', $output);
        $this->assertStringContainsString('1 minute 15 secondes', $output);

        wp_set_current_user(0);
    }

    public function test_generate_report_aggregates_totals_and_respects_filters() {
        $now = time();

        TEJLG_Export_History::record_job([
            'id'              => 'report-success',
            'status'          => 'completed',
            'zip_file_name'   => 'report-success.zip',
            'zip_file_size'   => 2048,
            'exclusions'      => ['node_modules'],
            'created_at'      => $now - 300,
            'updated_at'      => $now - 200,
            'completed_at'    => $now - 180,
            'created_by_name' => 'Reporter',
            'created_via'     => 'web',
            'duration'        => 60,
        ], [
            'origin'    => 'web',
            'timestamp' => $now - 180,
        ]);

        TEJLG_Export_History::record_job([
            'id'            => 'report-error',
            'status'        => 'failed',
            'zip_file_name' => 'report-error.zip',
            'zip_file_size' => 1024,
            'created_at'    => $now - 120,
            'updated_at'    => $now - 60,
            'completed_at'  => $now - 30,
            'created_via'   => 'cli',
            'duration'      => 90,
        ], [
            'origin'    => 'cli',
            'timestamp' => $now - 30,
        ]);

        $report = TEJLG_Export_History::generate_report([
            'window_days'     => 1,
            'include_entries' => true,
            'limit'           => 1,
        ]);

        $this->assertSame(2, $report['totals']['entries']);
        $this->assertSame(150, $report['totals']['duration_seconds']);
        $this->assertSame(3072, $report['totals']['archive_size_bytes']);
        $this->assertSame(75, $report['averages']['duration_seconds']);
        $this->assertSame(1536, $report['averages']['archive_size_bytes']);
        $this->assertSame(1, $report['counts']['results'][TEJLG_Export_History::RESULT_SUCCESS]);
        $this->assertSame(1, $report['counts']['results'][TEJLG_Export_History::RESULT_ERROR]);
        $this->assertArrayHasKey('cli', $report['counts']['origins']);
        $this->assertArrayHasKey('web', $report['counts']['origins']);
        $this->assertCount(1, $report['entries']);
        $this->assertSame('report-error', $report['entries'][0]['job_id'], 'Entries should be limited to the configured amount.');
    }

    public function test_report_ready_action_receives_payload() {
        $captured = null;

        $callback = function ($report, $entry, $job, $context, $args) use (&$captured) {
            $captured = [
                'report'  => $report,
                'entry'   => $entry,
                'job'     => $job,
                'context' => $context,
                'args'    => $args,
            ];
        };

        add_action('tejlg_export_history_report_ready', $callback, 10, 5);

        $now = time();

        TEJLG_Export_History::record_job([
            'id'            => 'report-hook',
            'status'        => 'completed',
            'zip_file_name' => 'report-hook.zip',
            'zip_file_size' => 4096,
            'created_at'    => $now - 100,
            'updated_at'    => $now - 50,
            'completed_at'  => $now - 25,
        ], [
            'origin' => 'web',
        ]);

        remove_action('tejlg_export_history_report_ready', $callback, 10);

        $this->assertNotNull($captured, 'Report hook should receive a payload when a job is recorded.');
        $this->assertSame('report-hook', $captured['entry']['job_id']);
        $this->assertArrayHasKey('totals', $captured['report']);
        $this->assertArrayHasKey('entries', $captured['report']);
        $this->assertEmpty($captured['report']['entries'], 'Default report generation should omit entry details.');
        $this->assertSame(10, $captured['args']['limit']);
    }
}

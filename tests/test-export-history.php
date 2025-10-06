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
}

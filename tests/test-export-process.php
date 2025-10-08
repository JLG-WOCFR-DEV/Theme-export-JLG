<?php

use PHPUnit\Framework\TestCase;

class TEJLG_Export_Process_Test_Double extends TEJLG_Export_Process {
    public function run_task($item) {
        return $this->task($item);
    }
}

/**
 * @group export-theme
 */
class Test_Export_Process extends TestCase {

    protected function setUp(): void {
        parent::setUp();

        if (!class_exists('ZipArchive')) {
            $this->markTestSkipped('ZipArchive is required to inspect generated archives.');
        }

        if (method_exists(TEJLG_Export::class, 'reset_jobs')) {
            TEJLG_Export::reset_jobs();
        }

        if (isset($GLOBALS['wp_options'])) {
            $GLOBALS['wp_options'] = [];
        }
    }

    protected function tearDown(): void {
        if (method_exists(TEJLG_Export::class, 'reset_jobs')) {
            TEJLG_Export::reset_jobs();
        }

        parent::tearDown();
    }

    public function test_root_level_files_do_not_create_dot_directories_in_zip() {
        $job_id  = sanitize_key('tejlg-process-' . wp_generate_uuid4());
        $zip_path = wp_tempnam('tejlg-process-zip');

        $this->assertIsString($zip_path, 'A temporary ZIP path should be generated.');
        $this->assertNotEmpty($zip_path, 'The temporary ZIP path should not be empty.');

        $writer = TEJLG_Zip_Writer::create($zip_path);
        $this->assertNotInstanceOf(WP_Error::class, $writer, 'The ZIP writer should initialize successfully.');

        if ($writer instanceof TEJLG_Zip_Writer) {
            $writer->add_directory('placeholder');
            $writer->close();
        }

        $job_payload = [
            'id'                => $job_id,
            'status'            => 'processing',
            'zip_path'          => $zip_path,
            'directories_added' => [
                'placeholder/' => true,
            ],
            'processed_items'   => 0,
            'total_items'       => 1,
            'created_at'        => time(),
            'updated_at'        => time(),
        ];

        TEJLG_Export::persist_job($job_payload);

        $file_path = wp_tempnam('tejlg-process-file');
        $this->assertIsString($file_path, 'A temporary file path should be created.');
        file_put_contents($file_path, 'Example content');

        $process = new TEJLG_Export_Process_Test_Double();
        $process->run_task([
            'job_id'               => $job_id,
            'type'                 => 'file',
            'real_path'            => $file_path,
            'relative_path_in_zip' => 'style.css',
        ]);

        $job_after = TEJLG_Export::get_job($job_id);
        $this->assertIsArray($job_after, 'The job should still be stored after processing.');
        $this->assertSame('completed', isset($job_after['status']) ? $job_after['status'] : '', 'Processing the last item should finalize the job.');

        $entries = [];

        $zip = new ZipArchive();
        $this->assertSame(true, $zip->open($zip_path), 'The generated archive should open with ZipArchive.');

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = $zip->getNameIndex($i);
        }

        $zip->close();

        $this->assertNotEmpty($entries, 'The archive should contain at least one entry.');
        $this->assertContains('style.css', $entries, 'The root-level file should be present in the archive.');

        foreach ($entries as $entry_name) {
            $normalized = rtrim((string) $entry_name, '/');
            $this->assertNotSame('.', $normalized, 'The archive should not contain a \'./\' directory entry.');
        }

        TEJLG_Export::delete_job($job_id);
        @unlink($zip_path);
        @unlink($file_path);
    }
}

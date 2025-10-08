<?php

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export.php';
require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export-process.php';
require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-zip-writer.php';

class TEJLG_Export_Process_Test_Double extends TEJLG_Export_Process {
    public function public_task($item) {
        return $this->task($item);
    }
}

/**
 * @group export-theme
 */
class Test_Export_Process extends WP_UnitTestCase {

    public function test_root_level_files_do_not_create_dot_directories_in_zip() {
        $job_id = sanitize_key('tejlg-process-' . wp_generate_uuid4());
        $zip_path = wp_tempnam('tejlg-process-zip');

        $this->assertIsString($zip_path, 'A temporary ZIP path should be generated.');
        $this->assertNotEmpty($zip_path, 'The temporary ZIP path should not be empty.');

        $writer = TEJLG_Zip_Writer::create($zip_path);
        $this->assertNotWPError($writer, 'The ZIP writer should initialize successfully.');

        if ($writer instanceof TEJLG_Zip_Writer) {
            $writer->close();
        }

        $job_payload = [
            'id'                => $job_id,
            'status'            => 'processing',
            'zip_path'          => $zip_path,
            'directories_added' => [],
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
        $process->public_task([
            'job_id'               => $job_id,
            'type'                 => 'file',
            'real_path'            => $file_path,
            'relative_path_in_zip' => 'style.css',
        ]);

        $job_after = TEJLG_Export::get_job($job_id);
        $this->assertIsArray($job_after, 'The job should still be stored after processing.');
        $this->assertSame('completed', isset($job_after['status']) ? $job_after['status'] : '', 'Processing the last item should finalize the job.');

        $entries = [];

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            $this->assertSame(true, $zip->open($zip_path), 'The generated archive should open with ZipArchive.');

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entries[] = $zip->getNameIndex($i);
            }

            $zip->close();
        } else {
            if (!class_exists('PclZip')) {
                require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
            }

            $pclzip = new PclZip($zip_path);
            $list   = $pclzip->listContent();

            $this->assertIsArray($list, 'PclZip should return the archive contents.');

            foreach ($list as $entry) {
                if (isset($entry['stored_filename'])) {
                    $entries[] = $entry['stored_filename'];
                } elseif (isset($entry['filename'])) {
                    $entries[] = $entry['filename'];
                }
            }
        }

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

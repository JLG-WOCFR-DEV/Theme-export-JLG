<?php
class TEJLG_Zip_Writer {
    const DRIVER_ZIPARCHIVE = 'ziparchive';
    const DRIVER_PCLZIP     = 'pclzip';

    /**
     * @var string
     */
    private $driver = '';

    /**
     * @var ZipArchive|PclZip|null
     */
    private $zip = null;

    /**
     * @var string
     */
    private $zip_path = '';

    /**
     * @var string
     */
    private $last_error = '';

    /**
     * Determines if ZipArchive is available and should be used.
     *
     * @return bool
     */
    public static function should_use_ziparchive() {
        $available = class_exists('ZipArchive');

        /**
         * Filters whether the ZipArchive driver should be used for writing archives.
         *
         * @param bool $available Whether ZipArchive is available.
         */
        return (bool) apply_filters('tejlg_zip_writer_use_ziparchive', $available);
    }

    /**
     * Creates a new writer instance prepared for writing a fresh archive.
     *
     * @param string $zip_path Absolute path to the archive on disk.
     *
     * @return TEJLG_Zip_Writer|WP_Error
     */
    public static function create($zip_path) {
        $zip_path = (string) $zip_path;

        $writer = new self();

        if ('' === $zip_path) {
            $writer->last_error = esc_html__("Le chemin du fichier ZIP est vide.", 'theme-export-jlg');
            return new WP_Error('tejlg_zip_writer_invalid_path', $writer->last_error);
        }

        if (!$writer->initialize($zip_path, true)) {
            return new WP_Error('tejlg_zip_writer_creation_failed', $writer->get_last_error());
        }

        return $writer;
    }

    /**
     * Opens an existing archive for append operations.
     *
     * @param string $zip_path Absolute path to the archive on disk.
     *
     * @return TEJLG_Zip_Writer|WP_Error
     */
    public static function open($zip_path) {
        $zip_path = (string) $zip_path;

        $writer = new self();

        if ('' === $zip_path) {
            $writer->last_error = esc_html__("Le chemin du fichier ZIP est vide.", 'theme-export-jlg');
            return new WP_Error('tejlg_zip_writer_invalid_path', $writer->last_error);
        }

        if (!$writer->initialize($zip_path, false)) {
            return new WP_Error('tejlg_zip_writer_open_failed', $writer->get_last_error());
        }

        return $writer;
    }

    /**
     * Adds an empty directory to the archive.
     *
     * @param string $directory_path Path to the directory inside the archive.
     *
     * @return bool
     */
    public function add_directory($directory_path) {
        $directory_path = rtrim((string) $directory_path, '/') . '/';

        if ('' === $directory_path) {
            $this->last_error = esc_html__("Le chemin du dossier dans l'archive est vide.", 'theme-export-jlg');
            return false;
        }

        if (self::DRIVER_ZIPARCHIVE === $this->driver) {
            return true === $this->zip->addEmptyDir($directory_path);
        }

        // PclZip does not support empty directories directly, so we emulate them
        // by creating a directory entry with no contents.
        $result = $this->zip->add([
            [
                PCLZIP_ATT_FILE_NAME          => $directory_path,
                PCLZIP_ATT_FILE_NEW_FULL_NAME => $directory_path,
                PCLZIP_ATT_FILE_CONTENT       => '',
            ],
        ]);

        if (0 === $result) {
            $this->last_error = $this->zip->errorInfo(true);
            return false;
        }

        return true;
    }

    /**
     * Adds a file to the archive.
     *
     * @param string $file_path Path to the real file on disk.
     * @param string $entry_path Target path inside the archive.
     *
     * @return bool
     */
    public function add_file($file_path, $entry_path) {
        $file_path  = (string) $file_path;
        $entry_path = (string) $entry_path;

        if ('' === $file_path || '' === $entry_path) {
            $this->last_error = esc_html__("Les chemins d'ajout de fichier à l'archive sont invalides.", 'theme-export-jlg');
            return false;
        }

        if (!file_exists($file_path)) {
            $this->last_error = esc_html__("Le fichier source est introuvable pour l'archive.", 'theme-export-jlg');
            return false;
        }

        if (self::DRIVER_ZIPARCHIVE === $this->driver) {
            return true === $this->zip->addFile($file_path, $entry_path);
        }

        $result = $this->zip->add([
            [
                PCLZIP_ATT_FILE_NAME          => $file_path,
                PCLZIP_ATT_FILE_NEW_FULL_NAME => $entry_path,
            ],
        ]);

        if (0 === $result) {
            $this->last_error = $this->zip->errorInfo(true);
            return false;
        }

        return true;
    }

    /**
     * Closes the underlying archive resource.
     */
    public function close() {
        if (self::DRIVER_ZIPARCHIVE === $this->driver && $this->zip instanceof ZipArchive) {
            $this->zip->close();
        }

        $this->zip = null;
    }

    /**
     * Returns the latest error message produced by the writer.
     *
     * @return string
     */
    public function get_last_error() {
        return $this->last_error;
    }

    /**
     * Returns the driver used by the writer.
     *
     * @return string
     */
    public function get_driver() {
        return $this->driver;
    }

    /**
     * Initializes the writer with the appropriate driver.
     *
     * @param string $zip_path  Archive path on disk.
     * @param bool   $create_new Whether to create/overwrite the archive.
     *
     * @return bool
     */
    private function initialize($zip_path, $create_new) {
        $this->zip_path = $zip_path;

        if (self::should_use_ziparchive()) {
            $zip = new ZipArchive();
            $flags = $create_new ? ZipArchive::CREATE | ZipArchive::OVERWRITE : 0;
            $result = $zip->open($zip_path, $flags);

            if (true !== $result) {
                $this->last_error = esc_html__("Impossible d'ouvrir l'archive ZIP.", 'theme-export-jlg');
                return false;
            }

            $this->driver = self::DRIVER_ZIPARCHIVE;
            $this->zip    = $zip;

            return true;
        }

        if (!class_exists('PclZip')) {
            require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
        }

        if (!class_exists('PclZip')) {
            $this->last_error = esc_html__("La classe PclZip est introuvable.", 'theme-export-jlg');
            return false;
        }

        if ($create_new && file_exists($zip_path) && !@unlink($zip_path)) {
            $this->last_error = esc_html__("Impossible de préparer le fichier ZIP pour l'écriture.", 'theme-export-jlg');
            return false;
        }

        $this->zip = new PclZip($zip_path);

        if (!$this->zip) {
            $this->last_error = esc_html__("Impossible d'initialiser PclZip.", 'theme-export-jlg');
            return false;
        }

        $this->driver = self::DRIVER_PCLZIP;

        return true;
    }
}

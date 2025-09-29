<?php
/**
 * Utility helpers for filesystem operations.
 */
class TEJLG_Files {
    /**
     * Deletes a file from the filesystem, logging failures when encountered.
     *
     * @param string $path Absolute path to the file to delete.
     * @return bool True when the file was deleted or does not exist, false otherwise.
     */
    public static function delete($path) {
        if (empty($path)) {
            return true;
        }

        if (!file_exists($path)) {
            return true;
        }

        if (@unlink($path)) {
            return true;
        }

        $message = sprintf('[Theme Export JLG] Impossible de supprimer le fichier : %s', $path);
        error_log($message);

        if (function_exists('doing_it_wrong')) {
            $version = defined('TEJLG_VERSION') ? TEJLG_VERSION : 'unknown';
            doing_it_wrong(__METHOD__, $message, $version);
        }

        return false;
    }
}

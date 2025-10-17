<?php
if (!defined('ABSPATH')) {
    exit;
}

class TEJLG_Export_Downloads {
    const OPTION_NAME = 'tejlg_export_download_tokens';
    const TOKEN_LENGTH_BYTES = 32;

    /**
     * Registers hooks used to serve persisted export downloads.
     */
    public static function init() {
        add_action('admin_post_tejlg_export_download', [__CLASS__, 'handle_archive_download']);
        add_action('admin_post_nopriv_tejlg_export_download', [__CLASS__, 'handle_archive_download']);

        add_action('admin_post_tejlg_export_summary_download', [__CLASS__, 'handle_summary_download']);
        add_action('admin_post_nopriv_tejlg_export_summary_download', [__CLASS__, 'handle_summary_download']);
    }

    /**
     * Creates a token giving access to a persisted export file.
     *
     * @param string $path Absolute path to the file on disk.
     * @param array  $args {
     *     Optional context for the token.
     *
     *     @type string $filename  Downloaded filename.
     *     @type string $mime_type MIME type served to clients.
     *     @type string $type      Token usage (archive|summary).
     *     @type int    $ttl       Lifetime in seconds.
     * }
     *
     * @return array<string,string> {
     *     @type string $token Token identifier.
     *     @type string $url   Public URL used to download the file.
     * }
     */
    public static function create_token($path, array $args = []) {
        $path = (string) $path;

        if ('' === $path || !file_exists($path)) {
            return [
                'token' => '',
                'url'   => '',
            ];
        }

        self::cleanup_expired_tokens();

        $tokens = self::get_tokens();

        $token = self::generate_token();

        $filename = isset($args['filename']) ? sanitize_file_name((string) $args['filename']) : basename($path);
        $mime     = isset($args['mime_type']) ? (string) $args['mime_type'] : 'application/octet-stream';
        $type     = isset($args['type']) ? sanitize_key((string) $args['type']) : 'archive';

        $ttl = isset($args['ttl']) ? (int) $args['ttl'] : WEEK_IN_SECONDS;
        $ttl = (int) apply_filters('tejlg_export_download_token_ttl', $ttl, $path, $args);

        if ($ttl <= 0) {
            $ttl = WEEK_IN_SECONDS;
        }

        $expires_at = time() + $ttl;

        $tokens[$token] = [
            'path'       => wp_normalize_path($path),
            'filename'   => $filename,
            'mime'       => $mime,
            'type'       => $type,
            'expires_at' => $expires_at,
            'uses'       => 0,
        ];

        self::persist_tokens($tokens);

        return [
            'token' => $token,
            'url'   => self::build_download_url($token, $type),
        ];
    }

    /**
     * Deletes tokens linked to a specific file path.
     *
     * @param string $path Absolute path of the file.
     */
    public static function forget_tokens_for_path($path) {
        $path = wp_normalize_path((string) $path);

        if ('' === $path) {
            return;
        }

        $tokens  = self::get_tokens();
        $updated = false;

        foreach ($tokens as $token => $data) {
            if (!is_array($data) || !isset($data['path'])) {
                continue;
            }

            if (wp_normalize_path((string) $data['path']) === $path) {
                unset($tokens[$token]);
                $updated = true;
            }
        }

        if ($updated) {
            self::persist_tokens($tokens);
        }
    }

    /**
     * Deletes all expired tokens.
     */
    public static function cleanup_expired_tokens() {
        $tokens  = self::get_tokens();
        $updated = false;
        $now     = time();

        foreach ($tokens as $token => $data) {
            if (!is_array($data) || empty($data['expires_at'])) {
                unset($tokens[$token]);
                $updated = true;
                continue;
            }

            $expires_at = (int) $data['expires_at'];

            if ($expires_at <= 0 || $expires_at <= $now) {
                unset($tokens[$token]);
                $updated = true;
            }
        }

        if ($updated) {
            self::persist_tokens($tokens);
        }
    }

    /**
     * Serves a persisted archive download.
     */
    public static function handle_archive_download() {
        self::serve_token_from_request('archive');
    }

    /**
     * Serves a persisted summary download.
     */
    public static function handle_summary_download() {
        self::serve_token_from_request('summary');
    }

    /**
     * Removes a specific token.
     *
     * @param string $token Token identifier.
     */
    public static function delete_token($token) {
        $token  = (string) $token;
        $tokens = self::get_tokens();

        if ('' === $token || !isset($tokens[$token])) {
            return;
        }

        unset($tokens[$token]);
        self::persist_tokens($tokens);
    }

    private static function serve_token_from_request($expected_type) {
        $expected_type = sanitize_key((string) $expected_type);

        $token = isset($_REQUEST['token']) ? sanitize_text_field((string) $_REQUEST['token']) : '';

        if ('' === $token) {
            wp_die(esc_html__('Lien de téléchargement invalide ou expiré.', 'theme-export-jlg'));
        }

        $entry = self::get_token_entry($token);

        if (empty($entry)) {
            wp_die(esc_html__('Lien de téléchargement invalide ou expiré.', 'theme-export-jlg'));
        }

        if ($expected_type && (!isset($entry['type']) || $expected_type !== (string) $entry['type'])) {
            wp_die(esc_html__('Ce lien de téléchargement n’est plus valide.', 'theme-export-jlg'));
        }

        $path = isset($entry['path']) ? (string) $entry['path'] : '';

        if ('' === $path || !file_exists($path)) {
            self::delete_token($token);
            wp_die(esc_html__('Le fichier demandé est introuvable.', 'theme-export-jlg'));
        }

        $filename = isset($entry['filename']) && '' !== (string) $entry['filename'] ? (string) $entry['filename'] : basename($path);
        $mime     = isset($entry['mime']) && '' !== (string) $entry['mime'] ? (string) $entry['mime'] : 'application/octet-stream';

        $filesize = filesize($path);

        nocache_headers();

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        if (false !== $filesize) {
            header('Content-Length: ' . (string) $filesize);
        }

        readfile($path);
        flush();

        self::delete_token($token);
        exit;
    }

    private static function get_token_entry($token) {
        $token  = (string) $token;
        $tokens = self::get_tokens();

        if ('' === $token || !isset($tokens[$token]) || !is_array($tokens[$token])) {
            return [];
        }

        $entry = $tokens[$token];

        if (!empty($entry['expires_at']) && (int) $entry['expires_at'] <= time()) {
            self::delete_token($token);

            return [];
        }

        return $entry;
    }

    private static function get_tokens() {
        $tokens = get_option(self::OPTION_NAME, []);

        if (!is_array($tokens)) {
            return [];
        }

        return $tokens;
    }

    private static function persist_tokens(array $tokens) {
        if (empty($tokens)) {
            delete_option(self::OPTION_NAME);

            return;
        }

        update_option(self::OPTION_NAME, $tokens, false);
    }

    private static function generate_token() {
        try {
            $bytes = random_bytes(self::TOKEN_LENGTH_BYTES);
        } catch (Exception $exception) {
            $bytes = wp_generate_password(self::TOKEN_LENGTH_BYTES, false, false);
        }

        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function build_download_url($token, $type) {
        $action = ('summary' === $type) ? 'tejlg_export_summary_download' : 'tejlg_export_download';

        return add_query_arg(
            [
                'action' => $action,
                'token'  => rawurlencode((string) $token),
            ],
            admin_url('admin-post.php')
        );
    }
}


<?php
if (!defined('ABSPATH')) {
    exit;
}

class TEJLG_Export_Connectors {
    /**
     * Bootstraps the remote connector dispatcher.
     */
    public static function init() {
        add_action('tejlg_export_notifications_dispatched', [__CLASS__, 'handle_event'], 15, 6);
    }

    /**
     * Dispatches connectors for successful exports.
     *
     * @param array<string,mixed>      $event   Normalized export event payload.
     * @param array<string,mixed>|null $payload Mail payload (if any).
     * @param array<string,mixed>      $entry   History entry data.
     * @param array<string,mixed>      $job     Raw job payload.
     * @param array<string,mixed>      $context Additional context supplied when recording the entry.
     * @param bool                     $sent    Whether wp_mail() reported success.
     */
    public static function handle_event($event, $payload, $entry, $job, $context, $sent) {
        unset($payload, $sent); // Not used but kept for signature parity with the action.

        if (!is_array($event) || empty($event['job_id'])) {
            return;
        }

        if (!class_exists('TEJLG_Export_History')) {
            return;
        }

        if (!isset($event['result']) || TEJLG_Export_History::RESULT_SUCCESS !== $event['result']) {
            return;
        }

        $persistent_path = isset($event['persistent_path']) ? (string) $event['persistent_path'] : '';

        if ('' === $persistent_path || !file_exists($persistent_path) || !is_readable($persistent_path)) {
            return;
        }

        $connectors = self::get_connectors($event, $entry, $job, $context);

        if (empty($connectors)) {
            return;
        }

        $results = [];

        foreach ($connectors as $connector) {
            $result = self::dispatch_connector($connector, $event, $entry, $job, $context);

            if (null === $result) {
                continue;
            }

            $results[] = $result;
        }

        if (empty($results)) {
            return;
        }

        TEJLG_Export_History::attach_remote_connector_results((string) $event['job_id'], $results);

        /**
         * Fires after the remote connector pipeline finished for a job.
         *
         * @param array<int,array<string,mixed>> $results Connector dispatch results.
         * @param array<string,mixed>            $event   Normalized export event payload.
         * @param array<string,mixed>            $entry   History entry data.
         * @param array<string,mixed>            $job     Raw job payload.
         * @param array<string,mixed>            $context Additional context supplied when recording the entry.
         */
        do_action('tejlg_export_remote_connectors_processed', $results, $event, $entry, $job, $context);
    }

    /**
     * Returns the list of enabled connectors for the event.
     *
     * @param array<string,mixed> $event
     * @param array<string,mixed> $entry
     * @param array<string,mixed> $job
     * @param array<string,mixed> $context
     *
     * @return array<int,array<string,mixed>>
     */
    private static function get_connectors($event, $entry, $job, $context) {
        $defaults = [];

        $s3_settings = apply_filters('tejlg_export_remote_connector_s3_settings', null, $event, $entry, $job, $context);

        if (is_array($s3_settings)) {
            $defaults[] = self::normalize_connector(array_merge(
                [
                    'id'   => 's3-primary',
                    'type' => 's3',
                ],
                $s3_settings
            ));
        }

        $sftp_settings = apply_filters('tejlg_export_remote_connector_sftp_settings', null, $event, $entry, $job, $context);

        if (is_array($sftp_settings)) {
            $defaults[] = self::normalize_connector(array_merge(
                [
                    'id'   => 'sftp-primary',
                    'type' => 'sftp',
                ],
                $sftp_settings
            ));
        }

        $connectors = apply_filters('tejlg_export_remote_connectors', $defaults, $event, $entry, $job, $context);

        if (!is_array($connectors)) {
            return [];
        }

        $normalized = [];

        foreach ($connectors as $connector) {
            $normalized_connector = self::normalize_connector($connector);

            if (null !== $normalized_connector && !empty($normalized_connector['enabled'])) {
                $normalized[] = $normalized_connector;
            }
        }

        return $normalized;
    }

    /**
     * Normalizes a connector configuration.
     *
     * @param mixed $connector
     *
     * @return array<string,mixed>|null
     */
    private static function normalize_connector($connector) {
        if (!is_array($connector) || empty($connector['type'])) {
            return null;
        }

        $type = sanitize_key((string) $connector['type']);
        $id   = isset($connector['id']) ? sanitize_key((string) $connector['id']) : $type;

        if ('' === $id) {
            $id = $type;
        }

        $enabled = true;

        if (isset($connector['enabled'])) {
            $enabled = (bool) $connector['enabled'];
        }

        $settings = isset($connector['settings']) && is_array($connector['settings'])
            ? $connector['settings']
            : array_diff_key($connector, array_flip(['type', 'id', 'enabled']));

        return [
            'id'       => $id,
            'type'     => $type,
            'enabled'  => $enabled,
            'settings' => $settings,
        ];
    }

    /**
     * Dispatches a single connector and returns its result payload.
     *
     * @param array<string,mixed> $connector
     * @param array<string,mixed> $event
     * @param array<string,mixed> $entry
     * @param array<string,mixed> $job
     * @param array<string,mixed> $context
     *
     * @return array<string,mixed>|null
     */
    private static function dispatch_connector(array $connector, $event, $entry, $job, $context) {
        $start = microtime(true);
        $status = 'skipped';
        $message = '';
        $location = '';
        $meta = [];

        try {
            switch ($connector['type']) {
                case 's3':
                    $result = self::dispatch_s3($connector, $event);
                    break;
                case 'sftp':
                    $result = self::dispatch_sftp($connector, $event);
                    break;
                default:
                    /**
                     * Filters custom connector dispatching.
                     *
                     * @param array<string,mixed>|null $result     Null to fallback to default skipped response.
                     * @param array<string,mixed>      $connector  Normalized connector configuration.
                     * @param array<string,mixed>      $event       Normalized export event payload.
                     * @param array<string,mixed>      $entry       History entry data.
                     * @param array<string,mixed>      $job         Raw job payload.
                     * @param array<string,mixed>      $context     Additional context supplied when recording the entry.
                     */
                    $result = apply_filters('tejlg_export_remote_connector_dispatch', null, $connector, $event, $entry, $job, $context);
                    break;
            }
        } catch (Throwable $exception) {
            $result = new WP_Error(
                'tejlg_remote_connector_exception',
                $exception->getMessage(),
                [
                    'connector' => $connector,
                    'event'     => $event,
                ]
            );
        }

        if (is_wp_error($result)) {
            $status  = 'error';
            $message = $result->get_error_message();
            $meta['error_data'] = $result->get_error_data();
        } elseif (is_array($result)) {
            $status   = isset($result['status']) ? sanitize_key((string) $result['status']) : 'success';
            $message  = isset($result['message']) ? (string) $result['message'] : '';
            $location = isset($result['location']) ? (string) $result['location'] : '';
            $meta     = isset($result['meta']) && is_array($result['meta']) ? $result['meta'] : [];
        }

        $duration = max(0, microtime(true) - $start);

        $payload = [
            'id'        => (string) $connector['id'],
            'type'      => (string) $connector['type'],
            'status'    => $status,
            'message'   => $message,
            'location'  => $location,
            'duration'  => $duration,
            'meta'      => $meta,
            'timestamp' => time(),
        ];

        /**
         * Filters the connector result payload before it is stored.
         *
         * @param array<string,mixed>      $payload   Result payload.
         * @param array<string,mixed>      $connector Connector configuration.
         * @param array<string,mixed>      $event     Normalized export event payload.
         * @param array<string,mixed>      $entry     History entry data.
         * @param array<string,mixed>      $job       Raw job payload.
         * @param array<string,mixed>      $context   Additional context supplied when recording the entry.
         */
        $payload = apply_filters('tejlg_export_remote_connector_result', $payload, $connector, $event, $entry, $job, $context);

        if (!is_array($payload) || empty($payload['id'])) {
            return null;
        }

        return $payload;
    }

    /**
     * Uploads the archive to an S3-compatible storage.
     *
     * @param array<string,mixed> $connector
     * @param array<string,mixed> $event
     *
     * @return array<string,mixed>|WP_Error
     */
    private static function dispatch_s3(array $connector, array $event) {
        $settings = isset($connector['settings']) ? $connector['settings'] : [];

        $bucket     = isset($settings['bucket']) ? (string) $settings['bucket'] : '';
        $region     = isset($settings['region']) ? (string) $settings['region'] : '';
        $access_key = isset($settings['access_key']) ? (string) $settings['access_key'] : '';
        $secret_key = isset($settings['secret_key']) ? (string) $settings['secret_key'] : '';

        if ('' === $bucket || '' === $region || '' === $access_key || '' === $secret_key) {
            return new WP_Error('tejlg_remote_connector_s3_incomplete', __('Configuration S3 incomplète.', 'theme-export-jlg'));
        }

        $object_key = self::build_s3_object_key($settings, $event);
        $file_path  = isset($event['persistent_path']) ? (string) $event['persistent_path'] : '';

        if ('' === $object_key) {
            return new WP_Error('tejlg_remote_connector_s3_object_key', __('Impossible de déterminer la clé S3.', 'theme-export-jlg'));
        }

        if ('' === $file_path || !file_exists($file_path)) {
            return new WP_Error('tejlg_remote_connector_missing_file', __('Fichier local introuvable pour l’upload S3.', 'theme-export-jlg'));
        }

        $endpoint          = isset($settings['endpoint']) ? (string) $settings['endpoint'] : '';
        $force_path_style  = !empty($settings['force_path_style']);
        $acl               = isset($settings['acl']) ? (string) $settings['acl'] : '';
        $storage_class     = isset($settings['storage_class']) ? (string) $settings['storage_class'] : '';
        $encryption        = isset($settings['server_side_encryption']) ? (string) $settings['server_side_encryption'] : '';
        $timeout           = isset($settings['timeout']) ? (int) $settings['timeout'] : 30;
        $content_type      = isset($settings['content_type']) ? (string) $settings['content_type'] : 'application/zip';

        $object_key = ltrim($object_key, '/');
        $encoded_key = self::encode_s3_key($object_key);

        if ('' === $endpoint) {
            if ($force_path_style) {
                $host = sprintf('s3.%s.amazonaws.com', $region);
                $uri  = '/' . rawurlencode($bucket) . '/' . $encoded_key;
            } else {
                $host = sprintf('%s.s3.%s.amazonaws.com', $bucket, $region);
                $uri  = '/' . $encoded_key;
            }

            $url = 'https://' . $host . $uri;
        } else {
            $endpoint = rtrim($endpoint, '/');
            $host     = parse_url($endpoint, PHP_URL_HOST);

            if (!is_string($host) || '' === $host) {
                return new WP_Error('tejlg_remote_connector_s3_endpoint', __('Endpoint S3 invalide.', 'theme-export-jlg'));
            }

            if ($force_path_style) {
                $uri = '/' . rawurlencode($bucket) . '/' . $encoded_key;
                $url = $endpoint . $uri;
            } else {
                $uri = '/' . $encoded_key;
                $url = $endpoint . $uri;
            }
        }

        $payload_hash = hash_file('sha256', $file_path);

        if (false === $payload_hash) {
            return new WP_Error('tejlg_remote_connector_s3_hash', __('Impossible de calculer l’empreinte du fichier.', 'theme-export-jlg'));
        }

        $amz_date = gmdate('Ymd\THis\Z');
        $date     = gmdate('Ymd');
        $scope    = implode('/', [$date, $region, 's3', 'aws4_request']);

        $headers = [
            'Host'              => $host,
            'x-amz-content-sha256' => $payload_hash,
            'x-amz-date'        => $amz_date,
            'Content-Type'      => $content_type,
        ];

        if ('' !== $acl) {
            $headers['x-amz-acl'] = $acl;
        }

        if ('' !== $storage_class) {
            $headers['x-amz-storage-class'] = $storage_class;
        }

        if ('' !== $encryption) {
            $headers['x-amz-server-side-encryption'] = $encryption;
        }

        ksort($headers, SORT_STRING | SORT_FLAG_CASE);

        $canonical_headers = '';
        $signed_headers    = [];

        foreach ($headers as $name => $value) {
            $lower = strtolower($name);
            $canonical_headers .= $lower . ':' . trim($value) . "\n";
            $signed_headers[] = $lower;
        }

        $canonical_request = implode("\n", [
            'PUT',
            $uri,
            '',
            $canonical_headers,
            implode(';', $signed_headers),
            $payload_hash,
        ]);

        $string_to_sign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amz_date,
            $scope,
            hash('sha256', $canonical_request),
        ]);

        $signature = self::sign_aws_request($secret_key, $date, $region, 's3', $string_to_sign);

        if (is_wp_error($signature)) {
            return $signature;
        }

        $headers['Authorization'] = sprintf(
            'AWS4-HMAC-SHA256 Credential=%1$s/%2$s, SignedHeaders=%3$s, Signature=%4$s',
            $access_key,
            $scope,
            implode(';', $signed_headers),
            $signature
        );

        $body = file_get_contents($file_path);

        if (false === $body) {
            return new WP_Error('tejlg_remote_connector_s3_body', __('Lecture du fichier impossible pour l’upload S3.', 'theme-export-jlg'));
        }

        $response = wp_remote_request(
            $url,
            [
                'method'  => 'PUT',
                'headers' => $headers,
                'body'    => $body,
                'timeout' => max(5, $timeout),
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code < 200 || $code >= 300) {
            return new WP_Error(
                'tejlg_remote_connector_s3_http',
                sprintf(
                    /* translators: %d: HTTP status code. */
                    __('Échec de l’upload S3 (statut HTTP %d).', 'theme-export-jlg'),
                    (int) $code
                ),
                [
                    'response' => $response,
                    'url'      => $url,
                ]
            );
        }

        $location = sprintf('s3://%s/%s', $bucket, $object_key);

        return [
            'status'   => 'success',
            'message'  => __('Archive envoyée sur le bucket S3.', 'theme-export-jlg'),
            'location' => $location,
        ];
    }

    /**
     * Sends the archive to an SFTP server.
     *
     * @param array<string,mixed> $connector
     * @param array<string,mixed> $event
     *
     * @return array<string,mixed>|WP_Error
     */
    private static function dispatch_sftp(array $connector, array $event) {
        $settings = isset($connector['settings']) ? $connector['settings'] : [];

        $host       = isset($settings['host']) ? (string) $settings['host'] : '';
        $username   = isset($settings['username']) ? (string) $settings['username'] : '';
        $password   = isset($settings['password']) ? (string) $settings['password'] : '';
        $privateKey = isset($settings['private_key']) ? (string) $settings['private_key'] : '';
        $publicKey  = isset($settings['public_key']) ? (string) $settings['public_key'] : '';
        $passphrase = isset($settings['passphrase']) ? (string) $settings['passphrase'] : '';
        $remotePath = isset($settings['remote_path']) ? (string) $settings['remote_path'] : '';
        $port       = isset($settings['port']) ? (int) $settings['port'] : 22;
        $timeout    = isset($settings['timeout']) ? (int) $settings['timeout'] : 10;
        $permissions = isset($settings['file_permissions']) ? (int) $settings['file_permissions'] : 0644;

        $file_path = isset($event['persistent_path']) ? (string) $event['persistent_path'] : '';

        if ('' === $host || '' === $username || '' === $remotePath) {
            return new WP_Error('tejlg_remote_connector_sftp_incomplete', __('Configuration SFTP incomplète.', 'theme-export-jlg'));
        }

        if ('' === $file_path || !file_exists($file_path)) {
            return new WP_Error('tejlg_remote_connector_missing_file', __('Fichier local introuvable pour le transfert SFTP.', 'theme-export-jlg'));
        }

        if (!function_exists('ssh2_connect')) {
            return new WP_Error('tejlg_remote_connector_sftp_extension', __('L’extension SSH2 PHP est requise pour le connecteur SFTP.', 'theme-export-jlg'));
        }

        $connection = @ssh2_connect($host, $port, [], [
            'disconnect' => static function () {
                // Silence warnings on disconnect.
            },
        ]);

        if (false === $connection) {
            return new WP_Error('tejlg_remote_connector_sftp_connect', __('Connexion SFTP impossible.', 'theme-export-jlg'));
        }

        $authenticated = false;

        if ('' !== $privateKey && function_exists('ssh2_auth_pubkey_file')) {
            $authenticated = @ssh2_auth_pubkey_file($connection, $username, $publicKey, $privateKey, $passphrase);
        }

        if (!$authenticated && '' !== $password) {
            $authenticated = @ssh2_auth_password($connection, $username, $password);
        }

        if (!$authenticated) {
            return new WP_Error('tejlg_remote_connector_sftp_auth', __('Authentification SFTP refusée.', 'theme-export-jlg'));
        }

        $sftp = @ssh2_sftp($connection);

        if (false === $sftp) {
            return new WP_Error('tejlg_remote_connector_sftp_session', __('Session SFTP indisponible.', 'theme-export-jlg'));
        }

        $remotePath = self::build_remote_path($remotePath, $event);
        $remoteDir  = dirname($remotePath);

        if (!self::ensure_sftp_directory($sftp, $remoteDir)) {
            return new WP_Error('tejlg_remote_connector_sftp_directory', __('Impossible de créer le répertoire distant.', 'theme-export-jlg'));
        }

        $stream = @fopen('ssh2.sftp://' . intval($sftp) . $remotePath, 'w');

        if (false === $stream) {
            return new WP_Error('tejlg_remote_connector_sftp_stream', __('Ouverture du flux SFTP impossible.', 'theme-export-jlg'));
        }

        $context = stream_context_create([
            'ssh2' => [
                'session' => $connection,
            ],
        ]);

        stream_set_timeout($stream, $timeout);

        $file = @fopen($file_path, 'rb');

        if (false === $file) {
            fclose($stream);

            return new WP_Error('tejlg_remote_connector_sftp_local', __('Lecture du fichier local impossible.', 'theme-export-jlg'));
        }

        $copied = stream_copy_to_stream($file, $stream);

        fclose($file);
        fclose($stream);

        if (false === $copied) {
            return new WP_Error('tejlg_remote_connector_sftp_copy', __('Transfert SFTP interrompu.', 'theme-export-jlg'));
        }

        if ($permissions > 0 && function_exists('ssh2_sftp_chmod')) {
            @ssh2_sftp_chmod($sftp, $remotePath, $permissions);
        }

        return [
            'status'   => 'success',
            'message'  => __('Archive transférée via SFTP.', 'theme-export-jlg'),
            'location' => 'sftp://' . $host . $remotePath,
        ];
    }

    /**
     * Ensures a remote directory exists on the SFTP server.
     *
     * @param resource $sftp
     * @param string   $directory
     *
     * @return bool
     */
    private static function ensure_sftp_directory($sftp, $directory) {
        $directory = untrailingslashit($directory);

        if ('' === $directory || '/' === $directory) {
            return true;
        }

        $segments = explode('/', ltrim($directory, '/'));
        $path     = '';

        foreach ($segments as $segment) {
            $segment = trim($segment);

            if ('' === $segment) {
                continue;
            }

            $path .= '/' . $segment;
            $stat = @ssh2_sftp_stat($sftp, $path);

            if (false !== $stat) {
                continue;
            }

            if (!@ssh2_sftp_mkdir($sftp, $path)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Builds the S3 object key based on the connector settings and event payload.
     *
     * @param array<string,mixed> $settings
     * @param array<string,mixed> $event
     *
     * @return string
     */
    private static function build_s3_object_key(array $settings, array $event) {
        $prefix = isset($settings['prefix']) ? (string) $settings['prefix'] : '';
        $filename = basename(isset($event['persistent_path']) ? (string) $event['persistent_path'] : '');

        if ('' === $filename && !empty($event['job_id'])) {
            $filename = $event['job_id'] . '.zip';
        }

        $default_key = ltrim($prefix . '/' . $filename, '/');

        /**
         * Filters the default S3 object key.
         *
         * @param string               $key      Proposed object key.
         * @param array<string,mixed>  $settings Connector settings.
         * @param array<string,mixed>  $event    Normalized export event payload.
         */
        $key = apply_filters('tejlg_export_remote_connector_s3_object_key', $default_key, $settings, $event);

        $key = is_string($key) ? trim($key) : '';

        return $key;
    }

    /**
     * Builds a deterministic remote path for SFTP transfers.
     *
     * @param string               $remotePath
     * @param array<string,mixed>  $event
     *
     * @return string
     */
    private static function build_remote_path($remotePath, array $event) {
        $remotePath = trim($remotePath);

        if ('' === $remotePath) {
            $remotePath = '/';
        }

        if ('/' === substr($remotePath, -1)) {
            $filename = basename(isset($event['persistent_path']) ? (string) $event['persistent_path'] : '');

            if ('' === $filename && !empty($event['job_id'])) {
                $filename = $event['job_id'] . '.zip';
            }

            $remotePath .= $filename;
        }

        return $remotePath;
    }

    /**
     * Encodes a key for use in S3 requests while preserving forward slashes.
     *
     * @param string $key
     *
     * @return string
     */
    private static function encode_s3_key($key) {
        return str_replace('%2F', '/', rawurlencode($key));
    }

    /**
     * Signs an AWS request using signature version 4.
     *
     * @param string $secret
     * @param string $date
     * @param string $region
     * @param string $service
     * @param string $string_to_sign
     *
     * @return string|WP_Error
     */
    private static function sign_aws_request($secret, $date, $region, $service, $string_to_sign) {
        if ('' === $secret) {
            return new WP_Error('tejlg_remote_connector_signature_secret', __('Clé secrète AWS manquante.', 'theme-export-jlg'));
        }

        $kDate    = hash_hmac('sha256', $date, 'AWS4' . $secret, true);
        $kRegion  = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        return hash_hmac('sha256', $string_to_sign, $kSigning);
    }
}

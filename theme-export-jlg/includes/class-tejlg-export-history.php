<?php
if (!defined('ABSPATH')) {
    exit;
}

class TEJLG_Export_History {
    const OPTION_NAME = 'tejlg_export_history_entries';

    const RESULT_SUCCESS = 'success';
    const RESULT_WARNING = 'warning';
    const RESULT_ERROR   = 'error';
    const RESULT_INFO    = 'info';

    public static function record_job($job, array $context = []) {
        if (!is_array($job) || empty($job['id'])) {
            return;
        }

        $entry = self::build_entry_from_job($job, $context);

        if (null === $entry) {
            return;
        }

        $entries = self::get_raw_entries();

        $entries = array_values(
            array_filter(
                $entries,
                static function ($existing) use ($entry) {
                    if (!is_array($existing) || empty($existing['job_id'])) {
                        return false;
                    }

                    return (string) $existing['job_id'] !== $entry['job_id'];
                }
            )
        );

        array_unshift($entries, $entry);

        $max_entries = apply_filters('tejlg_export_history_max_entries', 100);
        $max_entries = is_numeric($max_entries) ? (int) $max_entries : 100;

        if ($max_entries > 0 && count($entries) > $max_entries) {
            $entries = array_slice($entries, 0, $max_entries);
        }

        self::save_entries($entries);

        self::dispatch_recorded_hooks($entry, $job, $context);
    }

    public static function get_entries($args = []) {
        $args['limit'] = 0;

        $query   = self::normalize_query_args($args);
        $entries = self::get_filtered_entries($query);

        $per_page = isset($query['per_page']) ? (int) $query['per_page'] : 10;
        $current_page = isset($query['paged']) ? (int) $query['paged'] : 1;

        $total = count($entries);

        $offset = ($current_page - 1) * $per_page;
        $offset = $offset < 0 ? 0 : $offset;

        $page_entries = array_slice($entries, $offset, $per_page);

        $total_pages = 0 === $per_page ? 1 : (int) ceil($total / $per_page);
        $total_pages = $total_pages > 0 ? $total_pages : 1;

        return [
            'entries'       => $page_entries,
            'total'         => $total,
            'total_pages'   => $total_pages,
            'per_page'      => $per_page,
            'current_page'  => $current_page,
        ];
    }

    public static function get_entries_for_export($args = []) {
        $query   = self::normalize_query_args($args);
        $entries = self::get_filtered_entries($query);

        $limit = isset($query['limit']) ? (int) $query['limit'] : 0;

        if ($limit > 0 && count($entries) > $limit) {
            $entries = array_slice($entries, 0, $limit);
        }

        return [
            'entries' => $entries,
            'query'   => $query,
        ];
    }

    public static function normalize_query_args($args = []) {
        $defaults = [
            'per_page'        => 10,
            'paged'           => 1,
            'result'          => '',
            'origin'          => '',
            'initiator'       => '',
            'orderby'         => 'timestamp',
            'order'           => 'desc',
            'start_date'      => '',
            'end_date'        => '',
            'start_timestamp' => 0,
            'end_timestamp'   => 0,
            'limit'           => 0,
        ];

        $args = wp_parse_args($args, $defaults);

        $per_page = isset($args['per_page']) ? (int) $args['per_page'] : 10;
        $per_page = $per_page > 0 ? $per_page : 10;

        $paged = isset($args['paged']) ? (int) $args['paged'] : 1;
        $paged = $paged > 0 ? $paged : 1;

        $result_filter = isset($args['result']) ? sanitize_key((string) $args['result']) : '';
        $origin_filter = isset($args['origin']) ? sanitize_key((string) $args['origin']) : '';

        $orderby = isset($args['orderby']) ? sanitize_key((string) $args['orderby']) : 'timestamp';
        $allowed_orderby = ['timestamp', 'duration', 'zip_file_size'];
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'timestamp';
        }

        $order = isset($args['order']) ? strtolower((string) $args['order']) : 'desc';
        $order = 'asc' === $order ? 'asc' : 'desc';

        $start_date = isset($args['start_date']) ? sanitize_text_field((string) $args['start_date']) : '';
        $end_date   = isset($args['end_date']) ? sanitize_text_field((string) $args['end_date']) : '';

        $initiator = isset($args['initiator']) ? sanitize_text_field((string) $args['initiator']) : '';
        $initiator = trim($initiator);

        $start_timestamp = isset($args['start_timestamp']) && is_numeric($args['start_timestamp'])
            ? (int) $args['start_timestamp']
            : self::parse_date_filter($start_date, false);

        $end_timestamp = isset($args['end_timestamp']) && is_numeric($args['end_timestamp'])
            ? (int) $args['end_timestamp']
            : self::parse_date_filter($end_date, true);

        if ($start_timestamp > 0 && $end_timestamp > 0 && $end_timestamp < $start_timestamp) {
            $tmp = $start_timestamp;
            $start_timestamp = $end_timestamp;
            $end_timestamp   = $tmp;
        }

        $limit = isset($args['limit']) ? (int) $args['limit'] : 0;
        $limit = $limit >= 0 ? $limit : 0;

        return [
            'per_page'        => $per_page,
            'paged'           => $paged,
            'result'          => $result_filter,
            'origin'          => $origin_filter,
            'initiator'       => $initiator,
            'orderby'         => $orderby,
            'order'           => $order,
            'start_date'      => $start_date,
            'end_date'        => $end_date,
            'start_timestamp' => max(0, $start_timestamp),
            'end_timestamp'   => max(0, $end_timestamp),
            'limit'           => $limit,
        ];
    }

    private static function get_filtered_entries(array $query) {
        $entries = self::get_raw_entries();

        $entries = array_values(
            array_filter(
                $entries,
                static function ($entry) use ($query) {
                    if (!is_array($entry)) {
                        return false;
                    }

                    if ('' !== $query['result']) {
                        $entry_result = isset($entry['result']) ? (string) $entry['result'] : '';

                        if ($entry_result !== $query['result']) {
                            return false;
                        }
                    }

                    if ('' !== $query['origin']) {
                        $entry_origin = isset($entry['origin']) ? (string) $entry['origin'] : '';

                        if ($entry_origin !== $query['origin']) {
                            return false;
                        }
                    }

                    $timestamp = isset($entry['timestamp']) ? (int) $entry['timestamp'] : 0;

                    if ($query['start_timestamp'] > 0 && $timestamp < $query['start_timestamp']) {
                        return false;
                    }

                    if ($query['end_timestamp'] > 0 && $timestamp > $query['end_timestamp']) {
                        return false;
                    }

                    if ('' !== $query['initiator'] && !self::entry_matches_initiator($entry, $query['initiator'])) {
                        return false;
                    }

                    return true;
                }
            )
        );

        if (count($entries) > 1) {
            $entries = self::sort_entries($entries, $query['orderby'], $query['order']);
        }

        return $entries;
    }

    private static function sort_entries(array $entries, $orderby, $order) {
        usort(
            $entries,
            static function ($left, $right) use ($orderby, $order) {
                $left_value  = isset($left[$orderby]) ? (int) $left[$orderby] : 0;
                $right_value = isset($right[$orderby]) ? (int) $right[$orderby] : 0;

                if ($left_value === $right_value) {
                    $left_timestamp  = isset($left['timestamp']) ? (int) $left['timestamp'] : 0;
                    $right_timestamp = isset($right['timestamp']) ? (int) $right['timestamp'] : 0;

                    if ($left_timestamp === $right_timestamp) {
                        return 0;
                    }

                    return ('asc' === $order) ? ($left_timestamp <=> $right_timestamp) : ($right_timestamp <=> $left_timestamp);
                }

                return ('asc' === $order) ? ($left_value <=> $right_value) : ($right_value <=> $left_value);
            }
        );

        return $entries;
    }

    private static function parse_date_filter($value, $is_end_of_day) {
        $value = is_string($value) ? trim($value) : '';

        if ('' === $value) {
            return 0;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $value .= $is_end_of_day ? ' 23:59:59' : ' 00:00:00';
        }

        $timestamp = strtotime($value);

        if (false === $timestamp) {
            return 0;
        }

        return (int) $timestamp;
    }

    public static function count_entries() {
        return count(self::get_raw_entries());
    }

    /**
     * Returns filtered history entries suitable for exports and integrations.
     *
     * @param array<string,mixed> $args
     *
     * @return array<int,array<string,mixed>>
     */
    public static function export_entries($args = []) {
        $defaults = [
            'result'          => '',
            'origin'          => '',
            'initiator'       => '',
            'start_timestamp' => 0,
            'end_timestamp'   => 0,
            'limit'           => 0,
        ];

        $args = wp_parse_args($args, $defaults);

        $allowed_results = [
            self::RESULT_SUCCESS,
            self::RESULT_WARNING,
            self::RESULT_ERROR,
            self::RESULT_INFO,
        ];

        $result_filter = isset($args['result']) ? sanitize_key((string) $args['result']) : '';

        if (!in_array($result_filter, $allowed_results, true)) {
            $result_filter = '';
        }

        $origin_filter = isset($args['origin']) ? sanitize_key((string) $args['origin']) : '';

        $start_timestamp = isset($args['start_timestamp']) ? (int) $args['start_timestamp'] : 0;
        $start_timestamp = $start_timestamp > 0 ? $start_timestamp : 0;

        $end_timestamp = isset($args['end_timestamp']) ? (int) $args['end_timestamp'] : 0;
        $end_timestamp = $end_timestamp > 0 ? $end_timestamp : 0;

        if ($end_timestamp > 0 && $start_timestamp > 0 && $end_timestamp < $start_timestamp) {
            $end_timestamp = 0;
        }

        $limit = isset($args['limit']) ? (int) $args['limit'] : 0;
        $limit = $limit > 0 ? $limit : 0;

        $initiator_filter = isset($args['initiator']) ? sanitize_text_field((string) $args['initiator']) : '';
        $initiator_filter = trim($initiator_filter);

        $entries = self::get_raw_entries();
        $filtered = [];

        foreach ($entries as $entry) {
            $timestamp = isset($entry['timestamp']) ? (int) $entry['timestamp'] : 0;

            if ($start_timestamp > 0 && $timestamp > 0 && $timestamp < $start_timestamp) {
                continue;
            }

            if ($end_timestamp > 0 && $timestamp > 0 && $timestamp > $end_timestamp) {
                continue;
            }

            if ('' !== $result_filter) {
                $entry_result = isset($entry['result']) ? (string) $entry['result'] : '';

                if ($entry_result !== $result_filter) {
                    continue;
                }
            }

            if ('' !== $origin_filter) {
                $entry_origin = isset($entry['origin']) ? (string) $entry['origin'] : '';

                if ($entry_origin !== $origin_filter) {
                    continue;
                }
            }

            if ('' !== $initiator_filter && !self::entry_matches_initiator($entry, $initiator_filter)) {
                continue;
            }

            $filtered[] = $entry;

            if ($limit > 0 && count($filtered) >= $limit) {
                break;
            }
        }

        return $filtered;
    }

    public static function clear_history() {
        delete_option(self::OPTION_NAME);
    }

    private static function build_entry_from_job(array $job, array $context) {
        $job_id = isset($job['id']) ? (string) $job['id'] : '';

        if ('' === $job_id) {
            return null;
        }

        $status = isset($job['status']) ? (string) $job['status'] : '';

        $status_message = '';

        if (isset($context['status_message']) && is_string($context['status_message'])) {
            $status_message = $context['status_message'];
        } elseif (!empty($job['message']) && is_string($job['message'])) {
            $status_message = $job['message'];
        }

        $timestamps = [
            isset($job['completed_at']) ? (int) $job['completed_at'] : 0,
            isset($job['updated_at']) ? (int) $job['updated_at'] : 0,
            isset($job['created_at']) ? (int) $job['created_at'] : 0,
            isset($context['timestamp']) ? (int) $context['timestamp'] : 0,
        ];

        $timestamp = time();

        foreach ($timestamps as $candidate) {
            if ($candidate > 0) {
                $timestamp = $candidate;
                break;
            }
        }

        $start_times = [
            isset($context['start_time']) ? (int) $context['start_time'] : 0,
            isset($job['started_at']) ? (int) $job['started_at'] : 0,
            isset($job['created_at']) ? (int) $job['created_at'] : 0,
        ];

        $start_time = 0;

        foreach ($start_times as $candidate) {
            if ($candidate > 0) {
                $start_time = $candidate;
                break;
            }
        }

        $end_times = [
            isset($context['completed_at']) ? (int) $context['completed_at'] : 0,
            isset($job['completed_at']) ? (int) $job['completed_at'] : 0,
            isset($job['updated_at']) ? (int) $job['updated_at'] : 0,
            $timestamp,
        ];

        $duration = 0;

        if ($start_time > 0) {
            foreach ($end_times as $candidate) {
                if ($candidate >= $start_time) {
                    $duration = $candidate - $start_time;
                    break;
                }
            }
        }

        $user_id = 0;

        if (isset($context['user_id'])) {
            $user_id = (int) $context['user_id'];
        } elseif (isset($job['created_by'])) {
            $user_id = (int) $job['created_by'];
        } elseif (function_exists('get_current_user_id')) {
            $user_id = (int) get_current_user_id();
        }

        $user_name  = '';
        $user_login = '';

        if (isset($context['user_name']) && is_string($context['user_name'])) {
            $user_name = $context['user_name'];
        } elseif (!empty($job['created_by_name']) && is_string($job['created_by_name'])) {
            $user_name = $job['created_by_name'];
        } elseif ($user_id > 0) {
            $user = get_userdata($user_id);

            if ($user instanceof WP_User) {
                $user_name = $user->display_name;
                $user_login = $user->user_login;
            }
        }

        if (isset($context['user_login']) && is_string($context['user_login'])) {
            $user_login = $context['user_login'];
        } elseif (isset($job['created_by_login']) && is_string($job['created_by_login'])) {
            $user_login = $job['created_by_login'];
        }

        $user_name  = sanitize_text_field($user_name);
        $user_login = sanitize_user($user_login, true);

        $zip_path = isset($job['zip_path']) ? (string) $job['zip_path'] : '';
        $zip_file_name = isset($job['zip_file_name']) && '' !== $job['zip_file_name']
            ? (string) $job['zip_file_name']
            : ($zip_path !== '' ? basename($zip_path) : '');

        $zip_file_size = isset($job['zip_file_size']) ? (int) $job['zip_file_size'] : 0;

        if ($zip_file_size <= 0 && '' !== $zip_path && file_exists($zip_path)) {
            $zip_file_size = (int) filesize($zip_path);
        }

        $exclusions = [];

        if (isset($job['exclusions']) && is_array($job['exclusions'])) {
            $exclusions = array_values(
                array_filter(
                    array_map(
                        static function ($pattern) {
                            return (string) $pattern;
                        },
                        $job['exclusions']
                    ),
                    static function ($pattern) {
                        return '' !== trim($pattern);
                    }
                )
            );
        }

        $origin = '';

        if (isset($context['origin'])) {
            $origin = (string) $context['origin'];
        } elseif (isset($job['created_via'])) {
            $origin = (string) $job['created_via'];
        } elseif (defined('WP_CLI') && WP_CLI) {
            $origin = 'cli';
        } else {
            $origin = 'web';
        }

        $context_label = '';

        if (isset($context['context']) && is_string($context['context'])) {
            $context_label = $context['context'];
        } elseif (isset($job['context']) && is_string($job['context'])) {
            $context_label = $job['context'];
        } elseif (isset($context['trigger']) && is_string($context['trigger'])) {
            $context_label = $context['trigger'];
        } elseif (isset($job['trigger']) && is_string($job['trigger'])) {
            $context_label = $job['trigger'];
        } elseif ('' !== $origin) {
            $context_label = $origin;
        }

        $entry = [
            'job_id'        => $job_id,
            'status'        => $status,
            'timestamp'     => $timestamp,
            'user_id'       => $user_id,
            'user_name'     => $user_name,
            'user_login'    => $user_login,
            'zip_file_name' => $zip_file_name,
            'zip_file_size' => max(0, $zip_file_size),
            'exclusions'    => $exclusions,
            'origin'        => $origin,
            'duration'      => max(0, (int) $duration),
            'result'        => self::determine_result_from_status($status),
            'status_message'=> $status_message,
            'context'       => $context_label,
        ];

        if (!empty($job['persistent_path']) && is_string($job['persistent_path'])) {
            $entry['persistent_path'] = $job['persistent_path'];
        }

        if (!empty($job['persistent_url']) && is_string($job['persistent_url'])) {
            $entry['persistent_url'] = $job['persistent_url'];
        }

        if (isset($context['download_url']) && is_string($context['download_url'])) {
            $entry['persistent_url'] = $context['download_url'];
        }

        if (!empty($job['summary_meta']) && is_array($job['summary_meta'])) {
            $entry['summary_meta'] = self::sanitize_summary_meta($job['summary_meta']);
        }

        if (isset($context['summary_url']) && is_string($context['summary_url'])) {
            $entry['summary_url'] = $context['summary_url'];
        } elseif (isset($job['summary_persistent_url']) && is_string($job['summary_persistent_url'])) {
            $entry['summary_url'] = $job['summary_persistent_url'];
        }

        if (isset($context['summary_filename']) && is_string($context['summary_filename']) && '' !== $context['summary_filename']) {
            $entry['summary_filename'] = $context['summary_filename'];
        } elseif (isset($job['summary_file_name']) && is_string($job['summary_file_name']) && '' !== $job['summary_file_name']) {
            $entry['summary_filename'] = $job['summary_file_name'];
        }

        $entry = apply_filters('tejlg_export_history_entry', $entry, $job, $context);

        return self::normalize_entry($entry);
    }

    private static function normalize_entry($entry) {
        if (!is_array($entry) || empty($entry['job_id'])) {
            return null;
        }

        $entry['job_id'] = (string) $entry['job_id'];
        $entry['status'] = isset($entry['status']) ? (string) $entry['status'] : '';

        $timestamp = isset($entry['timestamp']) ? (int) $entry['timestamp'] : time();
        $entry['timestamp'] = $timestamp > 0 ? $timestamp : time();

        $entry['user_id'] = isset($entry['user_id']) ? (int) $entry['user_id'] : 0;
        $entry['user_name'] = isset($entry['user_name']) ? sanitize_text_field($entry['user_name']) : '';
        $entry['user_login'] = isset($entry['user_login']) ? sanitize_user((string) $entry['user_login'], true) : '';
        $entry['zip_file_name'] = isset($entry['zip_file_name']) ? sanitize_text_field((string) $entry['zip_file_name']) : '';
        $entry['zip_file_size'] = isset($entry['zip_file_size']) ? max(0, (int) $entry['zip_file_size']) : 0;
        $entry['origin'] = isset($entry['origin']) ? sanitize_key($entry['origin']) : '';
        $entry['duration'] = isset($entry['duration']) ? max(0, (int) $entry['duration']) : 0;
        $entry['result'] = isset($entry['result']) ? sanitize_key($entry['result']) : '';

        $allowed_results = [
            self::RESULT_SUCCESS,
            self::RESULT_WARNING,
            self::RESULT_ERROR,
            self::RESULT_INFO,
        ];

        if (!in_array($entry['result'], $allowed_results, true)) {
            $entry['result'] = '';
        }

        $exclusions = isset($entry['exclusions']) ? (array) $entry['exclusions'] : [];
        $entry['exclusions'] = array_values(
            array_filter(
                array_map(
                    static function ($pattern) {
                        return (string) $pattern;
                    },
                    $exclusions
                ),
                static function ($pattern) {
                    return '' !== trim($pattern);
                }
            )
        );

        if (isset($entry['persistent_path']) && is_string($entry['persistent_path'])) {
            $entry['persistent_path'] = sanitize_text_field($entry['persistent_path']);
        }

        if (isset($entry['persistent_url']) && is_string($entry['persistent_url'])) {
            $entry['persistent_url'] = esc_url_raw($entry['persistent_url']);
        }

        if (isset($entry['summary_meta']) && is_array($entry['summary_meta'])) {
            $entry['summary_meta'] = self::sanitize_summary_meta($entry['summary_meta']);
        }

        if (isset($entry['summary_url']) && is_string($entry['summary_url'])) {
            $entry['summary_url'] = esc_url_raw($entry['summary_url']);
        }

        if (isset($entry['summary_filename']) && is_string($entry['summary_filename'])) {
            $entry['summary_filename'] = sanitize_file_name($entry['summary_filename']);
        }

        if (isset($entry['status_message']) && is_string($entry['status_message'])) {
            $entry['status_message'] = sanitize_textarea_field($entry['status_message']);
        } else {
            $entry['status_message'] = '';
        }

        if (isset($entry['context']) && is_string($entry['context'])) {
            $entry['context'] = sanitize_text_field($entry['context']);
        } else {
            $entry['context'] = '';
        }

        if (isset($entry['remote_connectors'])) {
            $entry['remote_connectors'] = self::sanitize_remote_connector_logs($entry['remote_connectors']);
        }

        return $entry;
    }

    /**
     * Normalize summary metadata stored alongside an export entry.
     *
     * @param mixed $meta Raw summary metadata.
     *
     * @return array{included_count:int,excluded_count:int,warnings:array<int,string>} Normalized metadata.
     */
    public static function sanitize_summary_meta($meta) {
        $meta = is_array($meta) ? $meta : [];

        $warnings = isset($meta['warnings']) ? (array) $meta['warnings'] : [];
        $warnings = array_values(
            array_filter(
                array_map('sanitize_text_field', $warnings),
                static function ($warning) {
                    return '' !== $warning;
                }
            )
        );

        return [
            'included_count' => isset($meta['included_count']) ? max(0, (int) $meta['included_count']) : 0,
            'excluded_count' => isset($meta['excluded_count']) ? max(0, (int) $meta['excluded_count']) : 0,
            'warnings'       => $warnings,
        ];
    }

    /**
     * Attaches remote connector logs to the entry matching the provided job ID.
     *
     * @param string $job_id
     * @param array  $results
     *
     * @return bool
     */
    public static function attach_remote_connector_results($job_id, array $results) {
        $job_id = (string) $job_id;

        if ('' === $job_id) {
            return false;
        }

        $logs = self::sanitize_remote_connector_logs($results);

        $entries = self::get_raw_entries();
        $updated = false;

        foreach ($entries as $index => $entry) {
            if (!is_array($entry) || !isset($entry['job_id'])) {
                continue;
            }

            if ((string) $entry['job_id'] !== $job_id) {
                continue;
            }

            $entries[$index]['remote_connectors'] = $logs;
            $updated = true;
            break;
        }

        if (!$updated) {
            return false;
        }

        self::save_entries($entries);

        return true;
    }

    /**
     * Sanitizes remote connector logs stored alongside an entry.
     *
     * @param mixed $logs
     *
     * @return array<int,array<string,mixed>>
     */
    public static function sanitize_remote_connector_logs($logs) {
        if (!is_array($logs)) {
            return [];
        }

        $sanitized = [];

        foreach ($logs as $log) {
            if (!is_array($log)) {
                continue;
            }

            $id = isset($log['id']) ? sanitize_key((string) $log['id']) : '';

            if ('' === $id) {
                continue;
            }

            $type    = isset($log['type']) ? sanitize_key((string) $log['type']) : '';
            $status  = isset($log['status']) ? sanitize_key((string) $log['status']) : 'success';
            $message = isset($log['message']) ? sanitize_text_field((string) $log['message']) : '';
            $location = isset($log['location']) ? sanitize_text_field((string) $log['location']) : '';
            $duration = isset($log['duration']) ? (float) $log['duration'] : 0.0;
            $timestamp = isset($log['timestamp']) ? max(0, (int) $log['timestamp']) : time();
            $meta = isset($log['meta']) ? self::sanitize_remote_connector_meta($log['meta']) : [];

            $sanitized[] = [
                'id'        => $id,
                'type'      => $type,
                'status'    => $status,
                'message'   => $message,
                'location'  => $location,
                'duration'  => $duration,
                'timestamp' => $timestamp,
                'meta'      => $meta,
            ];
        }

        return $sanitized;
    }

    /**
     * Recursively sanitizes connector metadata.
     *
     * @param mixed $meta
     *
     * @return mixed
     */
    private static function sanitize_remote_connector_meta($meta) {
        if (is_array($meta)) {
            $sanitized = [];

            foreach ($meta as $key => $value) {
                $normalized_key = is_string($key) ? sanitize_key($key) : (string) $key;
                $sanitized[$normalized_key] = self::sanitize_remote_connector_meta($value);
            }

            return $sanitized;
        }

        if (is_scalar($meta)) {
            if (is_string($meta)) {
                return sanitize_text_field($meta);
            }

            if (is_bool($meta)) {
                return $meta;
            }

            if (is_int($meta) || is_float($meta)) {
                return $meta + 0;
            }
        }

        return '';
    }

    private static function get_raw_entries() {
        $stored = get_option(self::OPTION_NAME, []);

        if (!is_array($stored)) {
            return [];
        }

        $normalized = [];

        foreach ($stored as $entry) {
            $normalized_entry = self::normalize_entry($entry);

            if (null !== $normalized_entry) {
                $normalized[] = $normalized_entry;
            }
        }

        return $normalized;
    }

    private static function save_entries(array $entries) {
        $entries = array_values($entries);

        update_option(self::OPTION_NAME, $entries, false);
    }

    /**
     * Generates an aggregated report from the export history.
     *
     * @param array<string,mixed> $args
     *
     * @return array<string,mixed>
     */
    public static function generate_report($args = []) {
        $defaults = [
            'window_days'     => 7,
            'result'          => '',
            'origin'          => '',
            'initiator'       => '',
            'limit'           => 20,
            'include_entries' => true,
        ];

        $args = wp_parse_args($args, $defaults);

        $window_days = isset($args['window_days']) ? (int) $args['window_days'] : 7;
        $window_days = $window_days >= 0 ? $window_days : 7;

        $result_filter = isset($args['result']) ? sanitize_key((string) $args['result']) : '';
        $origin_filter = isset($args['origin']) ? sanitize_key((string) $args['origin']) : '';
        $initiator_filter = isset($args['initiator']) ? sanitize_text_field((string) $args['initiator']) : '';
        $initiator_filter = trim($initiator_filter);

        $limit = isset($args['limit']) ? (int) $args['limit'] : 20;
        $limit = $limit >= 0 ? $limit : 20;

        $include_entries = !empty($args['include_entries']);

        $cutoff = 0;

        if ($window_days > 0) {
            $cutoff = time() - ($window_days * DAY_IN_SECONDS);
        }

        $entries = self::get_raw_entries();

        $filtered_entries = [];
        $result_counts = [
            self::RESULT_SUCCESS => 0,
            self::RESULT_WARNING => 0,
            self::RESULT_ERROR   => 0,
            self::RESULT_INFO    => 0,
        ];
        $origin_counts = [];
        $total_duration = 0;
        $total_size     = 0;
        $period_start   = null;
        $period_end     = null;

        foreach ($entries as $entry) {
            $timestamp = isset($entry['timestamp']) ? (int) $entry['timestamp'] : 0;

            if ($cutoff > 0 && $timestamp < $cutoff) {
                continue;
            }

            $entry_result = isset($entry['result']) ? (string) $entry['result'] : '';

            if ('' !== $result_filter && $entry_result !== $result_filter) {
                continue;
            }

            $entry_origin = isset($entry['origin']) ? (string) $entry['origin'] : '';

            if ('' !== $origin_filter && $entry_origin !== $origin_filter) {
                continue;
            }

            if ('' !== $initiator_filter && !self::entry_matches_initiator($entry, $initiator_filter)) {
                continue;
            }

            $filtered_entries[] = $entry;

            if (!isset($result_counts[$entry_result])) {
                $result_counts[$entry_result] = 0;
            }

            $result_counts[$entry_result]++;

            $origin_key = '' !== $entry_origin ? $entry_origin : 'unknown';

            if (!isset($origin_counts[$origin_key])) {
                $origin_counts[$origin_key] = 0;
            }

            $origin_counts[$origin_key]++;

            $duration = isset($entry['duration']) ? (int) $entry['duration'] : 0;
            $size     = isset($entry['zip_file_size']) ? (int) $entry['zip_file_size'] : 0;

            $total_duration += $duration > 0 ? $duration : 0;
            $total_size     += $size > 0 ? $size : 0;

            if (null === $period_start || $timestamp < $period_start) {
                $period_start = $timestamp;
            }

            if (null === $period_end || $timestamp > $period_end) {
                $period_end = $timestamp;
            }
        }

        ksort($origin_counts);

        foreach ($result_counts as $key => $value) {
            $result_counts[$key] = (int) $value;
        }

        $total_entries = count($filtered_entries);

        $uptime_rate = null;

        if ($total_entries > 0) {
            $successes  = isset($result_counts[self::RESULT_SUCCESS]) ? (int) $result_counts[self::RESULT_SUCCESS] : 0;
            $uptime_rate = round(($successes / $total_entries) * 100, 1);
        }

        $average_duration = $total_entries > 0 ? (int) round($total_duration / $total_entries) : 0;
        $average_size     = $total_entries > 0 ? (int) round($total_size / $total_entries) : 0;

        $report = [
            'generated_at' => time(),
            'filters'      => [
                'window_days' => $window_days,
                'result'      => $result_filter,
                'origin'      => $origin_filter,
                'initiator'   => $initiator_filter,
                'limit'       => $limit,
            ],
            'totals'       => [
                'entries'             => $total_entries,
                'duration_seconds'    => (int) $total_duration,
                'archive_size_bytes'  => (int) $total_size,
            ],
            'averages'     => [
                'duration_seconds'   => $average_duration,
                'archive_size_bytes' => $average_size,
            ],
            'counts'       => [
                'results' => $result_counts,
                'origins' => $origin_counts,
            ],
            'uptime_rate'  => $uptime_rate,
            'period_start' => $period_start,
            'period_end'   => $period_end,
            'latest_entry' => !empty($filtered_entries) ? $filtered_entries[0] : null,
            'entries'      => $include_entries
                ? array_slice($filtered_entries, 0, $limit > 0 ? $limit : null)
                : [],
        ];

        /**
         * Filters the generated export history report before it is returned.
         *
         * @param array<string,mixed> $report Report payload.
         * @param array<string,mixed> $args   Report arguments.
         */
        $report = apply_filters('tejlg_export_history_report', $report, $args);

        return $report;
    }

    private static function dispatch_recorded_hooks(array $entry, array $job, array $context) {
        /**
         * Fires when a new export history entry has been recorded.
         *
         * @param array $entry   Normalized history entry data.
         * @param array $job     Raw job payload prior to normalization.
         * @param array $context Additional context supplied to the recorder.
         */
        do_action('tejlg_export_history_recorded', $entry, $job, $context);

        if (empty($entry['result'])) {
            return;
        }

        $result_action = sprintf('tejlg_export_history_recorded_%s', $entry['result']);

        /**
         * Fires when a history entry of a specific result type has been recorded.
         *
         * The dynamic portion of the hook name corresponds to the result key
         * (success, warning, error, info).
         *
         * @param array $entry   Normalized history entry data.
         * @param array $job     Raw job payload prior to normalization.
         * @param array $context Additional context supplied to the recorder.
         */
        do_action($result_action, $entry, $job, $context);

        $report_args = apply_filters(
            'tejlg_export_history_report_args',
            [
                'window_days'     => 7,
                'limit'           => 10,
                'include_entries' => false,
            ],
            $entry,
            $job,
            $context
        );

        if (!is_array($report_args)) {
            return;
        }

        $report = self::generate_report($report_args);

        /**
         * Fires after an export history report has been generated for observers.
         *
         * @param array<string,mixed> $report  Aggregated report payload.
         * @param array<string,mixed> $entry   History entry that triggered the generation.
         * @param array<string,mixed> $job     Raw job payload.
         * @param array<string,mixed> $context Additional context supplied to the recorder.
         * @param array<string,mixed> $args    Arguments used to build the report.
         */
        do_action('tejlg_export_history_report_ready', $report, $entry, $job, $context, $report_args);
    }

    private static function determine_result_from_status($status) {
        $status = (string) $status;

        if ('' === $status) {
            return self::RESULT_INFO;
        }

        switch ($status) {
            case 'completed':
                return self::RESULT_SUCCESS;
            case 'failed':
                return self::RESULT_ERROR;
            case 'cancelled':
                return self::RESULT_WARNING;
            default:
                return self::RESULT_INFO;
        }
    }

    public static function get_available_filters() {
        $entries    = self::get_raw_entries();
        $origins    = [];
        $results    = [];
        $initiators = [];

        foreach ($entries as $entry) {
            if (isset($entry['origin']) && '' !== $entry['origin']) {
                $origins[$entry['origin']] = true;
            }

            if (isset($entry['result']) && '' !== $entry['result']) {
                $results[$entry['result']] = true;
            }

            $user_id   = isset($entry['user_id']) ? (int) $entry['user_id'] : 0;
            $user_name = isset($entry['user_name']) ? (string) $entry['user_name'] : '';
            $user_login = isset($entry['user_login']) ? (string) $entry['user_login'] : '';

            $initiator_key = self::build_initiator_key($user_id, $user_login, $user_name);

            if (!isset($initiators[$initiator_key])) {
                $initiators[$initiator_key] = [
                    'id'    => $user_id,
                    'name'  => $user_name,
                    'login' => $user_login,
                    'value' => self::build_initiator_value($user_id, $user_login, $user_name),
                    'label' => self::build_initiator_label($user_id, $user_login, $user_name),
                ];
            }
        }

        ksort($origins);
        ksort($results);

        uasort(
            $initiators,
            static function ($left, $right) {
                $left_label  = isset($left['label']) ? (string) $left['label'] : '';
                $right_label = isset($right['label']) ? (string) $right['label'] : '';

                return strcasecmp($left_label, $right_label);
            }
        );

        return [
            'origins' => array_keys($origins),
            'results' => array_keys($results),
            'initiators' => array_values($initiators),
        ];
    }

    private static function build_initiator_key($user_id, $user_login, $user_name) {
        if ($user_id > 0) {
            return 'id:' . $user_id;
        }

        if ('' !== $user_login) {
            return 'login:' . strtolower($user_login);
        }

        if ('' !== $user_name) {
            $normalized = self::normalize_initiator_fragment($user_name);

            return 'name:' . $normalized;
        }

        return 'system';
    }

    private static function build_initiator_value($user_id, $user_login, $user_name) {
        if ('' !== $user_login) {
            return '@' . $user_login;
        }

        if ($user_id > 0) {
            return '#' . $user_id;
        }

        if ('' !== $user_name) {
            return $user_name;
        }

        return 'system';
    }

    private static function build_initiator_label($user_id, $user_login, $user_name) {
        if ($user_id > 0) {
            $label = '' !== $user_name
                ? $user_name
                : sprintf(__('Utilisateur #%d', 'theme-export-jlg'), $user_id);

            $details = [];

            if ('' !== $user_login) {
                $details[] = '@' . $user_login;
            }

            $details[] = '#' . $user_id;

            if (!empty($details)) {
                $label .= ' (' . implode(' · ', $details) . ')';
            }

            return $label;
        }

        if ('' !== $user_name) {
            if ('' !== $user_login) {
                return sprintf('%1$s (@%2$s)', $user_name, $user_login);
            }

            return $user_name;
        }

        if ('' !== $user_login) {
            return '@' . $user_login;
        }

        return __('Système', 'theme-export-jlg');
    }

    private static function normalize_initiator_fragment($value) {
        $value = is_string($value) ? $value : '';

        if ('' === $value) {
            return '';
        }

        if (function_exists('remove_accents')) {
            $value = remove_accents($value);
        }

        return strtolower($value);
    }

    private static function entry_matches_initiator(array $entry, $search) {
        $search = is_string($search) ? trim($search) : '';

        if ('' === $search) {
            return true;
        }

        $normalized = self::normalize_initiator_fragment($search);
        $normalized = ltrim($normalized, "#@ \t\n\r\0\x0B");

        if ('' === $normalized) {
            return true;
        }

        $candidates = [];

        $user_id = isset($entry['user_id']) ? (int) $entry['user_id'] : 0;
        if ($user_id > 0) {
            $candidates[] = (string) $user_id;
            $candidates[] = '#' . $user_id;
        }

        $user_login = isset($entry['user_login']) ? (string) $entry['user_login'] : '';

        if ('' !== $user_login) {
            $candidates[] = $user_login;
            $candidates[] = '@' . $user_login;
        }

        $user_name = isset($entry['user_name']) ? (string) $entry['user_name'] : '';

        if ('' !== $user_name) {
            $candidates[] = $user_name;
        }

        if ($user_id <= 0 && '' === $user_login && '' === $user_name) {
            $candidates[] = 'system';
        }

        foreach ($candidates as $candidate) {
            $candidate_normalized = self::normalize_initiator_fragment($candidate);

            if ('' === $candidate_normalized) {
                continue;
            }

            if (false !== strpos($candidate_normalized, $normalized)) {
                return true;
            }
        }

        return false;
    }

    public static function get_recent_stats($days = 7) {
        $days = is_numeric($days) ? (int) $days : 7;
        $days = $days > 0 ? $days : 7;

        $cutoff = time() - ($days * DAY_IN_SECONDS);

        $entries = self::get_raw_entries();

        $counts = [
            self::RESULT_SUCCESS => 0,
            self::RESULT_WARNING => 0,
            self::RESULT_ERROR   => 0,
            self::RESULT_INFO    => 0,
        ];

        $recent_entries = [];

        foreach ($entries as $entry) {
            $result = isset($entry['result']) ? (string) $entry['result'] : '';

            if (!isset($counts[$result])) {
                $result = self::RESULT_INFO;
            }

            $timestamp = isset($entry['timestamp']) ? (int) $entry['timestamp'] : 0;

            if ($timestamp >= $cutoff) {
                $counts[$result]++;
                $recent_entries[] = $entry;
            }
        }

        $total_recent = 0;

        foreach ($counts as $result => $count) {
            $counts[$result] = (int) $count;
            $total_recent   += (int) $count;
        }

        $uptime_rate = null;

        if ($total_recent > 0) {
            $successes  = isset($counts[self::RESULT_SUCCESS]) ? (int) $counts[self::RESULT_SUCCESS] : 0;
            $uptime_rate = round(($successes / $total_recent) * 100, 1);
        }

        $latest_entry = null;

        if (!empty($entries)) {
            $latest_entry = $entries[0];
        }

        return [
            'window_days'   => $days,
            'counts'        => $counts,
            'total_recent'  => $total_recent,
            'uptime_rate'   => $uptime_rate,
            'latest_entry'  => $latest_entry,
            'recent_entries'=> $recent_entries,
        ];
    }
}

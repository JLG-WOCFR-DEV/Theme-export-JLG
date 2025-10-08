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
        $defaults = [
            'per_page' => 10,
            'paged'    => 1,
            'result'   => '',
            'origin'   => '',
            'orderby'  => 'timestamp',
            'order'    => 'desc',
        ];

        $args = wp_parse_args($args, $defaults);

        $per_page = isset($args['per_page']) ? (int) $args['per_page'] : 10;
        $per_page = $per_page > 0 ? $per_page : 10;

        $current_page = isset($args['paged']) ? (int) $args['paged'] : 1;
        $current_page = $current_page > 0 ? $current_page : 1;

        $result_filter = isset($args['result']) ? sanitize_key((string) $args['result']) : '';
        $origin_filter = isset($args['origin']) ? sanitize_key((string) $args['origin']) : '';

        $orderby = isset($args['orderby']) ? sanitize_key((string) $args['orderby']) : 'timestamp';
        $allowed_orderby = ['timestamp', 'duration', 'zip_file_size'];
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'timestamp';
        }

        $order = isset($args['order']) ? strtolower((string) $args['order']) : 'desc';
        $order = 'asc' === $order ? 'asc' : 'desc';

        $entries = self::get_raw_entries();

        if ('' !== $result_filter) {
            $entries = array_values(
                array_filter(
                    $entries,
                    static function ($entry) use ($result_filter) {
                        return is_array($entry)
                            && isset($entry['result'])
                            && (string) $entry['result'] === $result_filter;
                    }
                )
            );
        }

        if ('' !== $origin_filter) {
            $entries = array_values(
                array_filter(
                    $entries,
                    static function ($entry) use ($origin_filter) {
                        return is_array($entry)
                            && isset($entry['origin'])
                            && (string) $entry['origin'] === $origin_filter;
                    }
                )
            );
        }

        $total = count($entries);

        if ($total > 1) {
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
        }

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

    public static function count_entries() {
        return count(self::get_raw_entries());
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

        $user_name = '';

        if (isset($context['user_name']) && is_string($context['user_name'])) {
            $user_name = $context['user_name'];
        } elseif (!empty($job['created_by_name']) && is_string($job['created_by_name'])) {
            $user_name = $job['created_by_name'];
        } elseif ($user_id > 0) {
            $user = get_userdata($user_id);

            if ($user instanceof WP_User) {
                $user_name = $user->display_name;
            }
        }

        $user_name = sanitize_text_field($user_name);

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

        return $entry;
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
            'limit'           => 20,
            'include_entries' => true,
        ];

        $args = wp_parse_args($args, $defaults);

        $window_days = isset($args['window_days']) ? (int) $args['window_days'] : 7;
        $window_days = $window_days >= 0 ? $window_days : 7;

        $result_filter = isset($args['result']) ? sanitize_key((string) $args['result']) : '';
        $origin_filter = isset($args['origin']) ? sanitize_key((string) $args['origin']) : '';

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
        $entries = self::get_raw_entries();
        $origins = [];
        $results = [];

        foreach ($entries as $entry) {
            if (isset($entry['origin']) && '' !== $entry['origin']) {
                $origins[$entry['origin']] = true;
            }

            if (isset($entry['result']) && '' !== $entry['result']) {
                $results[$entry['result']] = true;
            }
        }

        ksort($origins);
        ksort($results);

        return [
            'origins' => array_keys($origins),
            'results' => array_keys($results),
        ];
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

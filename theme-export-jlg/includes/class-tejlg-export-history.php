<?php
if (!defined('ABSPATH')) {
    exit;
}

class TEJLG_Export_History {
    const OPTION_NAME = 'tejlg_export_history_entries';

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
    }

    public static function get_entries($args = []) {
        $defaults = [
            'per_page' => 10,
            'paged'    => 1,
        ];

        $args = wp_parse_args($args, $defaults);

        $per_page = isset($args['per_page']) ? (int) $args['per_page'] : 10;
        $per_page = $per_page > 0 ? $per_page : 10;

        $current_page = isset($args['paged']) ? (int) $args['paged'] : 1;
        $current_page = $current_page > 0 ? $current_page : 1;

        $entries = self::get_raw_entries();
        $total   = count($entries);

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
}

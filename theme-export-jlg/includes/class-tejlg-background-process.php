<?php
/**
 * Background processing utilities for Theme Export JLG.
 *
 * @package ThemeExportJLG
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_Async_Request', false ) ) {
    abstract class WP_Async_Request {

        /**
         * Prefix.
         *
         * @var string
         */
        protected $prefix = 'wp';

        /**
         * Action.
         *
         * @var string
         */
        protected $action = 'async_request';

        /**
         * Identifier.
         *
         * @var string
         */
        protected $identifier = '';

        /**
         * Data.
         *
         * @var array
         */
        protected $data = [];

        /**
         * Initiate new async request.
         */
        public function __construct() {
            add_action( 'admin_init', [ $this, 'maybe_handle' ] );
            add_action( 'wp_ajax_' . $this->get_action(), [ $this, 'maybe_handle' ] );
            add_action( 'wp_ajax_nopriv_' . $this->get_action(), [ $this, 'maybe_handle' ] );

            $this->identifier = $this->prefix . '_' . $this->action;
        }

        /**
         * Get identifier.
         *
         * @return string
         */
        public function get_identifier() {
            return $this->prefix . '_' . $this->action;
        }

        /**
         * Get action name.
         *
         * @return string
         */
        public function get_action() {
            return $this->prefix . '_' . $this->action;
        }

        /**
         * Set data.
         *
         * @param array $data Data.
         *
         * @return $this
         */
        public function data( array $data ) {
            $this->data = $data;

            return $this;
        }

        /**
         * Dispatch the async request.
         *
         * @return array|WP_Error
         */
        public function dispatch() {
            $url  = admin_url( 'admin-ajax.php' );
            $args = [
                'timeout'   => 0.01,
                'blocking'  => false,
                'body'      => $this->data,
                'cookies'   => wp_unslash( $_COOKIE ),
                'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
            ];

            return wp_remote_post( add_query_arg( 'action', $this->get_action(), $url ), $args );
        }

        /**
         * Maybe handle async request.
         */
        public function maybe_handle() {
            if ( ! isset( $_REQUEST['action'] ) || $this->get_action() !== $_REQUEST['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                return;
            }

            $this->handle();
            wp_die();
        }

        /**
         * Handle.
         */
        abstract protected function handle();
    }
}

if ( ! class_exists( 'WP_Background_Process', false ) ) {
    abstract class WP_Background_Process extends WP_Async_Request {

        /**
         * Action.
         *
         * @var string
         */
        protected $action = 'background_process';

        /**
         * Start time of current process.
         *
         * @var int
         */
        protected $start_time = 0;

        /**
         * Cron hook identifier.
         *
         * @var string
         */
        protected $cron_hook_identifier;

        /**
         * Cron interval identifier.
         *
         * @var string
         */
        protected $cron_interval_identifier;

        /**
         * Initiate new background process.
         */
        public function __construct() {
            parent::__construct();

            $this->cron_hook_identifier     = $this->identifier . '_cron';
            $this->cron_interval_identifier = $this->identifier . '_cron_interval';

            add_action( $this->cron_hook_identifier, [ $this, 'handle_cron_healthcheck' ] );
            add_filter( 'cron_schedules', [ $this, 'schedule_cron_healthcheck' ] );
        }

        /**
         * Dispatch background process.
         */
        public function dispatch() {
            $dispatch = parent::dispatch();

            if ( is_wp_error( $dispatch ) ) {
                return $dispatch;
            }

            if ( false === $dispatch ) {
                return new WP_Error( 'wp_background_process_unavailable', __( 'Background processing is unavailable.', 'theme-export-jlg' ) );
            }

            $this->schedule_event();

            return $dispatch;
        }

        /**
         * Push to queue.
         *
         * @param mixed $data Data.
         *
         * @return $this
         */
        public function push_to_queue( $data ) {
            $queue = $this->get_queue();
            $queue[] = $data;
            $this->update_queue( $queue );

            return $this;
        }

        /**
         * Save queue.
         *
         * @return $this
         */
        public function save() {
            if ( ! $this->is_queue_empty() ) {
                $this->dispatch();
            }

            return $this;
        }

        /**
         * Update queue.
         *
         * @param array $queue Queue.
         */
        protected function update_queue( array $queue ) {
            update_site_option( $this->get_queue_key(), $queue );
        }

        /**
         * Get queue.
         *
         * @return array
         */
        protected function get_queue() {
            $queue = get_site_option( $this->get_queue_key(), [] );

            if ( ! is_array( $queue ) ) {
                $queue = [];
            }

            return $queue;
        }

        /**
         * Get queue option key.
         *
         * @return string
         */
        protected function get_queue_key() {
            return $this->identifier . '_queue';
        }

        /**
         * Is queue empty.
         *
         * @return bool
         */
        protected function is_queue_empty() {
            $queue = $this->get_queue();

            return empty( $queue );
        }

        /**
         * Maybe handle.
         */
        public function maybe_handle() {
            parent::maybe_handle();
        }

        /**
         * Handle.
         */
        protected function handle() {
            $this->handle_process();
            $this->stop_healthcheck();
        }

        /**
         * Handle queue.
         */
        protected function handle_queue() {
            $queue = $this->get_queue();

            if ( empty( $queue ) ) {
                $this->complete();
                $this->clear_queue();
                return;
            }

            $this->start_time = time();

            $batch = array_shift( $queue );
            $this->update_queue( $queue );

            $task = $this->task( $batch );

            if ( false !== $task ) {
                $queue = $this->get_queue();
                $queue[] = $task;
                $this->update_queue( $queue );
            }

            if ( ! $this->is_queue_empty() ) {
                $this->dispatch();
            } else {
                $this->complete();
                $this->clear_queue();
            }
        }

        /**
         * Task.
         *
         * @param mixed $item Queue item to iterate over.
         *
         * @return mixed
         */
        abstract protected function task( $item );

        /**
         * Complete.
         */
        protected function complete() {}

        /**
         * Clear queue.
         */
        protected function clear_queue() {
            delete_site_option( $this->get_queue_key() );
        }

        /**
         * Schedule event.
         */
        protected function schedule_event() {
            if ( ! wp_next_scheduled( $this->cron_hook_identifier ) ) {
                wp_schedule_event( time() + 10, $this->cron_interval_identifier, $this->cron_hook_identifier );
            }
        }

        /**
         * Schedule cron healthcheck.
         *
         * @param array $schedules Schedules.
         *
         * @return array
         */
        public function schedule_cron_healthcheck( $schedules ) {
            $schedules[ $this->cron_interval_identifier ] = [
                'interval' => apply_filters( $this->identifier . '_cron_interval', 5 * MINUTE_IN_SECONDS ),
                'display'  => __( 'Every Five Minutes', 'theme-export-jlg' ),
            ];

            return $schedules;
        }

        /**
         * Handle cron healthcheck.
         */
        public function handle_cron_healthcheck() {
            if ( $this->is_process_running() ) {
                return;
            }

            if ( $this->is_queue_empty() ) {
                $this->stop_healthcheck();
                return;
            }

            $this->dispatch();
        }

        /**
         * Is process running.
         *
         * @return bool
         */
        protected function is_process_running() {
            return (bool) get_site_transient( $this->identifier . '_process_lock' );
        }

        /**
         * Lock process.
         */
        protected function lock_process() {
            $this->start_time = time();
            $lock_duration    = apply_filters( $this->identifier . '_queue_lock_time', 60 );

            set_site_transient( $this->identifier . '_process_lock', microtime(), $lock_duration );
        }

        /**
         * Unlock process.
         */
        protected function unlock_process() {
            delete_site_transient( $this->identifier . '_process_lock' );
        }

        /**
         * Stop healthcheck.
         */
        protected function stop_healthcheck() {
            $timestamp = wp_next_scheduled( $this->cron_hook_identifier );

            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, $this->cron_hook_identifier );
            }
        }

        /**
         * Handle queue.
         */
        protected function handle_process() {
            if ( $this->is_process_running() ) {
                if ( $this->time_exceeded() ) {
                    $this->unlock_process();
                } else {
                    return;
                }
            }

            $this->lock_process();
            $this->handle_queue();
            $this->unlock_process();
        }

        /**
         * Check if the current process has exceeded the time limit.
         *
         * @return bool
         */
        protected function time_exceeded() {
            if ( empty( $this->start_time ) ) {
                return false;
            }

            $finish = $this->start_time + apply_filters( $this->identifier . '_queue_time_limit', 20 );

            return time() > $finish;
        }
    }
}

<?php
/**
 * Minimal implementation of the WP Background Processing library.
 *
 * This file is adapted from the original library published by Delicious Brains
 * under the GPL license. Only the portions required for the Theme Export JLG
 * plugin are included.
 *
 * @package Theme_Export_JLG
 */

if (class_exists('WP_Background_Process')) {
    return;
}

abstract class WP_Background_Process {

    /**
     * Action name used to identify the process.
     *
     * @var string
     */
    protected $action = 'background_process';

    /**
     * Queue identifier.
     *
     * @var string
     */
    protected $identifier = 'wp_background_process';

    /**
     * Cron hook identifier.
     *
     * @var string
     */
    protected $cron_hook_identifier;

    /**
     * Identifier for the option used to store queued items.
     *
     * @var string
     */
    protected $queue_key;

    /**
     * Data store used to temporarily hold the queue items.
     *
     * @var array
     */
    protected $data = [];

    /**
     * Whether the queue has been saved.
     *
     * @var bool
     */
    protected $queue_saved = false;

    /**
     * Initiate a new background process.
     */
    public function __construct() {
        $this->identifier           = $this->identifier . '_' . $this->action;
        $this->cron_hook_identifier = $this->identifier . '_cron';
        $this->queue_key            = $this->identifier . '_queue';

        add_action($this->cron_hook_identifier, [$this, 'handle_cron_healthcheck']);
        add_action('shutdown', [$this, 'dispatch']);
    }

    /**
     * Push an item to the queue.
     *
     * @param mixed $data Queue item to add.
     *
     * @return $this
     */
    public function push_to_queue($data) {
        $this->data[] = $data;
        $this->queue_saved = false;

        return $this;
    }

    /**
     * Save the queue to the options table.
     *
     * @return $this
     */
    public function save() {
        if (!empty($this->data)) {
            $queue = get_option($this->queue_key, []);

            if (!is_array($queue)) {
                $queue = [];
            }

            $queue[] = $this->data;

            update_option($this->queue_key, $queue, false);
            $this->data = [];
        }

        $this->queue_saved = true;

        return $this;
    }

    /**
     * Dispatch the queue for processing.
     */
    public function dispatch() {
        if (!$this->queue_saved && !empty($this->data)) {
            $this->save();
        }

        if ($this->is_processing()) {
            return;
        }

        if (!$this->has_items()) {
            return;
        }

        $this->schedule_event();
    }

    /**
     * Handle cron healthcheck.
     */
    public function handle_cron_healthcheck() {
        if ($this->is_processing()) {
            return;
        }

        if (!$this->has_items()) {
            $this->clear_scheduled_event();
            return;
        }

        $this->handle();
    }

    /**
     * Process queue items.
     */
    public function handle() {
        $this->lock_process();

        do {
            $batch = $this->get_batch();

            if (empty($batch)) {
                $this->unlock_process();
                break;
            }

            foreach ($batch as $key => $value) {
                $result = $this->task($value);

                if (false === $result) {
                    unset($batch[$key]);
                    continue;
                }

                $batch[$key] = $result;
            }

            if (!empty($batch)) {
                $this->update_batch($batch);
            } else {
                $this->delete_batch();
            }
        } while (!empty($batch));

        $this->unlock_process();

        if (!$this->has_items()) {
            $this->complete();
        }

        if ($this->has_items()) {
            $this->schedule_event();
        }
    }

    /**
     * Get the cron hook identifier used for scheduled events.
     *
     * @return string
     */
    public function get_cron_hook_identifier() {
        return $this->cron_hook_identifier;
    }

    /**
     * Determine if a process is currently running.
     *
     * @return bool
     */
    protected function is_processing() {
        return (bool) get_transient($this->identifier . '_process_lock');
    }

    /**
     * Lock the process to prevent concurrent execution.
     */
    protected function lock_process() {
        set_transient($this->identifier . '_process_lock', microtime(), MINUTE_IN_SECONDS);
    }

    /**
     * Unlock the process.
     */
    protected function unlock_process() {
        delete_transient($this->identifier . '_process_lock');
    }

    /**
     * Check if there are items left in the queue.
     *
     * @return bool
     */
    protected function has_items() {
        $queue = get_option($this->queue_key, []);

        if (!is_array($queue) || empty($queue)) {
            return false;
        }

        foreach ($queue as $batch) {
            if (!empty($batch)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieve the first batch from the queue.
     *
     * @return array
     */
    protected function get_batch() {
        $queue = get_option($this->queue_key, []);

        if (!is_array($queue) || empty($queue)) {
            return [];
        }

        $batch = array_shift($queue);

        update_option($this->queue_key, $queue, false);

        if (!is_array($batch)) {
            return [];
        }

        return $batch;
    }

    /**
     * Update the current batch after processing.
     *
     * @param array $batch Items to place back in the queue.
     */
    protected function update_batch($batch) {
        if (empty($batch)) {
            return;
        }

        $queue = get_option($this->queue_key, []);

        if (!is_array($queue)) {
            $queue = [];
        }

        array_unshift($queue, $batch);
        update_option($this->queue_key, $queue, false);
    }

    /**
     * Delete the current batch from the queue.
     */
    protected function delete_batch() {
        // No action required as the batch has already been removed.
    }

    /**
     * Schedule the next event.
     */
    protected function schedule_event() {
        if (wp_next_scheduled($this->cron_hook_identifier)) {
            return;
        }

        wp_schedule_single_event(time() + 5, $this->cron_hook_identifier);
    }

    /**
     * Clear any scheduled events for this process.
     */
    protected function clear_scheduled_event() {
        $timestamp = wp_next_scheduled($this->cron_hook_identifier);

        if (false !== $timestamp) {
            wp_unschedule_event($timestamp, $this->cron_hook_identifier);
        }
    }

    /**
     * Cancel the background process and clear any queued items.
     */
    public function cancel_process() {
        $this->data        = [];
        $this->queue_saved = true;

        delete_option($this->queue_key);

        $this->clear_scheduled_event();
        $this->unlock_process();
    }

    /**
     * Task to perform on each queue item.
     *
     * @param mixed $item Queue item.
     *
     * @return mixed False when complete, or the modified item to requeue.
     */
    abstract protected function task($item);

    /**
     * Called when the queue has been fully processed.
     */
    protected function complete() {}
}


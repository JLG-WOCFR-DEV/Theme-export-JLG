<?php

abstract class TEJLG_Admin_Page {
    protected $template_dir;

    /**
     * Shared context injected into each rendered template.
     *
     * @var array<string,mixed>
     */
    private $shared_context = [];

    public function __construct($template_dir) {
        $this->template_dir = trailingslashit($template_dir);
    }

    /**
     * Merge shared context variables that should be exposed to all templates.
     *
     * @param array<string,mixed> $context
     */
    public function set_shared_context(array $context) {
        $this->shared_context = array_merge($this->shared_context, $context);
    }

    protected function render_template($template, array $context = []) {
        $path = $this->template_dir . ltrim($template, '/');

        if (!file_exists($path)) {
            return;
        }

        $context = array_merge($this->shared_context, $context);

        extract($context, EXTR_SKIP);

        include $path;
    }
}

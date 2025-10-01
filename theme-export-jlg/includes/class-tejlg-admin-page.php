<?php

abstract class TEJLG_Admin_Page {
    protected $template_dir;

    public function __construct($template_dir) {
        $this->template_dir = trailingslashit($template_dir);
    }

    protected function render_template($template, array $context = []) {
        $path = $this->template_dir . ltrim($template, '/');

        if (!file_exists($path)) {
            return;
        }

        extract($context, EXTR_SKIP);

        include $path;
    }
}

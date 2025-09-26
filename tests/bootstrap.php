<?php

$_tests_dir = getenv('WP_TESTS_DIR');

if (!$_tests_dir) {
    $_tests_dir = getenv('WP_PHPUNIT__DIR');
}

if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

if (!is_dir($_tests_dir) || !file_exists($_tests_dir . '/includes/functions.php')) {
    fwrite(STDERR, sprintf("Could not find the WordPress test library in %s.\n", $_tests_dir));
    exit(1);
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter('muplugins_loaded', function () {
    require dirname(__DIR__) . '/theme-export-jlg/theme-export-jlg.php';
});

require $_tests_dir . '/includes/bootstrap.php';

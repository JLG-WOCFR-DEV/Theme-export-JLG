<?php

define('DB_NAME', getenv('WP_TESTS_DB_NAME') ?: 'wordpress_test');
define('DB_USER', getenv('WP_TESTS_DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('WP_TESTS_DB_PASSWORD') ?: '');
define('DB_HOST', getenv('WP_TESTS_DB_HOST') ?: 'localhost:/run/mysqld/mysqld.sock');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

define('WP_TESTS_DOMAIN', getenv('WP_TESTS_DOMAIN') ?: 'example.org');
define('WP_TESTS_EMAIL', getenv('WP_TESTS_EMAIL') ?: 'admin@example.org');
define('WP_TESTS_TITLE', getenv('WP_TESTS_TITLE') ?: 'Theme Export - JLG Tests');

define('WP_PHP_BINARY', getenv('WP_PHP_BINARY') ?: PHP_BINARY);

define('WPLANG', '');

define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', true);

define('ABSPATH', rtrim(getenv('WP_TESTS_ABSPATH') ?: '/usr/share/wordpress/', '/\\') . '/');

$table_prefix = 'wp_tests_';

<?php

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-admin.php';
require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-import.php';
require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export.php';

class TEJLG_WPDie_Exception extends Exception {}

/**
 * @group pattern-sanitizer
 */
class Test_Pattern_Sanitizer extends WP_UnitTestCase {

    protected $previous_home_url;
    protected $previous_site_url;
    protected $custom_batch_size = 3;

    public static function get_wp_die_handler() {
        return [self::class, 'handle_wp_die'];
    }

    public static function handle_wp_die($message, $title = '', $args = []) {
        throw new TEJLG_WPDie_Exception($message);
    }

    public static function replace_patterns_file_handle($handle, $temp_file) {
        $failing_handle = fopen('/dev/full', 'w');

        if (false === $failing_handle) {
            return $handle;
        }

        if (is_resource($handle)) {
            fclose($handle);
        }

        return $failing_handle;
    }

    public function setUp(): void {
        parent::setUp();

        $this->previous_home_url = get_option('home');
        $this->previous_site_url = get_option('siteurl');
        $this->custom_batch_size = 3;
    }

    public function tearDown(): void {
        update_option('home', $this->previous_home_url);
        update_option('siteurl', $this->previous_site_url);

        parent::tearDown();
    }

    public function test_block_comments_preserved_with_greater_than_in_attributes() {
        $user_id = self::factory()->user->create([
            'role' => 'subscriber',
        ]);

        wp_set_current_user($user_id);

        $raw_content = '<!-- wp:paragraph {"placeholder": ">"} -->Hello<!-- /wp:paragraph -->';

        $method = new ReflectionMethod(TEJLG_Import::class, 'sanitize_pattern_content_for_current_user');
        $method->setAccessible(true);

        $sanitized_content = $method->invoke(null, $raw_content);

        $this->assertStringContainsString('<!-- wp:paragraph {"placeholder": ">"} -->', $sanitized_content);
        $this->assertStringContainsString('<!-- /wp:paragraph -->', $sanitized_content);
    }

    public function test_block_comments_preserved_with_closing_sequence_in_attributes() {
        $user_id = self::factory()->user->create([
            'role' => 'subscriber',
        ]);

        wp_set_current_user($user_id);

        $raw_content = '<!-- wp:paragraph {"placeholder": "-->"} -->Content<!-- /wp:paragraph -->';

        $method = new ReflectionMethod(TEJLG_Import::class, 'sanitize_pattern_content_for_current_user');
        $method->setAccessible(true);

        $sanitized_content = $method->invoke(null, $raw_content);

        $this->assertStringContainsString('<!-- wp:paragraph {"placeholder": "-->"} -->', $sanitized_content);
        $this->assertStringContainsString('<!-- /wp:paragraph -->', $sanitized_content);
    }

    public function test_clean_pattern_content_preserves_prefixed_paths_outside_home_path() {
        update_option('home', 'http://example.com/sub');
        update_option('siteurl', 'http://example.com/sub');

        $content = '<a href="http://example.com/sub/page">Page</a>'
            . '<a href="http://example.com/submarine-news">News</a>';

        $method = new ReflectionMethod(TEJLG_Export::class, 'clean_pattern_content');
        $method->setAccessible(true);

        $cleaned = $method->invoke(null, $content);

        $this->assertStringContainsString('href="/page"', $cleaned);
        $this->assertStringContainsString('href="/submarine-news"', $cleaned);
    }

    public function test_clean_pattern_content_neutralizes_block_attribute_ids_without_touching_text() {
        $content = '<!-- wp:paragraph {"id":123} -->'
            . '<p>{"id":123}</p>'
            . '<!-- /wp:paragraph -->';

        $method = new ReflectionMethod(TEJLG_Export::class, 'clean_pattern_content');
        $method->setAccessible(true);

        $cleaned = $method->invoke(null, $content);

        $this->assertStringContainsString('<!-- wp:paragraph {"id":0}', $cleaned);
        $this->assertStringContainsString('<p>{"id":123}</p>', $cleaned);
        $this->assertStringNotContainsString('<!-- wp:paragraph {"id":123}', $cleaned);
    }

    public function test_export_patterns_json_keeps_subdirectory_prefix_for_media_urls() {
        update_option('home', 'https://example.com/subdir');
        update_option('siteurl', 'https://example.com/subdir');

        $media_url = 'https://example.com/subdir/wp-content/uploads/2024/05/test.png';
        $content   = '<!-- wp:image -->'
            . '<figure class="wp-block-image"><img src="' . $media_url . '" alt=""/></figure>'
            . '<!-- /wp:image -->';

        $pattern_id = self::factory()->post->create([
            'post_type'    => 'wp_block',
            'post_status'  => 'publish',
            'post_title'   => 'Subdir Pattern',
            'post_content' => $content,
        ]);

        add_filter('tejlg_export_stream_json_file', '__return_false');

        try {
            $json = TEJLG_Export::export_selected_patterns_json([$pattern_id], true);
        } finally {
            remove_filter('tejlg_export_stream_json_file', '__return_false');
        }

        $this->assertIsString($json);
        $this->assertNotSame('', $json);

        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);
        $this->assertArrayHasKey('content', $decoded[0]);
        $this->assertStringContainsString('/subdir/wp-content/uploads/2024/05/test.png', $decoded[0]['content']);
    }

    public function test_export_patterns_json_aborts_when_write_fails() {
        add_filter('wp_die_handler', [self::class, 'get_wp_die_handler']);
        add_filter('tejlg_export_patterns_file_handle', [self::class, 'replace_patterns_file_handle'], 10, 2);

        $this->expectException(TEJLG_WPDie_Exception::class);
        $this->expectExceptionMessage("Une erreur critique est survenue lors de l&#039;écriture du fichier JSON d&#039;export.");

        try {
            TEJLG_Export::export_patterns_json();
        } finally {
            remove_filter('tejlg_export_patterns_file_handle', [self::class, 'replace_patterns_file_handle'], 10);
            remove_filter('wp_die_handler', [self::class, 'get_wp_die_handler'], 10);
        }
    }

    public function filter_export_meta_keys($keys) {
        $keys[] = 'custom_meta_key';

        return array_values(array_unique($keys));
    }

    private function ensure_taxonomy_registered($taxonomy, $args = []) {
        if (taxonomy_exists($taxonomy)) {
            return;
        }

        $default_args = [
            'hierarchical' => false,
            'show_ui'      => true,
            'show_in_rest' => true,
        ];

        $args = wp_parse_args($args, $default_args);

        register_taxonomy($taxonomy, 'wp_block', $args);
    }

    public function test_export_patterns_json_includes_additional_metadata() {
        $this->ensure_taxonomy_registered('wp_pattern_category', [
            'hierarchical' => true,
        ]);
        $this->ensure_taxonomy_registered('wp_pattern_tag');

        $pattern_id = self::factory()->post->create([
            'post_type'    => 'wp_block',
            'post_status'  => 'publish',
            'post_title'   => 'Metadata Export Pattern',
            'post_name'    => 'metadata-export-pattern',
            'post_excerpt' => 'Résumé exporté',
            'post_content' => '<!-- wp:paragraph --><p>Metadata export content</p><!-- /wp:paragraph -->',
        ]);

        wp_set_post_terms($pattern_id, ['landing'], 'wp_pattern_category', false);
        wp_set_post_terms($pattern_id, ['featured'], 'wp_pattern_tag', false);

        update_post_meta($pattern_id, 'viewportWidth', 1440);
        update_post_meta($pattern_id, 'custom_meta_key', 'Valeur personnalisée');

        add_filter('tejlg_export_stream_json_file', '__return_false');
        add_filter('tejlg_export_patterns_meta_keys', [$this, 'filter_export_meta_keys']);

        try {
            $json = TEJLG_Export::export_selected_patterns_json([$pattern_id], false);
        } finally {
            remove_filter('tejlg_export_patterns_meta_keys', [$this, 'filter_export_meta_keys']);
            remove_filter('tejlg_export_stream_json_file', '__return_false');
        }

        $this->assertIsString($json);

        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);

        $exported_pattern = $decoded[0];

        $this->assertArrayHasKey('post_excerpt', $exported_pattern);
        $this->assertSame('Résumé exporté', $exported_pattern['post_excerpt']);

        $this->assertArrayHasKey('taxonomies', $exported_pattern);
        $this->assertArrayHasKey('wp_pattern_category', $exported_pattern['taxonomies']);
        $this->assertArrayHasKey('wp_pattern_tag', $exported_pattern['taxonomies']);
        $this->assertSame(['landing'], $exported_pattern['taxonomies']['wp_pattern_category']);
        $this->assertSame(['featured'], $exported_pattern['taxonomies']['wp_pattern_tag']);

        $this->assertArrayHasKey('meta', $exported_pattern);
        $this->assertArrayHasKey('viewportWidth', $exported_pattern['meta']);
        $this->assertArrayHasKey('custom_meta_key', $exported_pattern['meta']);
        $this->assertSame('Valeur personnalisée', $exported_pattern['meta']['custom_meta_key']);

        $this->assertArrayHasKey('viewportWidth', $exported_pattern);
        $this->assertSame(1440, $exported_pattern['viewportWidth']);

        wp_delete_post($pattern_id, true);
    }

    public function test_import_restores_excerpt_taxonomies_and_meta() {
        $admin_id = self::factory()->user->create([
            'role' => 'administrator',
        ]);

        wp_set_current_user($admin_id);

        $this->ensure_taxonomy_registered('wp_pattern_category', [
            'hierarchical' => true,
        ]);
        $this->ensure_taxonomy_registered('wp_pattern_tag');

        $pattern = [
            'title'         => 'Metadata Import Pattern',
            'slug'          => 'metadata-import-pattern',
            'content'       => '<!-- wp:paragraph --><p>Metadata import content</p><!-- /wp:paragraph -->',
            'post_excerpt'  => 'Résumé importé',
            'taxonomies'    => [
                'wp_pattern_category' => ['landing'],
                'wp_pattern_tag'      => ['featured'],
            ],
            'meta'          => [
                'custom_meta_key' => 'Valeur importée',
                'viewportWidth'   => 960,
            ],
            'viewportWidth' => 960,
        ];

        $create_payload = new ReflectionMethod(TEJLG_Import::class, 'create_patterns_storage_payload');
        $create_payload->setAccessible(true);

        $transient_id = 'tejlg_' . md5(uniqid('metadata-import', true));
        $payload      = $create_payload->invoke(null, [$pattern]);

        set_transient($transient_id, $payload, HOUR_IN_SECONDS);

        global $wp_settings_errors;
        $wp_settings_errors = [];

        TEJLG_Import::handle_patterns_import_step2($transient_id, [0]);

        $imported_post = get_page_by_path('metadata-import-pattern', OBJECT, 'wp_block');

        $this->assertInstanceOf(WP_Post::class, $imported_post);
        $this->assertSame('Résumé importé', $imported_post->post_excerpt);

        $category_terms = wp_get_post_terms($imported_post->ID, 'wp_pattern_category', ['fields' => 'slugs']);
        $this->assertContains('landing', $category_terms);

        $tag_terms = wp_get_post_terms($imported_post->ID, 'wp_pattern_tag', ['fields' => 'slugs']);
        $this->assertContains('featured', $tag_terms);

        $this->assertSame('Valeur importée', get_post_meta($imported_post->ID, 'custom_meta_key', true));
        $this->assertSame(960, (int) get_post_meta($imported_post->ID, 'viewportWidth', true));

        $messages = get_settings_errors('tejlg_import_messages');
        $this->assertNotEmpty($messages);
        $this->assertContains('success', wp_list_pluck($messages, 'type'));

        wp_delete_post($imported_post->ID, true);
        TEJLG_Import::delete_patterns_storage($transient_id, $payload);
    }

    public function test_preview_and_import_succeeds_with_array_content() {
        $admin_id = self::factory()->user->create([
            'role' => 'administrator',
        ]);

        wp_set_current_user($admin_id);

        $raw_block_content = '<!-- wp:paragraph --><p>Array content body</p><!-- /wp:paragraph -->';

        $pattern = [
            'title'   => 'Array Content Pattern',
            'slug'    => 'custom-patterns/array-content-pattern',
            'name'    => 'array-content-pattern',
            'content' => [
                'raw'      => $raw_block_content,
                'rendered' => '<p>Array content body</p>',
            ],
        ];

        $create_payload = new ReflectionMethod(TEJLG_Import::class, 'create_patterns_storage_payload');
        $create_payload->setAccessible(true);

        $preview_transient_id = 'tejlg_' . md5(uniqid('preview', true));
        $preview_payload      = $create_payload->invoke(null, [$pattern]);

        set_transient($preview_transient_id, $preview_payload, HOUR_IN_SECONDS);

        $template_dir   = dirname(__DIR__) . '/theme-export-jlg/templates/admin/';
        $import_page    = new TEJLG_Admin_Import_Page($template_dir, 'theme-export-jlg');
        $preview_method = new ReflectionMethod(TEJLG_Admin_Import_Page::class, 'render_patterns_preview_page');
        $preview_method->setAccessible(true);

        ob_start();
        $preview_method->invoke($import_page, $preview_transient_id);
        $preview_output = ob_get_clean();

        $this->assertStringContainsString('pattern-preview-iframe', $preview_output);
        $this->assertStringContainsString('Array Content Pattern', $preview_output);
        $this->assertStringNotContainsString('Erreur : Aucune composition valide', $preview_output);

        TEJLG_Import::delete_patterns_storage($preview_transient_id, $preview_payload);

        $import_transient_id = 'tejlg_' . md5(uniqid('import', true));
        $import_payload      = $create_payload->invoke(null, [$pattern]);

        set_transient($import_transient_id, $import_payload, HOUR_IN_SECONDS);

        global $wp_settings_errors;
        $wp_settings_errors = [];

        TEJLG_Import::handle_patterns_import_step2($import_transient_id, [0]);

        $imported_post = get_page_by_path('array-content-pattern', OBJECT, 'wp_block');
        $this->assertInstanceOf(WP_Post::class, $imported_post);
        $this->assertStringContainsString('Array content body', $imported_post->post_content);

        $messages = get_settings_errors('tejlg_import_messages');
        $this->assertNotEmpty($messages);

        $message_types = wp_list_pluck($messages, 'type');
        $this->assertContains('success', $message_types);

        wp_delete_post($imported_post->ID, true);
        TEJLG_Import::delete_patterns_storage($import_transient_id, $import_payload);
    }

    public function test_preview_handles_invalid_utf8_bytes_in_rendered_content() {
        $admin_id = self::factory()->user->create([
            'role' => 'administrator',
        ]);

        wp_set_current_user($admin_id);

        $invalid_byte = "\xC3";
        $raw_block_content = '<!-- wp:paragraph --><p>Texte invalide ' . $invalid_byte . '</p><!-- /wp:paragraph -->';

        $pattern = [
            'title'   => 'Invalid UTF8 Pattern',
            'slug'    => 'custom-patterns/invalid-utf8-pattern',
            'name'    => 'invalid-utf8-pattern',
            'content' => [
                'raw'      => $raw_block_content,
                'rendered' => '<p>Texte invalide ' . $invalid_byte . '</p>',
            ],
        ];

        $create_payload = new ReflectionMethod(TEJLG_Import::class, 'create_patterns_storage_payload');
        $create_payload->setAccessible(true);

        $preview_transient_id = 'tejlg_' . md5(uniqid('preview-invalid', true));
        $preview_payload      = $create_payload->invoke(null, [$pattern]);

        set_transient($preview_transient_id, $preview_payload, HOUR_IN_SECONDS);

        $template_dir   = dirname(__DIR__) . '/theme-export-jlg/templates/admin/';
        $import_page    = new TEJLG_Admin_Import_Page($template_dir, 'theme-export-jlg');
        $preview_method = new ReflectionMethod(TEJLG_Admin_Import_Page::class, 'render_patterns_preview_page');
        $preview_method->setAccessible(true);

        ob_start();
        $preview_method->invoke($import_page, $preview_transient_id);
        $preview_output = ob_get_clean();

        $this->assertStringContainsString('pattern-preview-iframe', $preview_output);
        $this->assertStringContainsString('Invalid UTF8 Pattern', $preview_output);
        $this->assertStringNotContainsString('Impossible d\'encoder l\'aperçu JSON', $preview_output);

        preg_match_all('/<script type="application\/json" class="pattern-preview-data">(.*?)<\/script>/s', $preview_output, $matches);

        $this->assertNotEmpty($matches[1]);

        $decoded_iframe = json_decode($matches[1][0], true);

        $this->assertNotNull($decoded_iframe);
        $this->assertIsString($decoded_iframe);
        $this->assertStringContainsString('Texte invalide', $decoded_iframe);

        TEJLG_Import::delete_patterns_storage($preview_transient_id, $preview_payload);
    }

    public function filter_export_batch_size($size) {
        return $this->custom_batch_size;
    }

    public function test_export_patterns_json_processes_all_pages_before_stopping() {
        $pattern_ids = [];

        for ($i = 0; $i < 7; $i++) {
            $pattern_ids[] = self::factory()->post->create([
                'post_type'    => 'wp_block',
                'post_status'  => 'publish',
                'post_title'   => 'Pattern ' . $i,
                'post_name'    => 'pattern-' . $i,
                'post_content' => '<!-- wp:paragraph --><p>Pattern ' . $i . '</p><!-- /wp:paragraph -->',
            ]);
        }

        add_filter('tejlg_export_patterns_batch_size', [$this, 'filter_export_batch_size']);
        add_filter('tejlg_export_stream_json_file', '__return_false');

        try {
            $json = TEJLG_Export::export_selected_patterns_json($pattern_ids, false);
        } finally {
            remove_filter('tejlg_export_patterns_batch_size', [$this, 'filter_export_batch_size']);
            remove_filter('tejlg_export_stream_json_file', '__return_false');
        }

        foreach ($pattern_ids as $pattern_id) {
            wp_delete_post($pattern_id, true);
        }

        $this->assertIsString($json);
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertCount(count($pattern_ids), $decoded);

        $slugs = wp_list_pluck($decoded, 'slug');
        $expected_slugs = array_map(
            static function ($index) {
                return 'pattern-' . $index;
            },
            range(0, 6)
        );

        $this->assertSame($expected_slugs, $slugs);
    }

    public function test_export_patterns_json_exports_all_patterns_with_partial_last_batch() {
        $pattern_ids = [];

        for ($i = 0; $i < 5; $i++) {
            $pattern_ids[] = self::factory()->post->create([
                'post_type'    => 'wp_block',
                'post_status'  => 'publish',
                'post_title'   => 'Partial Batch Pattern ' . $i,
                'post_name'    => 'partial-batch-pattern-' . $i,
                'post_content' => '<!-- wp:paragraph --><p>Partial ' . $i . '</p><!-- /wp:paragraph -->',
            ]);
        }

        $this->custom_batch_size = 4;

        add_filter('tejlg_export_patterns_batch_size', [$this, 'filter_export_batch_size']);
        add_filter('tejlg_export_stream_json_file', '__return_false');

        try {
            $json = TEJLG_Export::export_selected_patterns_json($pattern_ids, false);
        } finally {
            remove_filter('tejlg_export_patterns_batch_size', [$this, 'filter_export_batch_size']);
            remove_filter('tejlg_export_stream_json_file', '__return_false');
        }

        foreach ($pattern_ids as $pattern_id) {
            wp_delete_post($pattern_id, true);
        }

        $this->assertIsString($json);

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertCount(count($pattern_ids), $decoded);

        $slugs = wp_list_pluck($decoded, 'slug');
        $expected_slugs = array_map(
            static function ($index) {
                return 'partial-batch-pattern-' . $index;
            },
            range(0, 4)
        );

        $this->assertSame($expected_slugs, $slugs);
    }
}

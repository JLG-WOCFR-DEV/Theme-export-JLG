<?php

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-import.php';
require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-export.php';

/**
 * @group pattern-sanitizer
 */
class Test_Pattern_Sanitizer extends WP_UnitTestCase {

    protected $previous_home_url;
    protected $previous_site_url;

    public function setUp(): void {
        parent::setUp();

        $this->previous_home_url = get_option('home');
        $this->previous_site_url = get_option('siteurl');
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

        $this->assertStringContainsString('href="/sub/page"', $cleaned);
        $this->assertStringContainsString('href="/submarine-news"', $cleaned);
    }

    public function test_export_patterns_json_preserves_subdirectory_prefix_in_media_urls() {
        update_option('home', 'https://example.com/subdir');
        update_option('siteurl', 'https://example.com/subdir');

        $content = '<!-- wp:image --><figure class="wp-block-image">'
            . '<img src="https://example.com/subdir/wp-content/uploads/2024/01/image.png" alt="Example" />'
            . '</figure><!-- /wp:image -->';

        $pattern_id = self::factory()->post->create([
            'post_type'    => 'wp_block',
            'post_status'  => 'publish',
            'post_title'   => 'Subdir Pattern',
            'post_content' => $content,
        ]);

        $captured_json = '';

        $filter = static function ($should_stream, $file_path) use (&$captured_json) {
            $captured_json = file_get_contents($file_path);

            return false;
        };

        add_filter('tejlg_export_should_stream_json_file', $filter, 10, 3);

        TEJLG_Export::export_patterns_json([$pattern_id], true);

        remove_filter('tejlg_export_should_stream_json_file', $filter, 10);

        $this->assertNotSame('', $captured_json, 'The export JSON should be captured for inspection.');

        $decoded = json_decode($captured_json, true);

        $this->assertIsArray($decoded);
        $this->assertNotEmpty($decoded);
        $this->assertArrayHasKey('content', $decoded[0]);
        $this->assertStringContainsString('/subdir/wp-content/', $decoded[0]['content']);
    }
}

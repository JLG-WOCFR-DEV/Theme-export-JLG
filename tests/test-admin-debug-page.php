<?php

require_once dirname(__DIR__) . '/theme-export-jlg/includes/class-tejlg-admin-debug-page.php';

/**
 * @group admin
 */
class Test_Admin_Debug_Page extends WP_UnitTestCase {

    protected function setUp(): void {
        parent::setUp();
        TEJLG_Admin_Debug_Page::invalidate_pattern_summary_cache(0);
    }

    public function test_pattern_summary_is_cached_between_calls() {
        $template_dir = dirname(__DIR__) . '/theme-export-jlg/templates/admin/';
        $debug_page   = new TEJLG_Admin_Debug_Page($template_dir, 'theme-export-jlg');

        $admin_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($admin_id);

        $pattern_id = self::factory()->post->create([
            'post_type'   => 'wp_block',
            'post_title'  => 'Test Pattern',
            'post_status' => 'publish',
            'post_author' => $admin_id,
        ]);

        update_post_meta($pattern_id, 'wp_block_type', 'pattern');

        TEJLG_Admin_Debug_Page::invalidate_pattern_summary_cache($admin_id);

        $reflection = new ReflectionMethod($debug_page, 'get_custom_patterns_summary');
        $reflection->setAccessible(true);

        $queries_before_first_call = $GLOBALS['wpdb']->num_queries;
        $first_summary = $reflection->invoke($debug_page);
        $queries_after_first_call = $GLOBALS['wpdb']->num_queries;

        $second_summary = $reflection->invoke($debug_page);
        $queries_after_second_call = $GLOBALS['wpdb']->num_queries;

        $this->assertNotEmpty($first_summary, 'The summary should return at least one pattern.');
        $this->assertSame($first_summary, $second_summary, 'Cached results should be reused on subsequent calls.');
        $this->assertSame(
            $queries_after_first_call,
            $queries_after_second_call,
            'No additional database queries should run when using the cached summary.'
        );

        TEJLG_Admin_Debug_Page::invalidate_pattern_summary_cache($admin_id);

        $queries_after_invalidation = $GLOBALS['wpdb']->num_queries;
        $third_summary = $reflection->invoke($debug_page);

        $this->assertSame($first_summary, $third_summary, 'Summary contents should remain identical after cache invalidation.');
        $this->assertGreaterThan(
            $queries_after_invalidation,
            $GLOBALS['wpdb']->num_queries,
            'Cache invalidation should trigger a fresh database query.'
        );
    }
}

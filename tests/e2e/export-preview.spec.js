const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

test.describe('Export pattern preview', () => {
  test('loads the preview when requested', async ({ admin, page, requestUtils }) => {
    await requestUtils.activatePlugin('theme-export-jlg/theme-export-jlg.php');

    await requestUtils.deleteAllPosts({ postType: 'wp_block' });

    await requestUtils.createPost({
      postType: 'wp_block',
      title: 'Previewable Pattern',
      status: 'publish',
      content: '<!-- wp:paragraph --><p>Preview content from export</p><!-- /wp:paragraph -->',
    });

    await admin.visitAdminPage('admin.php', 'page=theme-export-jlg&tab=export&action=select_patterns');

    const patternItem = page.locator('.pattern-selection-item', { hasText: 'Previewable Pattern' });
    const previewTrigger = patternItem.locator('.pattern-preview-trigger[data-preview-trigger="expand"]');
    const liveContainer = patternItem.locator('.pattern-preview-live');
    const loadingIndicator = patternItem.locator('.pattern-preview-loading');
    await expect(previewTrigger).toBeVisible();

    await previewTrigger.click();

    await expect(liveContainer).toBeVisible();
    await expect(loadingIndicator).toBeHidden();

    const frameLocator = patternItem.frameLocator('.pattern-preview-iframe');
    await expect(frameLocator.locator('p')).toHaveText('Preview content from export');
  });
});

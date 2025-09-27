const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

test.describe('Pattern export selection screen', () => {
  test('filters patterns and toggles select-all based on visibility', async ({ admin, page, requestUtils }) => {
    await requestUtils.activatePlugin('theme-export-jlg/theme-export-jlg.php');

    await requestUtils.deleteAllPosts({ postType: 'wp_block' });

    const patterns = [
      'Alpha Pattern',
      'Beta Pattern',
      'Gamma Pattern',
    ];

    for (const title of patterns) {
      await requestUtils.createPost({
        postType: 'wp_block',
        title,
        status: 'publish',
        content: `<!-- wp:paragraph --><p>${title}</p><!-- /wp:paragraph -->`,
      });
    }

    await admin.visitAdminPage('admin.php', 'page=theme-export-jlg&tab=export&action=select_patterns');

    const selectAll = page.locator('#select-all-export-patterns');
    const searchInput = page.locator('#pattern-search');
    const visibleItems = page.locator('.pattern-selection-item:not(.is-hidden)');
    const alphaCheckbox = page.locator('.pattern-selection-item', { hasText: 'Alpha Pattern' }).locator('input[type="checkbox"]');
    const betaCheckbox = page.locator('.pattern-selection-item', { hasText: 'Beta Pattern' }).locator('input[type="checkbox"]');
    const gammaCheckbox = page.locator('.pattern-selection-item', { hasText: 'Gamma Pattern' }).locator('input[type="checkbox"]');

    await expect(visibleItems).toHaveCount(patterns.length);
    await expect(selectAll).not.toBeChecked();
    await expect(selectAll).toHaveJSProperty('indeterminate', false);

    await alphaCheckbox.check();

    await expect(selectAll).not.toBeChecked();
    await expect(selectAll).toHaveJSProperty('indeterminate', true);

    await searchInput.fill('beta');

    await expect(visibleItems).toHaveCount(1);
    await expect(visibleItems.first()).toContainText('Beta Pattern');
    await expect(page.locator('.pattern-selection-item.is-hidden')).toHaveCount(patterns.length - 1);
    await expect(selectAll).not.toBeChecked();
    await expect(selectAll).toHaveJSProperty('indeterminate', false);

    await selectAll.check();

    await expect(betaCheckbox).toBeChecked();
    await expect(alphaCheckbox).toBeChecked();
    await expect(gammaCheckbox).not.toBeChecked();

    await searchInput.fill('');

    await expect(visibleItems).toHaveCount(patterns.length);
    await expect(page.locator('.pattern-selection-item.is-hidden')).toHaveCount(0);
    await expect(selectAll).not.toBeChecked();
    await expect(selectAll).toHaveJSProperty('indeterminate', true);

    await selectAll.check();

    await expect(alphaCheckbox).toBeChecked();
    await expect(betaCheckbox).toBeChecked();
    await expect(gammaCheckbox).toBeChecked();
    await expect(selectAll).toBeChecked();
    await expect(selectAll).toHaveJSProperty('indeterminate', false);

    await searchInput.fill('gamma');

    await expect(visibleItems).toHaveCount(1);
    await expect(visibleItems.first()).toContainText('Gamma Pattern');
    await expect(page.locator('.pattern-selection-item.is-hidden')).toHaveCount(patterns.length - 1);
    await expect(selectAll).toBeChecked();
    await expect(selectAll).toHaveJSProperty('indeterminate', false);

    await selectAll.uncheck();

    await expect(gammaCheckbox).not.toBeChecked();
    await expect(alphaCheckbox).toBeChecked();
    await expect(betaCheckbox).toBeChecked();
    await expect(selectAll).not.toBeChecked();
    await expect(selectAll).toHaveJSProperty('indeterminate', false);

    await searchInput.fill('');

    await expect(visibleItems).toHaveCount(patterns.length);
    await expect(page.locator('.pattern-selection-item.is-hidden')).toHaveCount(0);
    await expect(alphaCheckbox).toBeChecked();
    await expect(betaCheckbox).toBeChecked();
    await expect(gammaCheckbox).not.toBeChecked();
    await expect(selectAll).not.toBeChecked();
    await expect(selectAll).toHaveJSProperty('indeterminate', true);
  });

  test('displays fallback title for untitled patterns and allows searching with it', async ({ admin, page, requestUtils }) => {
    await requestUtils.activatePlugin('theme-export-jlg/theme-export-jlg.php');

    await requestUtils.deleteAllPosts({ postType: 'wp_block' });

    await requestUtils.createPost({
      postType: 'wp_block',
      title: '   ',
      status: 'publish',
      content: '<!-- wp:paragraph --><p>Untitled Content</p><!-- /wp:paragraph -->',
    });

    await requestUtils.createPost({
      postType: 'wp_block',
      title: 'Zeta Pattern',
      status: 'publish',
      content: '<!-- wp:paragraph --><p>Zeta Pattern</p><!-- /wp:paragraph -->',
    });

    await admin.visitAdminPage('admin.php', 'page=theme-export-jlg&tab=export&action=select_patterns');

    const fallbackText = 'Composition sans titre #1';
    const fallbackItem = page.locator('.pattern-selection-item').filter({ hasText: fallbackText }).first();
    const visibleItems = page.locator('.pattern-selection-item:not(.is-hidden)');
    const searchInput = page.locator('#pattern-search');

    await expect(fallbackItem).toBeVisible();
    await expect(fallbackItem).toHaveAttribute('data-label', fallbackText);

    await searchInput.fill('sans titre #1');

    await expect(visibleItems).toHaveCount(1);
    await expect(fallbackItem).toBeVisible();

    await searchInput.fill('zeta');

    await expect(visibleItems).toHaveCount(1);
    await expect(visibleItems.first()).toContainText('Zeta Pattern');

    await searchInput.fill('sans titre');

    await expect(visibleItems).toHaveCount(1);
    await expect(fallbackItem).toBeVisible();
  });
});

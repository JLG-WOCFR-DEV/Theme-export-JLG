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
    const statusLiveRegion = page.locator('#pattern-selection-status');
    const alphaCheckbox = page.locator('.pattern-selection-item', { hasText: 'Alpha Pattern' }).locator('input[type="checkbox"]');
    const betaCheckbox = page.locator('.pattern-selection-item', { hasText: 'Beta Pattern' }).locator('input[type="checkbox"]');
    const gammaCheckbox = page.locator('.pattern-selection-item', { hasText: 'Gamma Pattern' }).locator('input[type="checkbox"]');

    await expect(visibleItems).toHaveCount(patterns.length);
    await expect(selectAll).not.toBeChecked();
    await expect(selectAll).toHaveJSProperty('indeterminate', false);
    await expect(statusLiveRegion).toHaveText('3 compositions visibles.');
    await expect(statusLiveRegion).toHaveAttribute('aria-live', 'polite');
    await expect(statusLiveRegion).toHaveAttribute('aria-busy', 'false');

    await alphaCheckbox.check();

    await expect(selectAll).not.toBeChecked();
    await expect(selectAll).toHaveJSProperty('indeterminate', true);

    await searchInput.fill('beta');

    await expect(visibleItems).toHaveCount(1);
    await expect(visibleItems.first()).toContainText('Beta Pattern');
    await expect(page.locator('.pattern-selection-item.is-hidden')).toHaveCount(patterns.length - 1);
    await expect(selectAll).not.toBeChecked();
    await expect(selectAll).toHaveJSProperty('indeterminate', false);
    await expect(statusLiveRegion).toHaveText('1 composition visible.');
    await expect(statusLiveRegion).toHaveAttribute('aria-busy', 'false');

    await selectAll.check();

    await expect(betaCheckbox).toBeChecked();
    await expect(alphaCheckbox).toBeChecked();
    await expect(gammaCheckbox).not.toBeChecked();
    await expect(selectAll).toBeChecked();
    await expect(selectAll).toHaveJSProperty('indeterminate', false);

    await selectAll.uncheck();

    await expect(betaCheckbox).not.toBeChecked();
    await expect(alphaCheckbox).toBeChecked();
    await expect(gammaCheckbox).not.toBeChecked();
    await expect(selectAll).not.toBeChecked();
    await expect(selectAll).toHaveJSProperty('indeterminate', false);

    await selectAll.check();

    await expect(betaCheckbox).toBeChecked();
    await expect(alphaCheckbox).toBeChecked();
    await expect(gammaCheckbox).not.toBeChecked();
    await expect(selectAll).toBeChecked();
    await expect(selectAll).toHaveJSProperty('indeterminate', false);

    await searchInput.fill('');

    await expect(visibleItems).toHaveCount(patterns.length);
    await expect(page.locator('.pattern-selection-item.is-hidden')).toHaveCount(0);
    await expect(selectAll).not.toBeChecked();
    await expect(selectAll).toHaveJSProperty('indeterminate', true);
    await expect(statusLiveRegion).toHaveText('3 compositions visibles.');
    await expect(statusLiveRegion).toHaveAttribute('aria-busy', 'false');

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
    await expect(statusLiveRegion).toHaveText('1 composition visible.');
    await expect(statusLiveRegion).toHaveAttribute('aria-busy', 'false');

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
    await expect(statusLiveRegion).toHaveText('3 compositions visibles.');
    await expect(statusLiveRegion).toHaveAttribute('aria-busy', 'false');
  });

  test('import preview select-all only affects visible patterns', async ({ admin, page, requestUtils }) => {
    await requestUtils.activatePlugin('theme-export-jlg/theme-export-jlg.php');

    await admin.visitAdminPage('admin.php', 'page=theme-export-jlg&tab=import');

    const importPatterns = [
      {
        title: 'Alpha Import',
        content: '<!-- wp:paragraph --><p>Alpha Import</p><!-- /wp:paragraph -->',
      },
      {
        title: 'Beta Import',
        content: '<!-- wp:paragraph --><p>Beta Import</p><!-- /wp:paragraph -->',
      },
      {
        title: 'Gamma Import',
        content: '<!-- wp:paragraph --><p>Gamma Import</p><!-- /wp:paragraph -->',
      },
    ];

    const patternsInput = page.locator('#patterns_json');

    await patternsInput.setInputFiles({
      name: 'patterns.json',
      mimeType: 'application/json',
      buffer: Buffer.from(JSON.stringify(importPatterns)),
    });

    await Promise.all([
      page.waitForNavigation(),
      page.click('button[name="tejlg_import_patterns_step1"]'),
    ]);

    const selectAll = page.locator('#select-all-patterns');
    const searchInput = page.locator('#tejlg-import-pattern-search');
    const visibleItems = page.locator('#patterns-preview-items .pattern-item:not(.is-hidden)');
    const hiddenItems = page.locator('#patterns-preview-items .pattern-item.is-hidden');
    const statusLiveRegion = page.locator('#pattern-import-status');
    const alphaCheckbox = page
      .locator('#patterns-preview-items .pattern-item', { hasText: 'Alpha Import' })
      .locator('input[type="checkbox"]');
    const betaCheckbox = page
      .locator('#patterns-preview-items .pattern-item', { hasText: 'Beta Import' })
      .locator('input[type="checkbox"]');
    const gammaCheckbox = page
      .locator('#patterns-preview-items .pattern-item', { hasText: 'Gamma Import' })
      .locator('input[type="checkbox"]');

    await expect(visibleItems).toHaveCount(importPatterns.length);
    await expect(selectAll).toBeChecked();
    await expect(selectAll).toHaveJSProperty('indeterminate', false);
    await expect(statusLiveRegion).toHaveText('3 compositions visibles.');
    await expect(statusLiveRegion).toHaveAttribute('aria-busy', 'false');

    await alphaCheckbox.uncheck();

    await expect(alphaCheckbox).not.toBeChecked();
    await expect(selectAll).not.toBeChecked();
    await expect(selectAll).toHaveJSProperty('indeterminate', true);

    await searchInput.fill('beta');

    await expect(visibleItems).toHaveCount(1);
    await expect(hiddenItems).toHaveCount(importPatterns.length - 1);
    await expect(visibleItems.first()).toContainText('Beta Import');
    await expect(selectAll).toBeChecked();
    await expect(selectAll).toHaveJSProperty('indeterminate', false);
    await expect(statusLiveRegion).toHaveText('1 composition visible.');
    await expect(statusLiveRegion).toHaveAttribute('aria-busy', 'false');

    await selectAll.uncheck();

    await expect(betaCheckbox).not.toBeChecked();
    await expect(alphaCheckbox).not.toBeChecked();
    await expect(gammaCheckbox).toBeChecked();
    await expect(selectAll).not.toBeChecked();
    await expect(selectAll).toHaveJSProperty('indeterminate', false);

    await selectAll.check();

    await expect(betaCheckbox).toBeChecked();
    await expect(alphaCheckbox).not.toBeChecked();
    await expect(gammaCheckbox).toBeChecked();
    await expect(selectAll).toBeChecked();
    await expect(selectAll).toHaveJSProperty('indeterminate', false);

    await searchInput.fill('');

    await expect(visibleItems).toHaveCount(importPatterns.length);
    await expect(hiddenItems).toHaveCount(0);
    await expect(alphaCheckbox).not.toBeChecked();
    await expect(betaCheckbox).toBeChecked();
    await expect(gammaCheckbox).toBeChecked();
    await expect(selectAll).not.toBeChecked();
    await expect(selectAll).toHaveJSProperty('indeterminate', true);
    await expect(statusLiveRegion).toHaveText('3 compositions visibles.');
    await expect(statusLiveRegion).toHaveAttribute('aria-busy', 'false');
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

  test('shows an error when exclusion patterns are invalid', async ({ admin, page, requestUtils }) => {
    await requestUtils.activatePlugin('theme-export-jlg/theme-export-jlg.php');

    await admin.visitAdminPage('admin.php', 'page=theme-export-jlg&tab=export');

    const textarea = page.locator('#tejlg_exclusion_patterns');
    const testButton = page.locator('[data-pattern-test-trigger]');
    const invalidNotice = page.locator('[data-pattern-test-invalid]');
    const feedback = page.locator('[data-pattern-test-feedback]');

    await textarea.fill('foo[bar');

    await Promise.all([
      page.waitForResponse((response) =>
        response.url().includes('admin-ajax.php') && response.request().postData().includes('tejlg_preview_exclusion_patterns')
      ),
      testButton.click(),
    ]);

    await expect(testButton).toBeEnabled();
    await expect(feedback).toBeVisible();
    await expect(feedback).toHaveClass(/notice-error/);
    await expect(invalidNotice).toBeVisible();
    await expect(invalidNotice).toContainText('Motifs invalides');
    await expect(textarea).toHaveClass(/has-pattern-error/);
    await expect(textarea).toHaveAttribute('aria-invalid', 'true');
  });
});

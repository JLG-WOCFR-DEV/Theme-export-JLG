const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

test.describe('Import preview iframe fallback', () => {
  test('uses srcdoc preview when Blob URLs are unavailable', async ({ admin, page, requestUtils }) => {
    await requestUtils.activatePlugin('theme-export-jlg/theme-export-jlg.php');

    await page.addInitScript(() => {
      if (window.URL) {
        window.URL.createObjectURL = undefined;
      }
    });

    await admin.visitAdminPage('admin.php', 'page=theme-export-jlg&tab=import');

    const patterns = [
      {
        title: 'Fallback Pattern',
        content: '<!-- wp:paragraph --><p>Fallback pattern content</p><!-- /wp:paragraph -->',
      },
    ];

    const patternsInput = page.locator('#patterns_json');

    await patternsInput.setInputFiles({
      name: 'patterns.json',
      mimeType: 'application/json',
      buffer: Buffer.from(JSON.stringify(patterns)),
    });

    await Promise.all([
      page.waitForNavigation(),
      page.click('button[name="tejlg_import_patterns_step1"]'),
    ]);

    const iframe = page.locator('.pattern-preview-iframe');
    const fallbackMessage = page.locator('.pattern-preview-message');

    await expect(fallbackMessage).toBeVisible();
    await expect(fallbackMessage).toContainText("Avertissement : l'aperçu est chargé via un mode de secours (sans Blob). Le rendu peut être limité.");

    await expect(iframe).toHaveAttribute('srcdoc', /Fallback pattern content/);

    const frameParagraph = page.frameLocator('.pattern-preview-iframe').locator('p');
    await expect(frameParagraph).toHaveText('Fallback pattern content');
  });
});

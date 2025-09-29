const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

test.describe('Async theme export', () => {
  test('starts a background job and exposes a download link', async ({ admin, page, requestUtils }) => {
    await requestUtils.activatePlugin('theme-export-jlg/theme-export-jlg.php');

    await admin.visitAdminPage('admin.php', 'page=theme-export-jlg&tab=export');

    const startButton = page.locator('#tejlg-theme-export-submit');
    await expect(startButton).toBeVisible();

    await startButton.click();

    const statusWrapper = page.locator('#tejlg-theme-export-status');
    await expect(statusWrapper).toBeVisible();

    const downloadButton = statusWrapper.locator('#tejlg-theme-export-download');

    await expect(downloadButton).toHaveAttribute('href', /action=tejlg_download_theme_export/, { timeout: 30000 });
    await expect(downloadButton).toBeVisible({ timeout: 30000 });
    await expect(startButton).toBeEnabled({ timeout: 30000 });
  });
});

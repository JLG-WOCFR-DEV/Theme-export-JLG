const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

test.describe('Theme export async flow', () => {
  test('starts a job and exposes completion feedback', async ({ admin, page, requestUtils }) => {
    await requestUtils.activatePlugin('theme-export-jlg/theme-export-jlg.php');

    await admin.visitAdminPage('admin.php', 'page=theme-export-jlg&tab=export');

    const startButton = page.locator('[data-export-start]');
    const feedback = page.locator('[data-export-feedback]');
    const statusText = page.locator('[data-export-status-text]');
    const downloadLink = page.locator('[data-export-download]');
    const progressBar = page.locator('[data-export-progress-bar]');

    await expect(startButton).toBeVisible();

    await startButton.click();

    await expect(feedback).toBeVisible();

    await expect(statusText).toContainText('Export terminé', { timeout: 15000 });

    await expect(progressBar).toHaveAttribute('value', '100');

    await expect(downloadLink).toBeVisible();
    await expect(downloadLink).toHaveAttribute('href', /admin-ajax\.php/);
  });

  test('resumes job status after page reload', async ({ admin, page, requestUtils }) => {
    await requestUtils.activatePlugin('theme-export-jlg/theme-export-jlg.php');

    await admin.visitAdminPage('admin.php', 'page=theme-export-jlg&tab=export');

    const startButton = page.locator('[data-export-start]');
    const feedback = page.locator('[data-export-feedback]');
    const statusText = page.locator('[data-export-status-text]');
    const downloadLink = page.locator('[data-export-download]');

    await expect(startButton).toBeVisible();

    const startRequest = page.waitForResponse((response) => {
      return response.url().includes('admin-ajax.php')
        && response.request().method() === 'POST'
        && response.url().includes('tejlg_start_theme_export');
    });

    await startButton.click();

    await startRequest;

    await page.reload();

    await expect(feedback).toBeVisible();

    await expect(statusText).toContainText('Export terminé', { timeout: 15000 });

    await expect(downloadLink).toBeVisible();
    await expect(downloadLink).toHaveAttribute('href', /admin-ajax\.php/);
  });
});

const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

test.describe('Child theme creation form', () => {
  test('preserves input value after validation error', async ({ admin, page, requestUtils }) => {
    await requestUtils.activatePlugin('theme-export-jlg/theme-export-jlg.php');

    await admin.visitAdminPage('admin.php', 'page=theme-export-jlg&tab=export');

    const invalidName = '!!!';
    const childThemeInput = page.locator('#child_theme_name');

    await childThemeInput.fill(invalidName);

    await Promise.all([
      page.waitForNavigation(),
      page.click('button[name="tejlg_create_child"]'),
    ]);

    const errorNotice = page.locator('.notice.notice-error');
    await expect(errorNotice).toContainText("Erreur : Le nom du thème enfant doit contenir des lettres ou des chiffres.");
    await expect(childThemeInput).toHaveValue(invalidName);
  });
});

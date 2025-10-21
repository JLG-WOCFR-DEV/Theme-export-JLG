const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

const pluginSlug = 'theme-export-jlg/theme-export-jlg.php';

const visitExportTab = async (admin) => {
  await admin.visitAdminPage('admin.php', 'page=theme-export-jlg&tab=export');
};

test.describe('Quick actions toolbar', () => {
  test('renders quick action buttons in the export toolbar', async ({ admin, page, requestUtils }) => {
    await requestUtils.activatePlugin(pluginSlug);

    await visitExportTab(admin);

    const toolbar = page.locator('.tejlg-mode-toolbar__actions');
    await expect(toolbar).toBeVisible();

    const buttons = toolbar.locator('.button');
    const buttonCount = await buttons.count();

    expect(buttonCount).toBeGreaterThanOrEqual(1);

    await expect(buttons.first()).toBeVisible();
    await expect(buttons.first()).toHaveText(/\S/);
  });
});

const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

const pluginSlug = 'theme-export-jlg/theme-export-jlg.php';

const visitExportTab = async (admin) => {
  await admin.visitAdminPage('admin.php', 'page=theme-export-jlg&tab=export');
};

test.describe('Quick actions panel', () => {
  test('opens the floating quick actions card', async ({ admin, page, requestUtils }) => {
    await requestUtils.activatePlugin(pluginSlug);

    await visitExportTab(admin);

    const quickActions = page.locator('.tejlg-quick-actions');
    await expect(quickActions).toBeVisible();

    const toggle = quickActions.locator('[data-quick-actions-toggle]');
    await toggle.click();

    await expect(quickActions).toHaveClass(/is-open/);

    const panel = quickActions.locator('.tejlg-quick-actions__panel');
    await expect(panel).toBeVisible();

    const actionButtons = quickActions.locator('.tejlg-quick-actions__link');
    await expect(actionButtons.first()).toBeVisible();
    await actionButtons.first().click();
  });
});

const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

const pluginSlug = 'theme-export-jlg/theme-export-jlg.php';

const visitExportTab = async (admin) => {
  await admin.visitAdminPage('admin.php', 'page=theme-export-jlg&tab=export');
};

test.describe('Quick actions toolbar', () => {
  test('renders quick action buttons in the export toolbar', async ({ admin, page, requestUtils }) => {
    await requestUtils.activatePlugin(pluginSlug);

    await visitExportTab(admin);

    const container = page.locator('[data-quick-actions]');
    const toggle = page.locator('button.button.button-primary.wp-ui-primary[data-quick-actions-toggle]');
    const links = page.locator('[data-quick-actions-link]');
    const menu = page.locator('[data-quick-actions-menu]');

    const buttons = toolbar.locator('.button');
    const buttonCount = await buttons.count();

    expect(buttonCount).toBeGreaterThanOrEqual(1);

    await toggle.click();

    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(menu).not.toHaveAttribute('hidden', '');
    await expect(menu).toBeVisible();
    await expect(container).toHaveClass(/is-open/);

    const firstLink = links.first();
    await expect(firstLink).toBeFocused();

    for (let index = 1; index < linkCount; index += 1) {
      await page.keyboard.press('Tab');
      await expect(links.nth(index)).toBeFocused();
    }

    await page.keyboard.press('Tab');
    const dismiss = page.locator('[data-quick-actions-dismiss]');
    await expect(dismiss).toBeFocused();

    await page.keyboard.press('Tab');
    await expect(toggle).toBeFocused();

    await page.keyboard.press('Shift+Tab');
    await expect(dismiss).toBeFocused();

    await page.keyboard.press('Escape');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(menu).toHaveAttribute('hidden', '');
    await expect(container).not.toHaveClass(/is-open/);
    await expect(toggle).toBeFocused();

    await toggle.click();
    await dismiss.click();

    const restore = page.locator('[data-quick-actions-restore]');
    await expect(restore).toBeVisible();
    await expect(restore).toBeFocused();

    await page.reload();
    await visitExportTab(admin);

    await expect(page.locator('[data-quick-actions-restore]')).toBeVisible();

    await restore.click();
    await expect(toggle).toBeFocused();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(menu).toHaveAttribute('hidden', '');
  });
});

const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

test.describe('Debug accordion accessibility', () => {
  test('allows keyboard navigation and aria-expanded toggling', async ({ admin, page, requestUtils }) => {
    await requestUtils.activatePlugin('theme-export-jlg/theme-export-jlg.php');

    await admin.visitAdminPage('admin.php', 'page=theme-export-jlg&tab=debug');

    const systemButton = page.getByRole('button', { name: 'Informations Système & WordPress' });
    const systemContent = page.locator('#tejlg-debug-section-system');
    const patternsButton = page.getByRole('button', { name: 'Compositions personnalisées enregistrées' });
    const patternsContent = page.locator('#tejlg-debug-section-patterns');

    await expect(systemButton).toHaveAttribute('aria-expanded', 'false');
    await expect(systemContent).toBeHidden();

    await systemButton.focus();
    await expect(systemButton).toBeFocused();

    await page.keyboard.press('Enter');
    await expect(systemButton).toHaveAttribute('aria-expanded', 'true');
    await expect(systemContent).toBeVisible();

    await page.keyboard.press('Space');
    await expect(systemButton).toHaveAttribute('aria-expanded', 'false');
    await expect(systemContent).toBeHidden();

    await patternsButton.focus();
    await expect(patternsButton).toBeFocused();

    await page.keyboard.press('Space');
    await expect(patternsButton).toHaveAttribute('aria-expanded', 'true');
    await expect(patternsContent).toBeVisible();
  });
});

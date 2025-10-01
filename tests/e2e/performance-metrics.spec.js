const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

test.describe('Performance metrics badge', () => {
  test('requires manual activation before updating metrics', async ({ admin, page, requestUtils }) => {
    await requestUtils.activatePlugin('theme-export-jlg/theme-export-jlg.php');

    await admin.visitAdminPage('admin.php', 'page=theme-export-jlg&tab=debug');

    const fpsMetric = page.locator('#tejlg-metric-fps');
    const latencyMetric = page.locator('#tejlg-metric-latency');
    const toggleButton = page.getByRole('button', { name: /Démarrer la mesure/i });
    const status = page.locator('[data-metrics-status]');

    await expect(fpsMetric).toHaveAttribute('aria-live', /polite|assertive/i);
    await expect(latencyMetric).toHaveAttribute('aria-live', /polite|assertive/i);

    await expect(toggleButton).toHaveAttribute('aria-pressed', 'false');
    await expect(status).toContainText('Appuyez pour mesurer');

    const initialFps = ((await fpsMetric.textContent()) || '').trim();
    const initialLatency = ((await latencyMetric.textContent()) || '').trim();
    await page.waitForTimeout(2000);
    await expect(fpsMetric).toHaveText(initialFps);
    await expect(latencyMetric).toHaveText(initialLatency);

    await toggleButton.click();
    await expect(toggleButton).toHaveAttribute('aria-pressed', 'true');
    await expect(status).toContainText(/Mesure en cours/i);

    const fpsText = await expect.poll(
      async () => (await fpsMetric.textContent())?.trim() || '',
      {
        timeout: 10000,
        message: 'Expected FPS metric to display a numeric value after activation',
      }
    );
    expect(fpsText).toMatch(/\d/);

    const latencyText = await expect.poll(
      async () => (await latencyMetric.textContent())?.trim() || '',
      {
        timeout: 10000,
        message: 'Expected latency metric to display a numeric value after activation',
      }
    );
    expect(latencyText).toMatch(/\d/);

    await toggleButton.click();
    await expect(toggleButton).toHaveAttribute('aria-pressed', 'false');
    await expect(status).toContainText(/Appuyez pour mesurer/i);
  });
});

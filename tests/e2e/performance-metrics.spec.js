const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

test.describe('Performance metrics badge', () => {
  test('updates FPS and latency metrics with accessible output', async ({ admin, page, requestUtils }) => {
    await requestUtils.activatePlugin('theme-export-jlg/theme-export-jlg.php');

    await admin.visitAdminPage('admin.php', 'page=theme-export-jlg&tab=debug');

    const fpsMetric = page.locator('#tejlg-metric-fps');
    const latencyMetric = page.locator('#tejlg-metric-latency');

    await expect(fpsMetric).toHaveAttribute('aria-live', /polite|assertive/i);
    await expect(latencyMetric).toHaveAttribute('aria-live', /polite|assertive/i);

    const fpsText = await expect.poll(
      async () => (await fpsMetric.textContent())?.trim() || '',
      {
        timeout: 10000,
        message: 'Expected FPS metric to display a numeric value',
      }
    );
    expect(fpsText).toMatch(/\d/);

    const latencyText = await expect.poll(
      async () => (await latencyMetric.textContent())?.trim() || '',
      {
        timeout: 10000,
        message: 'Expected latency metric to display a numeric value',
      }
    );
    expect(latencyText).toMatch(/\d/);
  });
});

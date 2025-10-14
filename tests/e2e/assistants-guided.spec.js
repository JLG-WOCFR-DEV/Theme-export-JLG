const fs = require('fs');
const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

const readDownloadContent = async (download) => {
    const stream = await download.createReadStream();

    if (stream) {
        let content = '';

        for await (const chunk of stream) {
            content += chunk.toString();
        }

        return content;
    }

    const path = await download.path();

    if (path) {
        return fs.promises.readFile(path, 'utf8');
    }

    return '';
};

test.describe('Assistants guidés', () => {
    test('mémorise l’étape courante de l’assistant export', async ({ admin, page, requestUtils }) => {
        await requestUtils.activatePlugin('theme-export-jlg/theme-export-jlg.php');

        await admin.visitAdminPage('admin.php', 'page=theme-export-jlg&tab=export');

        const exportAssistant = page.locator('[data-assistant-id="theme-export"]');
        const selectionStep = exportAssistant.locator('[data-assistant-step="selection"]');
        const confirmationStep = exportAssistant.locator('[data-assistant-step="confirmation"]');
        const selectionNext = selectionStep.locator('[data-assistant-next]');

        await expect(selectionNext).toBeVisible();

        await selectionNext.click();

        await expect(confirmationStep).toBeVisible();

        await page.reload();

        await expect(confirmationStep).toBeVisible();

        const confirmationStepper = exportAssistant.locator('[data-assistant-stepper-item][data-assistant-step-target="confirmation"]');
        await expect(confirmationStepper).toHaveClass(/is-active/);

        const confirmationPrev = confirmationStep.locator('[data-assistant-prev]');
        await confirmationPrev.click();

        await expect(selectionStep).toBeVisible();
    });

    test('guide l’import et génère un résumé téléchargeable', async ({ admin, page, requestUtils }) => {
        await requestUtils.activatePlugin('theme-export-jlg/theme-export-jlg.php');

        await admin.visitAdminPage('admin.php', 'page=theme-export-jlg&tab=import');

        const patterns = [
            {
                title: 'Premier bloc assisté',
                content: '<!-- wp:paragraph --><p>Contenu 1</p><!-- /wp:paragraph -->',
            },
            {
                title: 'Deuxième bloc assisté',
                content: '<!-- wp:paragraph --><p>Contenu 2</p><!-- /wp:paragraph -->',
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

        const importAssistant = page.locator('[data-assistant-id="import-preview"]');
        const selectionStep = importAssistant.locator('[data-assistant-step="selection"]');
        const confirmationStep = importAssistant.locator('[data-assistant-step="confirmation"]');
        const summaryStep = importAssistant.locator('[data-assistant-step="summary"]');

        await expect(selectionStep).toBeVisible();

        const selectionNext = selectionStep.locator('[data-assistant-next]');
        await selectionNext.click();

        await expect(confirmationStep).toBeVisible();

        const confirmationCount = confirmationStep.locator('[data-assistant-selection-count]');
        await expect(confirmationCount).toContainText('2 compositions sélectionnées');

        const previewItems = confirmationStep.locator('[data-assistant-selection-preview] li strong');
        await expect(previewItems).toHaveCount(2);

        const confirmationNext = confirmationStep.locator('[data-assistant-next]');
        await confirmationNext.click();

        await expect(summaryStep).toBeVisible();

        const summaryCount = summaryStep.locator('[data-assistant-summary-count]');
        await expect(summaryCount).toContainText('2 compositions sélectionnées');

        const downloadPromise = page.waitForEvent('download');
        await summaryStep.locator('[data-assistant-download-summary]').click();
        const download = await downloadPromise;
        const content = await readDownloadContent(download);
        const payload = JSON.parse(content);

        expect(payload.selection.count).toBe(2);
        expect(payload.selection.items[0].title).toBe('Premier bloc assisté');
        expect(payload.selection.items[1].title).toBe('Deuxième bloc assisté');
    });
});

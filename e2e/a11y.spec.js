const { test } = require('@playwright/test');
const { expectNoA11yViolations } = require('./helpers/a11y');

test.describe('Accessibility (WCAG 2.1 AA)', () => {
    // Starter sample. Unskip once your plugin/theme exposes a page worth auditing —
    // a stock WordPress homepage will have violations from the active theme that
    // are outside the scope of this project.
    test.skip('homepage has no a11y violations', async ({ page }) => {
        await page.goto('/');
        await expectNoA11yViolations(page);
    });
});

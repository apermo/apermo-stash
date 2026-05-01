const { test, expect } = require('@playwright/test');

test('admin dashboard loads', async ({ page }) => {
    await page.goto('/wp-admin/');
    await expect(page.locator('#wpadminbar')).toBeVisible();
});

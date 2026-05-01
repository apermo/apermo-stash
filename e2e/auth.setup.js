const { test } = require('@playwright/test');

const WP_ADMIN_USER = process.env.WP_ADMIN_USER || 'admin';
const WP_ADMIN_PASSWORD = process.env.WP_ADMIN_PASSWORD || 'admin';

test('authenticate as admin', async ({ page }) => {
    await page.goto('/wp-login.php');
    await page.locator('#user_login').fill(WP_ADMIN_USER);
    await page.locator('#user_pass').fill(WP_ADMIN_PASSWORD);
    await page.locator('#wp-submit').click();
    await page.waitForURL(/wp-admin/);
    await page.context().storageState({ path: '.auth/admin.json' });
});

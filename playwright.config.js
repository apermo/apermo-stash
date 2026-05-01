const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
    testDir: './e2e',
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    use: {
        baseURL: process.env.WP_BASE_URL || 'https://plugin-name.ddev.site',
        ignoreHTTPSErrors: true,
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'setup', testMatch: /.*\.setup\.js/ },
        {
            name: 'e2e',
            dependencies: ['setup'],
            use: { storageState: '.auth/admin.json' },
        },
    ],
});

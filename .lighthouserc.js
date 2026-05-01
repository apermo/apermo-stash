module.exports = {
    ci: {
        collect: {
            // Replace with your theme's public URL(s). Uses wp-env by default.
            url: ['http://localhost:8888/'],
            numberOfRuns: 3,
            settings: {
                chromeFlags: ['--no-sandbox'],
            },
        },
        assert: {
            assertions: {
                'categories:accessibility': ['error', { minScore: 0.9 }],
                'categories:performance': ['warn', { minScore: 0.8 }],
            },
        },
        upload: {
            target: 'filesystem',
            outputDir: '.lighthouseci',
        },
    },
};

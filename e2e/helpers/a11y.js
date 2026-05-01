const { expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;

const WCAG_AA_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'];

async function expectNoA11yViolations(page, { include, exclude } = {}) {
    let builder = new AxeBuilder({ page }).withTags(WCAG_AA_TAGS);
    if (include) builder = builder.include(include);
    if (exclude) builder = builder.exclude(exclude);
    const results = await builder.analyze();
    expect(results.violations, formatViolations(results.violations)).toEqual([]);
}

function formatViolations(violations) {
    if (violations.length === 0) return 'No violations';
    return violations
        .map((v) => `[${v.impact}] ${v.id}: ${v.help} (${v.nodes.length} node${v.nodes.length === 1 ? '' : 's'})`)
        .join('\n');
}

module.exports = { expectNoA11yViolations, WCAG_AA_TAGS };

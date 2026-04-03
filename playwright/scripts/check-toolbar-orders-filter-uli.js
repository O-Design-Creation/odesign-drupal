#!/usr/bin/env node
const { chromium } = require('playwright');

const uli = process.argv[2];
if (!uli) {
  console.error('Usage: node check-toolbar-orders-filter-uli.js <uli-url>');
  process.exit(1);
}
const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'https://odesign-drupal.ddev.site';

(async () => {
  const browser = await chromium.launch({ args: ['--ignore-certificate-errors'] });
  const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  await page.goto(uli, { waitUntil: 'networkidle', timeout: 30000 });
  if (await page.getByRole('heading', { name: /sandbox environment/i }).count()) {
    const continueLink = page.getByRole('link', { name: /continue/i });
    const continueButton = page.getByRole('button', { name: /continue/i });
    if (await continueLink.count()) {
      await continueLink.first().click();
      await page.waitForLoadState('networkidle');
    } else if (await continueButton.count()) {
      await continueButton.first().click();
      await page.waitForLoadState('networkidle');
    }
  }

  await page.goto(`${BASE_URL}/admin/boutique`, { waitUntil: 'networkidle', timeout: 30000 });
  if (await page.getByRole('heading', { name: /sandbox environment/i }).count()) {
    const continueLink = page.getByRole('link', { name: /continue/i });
    const continueButton = page.getByRole('button', { name: /continue/i });
    if (await continueLink.count()) {
      await continueLink.first().click();
      await page.waitForLoadState('networkidle');
      await page.goto(`${BASE_URL}/admin/boutique`, { waitUntil: 'networkidle', timeout: 30000 });
    } else if (await continueButton.count()) {
      await continueButton.first().click();
      await page.waitForLoadState('networkidle');
      await page.goto(`${BASE_URL}/admin/boutique`, { waitUntil: 'networkidle', timeout: 30000 });
    }
  }

  const href = await page
    .locator('a.odesign-toolbar-orders')
    .first()
    .getAttribute('href');

  const result = {
    href: (href || '').trim(),
    has_all_states: Boolean(href)
      && href.includes('pending_payment')
      && href.includes('processing')
      && href.includes('quality_check')
      && href.includes('packaged'),
  };

  console.log(JSON.stringify(result));
  await browser.close();

  if (!result.has_all_states) {
    process.exit(2);
  }
})();

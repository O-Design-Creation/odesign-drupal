#!/usr/bin/env node
const { chromium } = require('playwright');

const uli = process.argv[2];
if (!uli) {
  console.error('Usage: node check-dashboard-quick-links-uli.js <uli-url>');
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

  const titles = await page.$$eval('.quick-action-btn__title', (els) =>
    els.map((el) => (el.textContent || '').trim()).filter(Boolean)
  );
  const statLinks = await page.$$eval('.odesign-dashboard__stats .odesign-dashboard__stat--link', (els) =>
    els.map((el) => (el.getAttribute('href') || '').trim()).filter(Boolean)
  );
  console.log(JSON.stringify({ titles, statLinks }));
  await browser.close();
})();

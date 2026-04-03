#!/usr/bin/env node
const { chromium } = require('playwright');

const uli = process.argv[2];
if (!uli) {
  console.error('Usage: node check-dashboard-quick-links-uli.js <uli-url>');
  process.exit(1);
}

(async () => {
  const browser = await chromium.launch({ args: ['--ignore-certificate-errors'] });
  const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  await page.goto(uli, { waitUntil: 'networkidle', timeout: 30000 });
  await page.goto('https://odesign-drupal.ddev.site/admin/boutique', { waitUntil: 'networkidle', timeout: 30000 });

  const titles = await page.$$eval('.quick-action-btn__title', (els) =>
    els.map((el) => (el.textContent || '').trim()).filter(Boolean)
  );
  console.log(JSON.stringify(titles));
  await browser.close();
})();

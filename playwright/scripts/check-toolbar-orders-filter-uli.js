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

  const href = await page.evaluate(() => {
    const candidates = Array.from(document.querySelectorAll('a[href]'))
      .map((a) => a.getAttribute('href') || '')
      .filter(Boolean);

    const direct = candidates.find((value) => value.includes('/admin/boutique/commandes-a-traiter'));
    if (direct) {
      return direct;
    }

    const filtered = candidates.find((value) =>
      value.includes('/admin/commerce/orders')
      && value.includes('pending_payment')
      && value.includes('processing')
      && value.includes('quality_check')
      && value.includes('packaged')
    );
    return filtered || '';
  });

  const result = {
    href: (href || '').trim(),
    has_all_states: Boolean(href) && (
      href.includes('/admin/boutique/commandes-a-traiter')
      || (
        href.includes('pending_payment')
        && href.includes('processing')
        && href.includes('quality_check')
        && href.includes('packaged')
      )
    ),
  };

  console.log(JSON.stringify(result));
  await browser.close();

  if (!result.has_all_states) {
    process.exit(2);
  }
})();

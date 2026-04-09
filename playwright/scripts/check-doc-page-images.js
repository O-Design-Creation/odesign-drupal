#!/usr/bin/env node
/**
 * Vérifie que les images d'une page de documentation sont bien chargées.
 *
 * Usage:
 *   ULI=$(ddev drush uli --uid=2) && \
 *   node playwright/scripts/check-doc-page-images.js "$ULI" "/admin/boutique/documentation/16-avis-clients"
 */

const { chromium } = require('playwright');

const uli = process.argv[2];
const docPath = process.argv[3];
const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'https://odesign-drupal.ddev.site';

if (!uli || !docPath) {
  console.error('Usage: node playwright/scripts/check-doc-page-images.js <uli-url> <doc-path>');
  process.exit(1);
}

async function bypassSandbox(page) {
  const heading = page.getByRole('heading', { name: /sandbox environment/i });
  if (await heading.count()) {
    const link = page.getByRole('link', { name: /continue/i }).first();
    if (await link.count()) {
      await link.click();
      await page.waitForLoadState('networkidle');
    }
  }
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await context.newPage();

  await page.goto(uli, { waitUntil: 'networkidle', timeout: 30000 });
  await bypassSandbox(page);

  await page.goto(`${BASE_URL}${docPath}`, { waitUntil: 'networkidle', timeout: 30000 });
  await bypassSandbox(page);

  const report = await page.evaluate(() => {
    const imgs = Array.from(document.querySelectorAll('.odesign-documentation__markdown img'));
    return imgs.map((img) => ({
      src: img.getAttribute('src'),
      complete: img.complete,
      naturalWidth: img.naturalWidth,
      ok: img.complete && img.naturalWidth > 0,
    }));
  });

  console.log(JSON.stringify(report, null, 2));
  await browser.close();

  const broken = report.filter((r) => !r.ok);
  if (broken.length > 0) {
    console.error(`Images cassées: ${broken.length}`);
    process.exit(2);
  }
})();

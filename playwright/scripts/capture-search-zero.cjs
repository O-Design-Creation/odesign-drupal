#!/usr/bin/env node

const { chromium } = require('playwright');

(async () => {
  const uli = process.argv[2];
  if (!uli) {
    console.error('Usage: node playwright/scripts/capture-search-zero.cjs <uli-url>');
    process.exit(1);
  }

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1024, height: 900 },
    ignoreHTTPSErrors: true,
  });
  const page = await context.newPage();

  await page.goto(uli, { waitUntil: 'networkidle', timeout: 120000 });
  await page.goto('https://odesign-drupal.ddev.site/recherche?keys=zzzzqqq', {
    waitUntil: 'networkidle',
    timeout: 120000,
  });

  await page.screenshot({
    path: 'docs/utilisateur/screenshots/recherche-zero-resultat.png',
    fullPage: false,
  });

  await browser.close();
})();

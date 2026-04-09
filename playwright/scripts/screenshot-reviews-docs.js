#!/usr/bin/env node
/**
 * Capture les pages "avis clients" pour la doc gestionnaire.
 *
 * Usage:
 *   ULI=$(ddev drush uli --uid=2) && node playwright/scripts/screenshot-reviews-docs.js "$ULI"
 */

const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const uli = process.argv[2];
if (!uli) {
  console.error('Usage: node playwright/scripts/screenshot-reviews-docs.js <uli-url>');
  process.exit(1);
}

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'https://odesign-drupal.ddev.site';
const OUT_DIR = path.resolve(__dirname, '..', '..', 'docs', 'utilisateur', 'screenshots');
if (!fs.existsSync(OUT_DIR)) fs.mkdirSync(OUT_DIR, { recursive: true });

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

async function loginViaUli(page) {
  await page.goto(uli, { waitUntil: 'networkidle', timeout: 30000 });
  await bypassSandbox(page);
}

async function dismissCookies(page) {
  const denyButton = page.getByRole('button', { name: /non,\s*merci/i }).first();
  if (await denyButton.count()) {
    await denyButton.click();
    await page.waitForTimeout(400);
    return;
  }
  const acceptButton = page.getByRole('button', { name: /accepter/i }).first();
  if (await acceptButton.count()) {
    await acceptButton.click();
    await page.waitForTimeout(400);
  }
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1280, height: 900 },
    ignoreHTTPSErrors: true,
  });
  const page = await context.newPage();

  await loginViaUli(page);
  const targets = [
    { key: 'reviews-liste', url: '/admin/boutique/avis' },
    { key: 'reviews-formulaire-client', url: '/avis/commande/990013/docreviewtoken123' },
    { key: 'reviews-homepage-bloc', url: '/', sectionSelector: '.home-reviews' },
  ];

  for (const target of targets) {
    await page.goto(`${BASE_URL}${target.url}`, { waitUntil: 'networkidle', timeout: 30000 });
    await bypassSandbox(page);
    await dismissCookies(page);
    await page.waitForTimeout(1200);
    const outFile = path.resolve(OUT_DIR, `${target.key}.png`);
    if (target.sectionSelector) {
      const section = page.locator(target.sectionSelector).first();
      if (await section.count()) {
        await section.scrollIntoViewIfNeeded();
        await page.waitForTimeout(500);
        await section.screenshot({ path: outFile });
      }
      else {
        await page.screenshot({ path: outFile, fullPage: false });
      }
    }
    else {
      await page.screenshot({ path: outFile, fullPage: false });
    }
    console.log(`Capture enregistrée: ${outFile}`);
  }

  await browser.close();
})();

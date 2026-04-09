#!/usr/bin/env node
/**
 * Vérifie les images de tous les chapitres docs dans une seule session.
 *
 * Usage:
 *   ULI=$(ddev drush uli --uid=2) && node playwright/scripts/check-doc-images-all.js "$ULI"
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const uli = process.argv[2];
const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'https://odesign-drupal.ddev.site';
const DOC_DIR = path.resolve(__dirname, '..', '..', 'docs', 'utilisateur');

if (!uli) {
  console.error('Usage: node playwright/scripts/check-doc-images-all.js <uli-url>');
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

function listChapters() {
  return fs
    .readdirSync(DOC_DIR)
    .filter((name) => /^[0-9]{2}-[a-z0-9-]+\.md$/i.test(name))
    .map((name) => name.replace(/\.md$/i, ''))
    .sort();
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await context.newPage();

  await page.goto(uli, { waitUntil: 'networkidle', timeout: 30000 });
  await bypassSandbox(page);

  const chapters = listChapters();
  const summary = [];
  let hasBroken = false;

  for (const chapter of chapters) {
    await page.goto(`${BASE_URL}/admin/boutique/documentation/${chapter}`, { waitUntil: 'networkidle', timeout: 30000 });
    await bypassSandbox(page);

    const report = await page.evaluate(() => {
      const imgs = Array.from(document.querySelectorAll('.odesign-documentation__markdown img'));
      return imgs.map((img) => ({
        src: img.getAttribute('src'),
        ok: img.complete && img.naturalWidth > 0,
      }));
    });

    const broken = report.filter((r) => !r.ok).length;
    if (broken > 0) {
      hasBroken = true;
    }
    summary.push({ chapter, images: report.length, broken });
    console.log(`${chapter} images=${report.length} broken=${broken}`);
  }

  await browser.close();

  if (hasBroken) {
    console.error('Des images cassées ont été détectées.');
    process.exit(2);
  }
})();

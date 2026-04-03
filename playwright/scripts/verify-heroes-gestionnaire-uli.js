#!/usr/bin/env node
/**
 * Vérifie les contrôles gestionnaire heroes (AW-002 / VER-XXX) :
 *  1. Hero homepage via /admin/boutique/configuration
 *  2. Hero univers via /taxonomy/term/1/edit (Mariage)
 *
 * Usage : node verify-heroes-gestionnaire-uli.js <uli-url>
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const uli = process.argv[2];
if (!uli) {
  console.error('Usage: node verify-heroes-gestionnaire-uli.js <uli-url>');
  process.exit(1);
}
const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'https://odesign-drupal.ddev.site';

const outDir = path.join(__dirname, '../screenshots/heroes-gestionnaire');
fs.mkdirSync(outDir, { recursive: true });

async function gotoAndBypassSandbox(page, url) {
  await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
  // Click "Continue" button (Pantheon sandbox interstitial) if present
  const continueBtn = page.getByRole('button', { name: /continue/i });
  const continueLink = page.getByRole('link', { name: /continue/i });
  if (await continueBtn.count()) {
    await continueBtn.first().click();
    await page.waitForLoadState('networkidle');
  } else if (await continueLink.count()) {
    await continueLink.first().click();
    await page.waitForLoadState('networkidle');
  }
}

const results = {
  homepage_hero: {},
  univers_hero: {},
};

(async () => {
  const browser = await chromium.launch({ args: ['--ignore-certificate-errors'] });
  const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  // ── Authentification via ULI ────────────────────────────────────────────────
  await gotoAndBypassSandbox(page, uli);
  // Après le Continue Pantheon, Drupal redirige vers la page de l'utilisateur.
  // On navigue maintenant vers les pages à vérifier dans la même session.

  // ── 1. Hero homepage (/admin/boutique/configuration) ─────────────────────────
  await gotoAndBypassSandbox(page, `${BASE_URL}/admin/boutique/configuration`);

  const heroSummary = page.locator('summary').filter({ hasText: /hero de la page d.accueil/i });
  const heroSummaryCount = await heroSummary.count();
  results.homepage_hero.section_present = heroSummaryCount > 0;

  if (heroSummaryCount > 0) {
    const expanded = await heroSummary.first().getAttribute('aria-expanded');
    if (expanded !== 'true') {
      await heroSummary.first().click();
      await page.waitForTimeout(400);
    }

    const checkbox = page.locator('input[name="homepage_hero_enable_image"]');
    const select = page.locator('select[name="homepage_hero_text_mode"]');
    const fileField = page.locator('[id*="homepage-hero-image"], [data-drupal-selector*="homepage-hero-image"]');

    results.homepage_hero.checkbox_present = await checkbox.count() > 0;
    results.homepage_hero.select_present = await select.count() > 0;
    results.homepage_hero.file_field_present = await fileField.count() > 0;

    if (await select.count() > 0) {
      const opts = await select.locator('option').allTextContents();
      results.homepage_hero.text_mode_options = opts.map(s => s.trim());
    }

    await page.screenshot({ path: path.join(outDir, 'homepage-hero-section.png'), fullPage: false });
    results.homepage_hero.screenshot = 'heroes-gestionnaire/homepage-hero-section.png';
  }

  // ── 2. Hero univers (taxonomy/term/1/edit — Mariage) ─────────────────────────
  await gotoAndBypassSandbox(page, `${BASE_URL}/taxonomy/term/1/edit`);

  const termForm = page.locator('form[id*="taxonomy-term"]');
  results.univers_hero.term_form_accessible = await termForm.count() > 0;
  results.univers_hero.term_edit_url = '/taxonomy/term/1/edit';

  if (await termForm.count() > 0) {
    const heroImageField = page.locator(
      '[id*="field-hero-image"], [data-drupal-selector*="field-hero-image"]'
    );
    const heroTextModeField = page.locator('select[name*="field_hero_text_mode"]');

    results.univers_hero.hero_image_field_present = await heroImageField.count() > 0;
    results.univers_hero.hero_text_mode_field_present = await heroTextModeField.count() > 0;

    if (await heroTextModeField.count() > 0) {
      const opts = await heroTextModeField.locator('option').allTextContents();
      results.univers_hero.text_mode_options = opts.map(s => s.trim());
    }

    await page.screenshot({ path: path.join(outDir, 'univers-hero-term-edit.png'), fullPage: false });
    results.univers_hero.screenshot = 'heroes-gestionnaire/univers-hero-term-edit.png';
  }

  await browser.close();

  // ── Verdict ─────────────────────────────────────────────────────────────────
  const homepageOK =
    results.homepage_hero.section_present &&
    results.homepage_hero.checkbox_present &&
    results.homepage_hero.select_present;

  const universOK =
    results.univers_hero.term_form_accessible &&
    results.univers_hero.hero_image_field_present &&
    results.univers_hero.hero_text_mode_field_present;

  results.verdict = homepageOK && universOK ? 'OK' : 'FINDING';
  results.homepage_hero_ok = homepageOK;
  results.univers_hero_ok = universOK;

  console.log(JSON.stringify(results, null, 2));

  if (!homepageOK || !universOK) {
    process.exit(1);
  }
})();

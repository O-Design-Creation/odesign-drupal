#!/usr/bin/env node
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const uli = process.argv[2];
if (!uli) {
  console.error('Usage: node verify-promotions-config-uli.js <uli-url>');
  process.exit(1);
}
const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'https://odesign-drupal.ddev.site';

const outDir = '/var/www/html/playwright/screenshots/promotions-config';
fs.mkdirSync(outDir, { recursive: true });

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
  await page.goto(`${BASE_URL}/admin/boutique/configuration`, { waitUntil: 'networkidle', timeout: 30000 });
  if (await page.getByRole('heading', { name: /sandbox environment/i }).count()) {
    const continueLink = page.getByRole('link', { name: /continue/i });
    const continueButton = page.getByRole('button', { name: /continue/i });
    if (await continueLink.count()) {
      await continueLink.first().click();
      await page.waitForLoadState('networkidle');
      await page.goto(`${BASE_URL}/admin/boutique/configuration`, { waitUntil: 'networkidle', timeout: 30000 });
    } else if (await continueButton.count()) {
      await continueButton.first().click();
      await page.waitForLoadState('networkidle');
      await page.goto(`${BASE_URL}/admin/boutique/configuration`, { waitUntil: 'networkidle', timeout: 30000 });
    }
  }

  const promotionsSummary = page.locator('summary:has-text("Promotions volume (lots)")');
  const promotionsExpanded = await promotionsSummary.getAttribute('aria-expanded');
  if (promotionsExpanded !== 'true') {
    await promotionsSummary.click();
  }
  await page.waitForTimeout(400);

  const rule1Summary = page.getByRole('button', { name: 'Règle volume #1' });
  const rule1Expanded = await rule1Summary.getAttribute('aria-expanded');
  if (rule1Expanded !== 'true') {
    await rule1Summary.click();
  }
  await page.waitForTimeout(300);

  const enabled = page.locator('input[name="promotion_rule_0_enabled"]');
  const tiers = page.locator('textarea[name="promotion_rule_0_tiers"]');
  const scope = page.locator('select[name="promotion_rule_0_scope"]');
  const termsTarget = page.locator('input[name^="promotion_rule_0_target_terms"]');
  const productsTarget = page.locator('input[name^="promotion_rule_0_target_products"]');

  if (!(await enabled.count()) || !(await tiers.count()) || !(await scope.count())) {
    throw new Error('Champs promotions introuvables sur le formulaire.');
  }

  await scope.selectOption('life_occasion');
  await page.waitForTimeout(250);
  if (!(await termsTarget.count())) {
    throw new Error('Champ autocomplete Univers introuvable.');
  }

  await scope.selectOption('product');
  await page.waitForTimeout(250);
  if (!(await productsTarget.count())) {
    throw new Error('Champ autocomplete Produits introuvable.');
  }

  await scope.selectOption('life_occasion');
  if (!(await enabled.isChecked())) {
    await enabled.check({ force: true });
  }

  await tiers.fill('');
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle', timeout: 30000 }),
    page.$eval('form.odesign-config-form', (form) => form.requestSubmit()),
  ]);

  const hasTierError = (await page.locator('text=Ajoutez des paliers valides au format quantité:remise').count()) > 0;
  const hasTargetError = (await page.locator('text=Sélectionnez au moins une cible').count()) > 0;

  await page.screenshot({ path: path.join(outDir, 'promotions-config-validation.png'), fullPage: true });
  console.log(`tiers_error=${hasTierError ? 'yes' : 'no'}`);
  console.log(`target_error=${hasTargetError ? 'yes' : 'no'}`);
  console.log(`screenshot=${path.join(outDir, 'promotions-config-validation.png')}`);

  await browser.close();

  if (!hasTierError && !hasTargetError) {
    process.exit(2);
  }
})();

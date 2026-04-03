#!/usr/bin/env node
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const uli = process.argv[2];
if (!uli) {
  console.error('Usage: node verify-promotions-config-uli.js <uli-url>');
  process.exit(1);
}

const outDir = '/var/www/html/playwright/screenshots/promotions-config';
fs.mkdirSync(outDir, { recursive: true });

(async () => {
  const browser = await chromium.launch({ args: ['--ignore-certificate-errors'] });
  const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  await page.goto(uli, { waitUntil: 'networkidle', timeout: 30000 });
  await page.goto('https://odesign-drupal.ddev.site/admin/boutique/configuration', { waitUntil: 'networkidle', timeout: 30000 });

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
    const fieldNames = await page.$$eval('input,textarea,select', (els) =>
      els.map((e) => e.getAttribute('name')).filter((name) => name && name.includes('promotion_rule_0'))
    );
    console.log('debug_fields=' + fieldNames.join('|'));
    throw new Error('Champ autocomplete Univers introuvable.');
  }

  await scope.selectOption('product');
  await page.waitForTimeout(250);
  if (!(await productsTarget.count())) {
    const fieldNames = await page.$$eval('input,textarea,select', (els) =>
      els.map((e) => e.getAttribute('name')).filter((name) => name && name.includes('promotion_rule_0'))
    );
    console.log('debug_fields=' + fieldNames.join('|'));
    throw new Error('Champ autocomplete Produits introuvable.');
  }

  await scope.selectOption('life_occasion');
  if (!(await enabled.isChecked())) {
    await enabled.check({ force: true });
  }

  await tiers.fill('');
  const enabledNow = await enabled.isChecked();
  const tiersNow = await tiers.inputValue();
  console.log(`rule1_enabled=${enabledNow ? 'yes' : 'no'}`);
  console.log(`rule1_tiers_length=${tiersNow.length}`);
  const submitDebug = await page.$$eval('input#edit-submit', (els) =>
    els.map((el, idx) => {
      const r = el.getBoundingClientRect();
      const lineage = [];
      let current = el.parentElement;
      let depth = 0;
      while (current && depth < 5) {
        lineage.push({
          tag: current.tagName.toLowerCase(),
          cls: current.className || '',
          position: window.getComputedStyle(current).position,
          top: window.getComputedStyle(current).top,
        });
        current = current.parentElement;
        depth++;
      }
      return {
        idx,
        top: Math.round(r.top),
        left: Math.round(r.left),
        width: Math.round(r.width),
        height: Math.round(r.height),
        classes: (el.className || '').toString(),
        parent: el.closest('.region-sticky') ? 'region-sticky' : (el.closest('.form-actions') ? 'form-actions' : 'other'),
        lineage,
      };
    })
  );
  console.log('submit_debug=' + JSON.stringify(submitDebug));

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

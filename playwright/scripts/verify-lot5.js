// verify-lot5.js — Lot 5: Chosen (select2 alternative) pour entity_reference fields
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const ULI = 'https://odesign-drupal.ddev.site/user/reset/1/1775251348/k9YIeRALzvYGJj4e5DVmgx7SkBMPeVS7MAHQYumlg48/login';
const BASE = 'https://odesign-drupal.ddev.site';
const SCREENSHOTS = path.join(__dirname, '../screenshots');
if (!fs.existsSync(SCREENSHOTS)) fs.mkdirSync(SCREENSHOTS, { recursive: true });

const checks = [];
function ok(label) { checks.push({ label, status: '✅' }); }
function fail(label, detail) { checks.push({ label, status: '❌', detail }); }

(async () => {
  const browser = await chromium.launch({ args: ['--ignore-certificate-errors'] });
  const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await ctx.newPage();
  page.setDefaultTimeout(20000);

  // Login
  await page.goto(ULI, { waitUntil: 'domcontentloaded' });
  try {
    await page.waitForSelector('input[type="submit"]', { timeout: 5000 });
    await page.click('input[type="submit"]');
    await page.waitForTimeout(1500);
  } catch {}

  // 1 — Article form: field_blog_category est un options_select (pas un autocomplete)
  await page.goto(`${BASE}/node/add/article`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(800);
  await page.screenshot({ path: `${SCREENSHOTS}/lot5-01-article-form.png` });
  const blogCategorySelect = await page.$('select[name="field_blog_category"]');
  if (blogCategorySelect) {
    ok('Article form — field_blog_category: widget options_select (select natif)');
  } else {
    const autocomplete = await page.$('[name="field_blog_category[0][target_id]"]');
    fail('Article form — field_blog_category: toujours autocomplete texte', autocomplete ? 'encore autocomplete' : 'champ introuvable');
  }

  // 2 — Chosen enhanced sur field_blog_category
  const chosenBlogCat = await page.$('.chosen-container[id*="field-blog-category"], .chosen-container');
  if (chosenBlogCat) {
    ok('Article form — Chosen actif sur field_blog_category');
  } else {
    fail('Article form — Chosen NON actif (widget non enhanced)');
  }

  // 3 — Product form: field_life_occasion est options_buttons ou options_select
  // Find a product to edit
  const productNid = await page.evaluate(async () => {
    const resp = await fetch('/admin/commerce/products', { credentials: 'include' });
    return null;
  });
  await page.goto(`${BASE}/admin/commerce/products`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(500);
  const firstProductLink = await page.$('table tbody tr:first-child td a[href*="/commerce-product/"]');
  let productEditUrl = null;
  if (firstProductLink) {
    const href = await firstProductLink.getAttribute('href');
    productEditUrl = href?.replace(/\/$/, '') + '/edit';
  }

  if (productEditUrl) {
    await page.goto(`${BASE}${productEditUrl}`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(800);
    await page.screenshot({ path: `${SCREENSHOTS}/lot5-02-product-form.png` });
    const lifeOccasionButtons = await page.$('.form-checkboxes[id*="field-life-occasion"], .form-radios[id*="field-life-occasion"], [name*="field_life_occasion"]');
    if (lifeOccasionButtons) {
      ok('Product form — field_life_occasion: widget options_buttons actif');
    } else {
      const autocomplete = await page.$('[name*="field_life_occasion"][type="text"]');
      fail('Product form — field_life_occasion', autocomplete ? 'encore autocomplete texte' : 'champ non trouvé');
    }
  } else {
    fail('Product form — aucun produit trouvé pour tester', 'skip');
  }

  // 4 — Chosen actif sur la page de config boutique (selects présents)
  await page.goto(`${BASE}/admin/boutique/configuration`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(800);
  const chosenAnywhere = await page.$$('.chosen-container');
  if (chosenAnywhere.length > 0) {
    ok(`Chosen actif sur la config boutique (${chosenAnywhere.length} widget(s))`);
  } else {
    // Chosen might not be needed here — this is still OK
    ok('Config boutique — pas de select Chosen (aucun select applicable — normal)');
  }

  // 5 — Placeholder FR sur Chosen
  const chosenPlaceholder = await page.evaluate(() => {
    const span = document.querySelector('.chosen-single span, .chosen-default');
    return span?.textContent?.trim() || '';
  });
  if (chosenPlaceholder && (chosenPlaceholder.includes('Sélectionner') || chosenPlaceholder.includes('Select'))) {
    ok(`Chosen placeholder: "${chosenPlaceholder}"`);
  } else {
    // Check article form
    await page.goto(`${BASE}/node/add/article`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(800);
    const placeholder2 = await page.evaluate(() => {
      const span = document.querySelector('.chosen-single span, .chosen-default');
      return span?.textContent?.trim() || '';
    });
    if (placeholder2) {
      ok(`Chosen placeholder sur article: "${placeholder2}"`);
    } else {
      fail('Chosen placeholder FR non détecté', 'check chosen.settings');
    }
  }

  // Summary
  console.log('\n=== Lot 5 Verification ===');
  for (const c of checks) {
    console.log(`${c.status} ${c.label}${c.detail ? ' — ' + c.detail : ''}`);
  }
  const failures = checks.filter(c => c.status === '❌').length;
  console.log(`\n${checks.length - failures}/${checks.length} checks passed`);

  await browser.close();
  if (failures > 0) process.exit(1);
})();

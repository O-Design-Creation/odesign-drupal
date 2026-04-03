// verify-lot4.js — Lot 4: content_moderation + guide node type + form_alters
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const ULI = 'https://odesign-drupal.ddev.site/user/reset/1/1775250751/NAopznUEi8k_9t4_IjmI_dkwzfEWx2CtVWOgaCHYHk0/login';
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

  // Login via ULI — navigate directly to the ULI URL, which auto-logs in
  await page.goto(ULI, { waitUntil: 'domcontentloaded', timeout: 20000 });
  // The ULI page shows a "Log in" button — click it
  try {
    await page.waitForSelector('input[type="submit"]', { timeout: 5000 });
    await page.click('input[type="submit"]');
    await page.waitForTimeout(2000);
  } catch (e) {
    // already logged in or no button
  }
  console.log('Post-login URL:', page.url());

  // 1 — Guide marketing page
  await page.goto(`${BASE}/admin/boutique/guide-marketing`, { waitUntil: 'domcontentloaded', timeout: 20000 });
  await page.waitForTimeout(1000);
  await page.screenshot({ path: `${SCREENSHOTS}/lot4-01-guide-marketing.png` });
  const guideEmpty = await page.$('.odesign-marketing-guide__empty');
  const guideContent = await page.$('.field--name-body, .node--type-guide');
  const pageTitle = await page.title();
  if (guideEmpty) {
    ok('Guide marketing — empty state affiché avec CTA création');
  } else if (guideContent) {
    ok('Guide marketing — contenu guide affiché (nœud existant)');
  } else if (pageTitle.toLowerCase().includes('guide')) {
    ok(`Guide marketing — page accessible (title: ${pageTitle})`);
  } else {
    fail('Guide marketing — page inaccessible', pageTitle);
  }

  // 2 — Formulaire de création guide accessible
  await page.goto(`${BASE}/node/add/guide`, { waitUntil: 'domcontentloaded', timeout: 20000 });
  await page.waitForTimeout(500);
  await page.screenshot({ path: `${SCREENSHOTS}/lot4-02-guide-form.png` });
  const guideForm = await page.$('form.node-guide-form');
  if (guideForm) {
    ok('Formulaire guide — accessible (/node/add/guide)');
  } else {
    fail('Formulaire guide — non accessible', await page.title());
  }

  // 3 — moderation_state sur formulaire guide
  const moderationGuide = await page.$('[name="moderation_state[0][state]"]');
  if (moderationGuide) {
    ok('Formulaire guide — champ moderation_state présent');
  } else {
    fail('Formulaire guide — champ moderation_state ABSENT');
  }

  // 4 — Format selector masqué
  const formatVisible = await page.evaluate(() => {
    const wrapper = document.querySelector('.text-format-wrapper .filter-wrapper');
    if (!wrapper) return false;
    return getComputedStyle(wrapper).display !== 'none';
  });
  if (!formatVisible) {
    ok('Formulaire guide — sélecteur de format masqué');
  } else {
    fail('Formulaire guide — sélecteur de format VISIBLE');
  }

  // 5 — moderation_state sur formulaire article
  await page.goto(`${BASE}/node/add/article`, { waitUntil: 'domcontentloaded', timeout: 20000 });
  await page.waitForTimeout(500);
  await page.screenshot({ path: `${SCREENSHOTS}/lot4-03-article-workflow.png` });
  const moderationArticle = await page.$('[name="moderation_state[0][state]"]');
  if (moderationArticle) {
    ok('Formulaire article — champ moderation_state présent');
  } else {
    fail('Formulaire article — champ moderation_state ABSENT');
  }

  // 6 — Libellé workflow customisé sur formulaire guide
  await page.goto(`${BASE}/node/add/guide`, { waitUntil: 'domcontentloaded', timeout: 20000 });
  await page.waitForTimeout(500);
  const modLabel = await page.evaluate(() => {
    const el = document.querySelector('[name="moderation_state[0][state]"]');
    if (!el) return '';
    const container = el.closest('.form-item, .js-form-item');
    return container?.querySelector('label')?.textContent?.trim() || '';
  });
  if (modLabel && modLabel !== 'Moderation state') {
    ok(`Label workflow customisé: "${modLabel}"`);
  } else if (modLabel === 'Moderation state') {
    fail(`Label workflow non customisé: "${modLabel}"`);
  } else {
    fail('Label workflow introuvable');
  }

  // Summary
  console.log('\n=== Lot 4 Verification ===');
  for (const c of checks) {
    console.log(`${c.status} ${c.label}${c.detail ? ' — ' + c.detail : ''}`);
  }
  const failures = checks.filter(c => c.status === '❌').length;
  console.log(`\n${checks.length - failures}/${checks.length} checks passed`);

  await browser.close();
  if (failures > 0) process.exit(1);
})();

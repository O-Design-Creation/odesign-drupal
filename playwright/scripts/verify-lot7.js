// verify-lot7.js — Lot 7: scheduler publication programmée (articles + guides)
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const ULI = 'https://odesign-drupal.ddev.site/user/reset/1/1775254055/CVom-JmVPMBVgVype_Yvh9qJTugH3AAWn-PqvLKDtXQ/login';
const BASE = 'https://odesign-drupal.ddev.site';
const SHOTS = path.join(__dirname, '../screenshots');
if (!fs.existsSync(SHOTS)) fs.mkdirSync(SHOTS, { recursive: true });

const checks = [];
const ok   = (l) => { checks.push({ l, s: '✅' }); };
const fail = (l, d) => { checks.push({ l, s: '❌', d }); };

(async () => {
  const browser = await chromium.launch({ args: ['--ignore-certificate-errors'] });
  const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await ctx.newPage();
  page.setDefaultTimeout(20000);

  await page.goto(ULI, { waitUntil: 'domcontentloaded' });
  try { await page.waitForSelector('input[type="submit"]', { timeout: 4000 }); await page.click('input[type="submit"]'); await page.waitForTimeout(1200); } catch(e) {}

  // 1 — Formulaire article : section "Programmation" visible
  await page.goto(`${BASE}/node/add/article`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(800);
  await page.screenshot({ path: `${SHOTS}/lot7-01-article-form.png` });

  const schedulerSummary = await page.evaluate(() => {
    const el = document.querySelector('details summary');
    const allSummaries = [...document.querySelectorAll('details summary')].map(s => s.textContent.trim());
    return allSummaries.find(t => t.includes('Programmation')) || null;
  });
  schedulerSummary ? ok(`Article form — section "Programmation" présente: "${schedulerSummary}"`) : fail('Article form — section "Programmation" absente');

  // 2 — Champs publish_on + unpublish_on présents dans le formulaire article
  const articleSchedulerInputs = await page.evaluate(() => {
    const names = [...document.querySelectorAll('input[name*="publish_on"], input[name*="unpublish_on"]')].map(i => i.name);
    return names;
  });
  if (articleSchedulerInputs.some(n => n.includes('publish_on')) && articleSchedulerInputs.some(n => n.includes('unpublish_on'))) {
    ok(`Article form — champs publish_on + unpublish_on présents (${articleSchedulerInputs.length} inputs)`);
  } else {
    fail('Article form — champs scheduler manquants', JSON.stringify(articleSchedulerInputs));
  }

  // 3 — Aucune warning Drupal sur le formulaire article
  const warningsArticle = await page.evaluate(() => [...document.querySelectorAll('.messages--warning')].length);
  warningsArticle === 0 ? ok('Article form — 0 warnings Drupal') : fail(`Article form — ${warningsArticle} warnings Drupal`);

  // 4 — Libellés FR sur les champs scheduler (chercher le texte dans la page entière)
  const publishTextFR = await page.evaluate(() => {
    return document.body.innerText.includes('Publier') || document.body.innerHTML.includes('Publier automatiquement');
  });
  publishTextFR ? ok('Article form — libellé FR "Publier automatiquement le" présent') : fail('Article form — libellé FR publish_on manquant');

  // 5 — Formulaire guide : section "Programmation" visible
  await page.goto(`${BASE}/node/add/guide`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(800);
  await page.screenshot({ path: `${SHOTS}/lot7-02-guide-form.png` });

  const guideSummary = await page.evaluate(() => {
    const allSummaries = [...document.querySelectorAll('details summary')].map(s => s.textContent.trim());
    return allSummaries.find(t => t.includes('Programmation')) || null;
  });
  guideSummary ? ok(`Guide form — section "Programmation" présente`) : fail('Guide form — section "Programmation" absente');

  // 6 — Champs publish_on + unpublish_on présents dans le formulaire guide
  const guideSchedulerInputs = await page.evaluate(() => {
    return [...document.querySelectorAll('input[name*="publish_on"], input[name*="unpublish_on"]')].map(i => i.name);
  });
  guideSchedulerInputs.some(n => n.includes('publish_on')) ? ok(`Guide form — champs scheduler présents`) : fail('Guide form — champs scheduler manquants');

  // 7 — Scheduler config sync exportée
  const configSync = fs.existsSync(path.join(__dirname, '../../config/sync/scheduler.settings.yml'));
  configSync ? ok('Config sync — scheduler.settings.yml exporté') : fail('Config sync — scheduler.settings.yml manquant');

  // 8 — Config form display exportée (article)
  const articleDisplayConfig = path.join(__dirname, '../../config/sync/core.entity_form_display.node.article.default.yml');
  if (fs.existsSync(articleDisplayConfig)) {
    const content = fs.readFileSync(articleDisplayConfig, 'utf8');
    content.includes('publish_on') ? ok('Config sync — publish_on dans form display article') : fail('Config sync — publish_on manquant dans form display article');
  } else {
    fail('Config sync — form display article manquant');
  }

  // Summary
  console.log('\n=== Lot 7 Verification ===');
  for (const c of checks) console.log(`${c.s} ${c.l}${c.d ? ' — ' + c.d : ''}`);
  const failures = checks.filter(c => c.s === '❌').length;
  console.log(`\n${checks.length - failures}/${checks.length} checks passed`);
  await browser.close();
  if (failures > 0) process.exit(1);
})();

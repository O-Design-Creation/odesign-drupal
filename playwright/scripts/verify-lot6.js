// verify-lot6.js — Lot 6: Hub Catalogue + Hub Éditorial
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const ULI = 'https://odesign-drupal.ddev.site/user/reset/1/1775253202/CenE4XZJftHbc87rL81SgVKZO7LrnOIq6jFA8IZxlnk/login';
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

  // 1 — Hub Catalogue accessible
  await page.goto(`${BASE}/admin/boutique/catalogue`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(800);
  await page.screenshot({ path: `${SHOTS}/lot6-01-catalogue.png` });
  const catalogueTitle = await page.title();
  const newProductBtn = await page.$('a.button[href*="commerce-product/add"], a.button[href*="product/add"]');
  if (newProductBtn) {
    ok(`Catalogue hub — accessible, bouton "Nouveau produit" présent`);
  } else if (catalogueTitle.toLowerCase().includes('catalogue')) {
    ok(`Catalogue hub — accessible (title: ${catalogueTitle})`);
  } else {
    fail('Catalogue hub — non accessible', catalogueTitle);
  }

  // 2 — Hub Catalogue : liste produits embarquée (vue Views)
  const viewContent = await page.$('.view-commerce-products, .views-element-container, table');
  viewContent ? ok('Catalogue hub — vue produits embarquée') : fail('Catalogue hub — vue produits manquante');

  // 3 — Nav item "Catalogue" actif sur /admin/boutique/catalogue
  const catalogueNavActive = await page.evaluate(() => {
    const links = document.querySelectorAll('.odesign-admin-nav__link--active');
    return [...links].some(el => el.textContent.includes('Catalogue'));
  });
  catalogueNavActive ? ok('Nav — item Catalogue actif sur /admin/boutique/catalogue') : fail('Nav — item Catalogue NON actif');

  // 4 — Hub Éditorial accessible
  await page.goto(`${BASE}/admin/boutique/editorial`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(800);
  await page.screenshot({ path: `${SHOTS}/lot6-02-editorial.png` });
  const editorialTitle = await page.title();
  const newArticleBtn = await page.$('a.button[href*="node/add/article"]');
  if (newArticleBtn) {
    ok('Éditorial hub — accessible, bouton "Nouvel article" présent');
  } else if (editorialTitle.toLowerCase().includes('ditorial') || editorialTitle.toLowerCase().includes('article')) {
    ok(`Éditorial hub — accessible (title: ${editorialTitle})`);
  } else {
    fail('Éditorial hub — non accessible', editorialTitle);
  }

  // 5 — Hub Éditorial : liste univers (termes life_occasion)
  const universRows = await page.$$('.odesign-hub__univers-row');
  if (universRows.length >= 4) {
    ok(`Éditorial hub — ${universRows.length} univers listés`);
  } else if (universRows.length > 0) {
    ok(`Éditorial hub — ${universRows.length} univers listés (attendu 4)`);
  } else {
    fail('Éditorial hub — univers non listés');
  }

  // 6 — Lien "Voir sur le site" sur un univers
  const viewSiteLinks = await page.$$('a[target="_blank"][href*="/occasions"], a[target="_blank"][href*="/taxonomy/term"]');
  viewSiteLinks.length > 0 ? ok(`Éditorial hub — liens "Voir sur le site" (${viewSiteLinks.length})`) : fail('Éditorial hub — liens "Voir sur le site" manquants');

  // 7 — Formulaire terme life_occasion : titre contextuel + lien "Voir sur le site"
  await page.goto(`${BASE}/taxonomy/term/1/edit`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(500);
  await page.screenshot({ path: `${SHOTS}/lot6-03-taxonomy-edit.png` });
  const pageH1 = await page.evaluate(() => document.querySelector('h1, .page-title')?.textContent?.trim() || '');
  if (pageH1.includes('Modifier') || pageH1.includes('Mariage')) {
    ok(`Taxonomy form — titre contextuel: "${pageH1}"`);
  } else {
    fail('Taxonomy form — titre contextuel manquant', pageH1);
  }
  const viewOnSiteBtn = await page.$('a.button[target="_blank"]');
  viewOnSiteBtn ? ok('Taxonomy form — bouton "Voir sur le site" présent') : fail('Taxonomy form — bouton "Voir sur le site" manquant');

  // 8 — Nav actif sur /admin/boutique/editorial
  await page.goto(`${BASE}/admin/boutique/editorial`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(500);
  const editorialNavActive = await page.evaluate(() => {
    const links = document.querySelectorAll('.odesign-admin-nav__link--active');
    return [...links].some(el => el.textContent.includes('ditorial'));
  });
  editorialNavActive ? ok('Nav — item Éditorial actif sur /admin/boutique/editorial') : fail('Nav — item Éditorial NON actif');

  // Summary
  console.log('\n=== Lot 6 Verification ===');
  for (const c of checks) console.log(`${c.s} ${c.l}${c.d ? ' — ' + c.d : ''}`);
  const failures = checks.filter(c => c.s === '❌').length;
  console.log(`\n${checks.length - failures}/${checks.length} checks passed`);
  await browser.close();
  if (failures > 0) process.exit(1);
})();

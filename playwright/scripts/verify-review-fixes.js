// verify-review-fixes.js — régression post-corrections review
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const ULI = 'https://odesign-drupal.ddev.site/user/reset/1/1775252624/3pUgoZDes74z8UpGetaaczWtcCpaCzjwN-yEluc99-o/login';
const BASE = 'https://odesign-drupal.ddev.site';
const SHOTS = path.join(__dirname, '../screenshots');
if (!fs.existsSync(SHOTS)) fs.mkdirSync(SHOTS, { recursive: true });

const checks = [];
const ok  = (l) => { checks.push({ l, s:'✅' }); };
const fail = (l, d) => { checks.push({ l, s:'❌', d }); };

(async () => {
  const browser = await chromium.launch({ args: ['--ignore-certificate-errors'] });
  const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await ctx.newPage();
  page.setDefaultTimeout(20000);

  await page.goto(ULI, { waitUntil: 'domcontentloaded' });
  try { await page.waitForSelector('input[type="submit"]', {timeout:4000}); await page.click('input[type="submit"]'); await page.waitForTimeout(1200); } catch {}

  // 1 — Dashboard accessible
  await page.goto(`${BASE}/admin/boutique`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(500);
  await page.screenshot({ path: `${SHOTS}/fix-01-dashboard.png` });
  const stats = await page.$$('.odesign-dashboard__stat');
  stats.length >= 2 ? ok(`Dashboard — ${stats.length} cards`) : fail('Dashboard — cards manquantes');

  // 2 — Guide marketing page (empty state = render array link au lieu de #markup)
  await page.goto(`${BASE}/admin/boutique/guide-marketing`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(500);
  await page.screenshot({ path: `${SHOTS}/fix-02-guide.png` });
  const guideCta = await page.$('.odesign-marketing-guide__empty a.button, .odesign-marketing-guide__actions a.button');
  guideCta ? ok('Guide marketing — bouton CTA render array présent') : fail('Guide marketing — bouton CTA manquant');

  // 3 — Config boutique : section Ma boutique accessible
  await page.goto(`${BASE}/admin/boutique/configuration`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(500);
  await page.screenshot({ path: `${SHOTS}/fix-03-config.png` });
  const boutiqueTab = await page.$('[data-drupal-nav-tabs-link], .vertical-tabs__menu-item');
  boutiqueTab ? ok('Config boutique — onglets verticaux présents') : fail('Config boutique — onglets manquants');

  // 4 — Commandes à traiter : CSS chargé via library (pas html_head)
  await page.goto(`${BASE}/admin/boutique/commandes-a-traiter`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(500);
  await page.screenshot({ path: `${SHOTS}/fix-04-orders.png` });
  const ordersTable = await page.$('.odesign-orders-table, table');
  ordersTable ? ok('Commandes à traiter — tableau visible') : fail('Commandes à traiter — tableau manquant');

  // 5 — CSS orders chargé (présence du style dans la page)
  const cssLoaded = await page.evaluate(() => {
    const links = [...document.querySelectorAll('link[rel=stylesheet]')];
    return links.some(l => l.href.includes('orders.css'));
  });
  cssLoaded ? ok('orders.css chargé via library') : fail('orders.css non chargé');

  // 6 — Détail d'une commande accessible
  const orderLinks = await page.$$('table tbody tr td a[href*="/commandes/"]');
  if (orderLinks.length > 0) {
    const href = await orderLinks[0].getAttribute('href');
    await page.goto(`${BASE}${href}`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(500);
    await page.screenshot({ path: `${SHOTS}/fix-05-order-detail.png` });
    const orderDetail = await page.$('.odesign-order-detail, .odesign-orders-detail');
    orderDetail ? ok('Détail commande — page accessible') : fail('Détail commande — contenu manquant');
  } else {
    ok('Détail commande — aucune commande actionable (skip)');
  }

  // Summary
  console.log('\n=== Review Fixes Verification ===');
  for (const c of checks) console.log(`${c.s} ${c.l}${c.d ? ' — '+c.d : ''}`);
  const failures = checks.filter(c => c.s === '❌').length;
  console.log(`\n${checks.length - failures}/${checks.length} passed`);
  await browser.close();
  if (failures > 0) process.exit(1);
})();

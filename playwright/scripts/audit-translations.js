// audit-translations.js — Détecte les chaînes anglaises sur les pages gestionnaire
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const ULI = 'https://odesign-drupal.ddev.site/user/reset/1/1775254794/PmejWqc4snKClSvkGkwRQUsXHQ7RjP_nVe9zKZJFWzc/login';
const BASE = 'https://odesign-drupal.ddev.site';
const SHOTS = path.join(__dirname, '../screenshots');
if (!fs.existsSync(SHOTS)) fs.mkdirSync(SHOTS, { recursive: true });

// English keywords that shouldn't appear in a French gestionnaire UI
const EN_KEYWORDS = [
  'Close sidebar', 'Hide sidebar', 'Open sidebar',
  'Canceled', 'Completed', 'Pending', 'Draft', 'Published',
  'Scheduling options', 'Not scheduled', 'Publish on', 'Unpublish on',
  'In review', 'Needs review', 'Content Overview', 'Content Type',
  'Add order items', 'Edit billing information',
  'Customer comments', 'Default product variation', 'Current product variation',
  'Add credit memo', 'Add invoice', 'No invoices',
  'Placed order', 'Last payment', 'Order shipments',
];

const pages = [
  { url: '/admin/boutique', name: 'Dashboard' },
  { url: '/admin/boutique/commandes-a-traiter', name: 'Commandes à traiter' },
  { url: '/admin/commerce/orders', name: 'Toutes commandes' },
  { url: '/admin/boutique/catalogue', name: 'Hub Catalogue' },
  { url: '/admin/boutique/editorial', name: 'Hub Éditorial' },
  { url: '/admin/boutique/configuration', name: 'Configuration' },
  { url: '/node/add/article', name: 'Formulaire article' },
  { url: '/node/add/guide', name: 'Formulaire guide' },
  { url: '/taxonomy/term/1/edit', name: 'Formulaire univers' },
  { url: '/admin/content', name: 'Liste contenus' },
  { url: '/admin/structure/taxonomy/manage/life_occasion/overview', name: 'Univers liste' },
];

(async () => {
  const browser = await chromium.launch({ args: ['--ignore-certificate-errors'] });
  const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await ctx.newPage();
  page.setDefaultTimeout(20000);

  await page.goto(ULI, { waitUntil: 'domcontentloaded' });
  try { await page.click('input[type="submit"]'); await page.waitForTimeout(1200); } catch(e) {}

  const allFindings = [];

  for (const pg of pages) {
    await page.goto(BASE + pg.url, { waitUntil: 'networkidle' });
    await page.waitForTimeout(600);
    const text = await page.evaluate(() => document.body.innerText);
    const found = EN_KEYWORDS.filter(kw => text.includes(kw));
    if (found.length > 0) {
      allFindings.push({ page: pg.name, url: pg.url, english: found });
    }
  }

  if (allFindings.length === 0) {
    console.log('✅ Aucune chaîne anglaise détectée sur les pages gestionnaire');
  } else {
    console.log('⚠️  Chaînes anglaises détectées :');
    for (const f of allFindings) {
      console.log(`\n  📄 ${f.page} (${f.url})`);
      f.english.forEach(s => console.log(`     - "${s}"`));
    }
  }

  await browser.close();
})();

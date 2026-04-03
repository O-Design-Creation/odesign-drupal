// verify-translations.js — Vérifie l'absence de chaînes anglaises sur les pages gestionnaire
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const ULI = 'https://odesign-drupal.ddev.site/user/reset/1/1775255244/zNCoIK-v5fj9kGseHCzBN--87381ST_HP3melU1YfME/login';
const BASE = 'https://odesign-drupal.ddev.site';
const SHOTS = path.join(__dirname, '../screenshots');
if (!fs.existsSync(SHOTS)) fs.mkdirSync(SHOTS, { recursive: true });

// Chaînes anglaises qui ne devraient PAS apparaître dans l'interface gestionnaire
const EN_FORBIDDEN = [
  'Close sidebar',   // Gin - doit être "Fermer le panneau latéral"
  'Hide sidebar',    // Gin - doit être "Masquer le panneau latéral"
  'Scheduling options', // Scheduler - doit être "Programmation"
  'Not scheduled',   // Scheduler - doit être "Non planifié"
  'Content Overview',
  'Content Type',
  'Add order items',
  'Edit billing information',
  'Customer comments',
  'Default product variation',
  'Placed order',
  'Change text format?',
];

// Chaînes françaises qui DOIVENT être présentes sur les pages clés
const FR_REQUIRED = {
  '/admin/boutique': ['Tableau de bord', 'Commandes', 'Catalogue', 'Éditorial', 'Configuration'],
  '/admin/boutique/commandes-a-traiter': ['Commandes à traiter', 'Commandes'],
  '/admin/boutique/catalogue': ['Catalogue', 'Nouveau produit'],
  '/admin/boutique/editorial': ['Éditorial', 'Nouvel article', 'Univers'],
  '/node/add/article': ['Programmation', 'Non planifié', 'Créer un article'],
  '/node/add/guide': ['Programmation', 'Statut de publication'],
};

const checks = [];
const ok = (l) => checks.push({ s: '✅', l });
const fail = (l, d) => checks.push({ s: '❌', l, d });

(async () => {
  const browser = await chromium.launch({ args: ['--ignore-certificate-errors'] });
  const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await ctx.newPage();
  page.setDefaultTimeout(20000);

  await page.goto(ULI, { waitUntil: 'domcontentloaded' });
  try { await page.waitForSelector('input[type="submit"]', { timeout: 4000 }); await page.click('input[type="submit"]'); await page.waitForTimeout(1200); } catch (e) {}

  // --- Vérifier absence de chaînes anglaises sur toutes les pages ---
  const allPages = Object.keys(FR_REQUIRED).concat([
    '/admin/commerce/orders',
    '/taxonomy/term/1/edit',
  ]);

  const englishFound = {};
  for (const url of allPages) {
    await page.goto(BASE + url, { waitUntil: 'networkidle' });
    await page.waitForTimeout(500);
    const text = await page.evaluate(() => document.body.innerText);
    const found = EN_FORBIDDEN.filter(kw => text.includes(kw));
    if (found.length > 0) englishFound[url] = found;
  }

  if (Object.keys(englishFound).length === 0) {
    ok('Aucune chaîne anglaise détectée sur toutes les pages gestionnaire');
  } else {
    for (const [url, words] of Object.entries(englishFound)) {
      fail(`Chaînes anglaises sur ${url}`, words.join(', '));
    }
  }

  // --- Vérifier présence des chaînes françaises requises ---
  for (const [url, required] of Object.entries(FR_REQUIRED)) {
    await page.goto(BASE + url, { waitUntil: 'networkidle' });
    await page.waitForTimeout(500);
    await page.screenshot({ path: `${SHOTS}/tr-${url.replace(/\//g, '-').replace(/^-/, '')}.png` });
    const text = await page.evaluate(() => document.body.innerText);
    const missing = required.filter(kw => !text.includes(kw));
    if (missing.length === 0) {
      ok(`${url} — toutes les chaînes FR présentes (${required.length})`);
    } else {
      fail(`${url} — chaînes FR manquantes`, missing.join(', '));
    }
  }

  // --- Vérifier état "Programmation" dans le formulaire article (sidebar/vertical tab) ---
  await page.goto(BASE + '/node/add/article', { waitUntil: 'networkidle' });
  await page.waitForTimeout(600);
  const schedulerFR = await page.evaluate(() => {
    const summaries = [...document.querySelectorAll('details summary')].map(s => s.textContent.trim());
    return summaries.some(s => s.includes('Programmation'));
  });
  schedulerFR ? ok('Article form — onglet "Programmation" (scheduler) en français') : fail('Article form — onglet scheduler non traduit');

  // --- Résumé ---
  console.log('\n=== Vérification traductions FR ===');
  for (const c of checks) {
    console.log(`${c.s} ${c.l}${c.d ? ' — ' + c.d : ''}`);
  }
  const failures = checks.filter(c => c.s === '❌').length;
  console.log(`\n${checks.length - failures}/${checks.length} checks passed`);
  await browser.close();
  if (failures > 0) process.exit(1);
})();

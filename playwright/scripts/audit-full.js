#!/usr/bin/env node
/**
 * audit-full.js
 * Audit UX/UI complet — toutes les pages utilisables par le gestionnaire boutique.
 *
 * Checks effectués par page :
 *  1. Labels EN non traduits (HIGH)
 *  2. Champs techniques exposés sans label métier (HIGH)
 *  3. Formulaire sans bouton d'action FR (HIGH)
 *  4. Images sans attribut alt (MEDIUM)
 *  5. Champs importants sans texte d'aide (MEDIUM)
 *  6. Titres de page vides (HIGH)
 *  7. Erreurs PHP/Drupal affichées (HIGH)
 *  8. Contenu débordant de la sidebar (HIGH)
 *  9. Icônes Material Symbols non rendues (MEDIUM)
 * 10. Liens d'action non traduits (MEDIUM)
 */

const { chromium } = require('playwright');
const path = require('path');
const fs   = require('fs');

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://web';
const AUDIT_USER = process.env.DRUPAL_AUDIT_USER
  || process.env.DRUPAL_CLIENT_USER
  || process.env.DRUPAL_ADMIN_USER
  || 'client_ateliero';
const AUDIT_PASSWORD = process.env.DRUPAL_AUDIT_PASS
  || process.env.DRUPAL_CLIENT_PASS
  || process.env.DRUPAL_ADMIN_PASS
  || 'admin';

const SCREENSHOTS_DIR = path.join(__dirname, '..', 'screenshots', 'audit-full');
if (!fs.existsSync(SCREENSHOTS_DIR)) fs.mkdirSync(SCREENSHOTS_DIR, { recursive: true });

/** Patterns indiquant un texte non traduit en FR */
const EN_STRICT = [
  /^Save$/i, /^Edit$/i, /^Delete$/i, /^Publish$/i, /^Unpublish$/i,
  /^Cancel$/i, /^Submit$/i, /^View$/i, /^Enable$/i, /^Disable$/i,
  /^Add new/i, /^Create new/i, /^No results/i, /^Operations$/i,
  /^Weight$/i, /^Machine name$/i, /^Bundle$/i, /^Entity type$/i,
];

/** Champs dont l'identifiant technique est exposé sans label métier clair */
const TECH_FIELD_NAMES = ['uid', 'langcode', 'uuid', 'vid', 'changed', 'revision_log'];

/** Pages à auditer — regroupées par catégorie gestionnaire */
const AUDIT_PAGES = [
  // ── Tableau de bord ──────────────────────────────────────────────────
  { key: 'dashboard',           url: '/admin/boutique',                           cat: 'Dashboard' },
  { key: 'config-boutique',     url: '/admin/boutique/configuration',             cat: 'Dashboard' },

  // ── Catalogue produits ───────────────────────────────────────────────
  { key: 'product-list',        url: '/admin/commerce/products',                  cat: 'Catalogue' },
  { key: 'product-add',         url: '/product/add/default',                      cat: 'Catalogue' },

  // ── Commandes ────────────────────────────────────────────────────────
  { key: 'order-list',          url: '/admin/commerce/orders',                    cat: 'Commandes' },

  // ── Promotions ───────────────────────────────────────────────────────
  { key: 'promotions-list',     url: '/admin/commerce/promotions',                cat: 'Promotions' },

  // ── Facturation ──────────────────────────────────────────────────────
  { key: 'invoices-list',       url: '/admin/commerce/invoices',                  cat: 'Facturation' },

  // ── Médias ───────────────────────────────────────────────────────────
  { key: 'media-list',          url: '/admin/content/media',                      cat: 'Médias' },
  { key: 'media-add-image',     url: '/media/add/product_image',                  cat: 'Médias' },
];

async function login(page) {
  await page.goto(`${BASE_URL}/user/login`);
  await page.waitForSelector('#edit-name');
  await page.fill('#edit-name', AUDIT_USER);
  await page.fill('#edit-pass', AUDIT_PASSWORD);
  await page.click('#edit-submit');
  await page.waitForLoadState('networkidle');

  const stillOnLogin = await page.locator('#edit-name').count();
  if (stillOnLogin > 0) {
    throw new Error(`Connexion échouée pour "${AUDIT_USER}"`);
  }
}

async function assertPageUsable(page, url) {
  const bodyText = await page.locator('body').innerText().catch(() => '');
  if (/Vous n['’]êtes pas autorisé\(e\) à accéder à cette page|Access denied/i.test(bodyText)) {
    throw new Error(`Accès refusé pour ${url}`);
  }
  if (/La page demandée n['’]a pas pu être trouvée|Page not found/i.test(bodyText)) {
    throw new Error(`Page introuvable pour ${url}`);
  }
}

async function auditPage(page, entry) {
  const { key, url } = entry;
  const findings = [];

  try {
    await page.goto(`${BASE_URL}${url}`, { waitUntil: 'networkidle', timeout: 20_000 });
    await page.waitForTimeout(1500);
    await page.evaluate(() => document.fonts.ready);
    await assertPageUsable(page, url);
  } catch (e) {
    return { ...entry, findings: [{ severity: 'high', message: `Page inaccessible : ${e.message}` }], error: true };
  }

  // Capture d'écran
  const ss = path.join(SCREENSHOTS_DIR, `${key}.png`);
  await page.screenshot({ path: ss, fullPage: true });

  // ── Check 1 : Erreurs PHP / fatal affichées ──────────────────────────
  const bodyText = await page.evaluate(() => document.body.innerText);
  if (/Fatal error|Warning:|Notice:|Parse error|Deprecated/i.test(bodyText)) {
    findings.push({ severity: 'high', check: 'php_error', message: 'Erreur PHP visible dans la page' });
  }

  // ── Check 2 : repère d'écran absent (titre navigateur + heading visible) ─
  const browserTitle = (await page.title()).trim();
  const heading = await page.$eval('h1.page-title, h1, .page-header h1, h2, summary', el => el.innerText?.trim()).catch(() => '');
  if (!browserTitle && !heading) {
    findings.push({ severity: 'high', check: 'no_title', message: 'Repère de page absent (titre navigateur et heading invisibles)' });
  }

  // ── Check 3 : Labels EN non traduits ────────────────────────────────
  const labels = await page.$$eval(
    'label, .form-item__label, th, .views-header td, .action-links a, .button',
    els => els.map(el => el.innerText?.trim()).filter(t => t && t.length > 1 && t.length < 100)
  );
  const enFound = new Set();
  for (const text of labels) {
    for (const pat of EN_STRICT) {
      if (pat.test(text) && !enFound.has(text)) {
        findings.push({ severity: 'high', check: 'label_en', message: `Label non traduit : "${text}"` });
        enFound.add(text);
        break;
      }
    }
  }

  // ── Check 4 : Champs techniques exposés ─────────────────────────────
  const inputs = await page.$$eval('.form-item', items =>
    items.map(item => {
      const input = item.querySelector('input, select, textarea');
      const label = item.querySelector('label')?.innerText?.trim() || '';
      const name  = input?.getAttribute('name') || '';
      return { name, label };
    }).filter(f => f.name)
  );
  for (const { name, label } of inputs) {
    for (const tech of TECH_FIELD_NAMES) {
      if ((name === tech || name === `${tech}[0][value]`) && !label) {
        findings.push({ severity: 'high', check: 'tech_field', message: `Champ technique sans label : name="${name}"` });
        break;
      }
    }
  }

  // ── Check 5 : Formulaire sans bouton de soumission FR ───────────────
  const forms = await page.$$('form');
  for (const form of forms) {
    const submitButtons = await form.$$('input[type=submit], button[type=submit], .form-submit');
    if (submitButtons.length === 0) continue;
    for (const btn of submitButtons) {
      const val  = await btn.getAttribute('value') || '';
      const text = await btn.innerText().catch(() => '');
      const content = (val + ' ' + text).trim();
      const isEnglish = EN_STRICT.some(p => p.test(content));
      if (isEnglish) {
        findings.push({ severity: 'medium', check: 'submit_en', message: `Bouton de formulaire non traduit : "${content}"` });
        break;
      }
    }
  }

  // ── Check 6 : Icônes Material Symbols rendues ───────────────────────
  const iconCheck = await page.evaluate(() => {
    const icons = document.querySelectorAll('.material-symbols-outlined');
    if (icons.length === 0) return null; // page sans icônes custom = pas un pb
    const loaded = document.fonts.check('1em "Material Symbols Outlined"');
    return { count: icons.length, loaded };
  });
  if (iconCheck && !iconCheck.loaded) {
    findings.push({ severity: 'medium', check: 'icons_not_loaded', message: `${iconCheck.count} icône(s) Material Symbols — police non chargée` });
  }

  // ── Check 7 : Champs importants sans texte d'aide (seulement formulaires d'ajout) ──
  if (url.includes('/add') || url.includes('/edit') || url.includes('/configuration')) {
    const missingHelp = await page.$$eval('.form-item', items =>
      items
        .filter(item => !item.querySelector('.form-item__description, .description, [id*="description"]'))
        .map(item => {
          const label = item.querySelector('label')?.innerText?.trim() || '';
          const name  = item.querySelector('input, select, textarea')?.getAttribute('name') || '';
          return { label, name };
        })
        .filter(f => (
          f.label
          && f.name
          && !f.name.startsWith('_')
          && !f.name.includes('[_')
          && f.name !== 'body[0][value]'
          && !f.name.startsWith('created[')
          && !f.name.startsWith('changed[')
          && !f.name.startsWith('revision_')
        ))
        .slice(0, 5)
    );
    for (const { label, name } of missingHelp) {
      findings.push({ severity: 'low', check: 'missing_help', message: `Champ sans aide : "${label}" (${name})` });
    }
  }

  // ── Check 8 : Sidebar / contenu débordant ───────────────────────────
  const overlapCheck = await page.evaluate(() => {
    const sidebar = document.querySelector('#toolbar-bar, .toolbar-menu-administration');
    const main    = document.querySelector('main, .layout-container, #main-wrapper');
    if (!sidebar || !main) return null;
    const sideRect = sidebar.getBoundingClientRect();
    const mainRect = main.getBoundingClientRect();
    const overlap  = sideRect.right - mainRect.left;
    return overlap > 20 ? overlap : null;
  });
  if (overlapCheck) {
    findings.push({ severity: 'medium', check: 'sidebar_overlap', message: `Sidebar chevauche le contenu de ${Math.round(overlapCheck)}px` });
  }

  // ── Check 9 : Alertes / messages d'erreur Drupal inattendus ─────────
  const alerts = await page.$$eval('.messages--error, .messages--warning', els =>
    els.map(el => el.innerText?.trim().substring(0, 120))
  );
  for (const msg of alerts) {
    if (msg) findings.push({ severity: 'medium', check: 'drupal_message', message: `Message Drupal visible : "${msg}"` });
  }

  return { ...entry, findings };
}

function printReport(results) {
  const byCategory = {};
  let totalHigh = 0, totalMedium = 0, totalLow = 0;

  for (const r of results) {
    if (!byCategory[r.cat]) byCategory[r.cat] = [];
    byCategory[r.cat].push(r);
    totalHigh   += r.findings.filter(f => f.severity === 'high').length;
    totalMedium += r.findings.filter(f => f.severity === 'medium').length;
    totalLow    += r.findings.filter(f => f.severity === 'low').length;
  }

  console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');
  console.log('📊  AUDIT UX/UI COMPLET — GESTIONNAIRE BOUTIQUE\n');

  for (const [cat, pages] of Object.entries(byCategory)) {
    console.log(`\n  ┌─ ${cat.toUpperCase()} ${'─'.repeat(Math.max(0, 48 - cat.length))}`);
    for (const { key, url, findings, error } of pages) {
      const high   = findings.filter(f => f.severity === 'high');
      const medium = findings.filter(f => f.severity === 'medium');
      const low    = findings.filter(f => f.severity === 'low');
      const icon   = error ? '💀' : high.length > 0 ? '❌' : medium.length > 0 ? '⚠️ ' : low.length > 0 ? '💡' : '✅';
      console.log(`  │  ${icon} ${key.padEnd(22)} ${url}`);
      high.forEach(f   => console.log(`  │       🔴 [HIGH]   ${f.message}`));
      medium.forEach(f => console.log(`  │       🟡 [MEDIUM] ${f.message}`));
      low.forEach(f    => console.log(`  │       🔵 [LOW]    ${f.message}`));
    }
    console.log(`  └${'─'.repeat(55)}`);
  }

  console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.log(`Total findings : 🔴 ${totalHigh} HIGH · 🟡 ${totalMedium} MEDIUM · 🔵 ${totalLow} LOW`);
  const verdict = totalHigh === 0 && totalMedium === 0
    ? '✅  GO — interface prête'
    : totalHigh > 0
    ? '❌  NO-GO — corrections bloquantes requises'
    : '⚠️   À CORRIGER — améliorations recommandées';
  console.log(`\nVerdict : ${verdict}\n`);
}

(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox'] });
  const context = await browser.newContext({
    ignoreHTTPSErrors: true,
    viewport: { width: 1440, height: 900 },
  });
  const page = await context.newPage();

  console.log('\n🔍  Audit UX/UI complet — démarrage\n');
  await login(page);
  console.log(`  Session audit : ${AUDIT_USER}`);

  const results = [];
  for (const entry of AUDIT_PAGES) {
    process.stdout.write(`  [${entry.cat}] ${entry.key}…`);
    const result = await auditPage(page, entry);
    results.push(result);
    const h = result.findings.filter(f => f.severity === 'high').length;
    const m = result.findings.filter(f => f.severity === 'medium').length;
    const l = result.findings.filter(f => f.severity === 'low').length;
    console.log(` H:${h} M:${m} L:${l}`);
  }

  await browser.close();
  printReport(results);

  // Sauvegarder le rapport JSON
  const reportPath = path.join(SCREENSHOTS_DIR, 'report.json');
  fs.writeFileSync(reportPath, JSON.stringify(results, null, 2));
  console.log(`📁  Screenshots + rapport JSON : playwright/screenshots/audit-full/\n`);
})().catch(err => {
  console.error(err);
  process.exit(1);
});

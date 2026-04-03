#!/usr/bin/env node
/**
 * audit-backoffice-gestionnaire-uli.js
 * Captures d'écran complètes du back-office gestionnaire pour l'audit UX.
 * Usage : node scripts/audit-backoffice-gestionnaire-uli.js <uli-url>
 *
 * Captures : viewport 1440×900 (desktop) + 768×1024 (tablet)
 * Nécessite : ddev drush user:login --name=odesign
 */

const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const uli = process.argv[2];
if (!uli) {
  console.error('Usage: node audit-backoffice-gestionnaire-uli.js <uli-url>');
  console.error('  ex: node scripts/audit-backoffice-gestionnaire-uli.js "$(cd /path/to/repo && ddev drush user:login --name=odesign)"');
  process.exit(1);
}

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'https://odesign-drupal.ddev.site';
const OUT_DIR = path.join(__dirname, '..', 'screenshots', 'audit-backoffice');
if (!fs.existsSync(OUT_DIR)) fs.mkdirSync(OUT_DIR, { recursive: true });

const PAGES = [
  { key: '01-dashboard',           path: '/admin/boutique',                         label: 'Dashboard' },
  { key: '02-config',              path: '/admin/boutique/configuration',            label: 'Configuration boutique' },
  { key: '03-config-scrolled',     path: '/admin/boutique/configuration',            label: 'Configuration boutique (scrolled)', scrollY: 600 },
  { key: '04-marketing-guide',     path: '/admin/boutique/guide-marketing',          label: 'Guide marketing' },
  { key: '05-orders-all',          path: '/admin/commerce/orders',                  label: 'Toutes les commandes' },
  { key: '06-orders-actionable',   path: '/admin/boutique/commandes-a-traiter',      label: 'Commandes à traiter (redirect)' },
  { key: '07-products',            path: '/admin/commerce/products',                label: 'Liste produits' },
  { key: '08-product-create',      path: '/product/add/default',                    label: 'Créer produit' },
  { key: '09-content',             path: '/admin/content',                          label: 'Liste articles (tous types)' },
  { key: '10-content-articles',    path: '/admin/content?type=article',             label: 'Liste articles (filtrés)' },
  { key: '11-article-create',      path: '/node/add/article',                       label: 'Créer article' },
  { key: '12-taxonomy-life',       path: '/admin/structure/taxonomy/manage/life_occasion/overview', label: 'Univers (taxonomy overview)' },
  { key: '13-promotions',          path: '/admin/commerce/promotions',              label: 'Promotions' },
  { key: '14-invoices',            path: '/admin/commerce/invoices',                label: 'Factures' },
  { key: '15-media',               path: '/admin/content/media',                   label: 'Médias' },
];

async function bypassSandbox(page) {
  const heading = page.getByRole('heading', { name: /sandbox environment/i });
  if (await heading.count()) {
    const link = page.getByRole('link', { name: /continue/i }).first();
    if (await link.count()) { await link.click(); await page.waitForLoadState('networkidle'); }
  }
}

async function loginViaUli(page) {
  await page.goto(uli, { waitUntil: 'networkidle', timeout: 30000 });
  await bypassSandbox(page);
  // Si Drupal redirige vers un formulaire de changement de mot de passe, le sauter
  const pwReset = await page.locator('input[name="pass[pass1]"]').count();
  if (pwReset) {
    await page.goto(`${BASE_URL}/admin/boutique`, { waitUntil: 'networkidle' });
  }
  console.log('🔑 ULI consommé');
}

async function capture(page, key, pagePath, label, scrollY = 0) {
  try {
    await page.goto(`${BASE_URL}${pagePath}`, { waitUntil: 'networkidle', timeout: 20000 });
    await bypassSandbox(page);
    await page.waitForTimeout(1500);
    await page.evaluate(() => document.fonts.ready);
    if (scrollY) await page.evaluate((y) => window.scrollTo(0, y), scrollY);
    else await page.evaluate(() => window.scrollTo(0, 0));

    const filepath = path.join(OUT_DIR, `${key}.png`);
    await page.screenshot({ path: filepath, fullPage: false });
    console.log(`  ✅  ${key} — ${label}`);
    return { key, label, path: pagePath, file: filepath, ok: true };
  } catch (err) {
    console.warn(`  ⚠️  ${key} — ${label} : ${err.message}`);
    return { key, label, path: pagePath, ok: false, error: err.message };
  }
}

async function captureOrderDetail(page) {
  // Récupère l'URL de la première commande dans la liste
  try {
    await page.goto(`${BASE_URL}/admin/commerce/orders`, { waitUntil: 'networkidle', timeout: 15000 });
    const editLink = await page.locator('table tbody tr:first-child .dropbutton a, table tbody tr:first-child a[href*="/edit"]').first();
    if (await editLink.count()) {
      const href = await editLink.getAttribute('href');
      if (href) {
        const orderPath = new URL(href, BASE_URL).pathname;
        await capture(page, '16-order-detail', orderPath, 'Détail commande');
        // Scroll sur la partie workflow
        await page.goto(`${BASE_URL}${orderPath}`, { waitUntil: 'networkidle', timeout: 15000 });
        await page.evaluate(() => window.scrollTo(0, 400));
        await page.waitForTimeout(500);
        const filepath = path.join(OUT_DIR, '16b-order-detail-workflow.png');
        await page.screenshot({ path: filepath, fullPage: false });
        console.log(`  ✅  16b-order-detail-workflow`);
      }
    }
  } catch (e) {
    console.warn(`  ⚠️  16-order-detail : ${e.message}`);
  }
}

async function captureLifeOccasionTermEdit(page) {
  // Trouve et ouvre le premier terme life_occasion
  try {
    await page.goto(`${BASE_URL}/admin/structure/taxonomy/manage/life_occasion/overview`, { waitUntil: 'networkidle', timeout: 15000 });
    const editLink = await page.locator('table tbody tr:first-child a[href*="/edit"]').first();
    if (await editLink.count()) {
      const href = await editLink.getAttribute('href');
      if (href) {
        const termPath = new URL(href, BASE_URL).pathname;
        await capture(page, '17-life-occasion-term-edit', termPath, 'Édition terme life_occasion');
      }
    }
  } catch (e) {
    console.warn(`  ⚠️  17-life-occasion-term-edit : ${e.message}`);
  }
}

async function captureFirstProductEdit(page) {
  try {
    await page.goto(`${BASE_URL}/admin/commerce/products`, { waitUntil: 'networkidle', timeout: 15000 });
    const editLink = await page.locator('table tbody tr:first-child a[href*="/edit"]').first();
    if (await editLink.count()) {
      const href = await editLink.getAttribute('href');
      if (href) {
        const prodPath = new URL(href, BASE_URL).pathname;
        await capture(page, '18-product-edit', prodPath, 'Édition produit');
      }
    }
  } catch (e) {
    console.warn(`  ⚠️  18-product-edit : ${e.message}`);
  }
}

// ─── Tablet viewport ──────────────────────────────────────────────────────────
async function captureTablet(context, pagePath, key, label) {
  const tabletPage = await context.newPage();
  await tabletPage.setViewportSize({ width: 768, height: 1024 });
  try {
    await tabletPage.goto(`${BASE_URL}${pagePath}`, { waitUntil: 'networkidle', timeout: 15000 });
    await bypassSandbox(tabletPage);
    await tabletPage.waitForTimeout(1000);
    await tabletPage.evaluate(() => document.fonts.ready);
    const filepath = path.join(OUT_DIR, `${key}-tablet.png`);
    await tabletPage.screenshot({ path: filepath, fullPage: false });
    console.log(`  ✅  ${key}-tablet — ${label} (768px)`);
  } catch (e) {
    console.warn(`  ⚠️  ${key}-tablet : ${e.message}`);
  }
  await tabletPage.close();
}

(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox', '--ignore-certificate-errors'] });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true });
  const page = await context.newPage();

  console.log(`\n🎬  Audit UX back-office gestionnaire — ${BASE_URL}\n`);
  await loginViaUli(page);

  // ── Pages statiques ──────────────────────────────────────────────────────
  for (const pg of PAGES) {
    await capture(page, pg.key, pg.path, pg.label, pg.scrollY || 0);
  }

  // ── Pages dynamiques (URLs résolues depuis les listes) ──────────────────
  await captureOrderDetail(page);
  await captureLifeOccasionTermEdit(page);
  await captureFirstProductEdit(page);

  // ── Tablet : pages prioritaires ──────────────────────────────────────────
  console.log('\n📱  Captures tablet (768px)...\n');
  // Nouvelle session pour le tablet (ULI déjà consommé — on réutilise le contexte cookies)
  for (const pg of [
    { key: '01-dashboard', path: '/admin/boutique', label: 'Dashboard' },
    { key: '05-orders-all', path: '/admin/commerce/orders', label: 'Commandes' },
    { key: '02-config', path: '/admin/boutique/configuration', label: 'Configuration' },
  ]) {
    await captureTablet(context, pg.path, pg.key, pg.label);
  }

  await browser.close();
  console.log(`\n📁  Captures dans playwright/screenshots/audit-backoffice/\n`);
  console.log(`    Total : ${PAGES.length + 5} captures attendues`);
})().catch(err => {
  console.error(err);
  process.exit(1);
});

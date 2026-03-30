#!/usr/bin/env node
/**
 * screenshot-admin.js
 * Prend des captures d'écran des pages clés du back-office Drupal.
 *
 * Usage :
 *   node scripts/screenshot-admin.js           → toutes les pages
 *   node scripts/screenshot-admin.js dashboard → une page spécifique (clé ou chemin)
 *   node scripts/screenshot-admin.js /admin/commerce/orders
 */

const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE_URL  = process.env.PLAYWRIGHT_BASE_URL || 'http://web';
const AUDIT_USER = process.env.DRUPAL_AUDIT_USER
  || process.env.DRUPAL_CLIENT_USER
  || process.env.DRUPAL_ADMIN_USER
  || 'client_ateliero';
const AUDIT_PASSWORD = process.env.DRUPAL_AUDIT_PASS
  || process.env.DRUPAL_CLIENT_PASS
  || process.env.DRUPAL_ADMIN_PASS
  || 'admin';
const TARGET    = process.argv[2] || 'all';

const OUT_DIR = path.join(__dirname, '..', 'screenshots');
if (!fs.existsSync(OUT_DIR)) fs.mkdirSync(OUT_DIR, { recursive: true });

/** Pages à capturer pour un audit complet du back-office gestionnaire */
const PAGES = {
  'dashboard':       '/admin/boutique',
  'config':          '/admin/boutique/configuration',
  'products':        '/admin/commerce/products',
  'product-form':    '/product/add/default',
  'orders':          '/admin/commerce/orders',
  'order-detail':    null, // résolu dynamiquement depuis la liste
  'invoices':        '/admin/commerce/invoices',
  'promotions':      '/admin/commerce/promotions',
  'media':           '/admin/content/media',
};

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
  console.log(`🔑 Connecté en tant que ${AUDIT_USER}`);
}

async function ensurePageUsable(page, url) {
  const bodyText = await page.locator('body').innerText().catch(() => '');
  if (/Vous n['’]êtes pas autorisé\(e\) à accéder à cette page|Access denied/i.test(bodyText)) {
    throw new Error(`Accès refusé pour ${url}`);
  }
  if (/La page demandée n['’]a pas pu être trouvée|Page not found/i.test(bodyText)) {
    throw new Error(`Page introuvable pour ${url}`);
  }
}

async function capture(page, name, url) {
  try {
    await page.goto(`${BASE_URL}${url}`, { waitUntil: 'networkidle', timeout: 15_000 });
    await ensurePageUsable(page, url);
    // Attendre que Gin ait fini de charger sa sidebar et que les fonts (Material Symbols) soient prêtes
    await page.waitForTimeout(2000);
    await page.evaluate(() => document.fonts.ready);
    await page.evaluate(() => window.scrollTo(0, 0));
    const filepath = path.join(OUT_DIR, `${name}.png`);
    const content = page.locator('main, .dialog-off-canvas-main-canvas main, .layout-content').first();
    if (await content.count()) {
      await content.screenshot({ path: filepath });
    }
    else {
      await page.screenshot({ path: filepath, fullPage: true });
    }
    console.log(`  ✅  ${name}`);
    return filepath;
  } catch (err) {
    console.warn(`  ⚠️  ${name} : ${err.message}`);
    return null;
  }
}

async function resolveFirstOrderUrl(page) {
  await page.goto(`${BASE_URL}/admin/commerce/orders`, { waitUntil: 'networkidle' });
  const link = await page.$('table tbody tr:first-child td:last-child a');
  if (!link) return null;
  return new URL(await link.getAttribute('href'), BASE_URL).pathname;
}

(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox'] });
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    ignoreHTTPSErrors: true,
  });
  const page = await context.newPage();

  console.log(`\n🎬  Démarrage des captures — ${BASE_URL}\n`);
  await login(page);

  if (TARGET === 'all') {
    // Résoudre dynamiquement l'URL d'une commande
    const orderUrl = await resolveFirstOrderUrl(page);
    if (orderUrl) PAGES['05-order-detail'] = orderUrl;
    else delete PAGES['05-order-detail'];

    for (const [name, url] of Object.entries(PAGES)) {
      if (url) await capture(page, name, url);
    }
  } else if (TARGET in PAGES) {
    // Clé nommée : ddev playwright-screenshot dashboard
    const url = PAGES[TARGET];
    if (url) await capture(page, TARGET, url);
    else console.error(`La page "${TARGET}" n'a pas d'URL résolue statiquement.`);
  } else if (TARGET.startsWith('/')) {
    // Chemin libre : ddev playwright-screenshot /admin/commerce/products
    const name = TARGET.replace(/\//g, '_').replace(/^_/, '');
    await capture(page, name, TARGET);
  } else {
    console.error(`Clé inconnue "${TARGET}". Clés disponibles : ${Object.keys(PAGES).join(', ')}\nOu passez un chemin commençant par / ex: /admin/boutique`);
    process.exit(1);
  }

  await browser.close();
  console.log(`\n📁  Captures dans playwright/screenshots/\n`);
})().catch(err => {
  console.error(err);
  process.exit(1);
});

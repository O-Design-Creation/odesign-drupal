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
const ADMIN     = process.env.DRUPAL_ADMIN_USER   || 'admin';
const PASSWORD  = process.env.DRUPAL_ADMIN_PASS   || 'admin';
const TARGET    = process.argv[2] || 'all';

const OUT_DIR = path.join(__dirname, '..', 'screenshots');
if (!fs.existsSync(OUT_DIR)) fs.mkdirSync(OUT_DIR, { recursive: true });

/** Pages à capturer pour un audit complet du back-office gestionnaire */
const PAGES = {
  'dashboard':       '/admin/boutique',
  'products':        '/admin/commerce/products',
  'product-form':    '/admin/commerce/products/add/default',
  'orders':          '/admin/commerce/orders',
  'order-detail':    null, // résolu dynamiquement depuis la liste
  'customers':       '/admin/people',
  'media':           '/admin/content/media',
  'blocks':          '/admin/structure/block',
  'gin-settings':    '/admin/appearance/settings/gin',
  'permissions':     '/admin/people/permissions',
};

async function login(page) {
  await page.goto(`${BASE_URL}/user/login`);
  await page.waitForSelector('#edit-name');
  await page.fill('#edit-name', ADMIN);
  await page.fill('#edit-pass', PASSWORD);
  await page.click('#edit-submit');
  // Attendre la redirection post-login
  await page.waitForURL(/\/(admin|user\/\d+)/, { timeout: 10_000 }).catch(() => {});
  console.log(`🔑 Connecté en tant que ${ADMIN}`);
}

async function capture(page, name, url) {
  try {
    await page.goto(`${BASE_URL}${url}`, { waitUntil: 'networkidle', timeout: 15_000 });
    // Attendre que Gin ait fini de charger sa sidebar et que les fonts (Material Symbols) soient prêtes
    await page.waitForTimeout(2000);
    await page.evaluate(() => document.fonts.ready);
    const filepath = path.join(OUT_DIR, `${name}.png`);
    await page.screenshot({ path: filepath, fullPage: true });
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

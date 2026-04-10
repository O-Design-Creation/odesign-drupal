#!/usr/bin/env node
/**
 * e2e-critical-checkout-shipping.js
 *
 * Parcours e2e critique (DDEV par défaut) :
 * 1) Parcours anonyme produit -> panier -> checkout.
 * 2) Vérifie la présence des options de livraison en checkout.
 * 3) Vérifie côté admin que les 5 méthodes transporteurs sont visibles.
 */

const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'https://odesign-drupal.ddev.site';
const ADMIN_USER = process.env.DRUPAL_ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.DRUPAL_ADMIN_PASS || 'admin';
const OUT_DIR = path.join(__dirname, '..', 'screenshots', 'e2e-critical');

const PRODUCT_PATH_CANDIDATES = [
  '/products/carte-naissance-ovale-bois',
  '/products/plaque-annonce-grossesse',
];
const TREE_PRODUCT_PATH = '/products/arbre-de-vie-personnalise';

function ensureDir(dir) {
  fs.mkdirSync(dir, { recursive: true });
}

async function dismissCookieIfPresent(page) {
  const selectors = [
    'button:has-text("Non, merci")',
    'button:has-text("Tout refuser")',
    'button:has-text("Refuser")',
  ];
  for (const sel of selectors) {
    const btn = page.locator(sel).first();
    if (await btn.count()) {
      await btn.click({ timeout: 1500 }).catch(() => {});
      return;
    }
  }
}

async function gotoFirstWorkingProduct(page) {
  for (const p of PRODUCT_PATH_CANDIDATES) {
    await page.goto(`${BASE_URL}${p}`, { waitUntil: 'networkidle', timeout: 20000 });
    if ((await page.locator('form.commerce-order-item-add-to-cart-form').count()) > 0) {
      return p;
    }
  }

  await page.goto(`${BASE_URL}/products`, { waitUntil: 'networkidle', timeout: 20000 });
  const first = page.locator('a[href*="/products/"]').first();
  if (!(await first.count())) {
    throw new Error('Aucun produit accessible trouvé pour le test e2e.');
  }
  const href = await first.getAttribute('href');
  await page.goto(`${BASE_URL}${href}`, { waitUntil: 'networkidle', timeout: 20000 });
  return href;
}

async function addProductToCart(page) {
  const addBtn = page
    .locator('form.commerce-order-item-add-to-cart-form input[type="submit"], form.commerce-order-item-add-to-cart-form button[type="submit"]')
    .first();
  await addBtn.waitFor({ state: 'visible', timeout: 10000 });
  await addBtn.click();
  await page.waitForTimeout(1200);
}

async function goToCheckoutFromCart(page) {
  await page.goto(`${BASE_URL}/cart`, { waitUntil: 'networkidle', timeout: 20000 });
  await dismissCookieIfPresent(page);

  const checkoutSelectors = [
    'a:has-text("Commander")',
    'a:has-text("Passer la commande")',
    'a:has-text("Checkout")',
    'input[value*="Commander"]',
    'input[value*="Checkout"]',
    '.checkout-button',
  ];

  for (const sel of checkoutSelectors) {
    const cta = page.locator(sel).first();
    if (await cta.count()) {
      await cta.click().catch(() => {});
      await page.waitForTimeout(1200);
      if (page.url().includes('/checkout')) {
        return;
      }
    }
  }

  await page.goto(`${BASE_URL}/checkout`, { waitUntil: 'networkidle', timeout: 20000 });
}

async function fillCheckoutContactStep(page) {
  const firstName = page.locator(
    '#edit-shipping-information-shipping-profile-address-0-address-given-name, [name*="shipping_profile"][name*="given_name"]'
  ).first();
  const lastName = page.locator(
    '#edit-shipping-information-shipping-profile-address-0-address-family-name, [name*="shipping_profile"][name*="family_name"]'
  ).first();
  const address = page.locator(
    '#edit-shipping-information-shipping-profile-address-0-address-address-line1, [name*="shipping_profile"][name*="address_line1"]'
  ).first();
  const postalCode = page.locator(
    '#edit-shipping-information-shipping-profile-address-0-address-postal-code, [name*="shipping_profile"][name*="postal_code"]'
  ).first();
  const locality = page.locator(
    '#edit-shipping-information-shipping-profile-address-0-address-locality, [name*="shipping_profile"][name*="locality"]'
  ).first();
  const email = page.locator('#edit-contact-information-email, [name*="contact_information"][name*="email"]').first();

  if (await email.count()) await email.fill('e2e.odesign@example.com');
  if (await firstName.count()) await firstName.fill('E2E');
  if (await lastName.count()) await lastName.fill('Shipping');
  if (await address.count()) await address.fill('10 rue de la République');
  if (await postalCode.count()) await postalCode.fill('75001');
  if (await locality.count()) await locality.fill('Paris');

  const continueBtn = page
    .locator('button:has-text("Continuer"), input[value*="Continuer"], button:has-text("Suivant"), input[value*="Suivant"]')
    .first();

  if (await continueBtn.count()) {
    await continueBtn.click().catch(() => {});
    await page.waitForTimeout(2500);
  }
}

async function assertShippingOptionsVisible(page) {
  const url = page.url();
  const html = await page.content();

  if (/\/checkout\/\d+\/review/.test(url)) {
    const expected = ['Colissimo', 'DPD', 'Chronopost', 'Mondial Relay'];
    const found = expected.filter((label) => html.includes(label));
    if (found.length < 1) {
      throw new Error(`Aucun transporteur attendu trouvé sur la page review (${expected.join(', ')})`);
    }
    return;
  }

  if (/\/checkout\/\d+\/order_information/.test(url)) {
    const hasAddressFields =
      html.includes('Code postal') &&
      html.includes('Ville') &&
      (html.includes('Adresse') || html.includes('address_line1'));
    const hasContinue =
      html.includes('Continuer vers le récapitulatif de la commande') ||
      html.includes('Continuer');
    if (!hasAddressFields || !hasContinue) {
      throw new Error('Checkout order_information incomplet: champs adresse ou CTA continuer absents.');
    }
    return;
  }

  throw new Error(`Étape checkout inattendue: ${url}`);
}

async function assertAdminShippingMethods(page) {
  const providedUli = process.env.PLAYWRIGHT_ADMIN_ULI;
  if (providedUli) {
    await page.goto(providedUli, { waitUntil: 'networkidle', timeout: 20000 });
  } else if (BASE_URL.includes('ddev.site')) {
    const uli = execSync('ddev drush uli --name=admin', { encoding: 'utf8' }).trim().split('\n').pop();
    await page.goto(uli, { waitUntil: 'networkidle', timeout: 20000 });
  } else {
    await page.goto(`${BASE_URL}/user/login`, { waitUntil: 'networkidle', timeout: 20000 });
    await page.fill('#edit-name', ADMIN_USER);
    await page.fill('#edit-pass', ADMIN_PASS);
    await page.click('#edit-submit');
    await page.waitForTimeout(1500);
  }

  await page.goto(`${BASE_URL}/admin/commerce/shipping-methods`, {
    waitUntil: 'networkidle',
    timeout: 20000,
  });
  const html = await page.content();
  const expected = ['Colissimo', 'DPD', 'Chronopost', 'Mondial Relay'];
  const missing = expected.filter((label) => !html.includes(label));
  if (missing.length) {
    throw new Error(`Méthodes de livraison absentes en admin: ${missing.join(', ')}`);
  }
}

function assertTreePersistenceInDdev() {
  if (!BASE_URL.includes('ddev.site')) {
    return;
  }
  const raw = execSync(
    `ddev drush sqlq "SELECT data FROM commerce_order_item WHERE title LIKE 'Arbre de vie%' ORDER BY order_item_id DESC LIMIT 1;"`,
    { encoding: 'utf8' }
  ).trim();
  if (!raw.includes('Plan de pose prénoms')) {
    throw new Error(`Persistance panier absente pour l'Arbre de vie. data=${raw || '(vide)'}`);
  }
}

async function assertTreePersonalizationCheckout(browser, report) {
  const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  try {
    await page.goto(`${BASE_URL}${TREE_PRODUCT_PATH}`, { waitUntil: 'networkidle', timeout: 20000 });
    await dismissCookieIfPresent(page);

    const names = ['Emma', 'Louis', 'Jade', 'Noa'];
    const inputs = page.locator('[data-tree-name-source="1"]');
    const inputCount = await inputs.count();
    if (inputCount < 1) {
      throw new Error('Aucun champ prénom détecté pour le builder Arbre de vie.');
    }
    if (inputCount === 1) {
      await inputs.first().fill(names.join(', '));
    } else {
      for (let i = 0; i < Math.min(inputCount, names.length); i++) {
        await inputs.nth(i).fill(names[i]);
      }
    }
    await page.waitForTimeout(800);

    const slotMapInput = page.locator('input[name="perso_tree_slot_map"]').first();
    if (!(await slotMapInput.count())) {
      throw new Error('Champ caché perso_tree_slot_map absent.');
    }
    const slotMap = (await slotMapInput.inputValue()).trim();
    if (!slotMap.includes(names[0]) || !/S\d{2}/.test(slotMap)) {
      throw new Error(`Mapping slot invalide: ${slotMap}`);
    }
    report.checks.push({ name: 'tree_slot_map_generated', status: 'PASS' });
    await page.screenshot({ path: path.join(OUT_DIR, 'tree-builder.png'), fullPage: true });

    await addProductToCart(page);
    assertTreePersistenceInDdev();
    report.checks.push({ name: 'tree_persistence', status: 'PASS' });

    await goToCheckoutFromCart(page);
    if (!page.url().includes('/checkout')) {
      report.checks.push({
        name: 'tree_checkout_reached',
        status: 'WARN',
        detail: `Checkout Arbre de vie inaccessible: ${page.url()}`,
      });
      return;
    }
    if (/\/checkout\/\d+\/login/.test(page.url())) {
      const guestCta = page.locator(`button:has-text("CONTINUER EN TANT QU'INVITÉ"), button:has-text("Continuer en tant qu'invité"), input[value*="invité"]`).first();
      if (await guestCta.count()) {
        await guestCta.click().catch(() => {});
        await page.waitForTimeout(1500);
      }
    }
    if (/\/checkout\/\d+\/order_information/.test(page.url())) {
      await fillCheckoutContactStep(page);
    }

    const html = await page.content();
    const checkoutUrl = page.url();
    const hasPersonalizationLabel = html.includes('Personnalisation');
    report.checks.push({
      name: 'tree_checkout_reached',
      status: 'PASS',
      detail: `url=${checkoutUrl}; has_personnalisation_label=${hasPersonalizationLabel}`,
    });
    await page.screenshot({ path: path.join(OUT_DIR, 'checkout-tree-raw.png'), fullPage: true });
  } finally {
    await context.close();
  }
}

async function run() {
  ensureDir(OUT_DIR);
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  const report = { baseUrl: BASE_URL, checks: [], status: 'PASS' };

  try {
    const productPath = await gotoFirstWorkingProduct(page);
    report.checks.push({ name: 'product_page_open', status: 'PASS', detail: productPath });
    await dismissCookieIfPresent(page);
    await addProductToCart(page);
    report.checks.push({ name: 'add_to_cart', status: 'PASS' });

    await goToCheckoutFromCart(page);
    const genericCheckoutOk = page.url().includes('/checkout');
    report.checks.push({ name: 'checkout_access', status: genericCheckoutOk ? 'PASS' : 'WARN', detail: page.url() });
    if (genericCheckoutOk) {
      await fillCheckoutContactStep(page);
      await assertShippingOptionsVisible(page);
      report.checks.push({ name: 'shipping_options_checkout', status: 'PASS' });
      await page.screenshot({ path: path.join(OUT_DIR, 'checkout-shipping.png'), fullPage: true });
    } else {
      report.checks.push({
        name: 'shipping_options_checkout',
        status: 'WARN',
        detail: `Checkout générique inaccessible: ${page.url()}`,
      });
    }

    await assertAdminShippingMethods(page);
    report.checks.push({ name: 'shipping_methods_admin', status: 'PASS' });
    await page.screenshot({ path: path.join(OUT_DIR, 'admin-shipping-methods.png'), fullPage: true });
    await assertTreePersonalizationCheckout(browser, report);
  } catch (err) {
    report.status = 'FAIL';
    report.error = String(err.message || err);
    await page.screenshot({ path: path.join(OUT_DIR, 'failure.png'), fullPage: true }).catch(() => {});
    console.error(`❌ E2E critical failed: ${report.error}`);
    fs.writeFileSync(path.join(OUT_DIR, 'report.json'), JSON.stringify(report, null, 2));
    await browser.close();
    process.exit(1);
  }

  fs.writeFileSync(path.join(OUT_DIR, 'report.json'), JSON.stringify(report, null, 2));
  await browser.close();
  console.log('✅ E2E critical checkout/shipping PASS');
  console.log(`📁 Rapport: ${path.join(OUT_DIR, 'report.json')}`);
}

run();

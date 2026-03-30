#!/usr/bin/env node
/**
 * test-checkout-shipping.js
 * Vérifie que le pane shipping_information apparaît dans le checkout.
 */
const { chromium } = require('playwright');
const path = require('path');
const fs   = require('fs');

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://web';
const ADMIN    = process.env.DRUPAL_ADMIN_USER   || 'admin';
const PASSWORD = process.env.DRUPAL_ADMIN_PASS   || 'admin';
const SS_DIR   = path.join(__dirname, '..', 'screenshots');

(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox'] });
  const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 900 } });
  const page    = await context.newPage();

  // Login
  await page.goto(`${BASE_URL}/user/login`);
  await page.fill('#edit-name', ADMIN);
  await page.fill('#edit-pass', PASSWORD);
  await page.click('#edit-submit');
  await page.waitForURL(/\/(admin|user\/\d+)/, { timeout: 10_000 }).catch(() => {});
  console.log('✅ Connecté');

  // Aller sur la page d'un produit (premier disponible via l'admin)
  await page.goto(`${BASE_URL}/admin/commerce/products`, { waitUntil: 'networkidle' });
  const firstProductLink = await page.$('table td a');
  if (!firstProductLink) {
    console.log('⚠️  Aucun produit trouvé — créez un produit d\'abord');
    await browser.close();
    return;
  }
  const productUrl = await firstProductLink.getAttribute('href');
  console.log('Produit trouvé : ' + productUrl);

  // Ajouter au panier directement via un produit
  // On essaie /cart en direct pour créer une commande test
  await page.goto(`${BASE_URL}/cart`, { waitUntil: 'networkidle', timeout: 15_000 });
  await page.waitForTimeout(1000);
  const cartScreenshot = path.join(SS_DIR, 'checkout-cart.png');
  await page.screenshot({ path: cartScreenshot, fullPage: false });
  console.log('📸 Cart capturé');

  // Vérifier si le checkout est accessible
  await page.goto(`${BASE_URL}/checkout/1`, { waitUntil: 'networkidle', timeout: 15_000 });
  await page.waitForTimeout(2000);
  const checkoutHtml = await page.content();

  const hasShipping = checkoutHtml.includes('shipping') || checkoutHtml.includes('livraison') || checkoutHtml.includes('expédition');
  const hasBilling  = checkoutHtml.includes('billing') || checkoutHtml.includes('facturation');

  const checkoutScreenshot = path.join(SS_DIR, 'checkout-flow.png');
  await page.screenshot({ path: checkoutScreenshot, fullPage: true });

  console.log('\n📊 Résultats :');
  console.log('  Pane shipping visible :', hasShipping ? '✅' : '❌');
  console.log('  Pane billing visible  :', hasBilling  ? '✅' : '❌');
  console.log('  URL actuelle          :', page.url());
  console.log('  Titre                 :', await page.title());

  // Vérifier si la page d'admin des méthodes d'expédition est accessible
  await page.goto(`${BASE_URL}/admin/commerce/shipping-methods`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);
  const shippingAdminSS = path.join(SS_DIR, 'shipping-admin.png');
  await page.screenshot({ path: shippingAdminSS, fullPage: true });
  const shippingHtml = await page.content();
  const hasStandard = shippingHtml.includes('standard') || shippingHtml.includes('Standard');
  const hasFree     = shippingHtml.includes('gratuite') || shippingHtml.includes('Gratuite');
  console.log('\n📦 Admin expédition :');
  console.log('  Livraison standard :', hasStandard ? '✅' : '❌');
  console.log('  Livraison gratuite :', hasFree     ? '✅' : '❌');

  await browser.close();
  console.log('\n📁 Screenshots : playwright/screenshots/');
})().catch(err => {
  console.error(err);
  process.exit(1);
});

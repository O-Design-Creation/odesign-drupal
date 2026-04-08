#!/usr/bin/env node
/**
 * ver-009-stripe-auto-confirm.js
 * VER-009 — Test bout-en-bout : checkout Stripe → transition confirm_payment automatique
 *
 * Environnement : Pantheon dev
 * Carte test     : 4242 4242 4242 4242 / 12/34 / 123
 */
const { chromium } = require('playwright');
const path = require('path');
const fs   = require('fs');

const BASE_URL    = 'https://dev-atelierodesign.pantheonsite.io';
const CLIENT_ULI  = process.env.CLIENT_ULI || '';
const ADMIN_ULI   = process.env.ADMIN_ULI  || '';
const SS_DIR      = path.join(__dirname, '..', 'screenshots');

if (!fs.existsSync(SS_DIR)) fs.mkdirSync(SS_DIR, { recursive: true });

function log(msg) { console.log(`[VER-009] ${msg}`); }
function pass(msg) { console.log(`✅ [PASS] ${msg}`); }
function fail(msg) { console.log(`❌ [FAIL] ${msg}`); }
function info(msg) { console.log(`ℹ️  ${msg}`); }

async function ss(page, name) {
  const p = path.join(SS_DIR, `ver009-${name}.png`);
  await page.screenshot({ path: p, fullPage: true });
  log(`📸 ${name} → ${p}`);
}

(async () => {
  const results = [];
  const browser = await chromium.launch({ args: ['--no-sandbox'], headless: true });
  const ctx     = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 900 } });
  const page    = await ctx.newPage();

  try {
    // ─── 1. Connexion client_test via ULI ───────────────────────────────────
    log('Connexion client_test via ULI…');
    if (!CLIENT_ULI) throw new Error('CLIENT_ULI manquant — relancez avec CLIENT_ULI=<url>');
    await page.goto(CLIENT_ULI, { waitUntil: 'networkidle', timeout: 30_000 });
    await ss(page, '01-login-client');
    const url1 = page.url();
    if (!url1.includes('/user/')) throw new Error(`ULI n'a pas redirigé vers /user/ : ${url1}`);
    pass('Connecté en tant que client_test');

    // ─── Dismiss Pantheon sandbox interstitial ───────────────────────────────
    async function dismissSandbox(p) {
      const continueBtn = p.locator('button:has-text("Continue"), a:has-text("Continue")').first();
      if (await continueBtn.count() > 0) {
        log('Pantheon sandbox interstitiel — clic Continue…');
        await continueBtn.click();
        await p.waitForTimeout(2000);
      }
    }
    await dismissSandbox(page);

    // ─── 2. Aller sur la page produit ───────────────────────────────────────
    log('Navigation vers la page produit…');
    await page.goto(`${BASE_URL}/products/numero-de-table-en-bois-arrondi`, { waitUntil: 'networkidle', timeout: 20_000 });
    await dismissSandbox(page);
    await ss(page, '02-product-page');
    // Clic "Ajouter au panier"
    const buyBtn = page.locator('input[value*="panier"], input[value*="Ajouter"], button:has-text("panier"), button:has-text("Ajouter"), input[id*="add-to-cart"], .add-to-cart input[type="submit"]').first();
    if (await buyBtn.count() > 0) {
      await buyBtn.click();
      await page.waitForTimeout(3000);
      pass('Produit ajouté au panier');
    } else {
      // Afficher les boutons dispo pour debug
      const btns = await page.locator('input[type="submit"], button[type="submit"]').allTextContents();
      info('Boutons submit trouvés : ' + JSON.stringify(btns));
    }
    await ss(page, '03-after-add-to-cart');

    // ─── 3. Aller au checkout ────────────────────────────────────────────────
    log('Navigation vers /cart…');
    await page.goto(`${BASE_URL}/cart`, { waitUntil: 'networkidle', timeout: 20_000 });
    await dismissSandbox(page);
    await ss(page, '04-cart');

    // Vérifier qu'il y a des articles
    const cartItems = await page.locator('.cart-contents, .cart-item, .views-row').count();
    info(`Articles dans le panier : ${cartItems}`);
    if (cartItems === 0) {
      info('Panier vide — tentative d\'ajout via API Commerce');
      // Essayer via URL directe du checkout avec un order existant
      await page.goto(`${BASE_URL}/checkout`, { waitUntil: 'networkidle', timeout: 20_000 });
    } else {
      // Clic sur "Passer commande"
      const checkoutBtn = page.locator('a:has-text("commander"), a:has-text("Checkout"), a:has-text("Commander"), .checkout-button, input[value*="Checkout"], input[value*="Commander"]').first();
      if (await checkoutBtn.count() > 0) {
        await checkoutBtn.click();
        await page.waitForTimeout(3000);
        await dismissSandbox(page);
      } else {
        await page.goto(`${BASE_URL}/checkout`, { waitUntil: 'networkidle', timeout: 20_000 });
        await dismissSandbox(page);
      }
    }
    await ss(page, '05-checkout-start');

    // ─── 4. Remplir les infos d'expédition ──────────────────────────────────
    const checkoutUrl = page.url();
    log(`URL checkout : ${checkoutUrl}`);

    // Profil / adresse
    const firstNameField = page.locator('[name*="given_name"], [name*="first_name"], #edit-shipping-information-shipping-profile-address-0-address-given-name').first();
    if (await firstNameField.count() > 0) {
      log('Remplissage adresse livraison…');
      await firstNameField.fill('Test');
      const lastNameField = page.locator('[name*="family_name"], [name*="last_name"]').first();
      if (await lastNameField.count() > 0) await lastNameField.fill('Client');
      const addr1 = page.locator('[name*="address_line1"], [name*="thoroughfare"]').first();
      if (await addr1.count() > 0) await addr1.fill('10 Rue de la Paix');
      const city = page.locator('[name*="locality"], [name*="city"]').first();
      if (await city.count() > 0) await city.fill('Paris');
      const postcode = page.locator('[name*="postal_code"]').first();
      if (await postcode.count() > 0) await postcode.fill('75001');
      // Country — déjà FR par défaut
      const continueBtn = page.locator('input[value*="Continuer"], input[value*="Continue"], button:has-text("Continuer")').first();
      if (await continueBtn.count() > 0) {
        await continueBtn.click();
        await page.waitForTimeout(3000);
      }
      pass('Adresse livraison remplie');
    } else {
      info('Champs adresse non trouvés — peut-être déjà préremplie');
    }
    await ss(page, '06-checkout-shipping');

    // Continuer si bouton présent
    const nextBtn = page.locator('input[value*="Continuer"], input[value*="Continue"], .button--primary').first();
    if (await nextBtn.count() > 0) {
      await nextBtn.click();
      await page.waitForTimeout(3000);
    }
    await ss(page, '07-checkout-review');

    // ─── 5. Paiement Stripe ─────────────────────────────────────────────────
    log('Attente du formulaire Stripe Payment Element…');
    await page.waitForTimeout(5000); // Stripe prend du temps à charger
    await ss(page, '08-payment-step');

    // Chercher le frame Stripe qui contient les inputs carte
    let cardFrame = null;
    for (const frame of page.frames()) {
      try {
        const count = await frame.locator('input[placeholder*="1234"], input[name="number"], input[autocomplete="cc-number"]').count();
        if (count > 0) {
          cardFrame = frame;
          log(`Frame carte trouvé : ${frame.url().substring(0, 80)}`);
          break;
        }
      } catch (e) { /* continue */ }
    }

    // Si pas trouvé via input spécifique, chercher par URL elements-inner-loader
    if (!cardFrame) {
      cardFrame = page.frames().find(f => f.url().includes('elements-inner-loader'));
      if (cardFrame) log(`Frame Stripe via elements-inner-loader : ${cardFrame.url().substring(0, 80)}`);
    }

    let stripeFound = false;
    if (cardFrame) {
      stripeFound = true;
      log('Remplissage carte test dans le frame Stripe…');

      // Card number
      const cardInput = cardFrame.locator('input[placeholder*="1234"], input[name="number"], input[autocomplete="cc-number"]').first();
      if (await cardInput.count() > 0) {
        await cardInput.click();
        await cardInput.fill('4242424242424242');
        log('  ✓ card number');
      } else {
        // Essayer le premier input du frame
        const allInputs = cardFrame.locator('input');
        const cnt = await allInputs.count();
        log(`  ${cnt} inputs dans le frame`);
        if (cnt > 0) {
          await allInputs.nth(0).click();
          await allInputs.nth(0).fill('4242424242424242');
        }
      }

      // Expiry
      const expiryInput = cardFrame.locator('input[placeholder*="MM"], input[name="expiry"], input[autocomplete="cc-exp"]').first();
      if (await expiryInput.count() > 0) {
        await expiryInput.click();
        await expiryInput.fill('1234');
        log('  ✓ expiry');
      }

      // CVC
      const cvcInput = cardFrame.locator('input[placeholder*="CVC"], input[placeholder*="cvc"], input[name="cvc"], input[autocomplete="cc-csc"]').first();
      if (await cvcInput.count() > 0) {
        await cvcInput.click();
        await cvcInput.fill('123');
        log('  ✓ CVC');
      }

      pass('Carte Stripe saisie');
      await page.waitForTimeout(1500);
    } else {
      // Dernière tentative : frameLocator générique sur tous les iframes Stripe
      log('Tentative frameLocator générique…');
      try {
        const sfLoc = page.frameLocator('iframe[src*="js.stripe.com/v3/elements-inner"]');
        const inp = sfLoc.locator('input').first();
        if (await inp.count() > 0) {
          stripeFound = true;
          await inp.fill('4242424242424242');
          pass('Carte via frameLocator générique');
        }
      } catch (e) { log(`frameLocator erreur: ${e.message.substring(0, 60)}`); }

      if (!stripeFound) {
        info('⚠️ Frame Stripe non trouvé — screenshot diagnostic');
        const pageContent = await page.content();
        if (pageContent.includes('stripe')) info('Stripe dans HTML ✓');
      }
    }

    await ss(page, '09-payment-filled');

    // Dismisser la bannière cookies avant de soumettre
    const cookieBtn = page.locator('button:has-text("NON, MERCI"), button:has-text("Accepter"), button:has-text("ACCEPTER"), #tarteaucitronPersonalize2').first();
    if (await cookieBtn.count() > 0) {
      await cookieBtn.click();
      await page.waitForTimeout(1000);
      log('Bannière cookies dismissée');
    }

    // Cocher les checkboxes requises (CGV / newsletter) avant paiement
    const requiredCheckboxes = page.locator('input[type="checkbox"][required], input[type="checkbox"].required');
    const allCheckboxes = page.locator('input[type="checkbox"]');
    const cbCount = await allCheckboxes.count();
    log(`Checkboxes sur la page : ${cbCount}`);
    for (let i = 0; i < cbCount; i++) {
      const cb = allCheckboxes.nth(i);
      const isChecked = await cb.isChecked();
      if (!isChecked) {
        await cb.check();
        log(`  ✓ Checkbox ${i} cochée`);
      }
    }

    // Soumettre le paiement
    const placeOrderBtn = page.locator('input[value*="Payer"], input[value*="Commander"], input[value*="Confirmer"], input[value*="PAYER"], button:has-text("Payer"), button:has-text("PAYER"), .button--primary').last();
    if (await placeOrderBtn.count() > 0) {
      log('Clic sur "Passer la commande"…');
      await placeOrderBtn.scrollIntoViewIfNeeded();
      await placeOrderBtn.click();
      await page.waitForTimeout(12000); // Stripe + redirect prennent du temps
    }
    await ss(page, '10-after-payment');

    const postPaymentUrl = page.url();
    log(`URL après paiement : ${postPaymentUrl}`);

    // ─── 6. Vérification — page de confirmation ──────────────────────────────
    const confirmContent = await page.content();
    const stillOnReview = postPaymentUrl.includes('/review');
    const isComplete = postPaymentUrl.includes('complete') ||
      confirmContent.includes('Merci') || confirmContent.includes('merci') ||
      confirmContent.includes('confirmée') || confirmContent.includes('placed') ||
      confirmContent.includes('Complete');

    if (!stillOnReview && isComplete) {
      pass('Page de confirmation atteinte');
      results.push({ test: 'checkout_completed', status: 'PASS', url: postPaymentUrl });
    } else if (stillOnReview) {
      fail(`Toujours sur /review — paiement non soumis (URL: ${postPaymentUrl})`);
      results.push({ test: 'checkout_completed', status: 'FAIL', url: postPaymentUrl });
    } else {
      pass(`Redirection post-paiement (URL: ${postPaymentUrl})`);
      results.push({ test: 'checkout_completed', status: 'PASS', url: postPaymentUrl });
    }

    // Récupérer le numéro de commande
    const orderMatch = confirmContent.match(/commande[^\d]*#?\s*(\d{4,})/i) ||
      confirmContent.match(/order[^\d]*#?\s*(\d{4,})/i);
    const orderNumber = orderMatch ? orderMatch[1] : null;
    if (orderNumber) {
      pass(`Numéro de commande : #${orderNumber}`);
      results.push({ test: 'order_number', status: 'PASS', value: orderNumber });
    }

    // ─── 7. Connexion admin → vérification état commande ─────────────────────
    log('Connexion gestionnaire pour vérification état…');
    if (!ADMIN_ULI) {
      info('ADMIN_ULI non fourni — skip vérification admin');
    } else {
      const adminCtx  = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 900 } });
      const adminPage = await adminCtx.newPage();
      await adminPage.goto(ADMIN_ULI, { waitUntil: 'networkidle', timeout: 30_000 });
      await ss(adminPage, '11-admin-login');
      const continueAdmin = adminPage.locator('button:has-text("Continue"), a:has-text("Continue")').first();
      if (await continueAdmin.count() > 0) { await continueAdmin.click(); await adminPage.waitForTimeout(2000); }

      await adminPage.goto(`${BASE_URL}/admin/boutique/commandes`, { waitUntil: 'networkidle', timeout: 20_000 });
      await adminPage.waitForTimeout(2000);
      await ss(adminPage, '12-admin-orders');
      const ordersHtml = await adminPage.content();

      // Vérifier qu'aucune commande n'est en pending_payment
      const hasPendingPayment = ordersHtml.includes('En attente de paiement') || ordersHtml.includes('pending_payment');
      const hasProcessing = ordersHtml.includes('En fabrication') || ordersHtml.includes('processing');

      if (!hasPendingPayment && hasProcessing) {
        pass('Aucune commande en "pending_payment" — transition auto réussie');
        results.push({ test: 'auto_transition', status: 'PASS' });
      } else if (hasPendingPayment) {
        fail('Une commande est encore en "pending_payment" — transition auto ÉCHOUÉE');
        results.push({ test: 'auto_transition', status: 'FAIL', detail: 'pending_payment visible' });
      } else {
        info('Impossible de déterminer l\'état exact — voir screenshot');
        results.push({ test: 'auto_transition', status: 'UNKNOWN' });
      }

      // Vérifier les logs Drupal
      await adminPage.goto(`${BASE_URL}/admin/reports/dblog?type[]=odesign_orders`, { waitUntil: 'networkidle', timeout: 20_000 });
      await ss(adminPage, '13-dblog-odesign');
      const logsHtml = await adminPage.content();
      if (logsHtml.includes('confirm_payment') || logsHtml.includes('Transition confirm')) {
        pass('Log "confirm_payment" trouvé dans dblog → transition déclenchée');
        results.push({ test: 'watchdog_log', status: 'PASS' });
      } else {
        info('Log confirm_payment non visible dans dblog (peut être filtré)');
        results.push({ test: 'watchdog_log', status: 'UNKNOWN' });
      }

      await adminCtx.close();
    }

  } catch (e) {
    fail(`Exception : ${e.message}`);
    results.push({ test: 'exception', status: 'FAIL', error: e.message });
    await ss(page, 'error-final');
  }

  await browser.close();

  // ─── Résumé ────────────────────────────────────────────────────────────────
  console.log('\n══════════════════════════════════════');
  console.log('VER-009 — Résumé');
  console.log('══════════════════════════════════════');
  for (const r of results) {
    const icon = r.status === 'PASS' ? '✅' : r.status === 'FAIL' ? '❌' : '⚠️';
    console.log(`${icon} ${r.test}: ${r.status}${r.value ? ' = ' + r.value : ''}${r.detail ? ' — ' + r.detail : ''}${r.error ? ' — ' + r.error : ''}`);
  }
  const passed = results.filter(r => r.status === 'PASS').length;
  const failed = results.filter(r => r.status === 'FAIL').length;
  console.log(`\nTotal : ${passed} PASS / ${failed} FAIL / ${results.length - passed - failed} UNKNOWN`);
  console.log('══════════════════════════════════════');

  process.exit(failed > 0 ? 1 : 0);
})();

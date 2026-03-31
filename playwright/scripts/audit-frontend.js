/**
 * @file
 * audit-frontend.js
 *
 * Capture des pages frontend odesign_base et analyse UX/visuelle.
 * Pages auditées : accueil, catalogue, fiche produit, blog, pages légales.
 *
 * Usage : node playwright/scripts/audit-frontend.js
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE_URL = 'http://odesign-drupal.ddev.site';
const SCREENSHOTS_DIR = path.join(__dirname, '..', 'screenshots', 'frontend-audit');

// Pages à auditer.
const PAGES = [
  { name: '01-homepage',          path: '/',                                  label: 'Accueil' },
  { name: '02-catalogue',         path: '/produits',                          label: 'Catalogue (/produits)' },
  { name: '03-product-detail',    path: '/products/carte-naissance-ovale-bois', label: 'Fiche produit' },
  { name: '04-product-detail-2',  path: '/products/magnet-photo-personnalise-bois', label: 'Fiche produit #2' },
  { name: '05-blog',              path: '/blog',                              label: 'Blog (/blog)' },
  { name: '06-cgv',               path: '/cgv',                              label: 'CGV' },
  { name: '07-mentions-legales',  path: '/mentions-legales',                 label: 'Mentions légales' },
  { name: '08-panier',            path: '/cart',                             label: 'Panier' },
];

// Largeurs à capturer.
const VIEWPORTS = [
  { name: 'desktop', width: 1440, height: 900 },
  { name: 'mobile',  width: 390,  height: 844 },
];

// Findings accumulés pendant l'audit.
const findings = [];

function addFinding(severity, page, message) {
  findings.push({ severity, page, message });
  const icon = severity === 'error' ? '❌' : severity === 'warning' ? '⚠️' : 'ℹ️';
  console.log(`  ${icon} [${severity.toUpperCase()}] ${message}`);
}

async function auditPage(page, browserPage, pageDef, viewportName) {
  const url = BASE_URL + pageDef.path;

  try {
    const response = await browserPage.goto(url, { waitUntil: 'networkidle', timeout: 15000 });

    // ---------- Checks HTTP ----------
    if (!response || response.status() >= 400) {
      addFinding('error', pageDef.label, `HTTP ${response?.status() ?? 'no response'} sur ${pageDef.path}`);
      return;
    }

    // ---------- Screenshot ----------
    // Attendre le chargement complet des webfonts avant capture.
    await browserPage.evaluate(() => document.fonts.ready);
    const screenshotDir = path.join(SCREENSHOTS_DIR, viewportName);
    fs.mkdirSync(screenshotDir, { recursive: true });
    const screenshotPath = path.join(screenshotDir, `${pageDef.name}.png`);
    await browserPage.screenshot({ path: screenshotPath, fullPage: true });
    console.log(`  📸 ${screenshotPath.replace(path.join(__dirname, '../..'), '')}`);

    if (viewportName !== 'desktop') return; // Analyses JS uniquement sur desktop.

    // ---------- Analyses DOM ----------
    const analysis = await browserPage.evaluate(() => {
      const body = document.body;
      const results = {};

      // Titre de la page.
      results.title = document.title;

      // Présence éléments structurels.
      results.hasHeader   = !!document.querySelector('header, .site-header, #header');
      results.hasNav      = !!document.querySelector('nav, .main-nav');
      results.hasMain     = !!document.querySelector('main, [role="main"], #main-content');
      results.hasFooter   = !!document.querySelector('footer, .site-footer, #footer');
      results.hasSkipLink = !!document.querySelector('.visually-hidden--focusable, a[href="#main-content"]');

      // Palette : vérifier que les couleurs du studio sont appliquées.
      const bodyStyle = getComputedStyle(body);
      results.hasCssVars  = getComputedStyle(document.documentElement).getPropertyValue('--color-brand-dark').trim() !== '';

      // Images cassées.
      const imgs = Array.from(document.querySelectorAll('img'));
      results.totalImages    = imgs.length;
      results.brokenImages   = imgs.filter(img => !img.naturalWidth && img.complete).length;

      // Liens internes cassés (href vide ou #).
      const links = Array.from(document.querySelectorAll('a[href]'));
      results.totalLinks    = links.length;
      results.emptyLinks    = links.filter(a => a.getAttribute('href') === '#' || a.getAttribute('href') === '').length;
      results.drupaldLinks  = links.filter(a => a.getAttribute('href')?.includes('/node/')).length;

      // Contenu du panier (s'il y a lieu).
      results.hasCartBlock = !!document.querySelector('.cart--cart-block, [data-drupal-selector="cart-form"]');

      // Erreurs Drupal visibles.
      const messages = document.querySelector('.messages, .messages--error, [data-drupal-messages]');
      results.hasDrupalMessages = !!messages;
      results.drupalMessagesText = messages ? messages.textContent.substring(0, 200) : '';

      // Bandeaux RGPD.
      results.hasCookieBanner = !!document.querySelector('#sliding-popup, .eu-cookie-compliance-banner, [id*="cookie"]');

      // H1 présent ?
      const h1s = document.querySelectorAll('h1');
      results.h1Count = h1s.length;
      results.h1Text  = h1s.length ? h1s[0].textContent.trim().substring(0, 80) : '';

      // Produits dans la grille catalogue.
      results.productCards = document.querySelectorAll('.product-card, .views-row').length;

      // Prix visibles.
      results.priceElements = document.querySelectorAll('.product-card__price, .product-detail__price').length;

      // Formulaire add-to-cart.
      results.hasAddToCart = !!document.querySelector('[data-drupal-selector*="add-to-cart"], .add-to-cart-form, form[action*="cart"]');

      // Texte overflow visible.
      results.overflowIssues = Array.from(document.querySelectorAll('*')).filter(el => {
        const s = getComputedStyle(el);
        return s.overflowX === 'visible' && el.scrollWidth > el.clientWidth && el.clientWidth > 0;
      }).length;

      return results;
    });

    // ---------- Rapport des résultats ----------

    if (!analysis.hasHeader)   addFinding('error',   pageDef.label, 'Header manquant dans le DOM');
    if (!analysis.hasMain)     addFinding('error',   pageDef.label, 'Élément <main> / role="main" manquant (accessibilité)');
    if (!analysis.hasFooter)   addFinding('warning', pageDef.label, 'Footer manquant dans le DOM');
    if (!analysis.hasSkipLink) addFinding('warning', pageDef.label, 'Skip-link d\'accessibilité absent');

    if (!analysis.hasCssVars)  addFinding('error',   pageDef.label, 'Variables CSS --color-brand-dark non chargées (thème non actif ?)');

    if (analysis.brokenImages > 0) {
      addFinding('error', pageDef.label, `${analysis.brokenImages} image(s) cassée(s) sur ${analysis.totalImages}`);
    }

    if (analysis.drupaldLinks > 0) {
      addFinding('warning', pageDef.label, `${analysis.drupaldLinks} lien(s) /node/NNN non aliasé(s) visible(s)`);
    }

    if (analysis.emptyLinks > 0) {
      addFinding('warning', pageDef.label, `${analysis.emptyLinks} lien(s) href="#" ou vide(s)`);
    }

    if (analysis.h1Count === 0) {
      addFinding('error',   pageDef.label, 'Aucun H1 — problème SEO/accessibilité');
    } else if (analysis.h1Count > 1) {
      addFinding('warning', pageDef.label, `${analysis.h1Count} H1 sur la même page (doit être unique)`);
    }

    if (analysis.hasDrupalMessages) {
      addFinding('error', pageDef.label, `Message Drupal affiché : "${analysis.drupalMessagesText.substring(0, 100)}"`);
    }

    // Spécifique catalogue.
    if (pageDef.path === '/produits') {
      if (analysis.productCards === 0) {
        addFinding('error', pageDef.label, 'Aucune carte produit visible dans .product-grid / .views-row');
      } else {
        console.log(`  ✓ ${analysis.productCards} carte(s) produit(s) visibles`);
      }
      if (analysis.priceElements === 0) {
        addFinding('warning', pageDef.label, 'Aucun prix visible dans les cartes produit');
      }
    }

    // Spécifique fiche produit.
    if (pageDef.path.startsWith('/products/')) {
      if (!analysis.hasAddToCart) {
        addFinding('warning', pageDef.label, 'Formulaire add-to-cart introuvable dans le DOM');
      } else {
        console.log('  ✓ Formulaire add-to-cart présent');
      }
      if (analysis.priceElements === 0) {
        addFinding('warning', pageDef.label, 'Prix non affiché sur la fiche produit');
      }
    }

    // Cookie banner uniquement sur homepage.
    if (pageDef.path === '/') {
      if (!analysis.hasCookieBanner) {
        addFinding('warning', pageDef.label, 'Bandeau cookie eu_cookie_compliance non visible');
      } else {
        console.log('  ✓ Bandeau cookie détecté');
      }
    }

    if (analysis.overflowIssues > 2) {
      addFinding('info', pageDef.label, `${analysis.overflowIssues} éléments avec overflow potentiel (vérifier responsive)`);
    }

    console.log(`  ✓ "${analysis.title}" — H1: "${analysis.h1Text}" — liens: ${analysis.totalLinks}`);

  } catch (err) {
    addFinding('error', pageDef.label, `Exception lors de l'audit : ${err.message}`);
  }
}

(async () => {
  console.log('\n🔍 Audit frontend odesign_base\n' + '='.repeat(50));

  fs.mkdirSync(SCREENSHOTS_DIR, { recursive: true });

  // --font-render-hinting=none corrige le rendu des icon-fonts (PUA) en mode headless.
  const browser = await chromium.launch({ headless: true, args: ['--font-render-hinting=none'] });

  for (const viewport of VIEWPORTS) {
    console.log(`\n📐 Viewport : ${viewport.name} (${viewport.width}×${viewport.height})`);
    console.log('-'.repeat(50));

    const context = await browser.newContext({
      viewport: { width: viewport.width, height: viewport.height },
      userAgent: 'Playwright-FrontendAudit/1.0',
    });

    // Bloquer les ressources externes lourdes pour accélérer.
    await context.route('**/*.{woff,woff2,ttf,otf}', route => route.abort());

    const browserPage = await context.newPage();

    for (const pageDef of PAGES) {
      console.log(`\n[${pageDef.label}] ${BASE_URL}${pageDef.path}`);
      await auditPage(null, browserPage, pageDef, viewport.name);
    }

    await context.close();
  }

  await browser.close();

  // ---------- Rapport final ----------
  console.log('\n' + '='.repeat(50));
  console.log('📋 RAPPORT FINAL\n');

  const errors   = findings.filter(f => f.severity === 'error');
  const warnings = findings.filter(f => f.severity === 'warning');
  const infos    = findings.filter(f => f.severity === 'info');

  if (errors.length) {
    console.log(`❌ ERREURS (${errors.length}) :`);
    errors.forEach(f => console.log(`  • [${f.page}] ${f.message}`));
  }
  if (warnings.length) {
    console.log(`\n⚠️  AVERTISSEMENTS (${warnings.length}) :`);
    warnings.forEach(f => console.log(`  • [${f.page}] ${f.message}`));
  }
  if (infos.length) {
    console.log(`\nℹ️  INFOS (${infos.length}) :`);
    infos.forEach(f => console.log(`  • [${f.page}] ${f.message}`));
  }
  if (!findings.length) {
    console.log('✅ Aucun finding — toutes les pages passent l\'audit.');
  }

  // Sauvegarder le rapport JSON.
  const reportPath = path.join(SCREENSHOTS_DIR, 'audit-report.json');
  fs.writeFileSync(reportPath, JSON.stringify({ date: new Date().toISOString(), findings }, null, 2));
  console.log(`\n💾 Rapport JSON : ${reportPath}`);

  console.log('\n📸 Screenshots dans : playwright/screenshots/frontend-audit/');
  console.log('='.repeat(50) + '\n');

  process.exit(errors.length > 0 ? 1 : 0);
})();

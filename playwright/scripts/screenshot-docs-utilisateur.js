#!/usr/bin/env node
/**
 * screenshot-docs-utilisateur.js
 * Captures d'écran pour la documentation utilisateur gestionnaire_boutique.
 * Sortie : docs/utilisateur/screenshots/
 *
 * Usage :
 *   ULI=$(cd /path/to/repo && ddev drush uli --uid=2) && \
 *   node playwright/scripts/screenshot-docs-utilisateur.js "$ULI"
 */

const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const uli = process.argv[2];
if (!uli) {
  console.error('Usage: node screenshot-docs-utilisateur.js <uli-url>');
  console.error('Obtenir le lien : ddev drush uli --uid=2');
  process.exit(1);
}

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'https://odesign-drupal.ddev.site';
const OUT_DIR = path.resolve(__dirname, '..', '..', 'docs', 'utilisateur', 'screenshots');
if (!fs.existsSync(OUT_DIR)) fs.mkdirSync(OUT_DIR, { recursive: true });

/** Pages à capturer pour la documentation utilisateur */
const PAGES = [
  // Configuration boutique
  { key: 'config-boutique-onglet-boutique',    path: '/admin/boutique/configuration',                     label: 'Config — Onglet Boutique',          tab: 'boutique' },
  { key: 'config-boutique-onglet-emails',      path: '/admin/boutique/configuration#emails',              label: 'Config — Onglet Emails',            tab: 'emails' },
  { key: 'config-boutique-onglet-paiement',    path: '/admin/boutique/configuration#stripe',              label: 'Config — Onglet Paiement',          tab: 'stripe' },
  { key: 'config-boutique-onglet-hero',        path: '/admin/boutique/configuration#homepage-hero',       label: 'Config — Onglet Hero',              tab: 'homepage-hero' },
  { key: 'config-boutique-onglet-presentation',path: '/admin/boutique/configuration#homepage-story',      label: 'Config — Onglet Présentation',      tab: 'homepage-story' },
  { key: 'config-boutique-onglet-methode',     path: '/admin/boutique/configuration#homepage-methode',    label: 'Config — Comment ça marche',        tab: 'homepage-methode' },
  { key: 'config-boutique-onglet-promotions',  path: '/admin/boutique/configuration#promotions-volume',   label: 'Config — Promotions volume',        tab: 'promotions-volume' },

  // Commandes
  { key: 'commandes-liste',          path: '/admin/commerce/orders',              label: 'Commandes — liste' },

  // Produits
  { key: 'produits-liste',           path: '/admin/commerce/products',            label: 'Produits — liste' },
  { key: 'produits-formulaire-nouveau', path: '/product/add/default',             label: 'Produits — nouveau produit', maxHeight: 1200 },

  // Promotions
  { key: 'promotions-liste',         path: '/admin/commerce/promotions',          label: 'Promotions — liste' },

  // Contenu éditorial
  { key: 'contenu-liste',            path: '/admin/content',                      label: 'Contenu — liste' },
  { key: 'contenu-article-nouveau',  path: '/node/add/article',                   label: 'Contenu — nouvel article',  maxHeight: 1200 },
  // Note: /node/add/guide non accessible au rôle gestionnaire_boutique (admin uniquement)

  // Newsletter
  { key: 'newsletter-liste',         path: '/admin/content?type=simplenews_issue', label: 'Newsletter — liste' },
  { key: 'newsletter-abonnes',       path: '/admin/people/simplenews',            label: 'Newsletter — abonnés' },
  { key: 'newsletter-nouveau',       path: '/node/add/simplenews_issue',          label: 'Newsletter — nouvelle newsletter' },

  // Clients
  { key: 'clients-liste',            path: '/admin/commerce/customers',           label: 'Clients — liste' },
];

async function bypassSandbox(page) {
  const heading = page.getByRole('heading', { name: /sandbox environment/i });
  if (await heading.count()) {
    const link = page.getByRole('link', { name: /continue/i }).first();
    if (await link.count()) {
      await link.click();
      await page.waitForLoadState('networkidle');
    }
  }
}

async function loginViaUli(page) {
  await page.goto(uli, { waitUntil: 'networkidle', timeout: 30000 });
  await bypassSandbox(page);
  const pwReset = await page.locator('input[name="pass[pass1]"]').count();
  if (pwReset) {
    await page.goto(`${BASE_URL}/admin/boutique`, { waitUntil: 'networkidle' });
  }
  console.log('🔑 Connecté via ULI');
}

async function activateTab(page, tabKey) {
  if (!tabKey) return;
  // Drupal vertical tabs: les liens ont href="#edit-{key}"
  const tabLink = page.locator(`a[href="#edit-${tabKey}"]`).first();
  if (await tabLink.count()) {
    await tabLink.click();
    await page.waitForTimeout(600);
    return;
  }
  // Fallback: data-drupal-selector ou texte partiel
  const fallback = page.locator(`[data-drupal-selector="edit-${tabKey}"], .vertical-tabs__menu-item a[href*="${tabKey}"]`).first();
  if (await fallback.count()) {
    await fallback.click();
    await page.waitForTimeout(600);
  }
}

async function capture(page, entry) {
  try {
    const url = entry.path.split('#')[0];
    await page.goto(`${BASE_URL}${url}`, { waitUntil: 'networkidle', timeout: 20000 });
    await bypassSandbox(page);

    if (entry.tab) await activateTab(page, entry.tab);
    if (entry.scrollY) await page.evaluate((y) => window.scrollTo(0, y), entry.scrollY);

    await page.waitForTimeout(1500);
    await page.evaluate(() => document.fonts.ready);

    const outPath = path.join(OUT_DIR, `${entry.key}.png`);
    const MAX_HEIGHT = entry.maxHeight || 800; // hauteur max pour un mode d'emploi (A4 ≈ 800px @96dpi)
    const VIEWPORT_W = 1024;

    const main = page.locator('.layout-region-node-main, main .layout-container, main, .dialog-off-canvas-main-canvas').first();
    if (await main.count()) {
      const box = await main.boundingBox();
      if (box) {
        // Clip à la hauteur max pour éviter les captures infinies sur les listes longues
        const clipHeight = Math.min(box.height, MAX_HEIGHT);
        await page.screenshot({
          path: outPath,
          clip: { x: box.x, y: box.y, width: Math.min(box.width, VIEWPORT_W - box.x), height: clipHeight },
        });
      } else {
        await page.screenshot({ path: outPath, fullPage: false });
      }
    } else {
      await page.screenshot({ path: outPath, fullPage: false });
    }
    console.log(`  ✅  ${entry.key} — ${entry.label}`);
    return outPath;
  } catch (err) {
    console.warn(`  ⚠️  ${entry.key} — ${err.message}`);
    return null;
  }
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({
    viewport: { width: 1024, height: 768 },
    ignoreHTTPSErrors: true,
  });
  const page = await ctx.newPage();

  console.log('📸 Démarrage des captures documentation utilisateur');
  console.log(`   Sortie : ${OUT_DIR}\n`);

  await loginViaUli(page);

  let ok = 0, fail = 0;
  for (const entry of PAGES) {
    const result = await capture(page, entry);
    result ? ok++ : fail++;
  }

  await browser.close();

  console.log(`\n📊 Résultat : ${ok} ✅  ${fail} ⚠️`);
  console.log(`📁 Captures dans : ${OUT_DIR}`);

  // Génère un index markdown des captures
  const index = PAGES.map(e =>
    `| \`${e.key}.png\` | ${e.label} |\n`
  ).join('');
  const indexPath = path.join(OUT_DIR, 'index.md');
  fs.writeFileSync(indexPath, `# Index des captures — Documentation utilisateur\n\n| Fichier | Page |\n|---|---|\n${index}`);
  console.log(`📝 Index généré : ${indexPath}`);
})();

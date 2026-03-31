/**
 * Audit layout : screenshots de toutes les pages clés (frontend + back-office)
 */
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE = 'http://odesign-drupal.ddev.site';
const OUT  = path.join(__dirname, '../screenshots/layout-audit');
fs.mkdirSync(OUT, { recursive: true });

const PAGES = [
  // Frontend
  { key: 'home',        url: '/',                       label: 'Accueil' },
  { key: 'catalogue',   url: '/produits',               label: 'Catalogue' },
  { key: 'product',     url: '/products/carte-naissance-ovale-bois', label: 'Fiche produit' },
  { key: 'cart',        url: '/cart',                   label: 'Panier' },
  { key: 'blog',        url: '/blog',                   label: 'Blog liste' },
  { key: 'article',     url: '/blog/comment-personnaliser-une-carte-de-naissance-en-bois', label: 'Article blog' },
  { key: 'cgv',         url: '/cgv',                    label: 'CGV' },
  // Back-office gestionnaire (accès public en dev, sans auth par simplicit)
  { key: 'admin-content', url: '/admin/content',        label: 'Admin contenus' },
  { key: 'admin-products', url: '/admin/commerce/products', label: 'Admin produits' },
  { key: 'admin-orders',  url: '/admin/commerce/orders', label: 'Admin commandes' },
  { key: 'node-add-article', url: '/node/add/article',  label: 'Formulaire article' },
  { key: 'product-add',  url: '/admin/commerce/products/add/default', label: 'Formulaire produit' },
];

(async () => {
  const browser = await chromium.launch({ args: ['--font-render-hinting=none'] });
  for (const p of PAGES) {
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();
    try {
      await page.goto(BASE + p.url, { waitUntil: 'networkidle', timeout: 15000 });
      await page.screenshot({ path: `${OUT}/${p.key}-desktop.png`, fullPage: true });
      // Mobile
      const mctx = await browser.newContext({ viewport: { width: 390, height: 844 } });
      const mpage = await mctx.newPage();
      await mpage.goto(BASE + p.url, { waitUntil: 'networkidle', timeout: 15000 });
      await mpage.screenshot({ path: `${OUT}/${p.key}-mobile.png`, fullPage: true });
      await mctx.close();
      console.log(`✅ ${p.label}`);
    } catch(e) {
      console.log(`⚠️  ${p.label} — ${e.message.substring(0,60)}`);
    }
    await ctx.close();
  }
  await browser.close();
  console.log(`\nScreenshots → ${OUT}`);
})();

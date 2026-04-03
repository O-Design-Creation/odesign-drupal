const { chromium } = require('playwright');
const fs = require('fs');

/* ─── Configuration via variables d'environnement ──────────────────────────
   Copier playwright/.env.playwright.example → playwright/.env.playwright
   puis charger : export $(cat playwright/.env.playwright | xargs)
   ────────────────────────────────────────────────────────────────────────── */
const BASE    = process.env.PLAYWRIGHT_BASE || 'https://odesign-drupal.ddev.site';
const USER    = process.env.PLAYWRIGHT_USER;
const PASS    = process.env.PLAYWRIGHT_PASS;
const OUTDIR  = process.env.PLAYWRIGHT_OUTDIR || '/tmp/screenshots';
const SESSION = '/tmp/gestionnaire-session.json';

if (!USER || !PASS) {
  console.error('❌  PLAYWRIGHT_USER et PLAYWRIGHT_PASS sont requis.');
  console.error('    Copier playwright/.env.playwright.example → playwright/.env.playwright');
  console.error('    puis : export $(cat playwright/.env.playwright | xargs)');
  process.exit(1);
}

const PAGES = [
  { name: '01-dashboard',           url: '/admin/boutique' },
  { name: '02-config',              url: '/admin/boutique/configuration' },
  { name: '03-commandes-a-traiter', url: '/admin/boutique/commandes-a-traiter' },
  { name: '04-commandes-liste',     url: '/admin/commerce/orders' },
  { name: '05-catalogue',           url: '/admin/boutique/catalogue' },
  { name: '06-editorial',           url: '/admin/boutique/editorial' },
  { name: '07-univers-list',        url: '/admin/structure/taxonomy/manage/life_occasion/overview' },
  { name: '08-content-list',        url: '/admin/content' },
  { name: '09-article-create',      url: '/node/add/article' },
  { name: '10-guide-create',        url: '/node/add/guide' },
  { name: '11-product-list',        url: '/admin/commerce/products' },
];

const VIEWPORTS = [
  { label: 'desktop', width: 1440, height: 900 },
  { label: 'tablet',  width: 768,  height: 1024 },
  { label: 'mobile',  width: 390,  height: 844 },
];

async function login(browser) {
  const ctx = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  await page.goto(`${BASE}/user/login`, { waitUntil: 'load', timeout: 30000 });
  await page.fill('#edit-name', USER);
  await page.fill('#edit-pass', PASS);
  await page.click('#edit-submit');
  // Wait for URL change, not networkidle
  await page.waitForURL('**/admin/**', { timeout: 15000 });
  await page.waitForTimeout(1000);
  await ctx.storageState({ path: SESSION });
  await ctx.close();
  console.log('✅ Session sauvegardée');
}

(async () => {
  if (!fs.existsSync(OUTDIR)) fs.mkdirSync(OUTDIR, { recursive: true });
  const browser = await chromium.launch({ args: ['--ignore-certificate-errors'] });

  await login(browser);

  for (const vp of VIEWPORTS) {
    const ctx = await browser.newContext({
      viewport: { width: vp.width, height: vp.height },
      ignoreHTTPSErrors: true,
      storageState: SESSION,
    });
    const page = await ctx.newPage();

    for (const p of PAGES) {
      try {
        await page.goto(BASE + p.url, { waitUntil: 'domcontentloaded', timeout: 20000 });
        await page.waitForTimeout(900);
        await page.screenshot({ path: `${OUTDIR}/${p.name}-${vp.label}.png`, fullPage: true });
        console.log(`✅ ${p.name} [${vp.label}]`);
      } catch (e) {
        console.log(`❌ ${p.name} [${vp.label}]: ${e.message.split('\n')[0]}`);
      }
    }

    // Order detail
    try {
      await page.goto(BASE + '/admin/commerce/orders', { waitUntil: 'domcontentloaded', timeout: 20000 });
      await page.waitForTimeout(800);
      const link = await page.$('table td a');
      if (link) {
        const href = await link.getAttribute('href');
        await page.goto(BASE + href, { waitUntil: 'domcontentloaded', timeout: 20000 });
        await page.waitForTimeout(800);
        await page.screenshot({ path: `${OUTDIR}/12-order-detail-${vp.label}.png`, fullPage: true });
        console.log(`✅ 12-order-detail [${vp.label}]`);
      }
    } catch(e) { console.log(`❌ order-detail [${vp.label}]: ${e.message.split('\n')[0]}`); }

    // Univers edit
    try {
      await page.goto(BASE + '/admin/structure/taxonomy/manage/life_occasion/overview', { waitUntil: 'domcontentloaded', timeout: 20000 });
      await page.waitForTimeout(800);
      const link = await page.$('td a[href*="/edit"]');
      if (link) {
        const href = await link.getAttribute('href');
        await page.goto(BASE + href, { waitUntil: 'domcontentloaded', timeout: 20000 });
        await page.waitForTimeout(800);
        await page.screenshot({ path: `${OUTDIR}/13-univers-edit-${vp.label}.png`, fullPage: true });
        console.log(`✅ 13-univers-edit [${vp.label}]`);
      }
    } catch(e) { console.log(`❌ univers-edit [${vp.label}]: ${e.message.split('\n')[0]}`); }

    // Product create
    try {
      await page.goto(BASE + '/admin/commerce/products/add', { waitUntil: 'domcontentloaded', timeout: 20000 });
      await page.waitForTimeout(800);
      const link = await page.$('a[href*="/admin/commerce/products/add/"]');
      if (link) {
        const href = await link.getAttribute('href');
        await page.goto(BASE + href, { waitUntil: 'domcontentloaded', timeout: 20000 });
        await page.waitForTimeout(800);
      }
      await page.screenshot({ path: `${OUTDIR}/14-product-create-${vp.label}.png`, fullPage: true });
      console.log(`✅ 14-product-create [${vp.label}]`);
    } catch(e) { console.log(`❌ product-create [${vp.label}]: ${e.message.split('\n')[0]}`); }

    await ctx.close();
  }

  await browser.close();
  console.log(`\n📁 ${OUTDIR} — ${fs.readdirSync(OUTDIR).length} fichiers`);
})();

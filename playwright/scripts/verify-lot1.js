const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  const browser = await chromium.launch({ headless: true, ignoreHTTPSErrors: true });
  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await context.newPage();
  const ULI = fs.readFileSync('/tmp/uli.txt', 'utf8').trim();
  const results = [];

  const check = (name, pass, detail) => {
    results.push({ name, pass });
    console.log((pass ? '✅' : '❌') + ' ' + name + (detail ? ' — ' + detail : ''));
  };

  try {
    await page.goto(ULI, { waitUntil: 'networkidle', timeout: 30000 });
    await page.goto('https://odesign-drupal.ddev.site/admin/boutique', { waitUntil: 'networkidle', timeout: 30000 });

    const nav = await page.$('nav.odesign-admin-nav');
    check('Nav .odesign-admin-nav rendered', !!nav);

    const phosphorIcon = await page.$('.odesign-admin-nav .ph');
    check('Phosphor icons present (.ph)', !!phosphorIcon);

    const materialSymbol = await page.$('.odesign-admin-nav .material-symbols-outlined');
    check('No Material Symbols in nav', !materialSymbol);

    const navLinks = await page.$$('.odesign-admin-nav__items a');
    check('Nav items >= 4', navLinks.length >= 4, navLinks.length + ' items');

    const statsSection = await page.$('.odesign-dashboard__stats');
    check('Dashboard stats section rendered', !!statsSection);

    const oldQuickLinks = await page.$('.odesign-dashboard__quick-links');
    check('Old quick_links gone', !oldQuickLinks);

    const dashboard = await page.$('.odesign-dashboard');
    check('Dashboard renders', !!dashboard);

    await page.screenshot({ path: '/tmp/lot1-dashboard.png' });

    await page.goto('https://odesign-drupal.ddev.site/admin/commerce/orders', { waitUntil: 'networkidle', timeout: 20000 });
    const activeLink = await page.$('.odesign-admin-nav__items a[aria-current="page"]');
    check('Active nav item on orders page', !!activeLink);

    await page.screenshot({ path: '/tmp/lot1-orders.png' });

  } catch (e) {
    check('No crash', false, e.message);
  }

  const passed = results.filter(r => r.pass).length;
  console.log('\n' + passed + '/' + results.length + ' checks passed');
  await browser.close();
  process.exit(passed === results.length ? 0 : 1);
})();

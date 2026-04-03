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

    // Test 1: Page commandes à traiter (vraie page, plus redirect)
    await page.goto('https://odesign-drupal.ddev.site/admin/boutique/commandes-a-traiter', { waitUntil: 'networkidle', timeout: 30000 });
    const title = await page.title();
    check('Commandes à traiter loads (no 500)', !title.includes('Error') && !title.includes('500'), title);

    // Check it's a real page, not a redirect
    const currentUrl = page.url();
    check('Stays on /commandes-a-traiter (not redirect)', currentUrl.includes('commandes-a-traiter'), currentUrl);

    // Check page structure
    const ordersWrapper = await page.$('.odesign-orders');
    check('.odesign-orders wrapper rendered', !!ordersWrapper);

    // Check either table or empty state
    const hasTable = !!(await page.$('.odesign-orders__table'));
    const hasEmpty = !!(await page.$('.odesign-orders__empty'));
    check('Table or empty state shown', hasTable || hasEmpty, hasTable ? 'table' : 'empty');

    await page.screenshot({ path: '/tmp/lot3-actionable.png' });

    // Test 2: Get order IDs to test detail page
    // Try to find an order link or test with a known order
    const orderLink = await page.$('.odesign-orders__order-link');
    let orderDetailUrl = null;

    if (orderLink) {
      orderDetailUrl = await orderLink.getAttribute('href');
      check('Order links present', !!orderDetailUrl, orderDetailUrl);

      // Navigate to order detail
      await page.goto('https://odesign-drupal.ddev.site' + orderDetailUrl, { waitUntil: 'networkidle', timeout: 30000 });
      const detailTitle = await page.title();
      check('Order detail page loads', !detailTitle.includes('Error') && !detailTitle.includes('500'), detailTitle);

      const detailWrapper = await page.$('.odesign-order-detail');
      check('.odesign-order-detail wrapper rendered', !!detailWrapper);

      const transitionsSection = await page.$('.odesign-order-detail__transitions');
      check('Transitions section present', !!transitionsSection);

      const itemsTable = await page.$('.odesign-order-detail__items');
      check('Order items table present', !!itemsTable);

      await page.screenshot({ path: '/tmp/lot3-order-detail.png' });
    } else {
      // No orders — test directly with order ID 1
      await page.goto('https://odesign-drupal.ddev.site/admin/boutique/commandes/1', { waitUntil: 'networkidle', timeout: 20000 });
      const detailTitle = await page.title();
      const isOk = !detailTitle.includes('500') && !detailTitle.includes('403');
      check('Order detail page /1 accessible', isOk, detailTitle);

      const detailWrapper = await page.$('.odesign-order-detail');
      check('.odesign-order-detail wrapper rendered (order 1)', !!detailWrapper);

      await page.screenshot({ path: '/tmp/lot3-order-detail.png' });
    }

    // Test 3: Nav active state on commandes-a-traiter
    await page.goto('https://odesign-drupal.ddev.site/admin/boutique/commandes-a-traiter', { waitUntil: 'networkidle', timeout: 20000 });
    const navActive = await page.$('.odesign-admin-nav__items a[aria-current="page"]');
    check('Nav Commandes active on actionable page', !!navActive);

  } catch (e) {
    check('No crash', false, e.message);
  }

  const passed = results.filter(r => r.pass).length;
  console.log('\n' + passed + '/' + results.length + ' checks passed');
  await browser.close();
  process.exit(passed === results.length ? 0 : 1);
})();

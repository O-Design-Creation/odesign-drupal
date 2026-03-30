const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox'] });
  const p = await b.newPage();
  await p.setViewportSize({ width: 1280, height: 900 });
  await p.goto('http://web/user/login');
  await p.fill('#edit-name', 'admin'); await p.fill('#edit-pass', 'admin');
  await p.click('#edit-submit');
  await p.waitForURL(/\/(admin|user)/, { timeout: 10000 }).catch(()=>{});

  const pages = [
    { url: 'http://web/admin/commerce/orders', name: 'orders-list' },
    { url: 'http://web/admin/commerce/invoices', name: 'invoices-list' },
  ];

  for (const pg of pages) {
    await p.goto(pg.url, { waitUntil: 'networkidle' });
    await p.waitForTimeout(1000);
    await p.screenshot({ path: `/playwright/screenshots/${pg.name}.png`, fullPage: true });
    console.log(`✅ ${pg.name}`);
  }

  // Ouvrir une commande existante
  const orders = await p.$$('table tbody tr a');
  if (orders.length > 0) {
    await orders[0].click();
    await p.waitForTimeout(1500);
    await p.screenshot({ path: '/playwright/screenshots/order-detail.png', fullPage: true });
    console.log('✅ order-detail');
  }
  await b.close();
})();

const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox'] });
  const p = await b.newPage();
  await p.setViewportSize({ width: 1280, height: 900 });
  await p.goto('http://web/user/login');
  await p.fill('#edit-name', 'admin'); await p.fill('#edit-pass', 'admin');
  await p.click('#edit-submit');
  await p.waitForURL(/\/(admin|user)/, { timeout: 10000 }).catch(()=>{});

  // Ouvrir la liste et cliquer sur "Voir" de la première commande
  await p.goto('http://web/admin/commerce/orders', { waitUntil: 'networkidle' });
  await p.waitForTimeout(800);
  const viewBtn = await p.$('table tbody tr:first-child .dropbutton-action a');
  if (viewBtn) {
    await viewBtn.click();
    await p.waitForTimeout(2000);
    await p.screenshot({ path: '/playwright/screenshots/order-detail.png', fullPage: true });
    console.log('order-detail done');
  }

  // Page factures après traduction
  await p.goto('http://web/admin/commerce/invoices', { waitUntil: 'networkidle' });
  await p.waitForTimeout(600);
  await p.screenshot({ path: '/playwright/screenshots/invoices-fr.png', fullPage: true });
  console.log('invoices-fr done');

  await b.close();
})();

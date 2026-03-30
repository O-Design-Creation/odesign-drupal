const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox'] });
  const p = await b.newPage();
  await p.setViewportSize({ width: 1280, height: 900 });
  await p.goto('http://web/user/login');
  await p.fill('#edit-name', 'admin'); await p.fill('#edit-pass', 'admin');
  await p.click('#edit-submit');
  await p.waitForURL(/\/(admin|user)/, { timeout: 10000 }).catch(()=>{});

  // Page liste des promotions
  await p.goto('http://web/admin/commerce/promotions', { waitUntil: 'networkidle' });
  await p.waitForTimeout(1000);
  await p.screenshot({ path: '/playwright/screenshots/promotions-list.png', fullPage: true });

  // Page ajout d'une promotion
  await p.goto('http://web/promotion/add', { waitUntil: 'networkidle' });
  await p.waitForTimeout(1000);
  await p.screenshot({ path: '/playwright/screenshots/promotions-add.png', fullPage: true });

  console.log('Done');
  await b.close();
})();

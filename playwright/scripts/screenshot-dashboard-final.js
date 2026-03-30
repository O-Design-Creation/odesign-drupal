const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox'] });
  const p = await b.newPage();
  await p.setViewportSize({ width: 1280, height: 900 });
  await p.goto('http://web/user/login');
  await p.fill('#edit-name', 'admin'); await p.fill('#edit-pass', 'admin');
  await p.click('#edit-submit');
  await p.waitForURL(/\/(admin|user)/, { timeout: 10000 }).catch(()=>{});
  await p.goto('http://web/admin/boutique', { waitUntil: 'networkidle' });
  await p.waitForTimeout(2000);
  await p.screenshot({ path: '/playwright/screenshots/dashboard-tck013.png', fullPage: true });
  console.log('Done');
  await b.close();
})();

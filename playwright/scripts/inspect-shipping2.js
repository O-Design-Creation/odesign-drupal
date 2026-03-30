const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox'] });
  const p = await b.newPage();
  await p.goto('http://web/user/login');
  await p.fill('#edit-name', 'admin'); await p.fill('#edit-pass', 'admin');
  await p.click('#edit-submit');
  await p.waitForURL(/\/(admin|user)/, { timeout: 10000 }).catch(()=>{});
  await p.goto('http://web/admin/commerce/shipping-methods', { waitUntil: 'networkidle' });
  await p.waitForTimeout(1000);
  // Get all children of .region-content with their class names
  const html = await p.$eval('.region.region-content', el => el.innerHTML.substring(0, 3000));
  console.log(html);
  await b.close();
})();

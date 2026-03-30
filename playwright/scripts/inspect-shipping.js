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
  const blocks = await p.$$eval('*', els =>
    els.filter(el => (el.innerText || '').includes('ShipStation'))
       .map(el => ({ tag: el.tagName, cls: el.className.substring(0,80), id: el.id }))
       .slice(0, 6)
  );
  console.log(JSON.stringify(blocks, null, 2));
  await b.close();
})();

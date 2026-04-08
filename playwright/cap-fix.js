const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true });
  const p = await ctx.newPage();
  await p.goto('https://odesign-drupal.ddev.site/user/reset/1/1775653083/9adjwXl8YyUE4_kqwsJXk53oPEZAlKQ5u0RC7ONNTTU/login', { waitUntil: 'networkidle', timeout: 25000 });
  const btn = await p.$('input[type="submit"]');
  if (btn) { await btn.click(); await p.waitForTimeout(1000); }
  await p.goto('https://odesign-drupal.ddev.site/admin/boutique/personnalisation/templates', { waitUntil: 'networkidle', timeout: 20000 });
  await p.waitForTimeout(600);
  await p.screenshot({ path: '/tmp/perso-liste-fixed.png' });
  await b.close();
  console.log('done');
})().catch(e => { console.error(e.message); process.exit(1); });

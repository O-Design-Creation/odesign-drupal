const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true });
  const p = await ctx.newPage();
  await p.goto('https://odesign-drupal.ddev.site/user/reset/1/1775652991/of7mbBTos1tRvhv3-LnNUhJUcyNEyBwq8BjhaLXL0YE/login', { waitUntil: 'networkidle', timeout: 25000 });
  const btn = await p.$('input[type="submit"]');
  if (btn) { await btn.click(); await p.waitForTimeout(1000); }
  await p.goto('https://odesign-drupal.ddev.site/admin/boutique/personnalisation/templates', { waitUntil: 'networkidle', timeout: 20000 });
  await p.waitForTimeout(600);
  await p.screenshot({ path: '/tmp/perso-empty-state.png' });
  console.log('done');
  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });

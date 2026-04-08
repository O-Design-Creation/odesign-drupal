const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true });
  const p = await ctx.newPage();
  await p.goto('https://odesign-drupal.ddev.site/user/reset/1/1775653631/ziWove4X0M4OZMY8xXPHhqLPIyQP5bHD1Brs0zMWUoM/login', { waitUntil: 'networkidle', timeout: 25000 });
  const btn = await p.$('input[type="submit"]'); if (btn) { await btn.click(); await p.waitForTimeout(1000); }

  // Page doc
  await p.goto('https://odesign-drupal.ddev.site/admin/boutique/documentation/09-personnalisation', { waitUntil: 'networkidle', timeout: 20000 });
  await p.waitForTimeout(600);
  await p.screenshot({ path: '/tmp/doc-page-perso.png' });
  console.log('1: page doc');

  // Liste modèles (label FR)
  await p.goto('https://odesign-drupal.ddev.site/admin/boutique/personnalisation/templates', { waitUntil: 'networkidle', timeout: 20000 });
  await p.waitForTimeout(500);
  await p.screenshot({ path: '/tmp/doc-liste-fr.png' });
  console.log('2: liste FR');

  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });

const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true });
  const p = await ctx.newPage();
  await p.goto('https://odesign-drupal.ddev.site/user/reset/2/1775652024/-wZdephzPQMmGJJzw2a8NjXEFJWVKXZ1os2CFctq57M/login', { waitUntil: 'domcontentloaded', timeout: 20000 });
  await p.waitForTimeout(500);
  // Liste des templates
  await p.goto('https://odesign-drupal.ddev.site/admin/boutique/personnalisation/templates', { waitUntil: 'domcontentloaded', timeout: 20000 });
  await p.waitForTimeout(800);
  await p.screenshot({ path: '/tmp/perso-list.png' });
  console.log('1: liste');
  // Éditer un template existant
  await p.goto('https://odesign-drupal.ddev.site/admin/boutique/personnalisation/templates/gravure_photo/edit', { waitUntil: 'domcontentloaded', timeout: 20000 });
  await p.waitForTimeout(800);
  await p.screenshot({ path: '/tmp/perso-edit.png', fullPage: true });
  console.log('2: edit gravure_photo');
  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });

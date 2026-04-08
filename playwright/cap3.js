const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true });
  const p = await ctx.newPage();
  await p.goto('https://odesign-drupal.ddev.site/user/reset/2/1775652106/xNBZmfcDlmD4v8Fm9sj7bWjx2PgOxZyINtrRMHz-yUc/login', { waitUntil: 'domcontentloaded', timeout: 20000 });
  await p.waitForTimeout(500);

  // Liste templates
  await p.goto('https://odesign-drupal.ddev.site/admin/boutique/personnalisation/templates', { waitUntil: 'domcontentloaded', timeout: 20000 });
  await p.waitForTimeout(600);
  await p.screenshot({ path: '/tmp/perso-list2.png' });
  console.log('1: liste');

  // Éditer gravure_photo (URL correcte)
  await p.goto('https://odesign-drupal.ddev.site/admin/boutique/personnalisation/templates/gravure_photo/modifier', { waitUntil: 'domcontentloaded', timeout: 20000 });
  await p.waitForTimeout(600);
  await p.screenshot({ path: '/tmp/perso-edit2.png', fullPage: true });
  console.log('2: edit gravure_photo');

  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });

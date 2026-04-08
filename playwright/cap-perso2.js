const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true });
  const p = await ctx.newPage();

  await p.goto('https://odesign-drupal.ddev.site/user/reset/1/1775652540/0T4kbmai0wl8HGImnyGB_IDiVyrrCigi2KM8kSAwOQA/login', { waitUntil: 'domcontentloaded', timeout: 20000 });
  await p.waitForTimeout(500);

  // Formulaire création
  await p.goto('https://odesign-drupal.ddev.site/admin/boutique/personnalisation/add', { waitUntil: 'networkidle', timeout: 20000 });
  await p.waitForTimeout(1000);

  // Debug: voir les champs disponibles
  const inputs = await p.$$eval('input, textarea, select', els => els.map(e => e.id + '|' + e.name + '|' + e.type));
  console.log('Fields:', inputs.slice(0, 10).join('\n'));

  await p.screenshot({ path: '/tmp/doc-perso-add-debug.png', fullPage: true });
  console.log('debug screenshot saved');

  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });

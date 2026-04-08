const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox', '--ignore-certificate-errors'] });
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true });
  const page = await ctx.newPage();
  await page.goto('https://odesign-drupal.ddev.site/user/reset/2/1775651904/5FC0HQQaBLV3D7p4ON4daLzohi0WGbZLpFW7dCrMuQA/login', { waitUntil: 'domcontentloaded', timeout: 20000 });
  await page.waitForTimeout(1000);
  await page.goto('https://odesign-drupal.ddev.site/admin/boutique/personnalisation/templates', { waitUntil: 'domcontentloaded', timeout: 20000 });
  await page.waitForTimeout(1000);
  await page.screenshot({ path: '/tmp/perso-templates-list.png', fullPage: false });
  console.log('Screenshot 1: liste');
  await page.goto('https://odesign-drupal.ddev.site/admin/boutique/personnalisation/templates/ajouter', { waitUntil: 'domcontentloaded', timeout: 20000 });
  await page.waitForTimeout(1000);
  await page.screenshot({ path: '/tmp/perso-template-add.png', fullPage: true });
  console.log('Screenshot 2: formulaire');
  await browser.close();
})().catch(e => { console.error(e.message); process.exit(1); });

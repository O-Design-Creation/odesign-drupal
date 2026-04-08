const { chromium } = require('playwright');
const path = require('path');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const ctx = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1280, height: 800 } });
  const page = await ctx.newPage();
  await page.goto('https://odesign-drupal.ddev.site/', { waitUntil: 'networkidle', timeout: 25000 });
  
  const outDir = path.resolve(__dirname, '../docs/utilisateur/screenshots');
  const outDirDoc = path.resolve(__dirname, '../web/modules/custom/odesign_admin/images/doc-screenshots');
  
  const widget = await page.$('.campagne-widget');
  if (widget) {
    await widget.screenshot({ path: path.join(outDir, 'campagnes-widget-homepage.png') });
    await widget.screenshot({ path: path.join(outDirDoc, 'campagnes-widget-homepage.png') });
    console.log('OK: campagnes-widget-homepage.png');
  } else {
    console.log('WARN: .campagne-widget non trouvé');
    await page.screenshot({ path: path.join(outDir, 'campagnes-widget-homepage-debug.png'), fullPage: false });
  }
  
  await browser.close();
})().catch(e => { console.error(e.message); process.exit(1); });

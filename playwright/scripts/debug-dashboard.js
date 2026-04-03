const { chromium } = require('playwright');
const ULI = 'https://odesign-drupal.ddev.site/user/reset/1/1775252569/TsE1rarKO3zXh6gN7JlX_hyLpdhGDTxvfnfP8jIgvmY/login';
(async () => {
  const b = await chromium.launch({ args: ['--ignore-certificate-errors'] });
  const ctx = await b.newContext({ ignoreHTTPSErrors: true });
  const p = await ctx.newPage();
  p.setDefaultTimeout(15000);
  await p.goto(ULI, { waitUntil: 'domcontentloaded' });
  try { await p.waitForSelector('input[type="submit"]', { timeout: 4000 }); await p.click('input[type="submit"]'); await p.waitForTimeout(1200); } catch(e) {}
  await p.goto('https://odesign-drupal.ddev.site/admin/boutique', { waitUntil: 'networkidle' });
  const stats = await p.$$('.odesign-dashboard__stat');
  console.log('stats found:', stats.length);
  // Check all class names starting with odesign-dashboard
  const classes = await p.evaluate(() => {
    const els = document.querySelectorAll('[class*="odesign-dashboard"]');
    return [...new Set([...els].map(e => e.className))].slice(0, 10);
  });
  console.log('odesign-dashboard classes:', classes);
  await b.close();
})();

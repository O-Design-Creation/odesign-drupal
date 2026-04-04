const { chromium } = require('playwright');
const BASE = 'http://odesign-drupal.ddev.site';

(async () => {
  const browser = await chromium.launch();

  // Desktop
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1440, height: 900 });
  await page.goto(BASE, { waitUntil: 'networkidle' });
  const stripD = await page.$('.newsletter-strip');
  if (stripD) {
    await stripD.scrollIntoViewIfNeeded();
    await page.waitForTimeout(600);
    await stripD.screenshot({ path: '/tmp/newsletter-desktop.png' });
    console.log('Desktop OK');
  } else {
    console.log('ERREUR desktop: .newsletter-strip absent');
  }

  // Mobile
  const mobile = await browser.newPage();
  await mobile.setViewportSize({ width: 390, height: 844 });
  await mobile.goto(BASE, { waitUntil: 'networkidle' });
  const stripM = await mobile.$('.newsletter-strip');
  if (stripM) {
    await stripM.scrollIntoViewIfNeeded();
    await mobile.waitForTimeout(600);
    await stripM.screenshot({ path: '/tmp/newsletter-mobile.png' });
    console.log('Mobile OK');
  } else {
    console.log('ERREUR mobile: .newsletter-strip absent');
  }

  await browser.close();
})();

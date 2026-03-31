const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--font-render-hinting=none'] });
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto('http://odesign-drupal.ddev.site/blog', { waitUntil: 'networkidle' });
  await page.evaluate(() => document.fonts.ready);
  await page.screenshot({ path: 'playwright/screenshots/blog-test.png', fullPage: true });
  console.log('Screenshot saved');
  await browser.close();
})();

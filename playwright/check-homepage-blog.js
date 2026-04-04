const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--ignore-certificate-errors'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto('https://odesign-drupal.ddev.site/', { waitUntil: 'networkidle' });
  // scroll to blog section
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight * 0.75));
  await page.waitForTimeout(500);
  await page.screenshot({ path: '/tmp/homepage-blog-section.png', clip: { x: 0, y: 0, width: 1280, height: 900 } });
  // check if images exist
  const imgs = await page.$$eval('.home-bridge__article img, .home-bridge__article-media img', els => els.map(e => e.src));
  console.log('Blog images found:', imgs.length, imgs);
  await browser.close();
})();

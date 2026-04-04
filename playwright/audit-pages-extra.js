const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ args: ['--ignore-certificate-errors'] });
  const pages = [
    ['404', 'https://odesign-drupal.ddev.site/page-inexistante'],
    ['login', 'https://odesign-drupal.ddev.site/user/login'],
    ['naissance', 'https://odesign-drupal.ddev.site/occasions/naissance-bebe'],
    ['famille', 'https://odesign-drupal.ddev.site/occasions/cadeaux-personnalises-famille'],
    ['checkout', 'https://odesign-drupal.ddev.site/checkout'],
    ['contact', 'https://odesign-drupal.ddev.site/contact'],
  ];
  for (const [name, url] of pages) {
    for (const [w, h, suf] of [[1280, 800, 'desktop'], [390, 844, 'mobile']]) {
      const page = await browser.newPage();
      await page.setViewportSize({ width: w, height: h });
      await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
      await page.screenshot({ path: `/tmp/extra-${name}-${suf}.png`, fullPage: false });
      await page.close();
    }
  }
  await browser.close();
  console.log('Done');
})();

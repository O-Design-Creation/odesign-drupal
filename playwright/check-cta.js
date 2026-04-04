const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--ignore-certificate-errors'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1280, height: 800 });
  await page.goto('https://odesign-drupal.ddev.site/occasions/naissance-bebe', { waitUntil: 'networkidle' });
  // Click "VOIR TOUT LE CATALOGUE"
  const href = await page.$eval('a:has-text("VOIR TOUT LE CATALOGUE")', a => a.href);
  console.log('CTA href:', href);
  await page.click('a:has-text("VOIR TOUT LE CATALOGUE")');
  await page.waitForLoadState('networkidle');
  console.log('Destination URL:', page.url());
  
  // Also check hero CTA on homepage
  const page2 = await browser.newPage();
  await page2.goto('https://odesign-drupal.ddev.site', { waitUntil: 'networkidle' });
  const heroCta = await page2.$eval('a:has-text("Découvrir les univers"), a:has-text("DÉCOUVRIR LES UNIVERS"), .home-hero a', a => ({ href: a.href, text: a.textContent.trim() })).catch(() => null);
  console.log('Hero CTA:', heroCta);
  
  await browser.close();
})();

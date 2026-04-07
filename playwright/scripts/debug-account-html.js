const { chromium } = require('@playwright/test');

const BASE = 'https://odesign-drupal.ddev.site';
const PAGES = ['/user/4', '/user/4/orders/42'];

(async () => {
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await ctx.newPage();

  await page.goto(BASE + '/user/login');
  await page.waitForLoadState('networkidle');
  await page.fill('#edit-name', 'client_test');
  await page.fill('#edit-pass', 'Test1234!');
  await page.click('#edit-submit');
  await page.waitForLoadState('networkidle');
  console.log('After login URL:', page.url());

  for (const path of PAGES) {
    await page.goto(BASE + path, { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');
    console.log('\n=== ' + path + ' ===');
    console.log('Final URL:', page.url());
    // Check html lang
    const lang = await page.$eval('html', el => el.getAttribute('lang')).catch(() => 'MISSING');
    const title = await page.$eval('title', el => el.textContent).catch(() => 'MISSING');
    const h1 = await page.$eval('h1', el => el.textContent.trim()).catch(() => 'MISSING');
    const bodySnippet = await page.$eval('body', el => el.innerHTML.substring(0, 500)).catch(() => 'ERROR');
    console.log('html[lang]:', lang);
    console.log('<title>:', title);
    console.log('<h1>:', h1);
    console.log('body snippet:', bodySnippet.replace(/\n/g, ' ').substring(0, 300));
  }

  await browser.close();
})();

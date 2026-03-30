const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox'] });
  const p = await b.newPage();
  await p.setViewportSize({ width: 1280, height: 900 });
  await p.goto('http://mailhog:8025', { waitUntil: 'networkidle', timeout: 10000 }).catch(()=>{});
  await p.waitForTimeout(2000);
  await p.screenshot({ path: '/playwright/screenshots/mailhog-inbox.png', fullPage: true });
  console.log('URL:', p.url(), '| Title:', await p.title());
  await b.close();
})();

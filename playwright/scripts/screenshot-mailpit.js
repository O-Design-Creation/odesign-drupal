const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox', '--ignore-certificate-errors'] });
  const p = await b.newPage();
  await p.setViewportSize({ width: 1280, height: 900 });
  // Mailpit accessible via HTTP sur le réseau interne DDEV
  await p.goto('http://web:8025', { waitUntil: 'networkidle', timeout: 10000 }).catch(e => console.log('web:8025 err:', e.message));
  await p.waitForTimeout(1000);
  console.log('URL:', p.url(), '| Title:', await p.title().catch(()=>'?'));
  await p.screenshot({ path: '/playwright/screenshots/mailpit-inbox.png', fullPage: true });
  await b.close();
})();

const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox'] });
  const p = await b.newPage();
  await p.setViewportSize({ width: 1280, height: 1000 });
  await p.goto('http://web:8025', { waitUntil: 'networkidle' });
  await p.waitForTimeout(1500);

  // Cliquer sur le premier email (commande)
  await p.locator('.message').first().click();
  await p.waitForTimeout(3000);
  await p.screenshot({ path: '/playwright/screenshots/mailpit-open-email.png', fullPage: false });

  // Chercher et attendre l'iframe de preview HTML
  const iframeEl = await p.$('iframe');
  if (iframeEl) {
    console.log('iframe found');
    const frame = await iframeEl.contentFrame();
    await frame.waitForLoadState('load').catch(()=>{});
    await p.waitForTimeout(1000);
  } else {
    console.log('no iframe');
    const bodyText = await p.locator('body').innerText();
    console.log('Body snippet:', bodyText.substring(0, 200));
  }
  await p.screenshot({ path: '/playwright/screenshots/mailpit-open-email.png', fullPage: false });
  await b.close();
})();

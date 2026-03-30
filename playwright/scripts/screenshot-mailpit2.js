const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox'] });
  const p = await b.newPage();
  await p.setViewportSize({ width: 1280, height: 900 });
  await p.goto('http://web:8025', { waitUntil: 'networkidle' });
  await p.waitForTimeout(1000);
  await p.screenshot({ path: '/playwright/screenshots/mailpit-with-order.png', fullPage: true });
  // Cliquer sur le dernier email (commande)
  const emails = await p.$$('.messages-list .message');
  if (emails.length > 0) {
    // Cliquer sur le premier email (le plus récent)
    await emails[0].click();
    await p.waitForTimeout(1500);
    await p.screenshot({ path: '/playwright/screenshots/mailpit-order-receipt.png', fullPage: true });
    console.log('Email receipt screenshot taken');
  }
  await b.close();
})();

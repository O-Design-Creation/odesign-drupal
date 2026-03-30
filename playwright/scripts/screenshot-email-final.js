const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox'] });
  const p = await b.newPage();
  await p.setViewportSize({ width: 1280, height: 900 });
  await p.goto('http://web:8025', { waitUntil: 'networkidle' });
  await p.waitForTimeout(1000);
  await p.locator('.message').first().click();
  await p.waitForTimeout(2500);
  // S'assurer que l'onglet HTML est sélectionné
  await p.locator('button:has-text("HTML")').first().click().catch(()=>{});
  await p.waitForTimeout(1500);
  await p.screenshot({ path: '/playwright/screenshots/email-receipt-html-final.png', fullPage: false });
  // Screenshot de l'iframe seul
  const iframeEl = await p.$('iframe');
  if (iframeEl) {
    const frame = await iframeEl.contentFrame();
    await frame.waitForLoadState('domcontentloaded').catch(()=>{});
    await p.waitForTimeout(500);
    const box = await iframeEl.boundingBox();
    await p.setViewportSize({ width: 1280, height: Math.max(900, Math.ceil(box.y + box.height) + 50) });
    await p.screenshot({ path: '/playwright/screenshots/email-receipt-html-final.png', fullPage: true });
  }
  console.log('Done');
  await b.close();
})();

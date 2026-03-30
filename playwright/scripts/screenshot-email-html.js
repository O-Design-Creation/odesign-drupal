const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox'] });
  const p = await b.newPage();
  await p.setViewportSize({ width: 1280, height: 1000 });
  await p.goto('http://web:8025', { waitUntil: 'networkidle' });
  await p.waitForTimeout(1000);
  await p.locator('.message').first().click();
  await p.waitForTimeout(2000);

  // S'assurer que l'onglet HTML est sélectionné
  await p.locator('button:has-text("HTML")').first().click().catch(()=>{});
  await p.waitForTimeout(1000);

  // Trouver l'iframe et prendre une screenshot de son contenu
  const iframeEl = await p.$('iframe');
  if (iframeEl) {
    const box = await iframeEl.boundingBox();
    console.log('iframe box:', JSON.stringify(box));
    const frame = await iframeEl.contentFrame();
    await frame.waitForLoadState('load').catch(()=>{});
    await p.waitForTimeout(1000);
    // Agrandir la fenêtre pour voir l'iframe
    await p.setViewportSize({ width: 1280, height: 3000 });
    // Screenshot de la page complète
    await p.screenshot({ path: '/playwright/screenshots/email-html-full.png', fullPage: true });
    // Screenshot de juste l'iframe via evaluate
    const iframeHtml = await frame.content();
    console.log('iframe HTML preview:', iframeHtml.substring(0, 300));
  }
  await b.close();
})();

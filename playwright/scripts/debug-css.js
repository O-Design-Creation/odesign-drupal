const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox'] });
  const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await ctx.newPage();

  await page.goto('http://web/user/login');
  await page.fill('#edit-name', 'admin');
  await page.fill('#edit-pass', 'admin');
  await page.click('#edit-submit');
  await page.waitForURL(/\/(admin|user)/, { timeout: 10000 }).catch(() => {});
  await page.goto('http://web/admin/boutique', { waitUntil: 'networkidle' });
  await page.waitForTimeout(2000);

  const info = await page.evaluate(() => {
    const sheets = [...document.styleSheets].map(s => s.href || 'inline');
    const odesign = sheets.filter(h => h.includes('odesign') || h.includes('material') || h.includes('fonts.google'));

    const main = document.querySelector('main.page-content');
    const dash = document.querySelector('.odesign-dashboard');
    const ms = main ? getComputedStyle(main) : null;
    const ds = dash ? getComputedStyle(dash) : null;

    return {
      odesignSheets: odesign,
      totalSheets: sheets.length,
      mainPaddingLeft: ms ? ms.paddingInlineStart : 'MISSING',
      dashExists: !!dash,
      dashDisplay: ds ? ds.display : 'MISSING',
      dashPaddingLeft: ds ? ds.paddingInlineStart : 'MISSING',
      dashMaxWidth: ds ? ds.maxWidth : 'MISSING',
      quickActionsGrid: !!document.querySelector('.quick-actions-grid'),
      quickActionBtn: !!document.querySelector('.quick-action-btn'),
      fontsLoaded: [...document.fonts].filter(f => f.family.includes('Material')).map(f => f.family + ':' + f.status),
    };
  });

  console.log('=== CSS ODESIGN/MATERIAL ===');
  info.odesignSheets.forEach(s => console.log(' ', s));
  console.log('Total sheets:', info.totalSheets);
  console.log('=== main.page-content ===');
  console.log('  paddingInlineStart:', info.mainPaddingLeft);
  console.log('=== .odesign-dashboard ===');
  console.log('  exists:', info.dashExists);
  console.log('  display:', info.dashDisplay);
  console.log('  paddingInlineStart:', info.dashPaddingLeft);
  console.log('  maxWidth:', info.dashMaxWidth);
  console.log('=== COMPONENTS ===');
  console.log('  .quick-actions-grid:', info.quickActionsGrid);
  console.log('  .quick-action-btn:', info.quickActionBtn);
  console.log('=== FONTS ===');
  console.log('  Material:', JSON.stringify(info.fontsLoaded));

  await browser.close();
})().catch(e => { console.error(e.message); process.exit(1); });

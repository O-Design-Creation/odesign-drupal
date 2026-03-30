const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox'] });
  const p = await b.newPage();
  await p.setViewportSize({ width: 750, height: 1000 });
  await p.goto('http://web:8025', { waitUntil: 'networkidle' });
  await p.waitForTimeout(800);
  // API Mailpit
  const msgs = await p.evaluate(async () => {
    const r = await fetch('http://web:8025/api/v1/messages');
    return r.json();
  });
  console.log('API response keys:', Object.keys(msgs));
  const items = msgs.messages || msgs.items || [];
  if (items.length === 0) { console.log('No emails'); await b.close(); return; }
  const latest = items[0];
  console.log('Latest:', JSON.stringify(latest).substring(0, 200));
  const id = latest.ID || latest.id;
  await p.goto(`http://web:8025/view/${id}.html`, { waitUntil: 'networkidle' });
  await p.waitForTimeout(1500);
  await p.screenshot({ path: '/playwright/screenshots/email-order-receipt.png', fullPage: true });
  console.log('Done');
  await b.close();
})();

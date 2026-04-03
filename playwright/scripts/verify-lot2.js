const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  const browser = await chromium.launch({ headless: true, ignoreHTTPSErrors: true });
  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await context.newPage();
  const ULI = fs.readFileSync('/tmp/uli.txt', 'utf8').trim();
  const results = [];

  const check = (name, pass, detail) => {
    results.push({ name, pass });
    console.log((pass ? '✅' : '❌') + ' ' + name + (detail ? ' — ' + detail : ''));
  };

  try {
    await page.goto(ULI, { waitUntil: 'networkidle', timeout: 30000 });
    await page.goto('https://odesign-drupal.ddev.site/admin/boutique/configuration', { waitUntil: 'networkidle', timeout: 30000 });

    const title = await page.title();
    check('Config page loads (no 500)', !title.includes('Error') && !title.includes('500'), title);

    const vtabs = await page.$('.vertical-tabs');
    check('vertical_tabs rendered', !!vtabs);

    const tabLinks = await page.$$('.vertical-tabs__menu-item');
    check('6 tabs present', tabLinks.length === 6, tabLinks.length + ' tabs');

    // Check tab labels contain the expected sections
    const tabTexts = await Promise.all(tabLinks.map(t => t.textContent()));
    const allText = tabTexts.map(t => t.trim()).join(' | ');
    check('Tab Mailgun present', allText.includes('Mailgun'), allText);
    check('Tab Stripe present', allText.includes('Stripe'), allText);
    check('Tab Ma boutique present', allText.includes('boutique'), allText);
    check('Tab Hero present', allText.includes('Hero'), allText);
    check('Tab Je me présente present', allText.includes('pr') || allText.includes('sente'), allText);
    check('Tab Promotions present', allText.includes('Promotions') || allText.includes('volume'), allText);

    const saveBtn = await page.$('input[value*="Enregistrer"]');
    check('Save button present', !!saveBtn);

    const oldIntro = await page.$('.config-intro');
    check('Old monolithic intro gone', !oldIntro);

    // Click Mailgun tab and check smtp_username field
    const emailTab = tabLinks.find(async t => (await t.textContent()).includes('Mailgun'));
    if (emailTab) await emailTab.click();
    await page.waitForTimeout(400);
    const smtpField = await page.$('input[name="smtp_username"]');
    check('smtp_username field accessible', !!smtpField);

    await page.screenshot({ path: '/tmp/lot2-config.png' });

  } catch (e) {
    check('No crash', false, e.message);
  }

  const passed = results.filter(r => r.pass).length;
  console.log('\n' + passed + '/' + results.length + ' checks passed');
  await browser.close();
  process.exit(passed === results.length ? 0 : 1);
})();

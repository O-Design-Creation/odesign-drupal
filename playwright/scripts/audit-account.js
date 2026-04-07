const { chromium } = require('@playwright/test');
const { AxeBuilder } = require('@axe-core/playwright');
const fs = require('fs');

const BASE = 'https://odesign-drupal.ddev.site';

const PAGES = [
  { name: 'Dashboard Mon compte', path: '/user/4' },
  { name: 'Mes commandes', path: '/user/4/orders' },
  { name: 'Détail commande #42 (completed)', path: '/user/4/orders/42' },
  { name: 'Détail commande #45 (processing)', path: '/user/4/orders/45' },
  { name: 'Mon profil (edit)', path: '/user/4/edit' },
];

(async () => {
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await ctx.newPage();

  // Login
  await page.goto(BASE + '/user/login');
  await page.waitForLoadState('networkidle');
  await page.fill('#edit-name', 'client_test');
  await page.fill('#edit-pass', 'Test1234!');
  await page.click('#edit-submit');
  await page.waitForLoadState('networkidle');
  const loggedIn = page.url().includes('/user/');
  console.log(loggedIn ? '✅ Logged in as client_test' : '❌ Login failed: ' + page.url());
  if (!loggedIn) { await browser.close(); process.exit(1); }

  const results = [];

  for (const pg of PAGES) {
    const url = BASE + pg.path;
    await page.goto(url);
    await page.waitForLoadState('networkidle');
    const finalUrl = page.url();
    const redirected = !finalUrl.includes(pg.path);

    const axe = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
    const h1 = await page.$eval('h1', el => el.textContent.trim()).catch(() => null);
    const bodyText = await page.$eval('body', b => b.innerText).catch(() => '');
    const hasBackLink = /(retour|← )/i.test(bodyText);
    const hasOrderTable = !!(await page.$('table').catch(() => null));
    const orderRows = hasOrderTable ? (await page.$$('table tbody tr').catch(() => [])).length : 0;
    const hasStatusBadge = !!(await page.$('.order-status--completed, .order-status--processing').catch(() => null));
    const hasDashboardLinks = !!(await page.$('.account-dashboard__links').catch(() => null));

    results.push({ page: pg.name, path: pg.path, final_url: finalUrl, redirected, h1,
      hasBackLink, orderRows, hasStatusBadge, hasDashboardLinks,
      wcag: axe.violations.length,
      violations: axe.violations.map(v => ({ id: v.id, impact: v.impact, help: v.help })) });

    console.log('\n📄 ' + pg.name);
    console.log('   URL: ' + finalUrl + (redirected ? ' ⚠️ REDIRECT' : ''));
    console.log('   H1: "' + h1 + '"');
    if (hasDashboardLinks) console.log('   Dashboard links: ✅');
    if (hasBackLink)       console.log('   Back link: ✅');
    if (hasOrderTable)     console.log('   Lignes commandes: ' + orderRows);
    if (hasStatusBadge)    console.log('   Status badge: ✅');
    console.log('   WCAG: ' + axe.violations.length + ' violation(s)');
    axe.violations.forEach(v => console.log('   ❌ [' + v.impact + '] ' + v.id + ': ' + v.help));
  }

  fs.writeFileSync('/playwright/scripts/audit-account-results.json', JSON.stringify(results, null, 2));
  await browser.close();
  console.log('\n✅ Audit terminé');
})();

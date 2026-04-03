const { chromium } = require('playwright');

const ULI = process.argv[2];
const BASE = 'https://odesign-drupal.ddev.site';
const SCREENSHOTS = '/home/estoclet/perso_projects/odesign-project/odesign-drupal/playwright/screenshots/verify-lot0';

const { execSync } = require('child_process');
execSync(`mkdir -p ${SCREENSHOTS}`);

(async () => {
  const browser = await chromium.launch({ args: ['--ignore-certificate-errors'] });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1440, height: 900 });

  // Auth
  await page.goto(ULI, { waitUntil: 'networkidle' });

  const checks = [
    { url: `${BASE}/admin/boutique/commandes-a-traiter`, name: '01-commandes-a-traiter', check: 'no-500' },
    { url: `${BASE}/admin/boutique`, name: '02-dashboard-nav-active', check: 'nav' },
    { url: `${BASE}/admin/boutique/configuration`, name: '03-config-nav-active', check: 'nav' },
    { url: `${BASE}/admin/content/media`, name: '04-media-articles-not-active', check: 'nav' },
    { url: `${BASE}/node/add/article`, name: '05-article-form-tabs', check: 'tabs' },
    { url: `${BASE}/admin/structure/taxonomy/manage/life_occasion/add`, name: '06-taxonomy-form', check: 'taxonomy' },
  ];

  let passed = 0, failed = 0;
  
  for (const check of checks) {
    await page.goto(check.url, { waitUntil: 'networkidle', timeout: 15000 }).catch(() => {});
    await page.screenshot({ path: `${SCREENSHOTS}/${check.name}.png`, fullPage: false });
    
    const title = await page.title();
    const h1 = await page.$eval('h1', el => el.textContent.trim()).catch(() => '?');
    const is500 = title.includes('500') || title.includes('Error');
    
    // Check nav active state
    const activeLinks = await page.$$eval('[aria-current="page"]', els => els.map(e => e.getAttribute('href')));
    
    // Check field_group tabs on article form
    const hasTabs = await page.$$eval('.field-group-tabs-wrapper, .field-group-tab, [data-drupal-selector*="group-article"]', els => els.length > 0).catch(() => false);
    
    // Check format selector hidden
    const hasFormatSelector = await page.$('.js-filter-wrapper, .filter-wrapper').then(el => !!el).catch(() => false);
    
    console.log(`${is500 ? '❌' : '✅'} ${check.name}`);
    console.log(`   Title: ${title}`);
    console.log(`   H1: ${h1}`);
    console.log(`   Active nav: ${JSON.stringify(activeLinks)}`);
    if (check.check === 'tabs') console.log(`   Has tabs: ${hasTabs} | Has format selector: ${hasFormatSelector}`);
    console.log('');
    
    if (!is500) passed++; else failed++;
  }
  
  console.log(`\nRésultat: ${passed} OK / ${failed} erreurs`);
  await browser.close();
})();

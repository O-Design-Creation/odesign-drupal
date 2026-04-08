const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true });
  const p = await ctx.newPage();
  await p.goto('https://odesign-drupal.ddev.site/user/reset/2/1775652270/DL-nb560nsKfDLemeGEq8gaXQFki9XskddHgLyrRRlQ/login', { waitUntil: 'domcontentloaded', timeout: 20000 });
  await p.waitForTimeout(500);

  // 1. Nav avec Configuration ▾
  await p.goto('https://odesign-drupal.ddev.site/admin/boutique', { waitUntil: 'domcontentloaded', timeout: 20000 });
  await p.waitForTimeout(600);
  // Ouvrir le dropdown Configuration
  const configBtn = await p.$('[data-group="configuration"], button:has-text("Configuration")');
  if (configBtn) { await configBtn.click(); await p.waitForTimeout(400); }
  await p.screenshot({ path: '/tmp/nav-config-group.png' });
  console.log('1: nav Configuration groupe');

  // 2. Liste templates (titre FR)
  await p.goto('https://odesign-drupal.ddev.site/admin/boutique/personnalisation/templates', { waitUntil: 'domcontentloaded', timeout: 20000 });
  await p.waitForTimeout(600);
  await p.screenshot({ path: '/tmp/perso-list-fr.png' });
  console.log('2: liste templates FR');

  // 3. Edit template avec champs
  await p.goto('https://odesign-drupal.ddev.site/admin/boutique/personnalisation/templates/gravure_photo/modifier', { waitUntil: 'domcontentloaded', timeout: 20000 });
  await p.waitForTimeout(600);
  await p.screenshot({ path: '/tmp/perso-edit-full.png', fullPage: true });
  console.log('3: edit template complet');

  // 4. Fiche produit avec champ template (vérifier que le champ est visible)
  // Chercher un produit existant
  await p.goto('https://odesign-drupal.ddev.site/admin/commerce/products', { waitUntil: 'domcontentloaded', timeout: 20000 });
  await p.waitForTimeout(600);
  // Cliquer sur Modifier du premier produit
  const editLink = await p.$('table tbody tr:first-child a:has-text("Modifier"), table tbody tr:first-child a[href*="edit"]');
  if (editLink) {
    await editLink.click();
    await p.waitForTimeout(800);
    await p.screenshot({ path: '/tmp/produit-champ-template.png', fullPage: false });
    console.log('4: produit - champ template visible');
  } else {
    console.log('4: pas de lien edit trouvé');
  }

  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });

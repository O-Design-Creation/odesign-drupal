const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true });
  const p = await ctx.newPage();
  await p.goto('https://odesign-drupal.ddev.site/user/reset/1/1775653123/-dth2bC1b5pH7Gnpnb5pCjiXNuKlmD5zCNq25ervUiI/login', { waitUntil: 'networkidle', timeout: 25000 });
  const btn = await p.$('input[type="submit"]'); if (btn) { await btn.click(); await p.waitForTimeout(1000); }

  // 1. Liste (déjà capturée, mais refaire propre)
  await p.goto('https://odesign-drupal.ddev.site/admin/boutique/personnalisation/templates', { waitUntil: 'networkidle', timeout: 20000 });
  await p.waitForTimeout(500);
  await p.screenshot({ path: '/tmp/doc-perso-liste.png' });
  console.log('1: liste');

  // 2. Formulaire de création (vierge)
  await p.goto('https://odesign-drupal.ddev.site/admin/boutique/personnalisation/templates/ajouter', { waitUntil: 'networkidle', timeout: 20000 });
  await p.waitForTimeout(600);
  await p.screenshot({ path: '/tmp/doc-perso-nouveau.png', fullPage: true });
  console.log('2: form création');

  // 3. Formulaire avec 1 champ ajouté (texte simple)
  await p.fill('[name="label"]', 'Prénom à graver');
  const addBtn = await p.$('input[value*="Ajouter"]');
  if (addBtn) { await addBtn.click(); await p.waitForTimeout(1500); }
  await p.screenshot({ path: '/tmp/doc-perso-champ-ajout.png', fullPage: true });
  console.log('3: champ ajouté');

  // 4. Champ produit template — trouver l'arbre de vie
  await p.goto('https://odesign-drupal.ddev.site/admin/commerce/products', { waitUntil: 'networkidle', timeout: 20000 });
  await p.waitForTimeout(500);
  const rows = await p.$$eval('tr', rs => rs.map(r => ({ text: r.innerText.slice(0,80), href: (r.querySelector('a[href*="/edit"]') || {}).href })));
  const arbre = rows.find(r => r.text.includes('Scrabble') || r.text.includes('rbre de vie'));
  if (arbre && arbre.href) {
    await p.goto(arbre.href, { waitUntil: 'networkidle', timeout: 20000 });
    await p.waitForTimeout(1000);
    await p.evaluate(() => {
      const el = document.querySelector('.field--name-field-personalization-template');
      if (el) el.scrollIntoView({ block: 'center' });
    });
    await p.waitForTimeout(400);
    await p.screenshot({ path: '/tmp/doc-produit-champ-template.png' });
    console.log('4: champ template produit');
  }

  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });

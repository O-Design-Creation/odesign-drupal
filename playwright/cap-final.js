const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true });
  const p = await ctx.newPage();

  await p.goto('https://odesign-drupal.ddev.site/user/reset/1/1775652738/WilewtDGoLN7j8gdY9GlvP13c1pCcCqbqGySmoX-mwg/login', { waitUntil: 'networkidle', timeout: 25000 });
  await p.waitForTimeout(500);
  const btn = await p.$('input[type="submit"][value*="Connexion"], input[type="submit"]');
  if (btn) { await btn.click(); await p.waitForTimeout(1500); }
  console.log('URL:', p.url());

  // 1. Liste templates
  await p.goto('https://odesign-drupal.ddev.site/admin/boutique/personnalisation/templates', { waitUntil: 'networkidle', timeout: 20000 });
  await p.waitForTimeout(500);
  await p.screenshot({ path: '/tmp/doc-perso-liste.png' });
  console.log('1: liste OK -', p.url());

  // 2. Formulaire création
  await p.goto('https://odesign-drupal.ddev.site/admin/boutique/personnalisation/templates/ajouter', { waitUntil: 'networkidle', timeout: 20000 });
  await p.waitForTimeout(800);
  await p.screenshot({ path: '/tmp/doc-perso-nouveau.png', fullPage: true });
  const f = await p.$$eval('input, textarea, select', els => els.map(e => e.id).filter(Boolean).slice(0, 10));
  console.log('2: form fields:', f.join(', '));

  // Remplir le label
  const labelField = await p.$('[name="label"], #edit-label');
  if (labelField) {
    await labelField.fill('Prénom à graver');
    // Ajouter champ
    const addBtn = await p.$('input[value*="Ajouter"], button:has-text("Ajouter un champ")');
    if (addBtn) { await addBtn.click(); await p.waitForTimeout(1500); }
    await p.screenshot({ path: '/tmp/doc-perso-champ.png', fullPage: true });
    console.log('3: champ ajouté');
  } else {
    console.log('3: label not found');
    // Dump HTML
    const html = await p.$eval('main, #main-content, .layout-main-wrapper', el => el.innerText.substring(0, 500)).catch(() => 'no main');
    console.log('Page content:', html);
  }

  // 4. Produit — champ template
  await p.goto('https://odesign-drupal.ddev.site/admin/commerce/products', { waitUntil: 'networkidle', timeout: 20000 });
  await p.waitForTimeout(500);
  const editLinks = await p.$$('td a:has-text("Modifier"), a[href*="/edit"]');
  if (editLinks.length > 0) {
    // Trouver l'arbre de vie
    const links = await p.$$eval('tr', rows => rows.map(r => ({ text: r.innerText.substring(0,60), href: (r.querySelector('a[href*="edit"]') || {}).href }))).catch(() => []);
    const arbre = links.find(l => l.text.includes('rbre') || l.text.includes('Scrabble'));
    if (arbre && arbre.href) {
      await p.goto(arbre.href, { waitUntil: 'networkidle', timeout: 20000 });
    } else {
      await editLinks[0].click();
      await p.waitForTimeout(1500);
    }
    await p.waitForTimeout(1000);
    await p.evaluate(() => {
      const el = document.querySelector('.field--name-field-personalization-template, [data-drupal-selector*="personalization"]');
      if (el) el.scrollIntoView({ block: 'center' });
    });
    await p.waitForTimeout(400);
    await p.screenshot({ path: '/tmp/doc-produit-template.png' });
    console.log('4: produit template field');
  }

  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });

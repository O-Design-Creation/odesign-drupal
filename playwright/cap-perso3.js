const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true });
  const p = await ctx.newPage();

  // Login — cliquer sur le bouton de connexion
  await p.goto('https://odesign-drupal.ddev.site/user/reset/1/1775652621/WzkuzMheoYMlSvla7W5TLS850axh-TMiAclms7yaGHw/login', { waitUntil: 'networkidle', timeout: 25000 });
  await p.waitForTimeout(500);
  // S'il y a un bouton "Se connecter"
  const loginBtn = await p.$('input[type="submit"], button[type="submit"]');
  if (loginBtn) { await loginBtn.click(); await p.waitForTimeout(1500); }
  console.log('URL after login:', p.url());

  // 1. Liste templates
  await p.goto('https://odesign-drupal.ddev.site/admin/boutique/personnalisation', { waitUntil: 'networkidle', timeout: 20000 });
  await p.waitForTimeout(800);
  await p.screenshot({ path: '/tmp/doc-perso-liste.png' });
  console.log('1: liste OK');

  // 2. Formulaire création
  await p.goto('https://odesign-drupal.ddev.site/admin/boutique/personnalisation/add', { waitUntil: 'networkidle', timeout: 20000 });
  await p.waitForTimeout(800);
  const f = await p.$$eval('input, textarea, select', els => els.map(e => (e.id||'?') + '|' + e.name).slice(0, 15));
  console.log('Form fields:', f.join(', '));
  await p.screenshot({ path: '/tmp/doc-perso-nouveau.png', fullPage: true });
  console.log('2: form OK');

  // 3. Remplir et ajouter un champ
  const labelField = await p.$('[name="label"]');
  if (labelField) {
    await labelField.fill('Prénom gravé');
    const addBtn = await p.$('input[value*="Ajouter"], button:has-text("Ajouter")');
    if (addBtn) {
      await addBtn.click();
      await p.waitForTimeout(1500);
    }
    await p.screenshot({ path: '/tmp/doc-perso-champ.png', fullPage: true });
    console.log('3: champ ajouté');
  } else {
    console.log('3: label field not found');
  }

  // 4. Produit — champ template
  await p.goto('https://odesign-drupal.ddev.site/admin/commerce/products', { waitUntil: 'networkidle', timeout: 20000 });
  await p.waitForTimeout(500);
  const editLinks = await p.$$('a:has-text("Modifier")');
  if (editLinks.length > 0) {
    await editLinks[0].click();
    await p.waitForTimeout(1500);
    // Scroll vers champ personalization_template
    await p.evaluate(() => {
      const el = document.querySelector('.field--name-field-personalization-template, [id*="personalization"]');
      if (el) el.scrollIntoView({ block: 'center' });
    });
    await p.waitForTimeout(500);
    await p.screenshot({ path: '/tmp/doc-produit-template.png' });
    console.log('4: produit template field');
  }

  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });

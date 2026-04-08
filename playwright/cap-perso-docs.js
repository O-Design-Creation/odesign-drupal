const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true });
  const p = await ctx.newPage();

  // Login
  await p.goto('https://odesign-drupal.ddev.site/user/reset/1/1775652540/0T4kbmai0wl8HGImnyGB_IDiVyrrCigi2KM8kSAwOQA/login', { waitUntil: 'domcontentloaded', timeout: 20000 });
  await p.waitForTimeout(500);

  // 1. Liste templates (vide)
  await p.goto('https://odesign-drupal.ddev.site/admin/boutique/personnalisation', { waitUntil: 'domcontentloaded', timeout: 15000 });
  await p.waitForTimeout(500);
  await p.screenshot({ path: '/tmp/doc-perso-liste.png' });
  console.log('1: liste templates');

  // 2. Formulaire création d'un template
  await p.goto('https://odesign-drupal.ddev.site/admin/boutique/personnalisation/add', { waitUntil: 'domcontentloaded', timeout: 15000 });
  await p.waitForTimeout(800);
  await p.screenshot({ path: '/tmp/doc-perso-nouveau.png', fullPage: true });
  console.log('2: formulaire création');

  // 3. Remplir un template complet pour screenshot
  await p.fill('#edit-label', 'Prénom gravé');
  await p.fill('#edit-id', 'prenom_grave');
  // Ajouter premier champ
  const addBtn = await p.$('input[value="Ajouter un champ"], button:has-text("Ajouter")');
  if (addBtn) {
    await addBtn.click();
    await p.waitForTimeout(1000);
    await p.screenshot({ path: '/tmp/doc-perso-champ-ajoute.png', fullPage: true });
    console.log('3: champ ajouté');
  }

  // 4. Template avec modificateur de prix — remplir le formulaire
  await p.screenshot({ path: '/tmp/doc-perso-form-filled.png', fullPage: true });
  console.log('4: form filled');

  // 5. Fiche produit — champ template
  await p.goto('https://odesign-drupal.ddev.site/admin/commerce/products', { waitUntil: 'domcontentloaded', timeout: 15000 });
  await p.waitForTimeout(500);
  // Trouver "Arbre de vie"
  const editLink = await p.$('a:has-text("Modifier")');
  if (editLink) {
    await editLink.click();
    await p.waitForTimeout(1000);
    // Scroller jusqu'au champ template
    await p.evaluate(() => {
      const el = document.querySelector('[name="field_personalization_template[0][target_id]"], .field--name-field-personalization-template');
      if (el) el.scrollIntoView({ behavior: 'instant', block: 'center' });
    });
    await p.waitForTimeout(300);
    await p.screenshot({ path: '/tmp/doc-produit-champ-template.png' });
    console.log('5: champ template sur produit');
  }

  // 6. Frontend produit sans template assigné (template supprimé) vs avec — reprendre les existants
  await b.close();
  console.log('done');
})().catch(e => { console.error(e.message); process.exit(1); });

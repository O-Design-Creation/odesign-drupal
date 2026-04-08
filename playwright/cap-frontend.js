const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true });
  const p = await ctx.newPage();

  // Login
  await p.goto('https://odesign-drupal.ddev.site/user/reset/1/1775652370/NmasmtTwUWbwAPVb37Vc1nCA461NOIDL9gw8ZbJ-6PM/login', { waitUntil: 'domcontentloaded', timeout: 20000 });
  await p.waitForTimeout(500);

  // Fiche produit Arbre de vie avec formulaire perso
  await p.goto('https://odesign-drupal.ddev.site/products/arbre-de-vie-en-bois-facon-scrabble', { waitUntil: 'domcontentloaded', timeout: 20000 });
  await p.waitForTimeout(1000);

  // Screenshot complet de la fiche produit
  await p.screenshot({ path: '/tmp/frontend-arbre-vie.png', fullPage: true });
  console.log('1: fiche produit complète');

  // Zoom sur le formulaire de personnalisation uniquement
  const persoSection = await p.$('.personalization-fields, [class*="personalization"]');
  if (persoSection) {
    await persoSection.screenshot({ path: '/tmp/frontend-perso-form.png' });
    console.log('2: section personnalisation zoomée');
  } else {
    console.log('2: section perso non trouvée — capture bas de page');
    await p.evaluate(() => window.scrollTo(0, document.body.scrollHeight / 2));
    await p.waitForTimeout(300);
    await p.screenshot({ path: '/tmp/frontend-perso-form.png' });
  }

  // Remplir le champ texte pour tester la validation
  const textInput = await p.$('.personalization-fields input[type="text"]');
  if (textInput) {
    await textInput.type('Le bonheur de la famille');
    await p.waitForTimeout(300);
    await p.screenshot({ path: '/tmp/frontend-perso-filled.png', fullPage: false });
    console.log('3: formulaire rempli');
  }

  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });

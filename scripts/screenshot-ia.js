/**
 * Screenshots documentation 15-assistant-ia.md — session unique.
 * Run: NODE_PATH=/home/estoclet/.nvm/versions/node/v22.22.1/lib/node_modules node scripts/screenshot-ia.js
 */

const puppeteer = require('puppeteer');
const { execSync } = require('child_process');
const fs   = require('fs');
const path = require('path');

const DDEV_SITE = 'https://odesign-drupal.ddev.site';
const SS_DIR  = '/home/estoclet/perso_projects/odesign-project/odesign-drupal/docs/utilisateur/screenshots';
const DOC_DIR = '/home/estoclet/perso_projects/odesign-project/odesign-drupal/web/modules/custom/odesign_admin/images/doc-screenshots';
const CWD     = '/home/estoclet/perso_projects/odesign-project/odesign-drupal';

function resizeWithPil(src, dst) {
  execSync(`python3 -c "
from PIL import Image
img = Image.open('${src}')
w, h = img.size
if w > 1024:
    img = img.resize((1024, int(h * 1024 / w)), Image.LANCZOS)
img.save('${dst}')
"`);
}

async function main() {
  const otlRaw = execSync('ddev exec drush uli --uid=10 --no-browser 2>&1', { cwd: CWD }).toString();
  const otlUrl = otlRaw.match(/https?:\/\/\S+/)?.[0];
  if (!otlUrl) throw new Error('OTL non obtenu : ' + otlRaw);
  console.log('OTL :', otlUrl.substring(0, 70) + '…');

  const browser = await puppeteer.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--ignore-certificate-errors', '--disable-gpu'],
  });

  // Une seule page — session partagée.
  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 800 });

  // Connexion via OTL.
  console.log('Connexion…');
  await page.goto(otlUrl, { waitUntil: 'networkidle0', timeout: 30000 });

  async function shot(name, url, afterLoad) {
    console.log('\n→ Capture : ' + name);
    await page.goto(url, { waitUntil: 'networkidle0', timeout: 30000 });
    if (afterLoad) await afterLoad(page);
    const raw = path.join(SS_DIR, name + '-raw.png');
    const out = path.join(SS_DIR, name + '.png');
    await page.screenshot({ path: raw });
    resizeWithPil(raw, out);
    fs.unlinkSync(raw);
    fs.copyFileSync(out, path.join(DOC_DIR, name + '.png'));
    console.log('  ✅ ' + name + '.png');
  }

  // 1. Section IA — BoutiqueConfigForm.
  await shot('ia-config-boutique', DDEV_SITE + '/admin/boutique/configuration', async (page) => {
    // Cliquer sur l'onglet "Intelligence artificielle".
    await page.evaluate(() => {
      const links = Array.from(document.querySelectorAll('.vertical-tabs__menu-item a'));
      const iaLink = links.find(a => /Intelligence|artifi/i.test(a.textContent));
      if (iaLink) iaLink.click();
    });
    await new Promise(r => setTimeout(r, 800));
  });

  // 2. CKEditor — fiche produit.
  await shot('ia-ckeditor-produit', DDEV_SITE + '/admin/commerce/products', async (page) => {
    const editHref = await page.evaluate(() => {
      const links = Array.from(document.querySelectorAll('a[href*="/edit"]'));
      return links[0]?.href;
    });
    if (editHref) {
      await page.goto(editHref, { waitUntil: 'networkidle0', timeout: 30000 });
    }
  });

  // 3. CKEditor — article de blog.
  await shot('ia-ckeditor-article', DDEV_SITE + '/node/add/article', null);

  // 4. Bouton IA — formulaire newsletter.
  await shot('ia-newsletter-bouton', DDEV_SITE + '/node/add/simplenews_issue', async (page) => {
    await page.waitForSelector('.odesign-ai-btn', { timeout: 5000 }).catch(() => {
      console.log('  ⚠ bouton .odesign-ai-btn non trouvé (peut nécessiter drush cr)');
    });
  });

  await browser.close();
  console.log('\n✅ Tous les screenshots terminés.');
}

main().catch(err => {
  console.error('ERREUR :', err.message || err);
  process.exit(1);
});

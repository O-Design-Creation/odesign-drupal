#!/usr/bin/env node
/**
 * Garde-fou e2e post-import des articles de blog Shopify -> Drupal.
 *
 * Vérifie automatiquement :
 * - Import sans erreur.
 * - Alias Drupal strictement égal au path Shopify.
 * - Image de couverture renseignée.
 * - Redirection 301 legacy /blog/{slug} -> alias canonique.
 * - CKEditor actif pour un compte gestionnaire sur le formulaire d'édition.
 */

const { chromium } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'https://odesign-drupal.ddev.site';
const BLOG_SOURCE_JSON_DDEV = process.env.BLOG_SOURCE_JSON_DDEV || '/var/www/html/scripts/blog-articles-real.json';
const OUT_DIR = path.join(__dirname, '..', 'screenshots', 'e2e-blog-import-guard');

function run(cmd) {
  return execSync(cmd, { encoding: 'utf8' }).trim();
}

function normalizePathnameFromUrl(url) {
  let pathname = '';
  try {
    pathname = new URL(url).pathname || '';
  } catch (_e) {
    return '';
  }
  pathname = pathname.trim();
  if (!pathname.startsWith('/')) {
    pathname = `/${pathname}`;
  }
  if (pathname.length > 1) {
    pathname = pathname.replace(/\/+$/, '');
  }
  return pathname;
}

function getGestionnaireUid() {
  const out = run(
    `ddev exec drush sqlq "SELECT u.uid FROM users_field_data u JOIN user__roles r ON r.entity_id=u.uid WHERE r.roles_target_id='gestionnaire_boutique' ORDER BY u.uid LIMIT 1;"`
  );
  const uid = parseInt(out.split('\n').pop(), 10);
  if (!Number.isInteger(uid)) {
    throw new Error(`Impossible de déterminer l'UID gestionnaire. sortie=${out}`);
  }
  return uid;
}

function getBlogRows() {
  const sql =
    `SELECT n.nid, u.field_shopify_article_url_value AS shopify_url, pa.alias, fi.field_image_target_id AS image_fid
     FROM node_field_data n
     JOIN node__field_shopify_article_url u ON u.entity_id=n.nid
     JOIN path_alias pa ON pa.path=CONCAT('/node/',n.nid)
     LEFT JOIN node__field_image fi ON fi.entity_id=n.nid
     WHERE n.type='article' AND u.field_shopify_article_url_value <> ''
     ORDER BY n.nid;`;
  const out = run(`ddev exec drush sqlq "${sql}"`);
  const rows = out
    .split('\n')
    .map((l) => l.trim())
    .filter(Boolean)
    .map((line) => {
      const [nid, shopify_url, alias, image_fid] = line.split('\t');
      return {
        nid: parseInt(nid, 10),
        shopify_url: (shopify_url || '').trim(),
        alias: (alias || '').trim(),
        image_fid: (image_fid || '').trim(),
      };
    });
  return rows.filter((r) => Number.isInteger(r.nid));
}

function assertImportNoError(report) {
  const cmd = `ddev exec bash -lc 'cd /var/www/html && BLOG_SOURCE_JSON=${BLOG_SOURCE_JSON_DDEV} drush php:script scripts/create-blog-articles.php'`;
  const out = run(cmd);
  report.importOutput = out;
  if (/\[ERR\]/.test(out) || !/Erreurs\s*:\s*0/.test(out)) {
    throw new Error(`Import blog en erreur.\n${out}`);
  }
}

function assertStrictAliases(rows, report) {
  const mismatches = [];
  for (const row of rows) {
    const expected = normalizePathnameFromUrl(row.shopify_url);
    if (!expected || row.alias !== expected) {
      mismatches.push({ nid: row.nid, alias: row.alias, expected, shopify_url: row.shopify_url });
    }
  }
  report.strictAliasChecked = rows.length;
  if (mismatches.length) {
    throw new Error(`Alias non strictement alignés Shopify: ${JSON.stringify(mismatches, null, 2)}`);
  }
}

function assertNoAliasDuplicates(rows) {
  const sql =
    `SELECT n.nid, COUNT(pa.alias) AS alias_count
     FROM node_field_data n
     JOIN node__field_shopify_article_url u ON u.entity_id=n.nid
     JOIN path_alias pa ON pa.path=CONCAT('/node/', n.nid)
     WHERE n.type='article' AND u.field_shopify_article_url_value <> ''
     GROUP BY n.nid
     HAVING alias_count <> 1;`;
  const out = run(`ddev exec drush sqlq "${sql}"`);
  if (out.trim() !== '') {
    throw new Error(`Doublons d'alias détectés:\n${out}`);
  }
  if (rows.length < 1) {
    throw new Error('Aucun article importé détecté (field_shopify_article_url).');
  }
}

function assertImagesFilled(rows, report) {
  const missing = rows.filter((r) => !r.image_fid || r.image_fid === 'NULL');
  report.imagesChecked = rows.length;
  if (missing.length) {
    throw new Error(`Image de couverture absente sur certains articles: ${JSON.stringify(missing, null, 2)}`);
  }
}

function assertRedirects(rows, report) {
  const missing = [];
  for (const row of rows) {
    const slug = row.alias.split('/').filter(Boolean).pop();
    const source = `blog/${slug}`;
    const destination = `internal:${row.alias}`;
    const sql =
      `SELECT COUNT(*) FROM redirect
       WHERE redirect_source__path='${source}'
         AND redirect_redirect__uri='${destination}'
         AND status_code=301;`;
    const out = run(`ddev exec drush sqlq "${sql}"`).split('\n').pop().trim();
    if (out !== '1') {
      missing.push({ nid: row.nid, source, destination, found: out });
    }
  }
  report.redirectsChecked = rows.length;
  if (missing.length) {
    throw new Error(`Redirections 301 manquantes/incohérentes: ${JSON.stringify(missing, null, 2)}`);
  }
}

async function assertGestionnaireEditor(rows, report) {
  const targetNid = rows[rows.length - 1].nid;
  const uid = getGestionnaireUid();
  const uliRaw = run(`ddev exec drush uli --uid=${uid} --no-browser`);
  const uli = (uliRaw.match(/https?:\/\/\S+/) || [])[0];
  if (!uli) {
    throw new Error(`ULI gestionnaire introuvable. sortie=${uliRaw}`);
  }

  fs.mkdirSync(OUT_DIR, { recursive: true });
  const screenshotPath = path.join(OUT_DIR, `node-${targetNid}-edit-gestionnaire.png`);

  const browser = await chromium.launch({ headless: true, args: ['--ignore-certificate-errors'] });
  const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1600, height: 1200 } });
  const page = await context.newPage();

  const bypassSandbox = async () => {
    const continueBtn = page.getByRole('button', { name: /continue/i });
    const continueLink = page.getByRole('link', { name: /continue/i });
    if (await continueBtn.count()) {
      await continueBtn.first().click();
      await page.waitForLoadState('networkidle');
    } else if (await continueLink.count()) {
      await continueLink.first().click();
      await page.waitForLoadState('networkidle');
    }
  };

  try {
    await page.goto(uli, { waitUntil: 'networkidle', timeout: 45000 });
    await bypassSandbox();
    await page.goto(`${BASE_URL}/node/${targetNid}/edit`, { waitUntil: 'networkidle', timeout: 45000 });
    await bypassSandbox();
    await page.waitForTimeout(3000);

    const checks = await page.evaluate(() => {
      const ckCount = document.querySelectorAll('.ck-editor').length;
      const disabledMsgCount = document.body.textContent.includes('Ce champ a été désactivé') ? 1 : 0;
      const textarea = document.querySelector('textarea[name="body[0][value]"]');
      const formatInput = document.querySelector('select[name="body[0][format]"], input[name="body[0][format]"]');
      return {
        ckCount,
        disabledMsgCount,
        textareaDisabled: textarea ? !!textarea.disabled : null,
        hasFormatInput: !!formatInput,
      };
    });

    await page.screenshot({ path: screenshotPath, fullPage: true });
    report.editorCheck = checks;
    report.editorScreenshot = screenshotPath;

    if (checks.disabledMsgCount > 0 || checks.textareaDisabled) {
      throw new Error(`Champ body désactivé pour gestionnaire: ${JSON.stringify(checks)}`);
    }
    if (checks.ckCount < 1) {
      throw new Error(`CKEditor non détecté pour gestionnaire: ${JSON.stringify(checks)}`);
    }
  } finally {
    await context.close();
    await browser.close();
  }
}

async function runE2E() {
  const report = {
    baseUrl: BASE_URL,
    sourceJsonDdev: BLOG_SOURCE_JSON_DDEV,
    status: 'PASS',
    strictAliasChecked: 0,
    imagesChecked: 0,
    redirectsChecked: 0,
  };

  try {
    assertImportNoError(report);
    const rows = getBlogRows();
    assertNoAliasDuplicates(rows);
    assertStrictAliases(rows, report);
    assertImagesFilled(rows, report);
    assertRedirects(rows, report);
    await assertGestionnaireEditor(rows, report);
  } catch (error) {
    report.status = 'FAIL';
    report.error = String(error && error.message ? error.message : error);
  }

  fs.mkdirSync(OUT_DIR, { recursive: true });
  const reportPath = path.join(OUT_DIR, 'report.json');
  fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
  console.log(JSON.stringify(report, null, 2));
  console.log(`Report: ${reportPath}`);

  if (report.status !== 'PASS') {
    process.exit(1);
  }
}

runE2E();

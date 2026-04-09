/**
 * audit-seo.js — Audit SEO technique via Playwright
 * O'Design Création
 *
 * Vérifie par page :
 *   - <title> présent et non vide
 *   - <meta name="description"> présent et non vide
 *   - og:title, og:description, og:url présents
 *   - canonical URL présente
 *   - H1 unique
 *   - Images alt manquant
 *   - robots noindex absent (sauf pages légales)
 *   - /sitemap.xml accessible et valide
 *   - /robots.txt accessible
 *
 * Usage : node playwright/scripts/audit-seo.js
 */

const { chromium } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const BASE_URL = process.env.BASE_URL || 'http://odesign-drupal.ddev.site';

const PAGES = [
  { label: 'Accueil / Catalogue',   path: '/',                              allowNoindex: false },
  { label: 'Fiche produit',         path: '/products/carte-naissance-ovale-bois', allowNoindex: false },
  { label: 'Blog',                   path: '/blog',                          allowNoindex: false },
  { label: 'Article blog',           path: '/blog/comment-personnaliser-une-carte-de-naissance-en-bois', allowNoindex: false },
  { label: 'CGV',                    path: '/cgv',                           allowNoindex: true  },
  { label: 'Mentions légales',       path: '/mentions-legales',              allowNoindex: true  },
  { label: 'Panier',                 path: '/cart',                          allowNoindex: true  },
];

async function auditPage(page, { label, path: pagePath, allowNoindex }) {
  const url = BASE_URL + pagePath;
  const issues = [];
  const info = [];

  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });

  // Title
  const title = await page.title();
  if (!title || title.trim() === '') {
    issues.push({ severity: 'error', msg: '<title> manquant ou vide' });
  } else {
    info.push(`title: "${title.substring(0, 60)}"`);
  }

  // Meta description
  const desc = await page.$eval('meta[name="description"]', el => el.getAttribute('content')).catch(() => null);
  if (!desc || desc.trim() === '') {
    issues.push({ severity: 'warning', msg: '<meta name="description"> manquant ou vide' });
  } else {
    info.push(`description: ${desc.length} chars`);
  }

  // OG tags
  for (const og of ['og:title', 'og:description', 'og:url']) {
    const val = await page.$eval(`meta[property="${og}"]`, el => el.getAttribute('content')).catch(() => null);
    if (!val) {
      issues.push({ severity: 'warning', msg: `<meta property="${og}"> absent` });
    }
  }

  // Canonical
  const canonical = await page.$eval('link[rel="canonical"]', el => el.getAttribute('href')).catch(() => null);
  if (!canonical) {
    issues.push({ severity: 'warning', msg: 'Canonical URL absente' });
  }

  // H1 unique
  const h1s = await page.$$eval('h1', els => els.map(e => e.textContent.trim()));
  if (h1s.length === 0) {
    issues.push({ severity: 'error', msg: 'Aucun H1 sur la page' });
  } else if (h1s.length > 1) {
    issues.push({ severity: 'warning', msg: `${h1s.length} H1 trouvés (doit être unique)` });
  } else {
    info.push(`H1: "${h1s[0].substring(0, 50)}"`);
  }

  // Images sans alt
  const imgsMissingAlt = await page.$$eval('img:not([alt])', els => els.length);
  if (imgsMissingAlt > 0) {
    issues.push({ severity: 'warning', msg: `${imgsMissingAlt} image(s) sans attribut alt` });
  }

  // Robots noindex
  const noindex = await page.$eval('meta[name="robots"]', el => el.getAttribute('content')).catch(() => null);
  if (noindex && noindex.includes('noindex') && !allowNoindex) {
    issues.push({ severity: 'error', msg: `noindex détecté sur une page publique : "${noindex}"` });
  }

  return { label, url, issues, info };
}

async function checkSitemapAndRobots(page) {
  const results = [];

  // Sitemap
  const sitemapRes = await page.goto(BASE_URL + '/sitemap.xml', { timeout: 15000 }).catch(() => null);
  if (!sitemapRes || sitemapRes.status() !== 200) {
    results.push({ type: 'sitemap', severity: 'error', msg: '/sitemap.xml inaccessible' });
  } else {
    const content = await page.content();
    const urlCount = (content.match(/<loc>/g) || []).length;
    results.push({ type: 'sitemap', severity: 'ok', msg: `/sitemap.xml OK — ${urlCount} URLs indexées` });
  }

  // robots.txt
  const robotsRes = await page.goto(BASE_URL + '/robots.txt', { timeout: 15000 }).catch(() => null);
  if (!robotsRes || robotsRes.status() !== 200) {
    results.push({ type: 'robots', severity: 'warning', msg: '/robots.txt inaccessible' });
  } else {
    results.push({ type: 'robots', severity: 'ok', msg: '/robots.txt OK' });
  }

  return results;
}

(async () => {
  const browser = await chromium.launch({
    args: ['--font-render-hinting=none', '--no-sandbox'],
  });
  const page = await browser.newPage();
  await page.setExtraHTTPHeaders({ 'Accept-Language': 'fr-FR,fr;q=0.9' });

  console.log('\n' + '='.repeat(60));
  console.log('🔍 AUDIT SEO — O'Design Création');
  console.log('='.repeat(60) + '\n');

  const pageResults = [];
  for (const pageConfig of PAGES) {
    process.stdout.write(`[${pageConfig.label}] ${BASE_URL + pageConfig.path}\n`);
    try {
      const result = await auditPage(page, pageConfig);
      pageResults.push(result);
      if (result.issues.length === 0) {
        console.log('  ✅ OK — ' + result.info.join(' | '));
      } else {
        for (const issue of result.issues) {
          const icon = issue.severity === 'error' ? '  ❌' : '  ⚠️ ';
          console.log(`${icon} ${issue.msg}`);
        }
        if (result.info.length) console.log('  ℹ️  ' + result.info.join(' | '));
      }
    } catch (e) {
      pageResults.push({ label: pageConfig.label, url: BASE_URL + pageConfig.path, issues: [{ severity: 'error', msg: 'Erreur: ' + e.message }], info: [] });
      console.log(`  ❌ Erreur: ${e.message}`);
    }
    console.log('');
  }

  // Sitemap + robots
  console.log('[ Infrastructure SEO ]');
  const infraResults = await checkSitemapAndRobots(page);
  for (const r of infraResults) {
    const icon = r.severity === 'ok' ? '  ✅' : r.severity === 'warning' ? '  ⚠️ ' : '  ❌';
    console.log(`${icon} ${r.msg}`);
  }

  await browser.close();

  // Rapport final
  const errors   = pageResults.flatMap(r => r.issues.filter(i => i.severity === 'error'));
  const warnings = pageResults.flatMap(r => r.issues.filter(i => i.severity === 'warning'));

  console.log('\n' + '='.repeat(60));
  console.log('📋 RAPPORT FINAL SEO');
  console.log('='.repeat(60));

  if (errors.length === 0 && warnings.length === 0) {
    console.log('\n✅ Aucun problème SEO détecté\n');
  } else {
    if (errors.length)   console.log(`\n❌ ERREURS (${errors.length}) :`);
    for (const e of errors)   console.log(`   • ${e.msg}`);
    if (warnings.length) console.log(`\n⚠️  AVERTISSEMENTS (${warnings.length}) :`);
    for (const w of warnings) console.log(`   • ${w.msg}`);
    console.log('');
  }

  // JSON report
  const reportDir = path.join(__dirname, '../screenshots/frontend-audit');
  if (!fs.existsSync(reportDir)) fs.mkdirSync(reportDir, { recursive: true });
  const reportPath = path.join(reportDir, 'seo-report.json');
  fs.writeFileSync(reportPath, JSON.stringify({ pageResults, infraResults, timestamp: new Date().toISOString() }, null, 2));
  console.log(`💾 Rapport JSON : ${reportPath.replace('/var/www/html/', '')}\n`);

  process.exit(errors.length > 0 ? 1 : 0);
})();

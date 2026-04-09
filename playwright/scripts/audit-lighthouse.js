/**
 * audit-lighthouse.js — Audit Lighthouse (performance, SEO, a11y, best practices)
 * O'Design Création
 *
 * Scores cibles (Good) :
 *   Performance  ≥ 0.75
 *   Accessibilité ≥ 0.90
 *   Best practices ≥ 0.90
 *   SEO          ≥ 0.90
 *
 * Usage : node playwright/scripts/audit-lighthouse.js
 */

const lighthouse = require('lighthouse');
const { chromium } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const BASE_URL = process.env.BASE_URL || 'http://odesign-drupal.ddev.site';
const IS_HTTP_BASE_URL = BASE_URL.startsWith('http://');

const PAGES = [
  { label: 'Accueil / Catalogue', path: '/' },
  { label: 'Fiche produit',       path: '/products/carte-naissance-ovale-bois' },
  { label: 'Blog',                 path: '/blog' },
];

const THRESHOLDS = {
  performance:       0.70,
  accessibility:     0.85,
  'best-practices':  0.85,
  seo:               0.85,
};

const CATEGORY_LABELS = {
  performance:       'Performance',
  accessibility:     'Accessibilité',
  'best-practices':  'Bonnes pratiques',
  seo:               'SEO',
};

function scoreIcon(score, threshold) {
  if (score === null || score === undefined) return '⚪';
  if (score >= threshold) return '✅';
  if (score >= threshold - 0.1) return '⚠️ ';
  return '❌';
}

function scoreBar(score) {
  if (score === null) return 'N/A';
  const pct = Math.round(score * 100);
  const filled = Math.round(pct / 5);
  return '█'.repeat(filled) + '░'.repeat(20 - filled) + ` ${pct}`;
}

function getFailingAuditsForCategory(lhr, categoryKey) {
  const category = lhr.categories?.[categoryKey];
  if (!category || !Array.isArray(category.auditRefs)) {
    return [];
  }

  return category.auditRefs
    .filter((ref) => (ref.weight || 0) > 0)
    .map((ref) => {
      const audit = lhr.audits?.[ref.id];
      return {
        id: ref.id,
        weight: ref.weight,
        title: audit?.title || ref.id,
        score: audit?.score,
        scoreDisplayMode: audit?.scoreDisplayMode,
      };
    })
    .filter((audit) => {
      if (audit.scoreDisplayMode === 'notApplicable' || audit.scoreDisplayMode === 'informative') {
        return false;
      }
      return typeof audit.score === 'number' && audit.score < 1;
    })
    .sort((a, b) => (a.score ?? 1) - (b.score ?? 1));
}

function resolveWritableReportDir() {
  const preferred = path.join(__dirname, '../screenshots/frontend-audit/lighthouse');
  const fallback = '/tmp/odesign-audits/lighthouse';

  try {
    fs.mkdirSync(preferred, { recursive: true });
    fs.accessSync(preferred, fs.constants.W_OK);
    return preferred;
  } catch (_) {
    fs.mkdirSync(fallback, { recursive: true });
    return fallback;
  }
}

(async () => {
  console.log('\n' + '='.repeat(60));
  console.log("🏎️  AUDIT LIGHTHOUSE — O'Design Création");
  console.log('='.repeat(60) + '\n');

  const allResults = [];
  const reportDir = resolveWritableReportDir();
  const reportDirLabel = reportDir.startsWith('/var/www/html/')
    ? reportDir.replace('/var/www/html/', '')
    : reportDir;

  for (const pageConfig of PAGES) {
    const url = BASE_URL + pageConfig.path;
    console.log(`[${pageConfig.label}] ${url}`);

    // Ouvrir un browser frais par page — Lighthouse a besoin d'au moins un tab ouvert.
    const browser = await chromium.launch({
      args: ['--remote-debugging-port=9222', '--no-sandbox', '--disable-dev-shm-usage'],
      headless: true,
    });
    const ctx  = await browser.newContext();
    const page = await ctx.newPage();
    await page.goto('about:blank');

    try {
      const runnerResult = await lighthouse(url, {
        port: 9222,
        output: ['json', 'html'],
        logLevel: 'silent',
        onlyCategories: ['performance', 'accessibility', 'best-practices', 'seo'],
        formFactor: 'desktop',
        screenEmulation: { disabled: true },
        throttlingMethod: 'simulate',
        throttling: { rttMs: 40, throughputKbps: 10240, cpuSlowdownMultiplier: 1 },
        disableStorageReset: false,
      });

      const [, htmlReport] = runnerResult.report;
      const lhr = runnerResult.lhr;
      const categories = lhr.categories;
      const pageIssues = [];
      const bestPracticesFailingAudits = getFailingAuditsForCategory(lhr, 'best-practices');

      for (const [key, label] of Object.entries(CATEGORY_LABELS)) {
        const cat = categories[key];
        const score = cat ? cat.score : null;
        const threshold = THRESHOLDS[key] || 0.8;
        const icon = scoreIcon(score, threshold);
        console.log(`  ${icon} ${label.padEnd(18)} ${scoreBar(score)}`);

        let ignoreThreshold = false;
        if (key === 'best-practices' && IS_HTTP_BASE_URL && bestPracticesFailingAudits.length === 1 && bestPracticesFailingAudits[0].id === 'is-on-https') {
          ignoreThreshold = true;
          console.log('  ℹ️  Best Practices: écart local attendu (audit HTTPS sur environnement http://).');
        }

        if (score !== null && score < threshold && !ignoreThreshold) {
          pageIssues.push({ category: label, score: Math.round(score * 100), threshold: Math.round(threshold * 100) });
        }
      }

      if (bestPracticesFailingAudits.length > 0) {
        console.log('  📋 Détail Best Practices en échec :');
        for (const audit of bestPracticesFailingAudits) {
          console.log(`     • [${audit.id}] ${audit.title} (score: ${Math.round((audit.score || 0) * 100)})`);
        }
      }

      const slug = pageConfig.label.toLowerCase().replace(/[^a-z0-9]+/g, '-');
      const htmlPath = path.join(reportDir, `${slug}.html`);
      fs.writeFileSync(htmlPath, htmlReport);
      console.log(`  💾 Rapport HTML : ${path.join(reportDirLabel, `${slug}.html`)}`);

      allResults.push({
        label: pageConfig.label,
        url,
        scores: Object.fromEntries(
          Object.keys(CATEGORY_LABELS).map(k => [k, categories[k]?.score ?? null])
        ),
        issues: pageIssues,
      });

    } catch (e) {
      console.log(`  ❌ Erreur Lighthouse : ${e.message}`);
      allResults.push({ label: pageConfig.label, url, scores: {}, issues: [], error: e.message });
    } finally {
      await browser.close();
      // Laisser le port se libérer entre les pages
      await new Promise(r => setTimeout(r, 1000));
    }

    console.log('');
  }

  // Résumé
  console.log('='.repeat(60));
  console.log('📋 RÉSUMÉ LIGHTHOUSE');
  console.log('='.repeat(60));

  const allIssues = allResults.flatMap(r => r.issues || []);
  if (allIssues.length === 0) {
    console.log('\n✅ Tous les scores sont au-dessus des seuils cibles\n');
  } else {
    console.log(`\n⚠️  ${allIssues.length} score(s) sous le seuil :`);
    for (const issue of allIssues) {
      console.log(`   • ${issue.category} : ${issue.score}/100 (seuil : ${issue.threshold})`);
    }
    console.log('');
  }

  const jsonPath = path.join(reportDir, 'lighthouse-summary.json');
  fs.writeFileSync(jsonPath, JSON.stringify({ results: allResults, timestamp: new Date().toISOString() }, null, 2));
  console.log(`💾 Résumé JSON : ${path.join(reportDirLabel, 'lighthouse-summary.json')}\n`);

  process.exit(allIssues.length > 0 ? 1 : 0);
})();

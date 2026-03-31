/**
 * audit-performance.js — Audit performance via Playwright
 * Atelier O Design
 *
 * Métriques mesurées :
 *   - LCP (Largest Contentful Paint)
 *   - FCP (First Contentful Paint)
 *   - CLS (Cumulative Layout Shift)
 *   - TTI (Time to Interactive approx.)
 *   - Taille totale de la page (HTML+CSS+JS+images)
 *   - Nombre de requêtes
 *
 * Seuils Core Web Vitals (Good / Needs improvement / Poor)
 */

const { chromium } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const BASE_URL = process.env.BASE_URL || 'http://odesign-drupal.ddev.site';

const PAGES = [
  { label: 'Accueil / Catalogue', path: '/' },
  { label: 'Fiche produit',       path: '/products/carte-naissance-ovale-bois' },
  { label: 'Blog',                 path: '/blog' },
  { label: 'Panier',               path: '/cart' },
];

// Seuils Core Web Vitals (ms / score)
const THRESHOLDS = {
  FCP: { good: 1800,  poor: 3000  },
  LCP: { good: 2500,  poor: 4000  },
  CLS: { good: 0.1,   poor: 0.25  },
  TTI: { good: 3800,  poor: 7300  },
  totalKB: { good: 1000, poor: 2000 }, // Ko
  requests: { good: 30,  poor: 60  },
};

const RED    = '\x1b[31m';
const YELLOW = '\x1b[33m';
const GREEN  = '\x1b[32m';
const CYAN   = '\x1b[36m';
const DIM    = '\x1b[2m';
const RESET  = '\x1b[0m';

function grade(value, thresholds, unit = 'ms') {
  const v = typeof value === 'number' ? value : parseFloat(value);
  const formatted = unit === 'ms'
    ? `${Math.round(v)}ms`
    : unit === 'kb'
    ? `${Math.round(v)}Ko`
    : `${v.toFixed(3)}`;

  if (v <= thresholds.good)  return `${GREEN}${formatted} ✓${RESET}`;
  if (v <= thresholds.poor)  return `${YELLOW}${formatted} ~${RESET}`;
  return `${RED}${formatted} ✗${RESET}`;
}

async function measurePage(browser, pageDef) {
  const ctx = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    locale: 'fr-FR',
  });

  // Intercepter toutes les requêtes pour mesurer le poids total
  let totalBytes = 0;
  let requestCount = 0;

  ctx.on('response', async (response) => {
    try {
      requestCount++;
      const headers = response.headers();
      const contentLength = headers['content-length'];
      if (contentLength) {
        totalBytes += parseInt(contentLength, 10);
      } else {
        const body = await response.body().catch(() => Buffer.alloc(0));
        totalBytes += body.length;
      }
    } catch (_) {}
  });

  const page = await ctx.newPage();
  const url = BASE_URL + pageDef.path;

  const startTime = Date.now();
  await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
  const loadTime = Date.now() - startTime;

  // Mesurer les métriques via Performance API
  const metrics = await page.evaluate(() => {
    const perf = window.performance;
    const entries = perf.getEntriesByType('navigation')[0];
    const paintEntries = perf.getEntriesByType('paint');

    const fcp = paintEntries.find(e => e.name === 'first-contentful-paint');

    // CLS via PerformanceObserver (valeur courante)
    let cls = 0;
    try {
      const layoutShifts = perf.getEntriesByType('layout-shift');
      cls = layoutShifts.reduce((acc, e) => acc + (e.hadRecentInput ? 0 : e.value), 0);
    } catch (_) {}

    // LCP depuis PerformanceObserver si disponible
    let lcp = 0;
    try {
      const lcpEntries = perf.getEntriesByType('largest-contentful-paint');
      if (lcpEntries.length > 0) {
        lcp = lcpEntries[lcpEntries.length - 1].renderTime || lcpEntries[lcpEntries.length - 1].loadTime;
      }
    } catch (_) {}

    return {
      fcp:          fcp ? Math.round(fcp.startTime) : null,
      lcp:          lcp ? Math.round(lcp) : null,
      cls:          parseFloat(cls.toFixed(4)),
      domContentLoaded: Math.round(entries ? entries.domContentLoadedEventEnd : 0),
      loadEventEnd:     Math.round(entries ? entries.loadEventEnd : 0),
      transferSize:     entries ? entries.transferSize : 0,
    };
  });

  await ctx.close();

  return {
    label: pageDef.label,
    url,
    fcp:      metrics.fcp,
    lcp:      metrics.lcp,
    cls:      metrics.cls,
    loadTime,
    totalKB:  Math.round(totalBytes / 1024),
    requests: requestCount,
    domContentLoaded: metrics.domContentLoaded,
  };
}

async function main() {
  const browser = await chromium.launch({
    headless: true,
    args: ['--no-sandbox'],
  });

  console.log(`\n${CYAN}⚡ Audit performance — Core Web Vitals${RESET}`);
  console.log('='.repeat(60));

  const REPORT_DIR = path.join(__dirname, '..', 'screenshots', 'perf-audit');
  if (!fs.existsSync(REPORT_DIR)) fs.mkdirSync(REPORT_DIR, { recursive: true });

  const allResults = [];
  let totalIssues = 0;

  for (const pageDef of PAGES) {
    console.log(`\n[${pageDef.label}] ${BASE_URL + pageDef.path}`);
    try {
      const r = await measurePage(browser, pageDef);
      allResults.push(r);

      // Affichage métriques
      if (r.fcp !== null) {
        process.stdout.write(`  FCP   : ${grade(r.fcp, THRESHOLDS.FCP, 'ms')}\n`);
      }
      if (r.lcp !== null && r.lcp > 0) {
        process.stdout.write(`  LCP   : ${grade(r.lcp, THRESHOLDS.LCP, 'ms')}\n`);
      }
      process.stdout.write(`  CLS   : ${grade(r.cls, THRESHOLDS.CLS, 'score')}\n`);
      process.stdout.write(`  Load  : ${grade(r.loadTime, THRESHOLDS.TTI, 'ms')}\n`);
      process.stdout.write(`  Poids : ${grade(r.totalKB, THRESHOLDS.totalKB, 'kb')}\n`);
      process.stdout.write(`  Req.  : ${grade(r.requests, THRESHOLDS.requests, 'n')}\n`);

      // Compter les dépassements
      if (r.fcp > THRESHOLDS.FCP.poor) totalIssues++;
      if (r.lcp > THRESHOLDS.LCP.poor) totalIssues++;
      if (r.cls > THRESHOLDS.CLS.poor) totalIssues++;

    } catch (err) {
      console.log(`  ${RED}✗ Erreur : ${err.message}${RESET}`);
      allResults.push({ label: pageDef.label, error: err.message });
    }
  }

  await browser.close();

  const reportPath = path.join(REPORT_DIR, 'perf-report.json');
  fs.writeFileSync(reportPath, JSON.stringify({
    date: new Date().toISOString(),
    base_url: BASE_URL,
    thresholds: THRESHOLDS,
    pages: allResults,
  }, null, 2));

  console.log('\n' + '='.repeat(60));
  console.log(`${CYAN}📋 RAPPORT PERFORMANCE${RESET}\n`);

  if (totalIssues === 0) {
    console.log(`${GREEN}✅ Core Web Vitals dans les seuils "Good" ou "Needs improvement"${RESET}`);
  } else {
    console.log(`${RED}❌ ${totalIssues} métrique(s) en zone "Poor"${RESET}`);
  }

  console.log(`\n${DIM}Seuils appliqués :${RESET}`);
  console.log(`  FCP   : ≤${THRESHOLDS.FCP.good}ms Good / ≤${THRESHOLDS.FCP.poor}ms Needs improvement`);
  console.log(`  LCP   : ≤${THRESHOLDS.LCP.good}ms Good / ≤${THRESHOLDS.LCP.poor}ms Needs improvement`);
  console.log(`  CLS   : ≤${THRESHOLDS.CLS.good} Good / ≤${THRESHOLDS.CLS.poor} Needs improvement`);
  console.log(`  Poids : ≤${THRESHOLDS.totalKB.good}Ko Good / ≤${THRESHOLDS.totalKB.poor}Ko Needs improvement`);

  console.log(`\n💾 Rapport JSON : ${reportPath}`);
  console.log('='.repeat(60) + '\n');

  process.exit(totalIssues > 0 ? 1 : 0);
}

main().catch(err => {
  console.error('Erreur audit perf :', err);
  process.exit(1);
});

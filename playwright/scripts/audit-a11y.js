/**
 * audit-a11y.js — Audit accessibilité axe-core + Playwright
 * Atelier O Design
 *
 * Usage : node playwright/scripts/audit-a11y.js [url]
 * Produit un rapport JSON + résumé console
 */

const { chromium } = require('@playwright/test');
const { AxeBuilder } = require('@axe-core/playwright');
const fs = require('fs');
const path = require('path');

const BASE_URL = process.env.BASE_URL || 'http://odesign-drupal.ddev.site';

const PAGES = [
  { label: 'Accueil / Catalogue',       path: '/' },
  { label: 'Catalogue produits',         path: '/produits' },
  { label: 'Fiche produit',              path: '/products/carte-naissance-ovale-bois' },
  { label: 'Blog',                        path: '/blog' },
  { label: 'CGV',                         path: '/cgv' },
  { label: 'Mentions légales',            path: '/mentions-legales' },
  { label: 'Panier',                      path: '/cart' },
];

// Règles WCAG 2.1 AA ciblées
const AXE_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'best-practice'];

// Violations à signaler en erreur (critique ou sérieux)
const CRITICAL_IMPACT = ['critical', 'serious'];

const REPORT_DIR = path.join(__dirname, '..', 'screenshots', 'a11y-audit');
if (!fs.existsSync(REPORT_DIR)) fs.mkdirSync(REPORT_DIR, { recursive: true });

const RED    = '\x1b[31m';
const YELLOW = '\x1b[33m';
const GREEN  = '\x1b[32m';
const CYAN   = '\x1b[36m';
const DIM    = '\x1b[2m';
const RESET  = '\x1b[0m';

function impact(imp) {
  if (imp === 'critical') return `${RED}CRITIQUE${RESET}`;
  if (imp === 'serious')  return `${RED}SÉRIEUX ${RESET}`;
  if (imp === 'moderate') return `${YELLOW}MODÉRÉ  ${RESET}`;
  return `${DIM}MINEUR  ${RESET}`;
}

async function auditPage(page, pageDef) {
  const url = BASE_URL + pageDef.path;
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 15000 });

  // Dismiss cookie banner si présent
  const accept = page.locator('#sliding-popup .agree-button').first();
  if (await accept.isVisible({ timeout: 2000 }).catch(() => false)) {
    await accept.click();
    await page.waitForTimeout(400);
  }

  const results = await new AxeBuilder({ page })
    .withTags(AXE_TAGS)
    .exclude('#toolbar-administration') // Exclure barre admin Drupal
    .analyze();

  return {
    url,
    label: pageDef.label,
    violations: results.violations,
    passes: results.passes.length,
    incomplete: results.incomplete.length,
  };
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    locale: 'fr-FR',
  });
  const page = await ctx.newPage();

  console.log(`\n${CYAN}♿ Audit accessibilité axe-core (WCAG 2.1 AA)${RESET}`);
  console.log('='.repeat(60));

  const allResults = [];
  let totalErrors = 0;
  let totalWarnings = 0;

  for (const pageDef of PAGES) {
    process.stdout.write(`\n[${pageDef.label}] ${BASE_URL + pageDef.path}\n`);

    try {
      const result = await auditPage(page, pageDef);
      allResults.push(result);

      const criticals = result.violations.filter(v => CRITICAL_IMPACT.includes(v.impact));
      const warnings  = result.violations.filter(v => !CRITICAL_IMPACT.includes(v.impact));

      totalErrors   += criticals.length;
      totalWarnings += warnings.length;

      if (result.violations.length === 0) {
        console.log(`  ${GREEN}✓ 0 violation — ${result.passes} règles OK${RESET}`);
      } else {
        result.violations.forEach(v => {
          const affectedCount = v.nodes.length;
          console.log(`  ${impact(v.impact)} [${v.id}] ${v.description}`);
          console.log(`  ${DIM}  → ${affectedCount} élément(s) affecté(s) | ${v.helpUrl}${RESET}`);
          // Afficher le premier élément affecté
          if (v.nodes[0] && v.nodes[0].target) {
            console.log(`  ${DIM}  → Exemple : ${v.nodes[0].target[0]}${RESET}`);
          }
        });
        if (result.incomplete > 0) {
          console.log(`  ${YELLOW}⚠ ${result.incomplete} règle(s) à vérifier manuellement${RESET}`);
        }
      }

    } catch (err) {
      console.log(`  ${RED}✗ Erreur : ${err.message}${RESET}`);
      allResults.push({ url: BASE_URL + pageDef.path, label: pageDef.label, error: err.message });
    }
  }

  await browser.close();

  // Sauvegarder rapport JSON
  const reportPath = path.join(REPORT_DIR, 'a11y-report.json');
  fs.writeFileSync(reportPath, JSON.stringify({
    date: new Date().toISOString(),
    base_url: BASE_URL,
    summary: { errors: totalErrors, warnings: totalWarnings },
    pages: allResults,
  }, null, 2));

  // Résumé final
  console.log('\n' + '='.repeat(60));
  console.log(`${CYAN}📋 RAPPORT ACCESSIBILITÉ${RESET}\n`);

  if (totalErrors === 0 && totalWarnings === 0) {
    console.log(`${GREEN}✅ 0 violation — Toutes les pages passent WCAG 2.1 AA${RESET}`);
  } else {
    if (totalErrors > 0) {
      console.log(`${RED}❌ ${totalErrors} violation(s) critique(s)/sérieuse(s)${RESET}`);
    }
    if (totalWarnings > 0) {
      console.log(`${YELLOW}⚠  ${totalWarnings} violation(s) modérée(s)/mineure(s)${RESET}`);
    }
  }

  console.log(`\n💾 Rapport JSON : ${reportPath}`);
  console.log('='.repeat(60) + '\n');

  process.exit(totalErrors > 0 ? 1 : 0);
}

main().catch(err => {
  console.error('Erreur audit a11y :', err);
  process.exit(1);
});

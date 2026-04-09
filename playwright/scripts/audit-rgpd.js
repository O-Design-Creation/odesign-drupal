/**
 * audit-rgpd.js — Audit RGPD automatisé (niveau technique)
 * O'Design Création
 *
 * Vérifie :
 * - présence d'un mécanisme de consentement cookies (banner/module)
 * - absence de cookies non essentiels visibles avant consentement (approximation technique)
 * - présence des pages légales clés
 * - présence d'un lien vers la politique de confidentialité dans le footer
 * - présence d'une mention RGPD sur le formulaire newsletter
 *
 * Usage : node playwright/scripts/audit-rgpd.js
 */

const { chromium } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const BASE_URL = process.env.BASE_URL || 'http://odesign-drupal.ddev.site';

const LEGAL_PAGES = [
  '/politique-confidentialite',
  '/mentions-legales',
  '/cgv',
];

const ESSENTIAL_COOKIE_PREFIXES = [
  'SESS',
  'SSESS',
  'has_js',
  'cookie-agreed',
  'cookieconsent',
  'eu_cookie_compliance',
  'PHPSESSID',
];

const REPORT_DIR = path.join(__dirname, '..', 'screenshots', 'frontend-audit');
if (!fs.existsSync(REPORT_DIR)) fs.mkdirSync(REPORT_DIR, { recursive: true });

function isPotentiallyNonEssential(name) {
  if (!name) return false;
  return !ESSENTIAL_COOKIE_PREFIXES.some(prefix => name.startsWith(prefix));
}

async function checkLegalPages(page) {
  const issues = [];
  for (const pagePath of LEGAL_PAGES) {
    const res = await page.goto(BASE_URL + pagePath, { waitUntil: 'domcontentloaded', timeout: 20000 }).catch(() => null);
    if (!res || res.status() >= 400) {
      issues.push(`Page légale inaccessible: ${pagePath}`);
    }
  }
  return issues;
}

async function checkHomeRgpd(page) {
  const issues = [];
  const info = [];

  await page.goto(BASE_URL + '/', { waitUntil: 'domcontentloaded', timeout: 20000 });

  const consentBannerVisible = await page.evaluate(() => {
    const selectors = [
      '#sliding-popup',
      '.eu-cookie-compliance-banner',
      '[data-drupal-selector=\"edit-gdpr-consent\"]',
      '.newsletter-gdpr-checkbox',
      '#cookieconsent',
    ];
    return selectors.some((selector) => {
      const el = document.querySelector(selector);
      if (!el) return false;
      const style = window.getComputedStyle(el);
      return style.display !== 'none' && style.visibility !== 'hidden';
    });
  });

  if (!consentBannerVisible) {
    issues.push('Aucun mécanisme de consentement visible détecté (cookies/newsletter).');
  } else {
    info.push('Mécanisme de consentement détecté.');
  }

  const cookiesBefore = await page.context().cookies();
  const nonEssentialBefore = cookiesBefore.filter(c => isPotentiallyNonEssential(c.name));
  if (nonEssentialBefore.length > 0) {
    issues.push(
      `Cookies potentiellement non essentiels présents avant consentement: ${nonEssentialBefore.map(c => c.name).join(', ')}`
    );
  } else {
    info.push('Pas de cookie non essentiel évident avant consentement.');
  }

  const privacyLinkInFooter = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('footer a')).some((a) => {
      const href = (a.getAttribute('href') || '').toLowerCase();
      return href.includes('politique-confidentialite');
    });
  });

  if (!privacyLinkInFooter) {
    issues.push('Lien "Politique de confidentialité" absent du footer.');
  } else {
    info.push('Lien vers la politique de confidentialité présent dans le footer.');
  }

  const newsletterMention = await page.evaluate(() => {
    const label = document.querySelector('label[for="edit-gdpr-consent"]');
    return label ? label.textContent.trim() : '';
  });

  if (!newsletterMention || !newsletterMention.toLowerCase().includes('politique de confidentialité')) {
    issues.push('Mention RGPD newsletter absente ou incomplète.');
  } else {
    info.push('Mention RGPD newsletter détectée.');
  }

  return { issues, info, cookiesBefore };
}

async function main() {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage();

  console.log('\n' + '='.repeat(60));
  console.log("🛡️  AUDIT RGPD — O'Design Création");
  console.log('='.repeat(60) + '\n');

  const legalIssues = await checkLegalPages(page);
  const homeCheck = await checkHomeRgpd(page);

  if (legalIssues.length === 0) {
    console.log('✅ Pages légales: OK');
  } else {
    for (const issue of legalIssues) console.log(`❌ ${issue}`);
  }

  if (homeCheck.issues.length === 0) {
    console.log('✅ Contrôles RGPD homepage: OK');
  } else {
    for (const issue of homeCheck.issues) console.log(`⚠️  ${issue}`);
  }

  if (homeCheck.info.length) {
    for (const item of homeCheck.info) console.log(`ℹ️  ${item}`);
  }

  const reportPath = path.join(REPORT_DIR, 'rgpd-report.json');
  fs.writeFileSync(reportPath, JSON.stringify({
    timestamp: new Date().toISOString(),
    base_url: BASE_URL,
    legal_issues: legalIssues,
    home_issues: homeCheck.issues,
    info: homeCheck.info,
    cookies_before_consent: homeCheck.cookiesBefore.map(c => ({
      name: c.name,
      domain: c.domain,
      path: c.path,
      expires: c.expires,
      secure: c.secure,
      sameSite: c.sameSite,
    })),
  }, null, 2));

  console.log(`\n💾 Rapport JSON : ${reportPath.replace('/var/www/html/', '')}\n`);

  await browser.close();
  process.exit((legalIssues.length + homeCheck.issues.length) > 0 ? 1 : 0);
}

main().catch((err) => {
  console.error('Erreur audit RGPD :', err);
  process.exit(1);
});

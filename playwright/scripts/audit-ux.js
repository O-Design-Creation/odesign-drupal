#!/usr/bin/env node
/**
 * audit-ux.js
 * Analyse les pages admin et remonte les problèmes d'interface :
 * - labels encore en anglais
 * - boutons sans texte FR
 * - champs sans description (#description)
 * - éléments techniques exposés
 *
 * Usage :
 *   node scripts/audit-ux.js           → toutes les pages
 *   node scripts/audit-ux.js /admin/commerce/products/add/default → formulaire produit
 */

const { chromium } = require('playwright');

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://web';
const ADMIN    = process.env.DRUPAL_ADMIN_USER   || 'admin';
const PASSWORD = process.env.DRUPAL_ADMIN_PASS   || 'admin';
const TARGET   = process.argv[2] || 'all';

/** Mots courants anglais qui signalent une chaîne non traduite dans l'UI */
const EN_PATTERNS = [
  /\bSave\b/, /\bEdit\b/, /\bDelete\b/, /\bPublish\b/, /\bUnpublish\b/,
  /\bCancel\b/, /\bAdd\b/, /\bCreate\b/, /\bUpdate\b/, /\bSubmit\b/,
  /\bView\b/, /\bTitle\b/, /\bStatus\b/, /\bEnabled\b/, /\bDisabled\b/,
  /\bRequired\b/, /\bOptional\b/, /\bPlaceholder\b/,
];

/** Champs techniques qui ne devraient pas être exposés au gestionnaire */
const TECHNICAL_FIELDS = ['uid', 'langcode', 'created', 'changed', 'path', 'uuid', 'vid'];

const AUDIT_PAGES = {
  'dashboard':    '/admin/boutique',
  'product-form': '/admin/commerce/products/add/default',
  'order-form':   '/admin/commerce/orders/add',
};

async function login(page) {
  await page.goto(`${BASE_URL}/user/login`);
  await page.waitForSelector('#edit-name');
  await page.fill('#edit-name', ADMIN);
  await page.fill('#edit-pass', PASSWORD);
  await page.click('#edit-submit');
  await page.waitForURL(/\/(admin|user\/\d+)/, { timeout: 10_000 }).catch(() => {});
}

async function auditPage(page, name, url) {
  const findings = [];

  await page.goto(`${BASE_URL}${url}`, { waitUntil: 'networkidle', timeout: 15_000 });
  await page.waitForTimeout(500);

  // 1. Labels visibles avec du texte anglais
  const labels = await page.$$eval('label, .form-item__label, button, .button, .action-links a', els =>
    els.map(el => ({ text: el.innerText?.trim(), tag: el.tagName.toLowerCase() }))
       .filter(el => el.text && el.text.length > 0 && el.text.length < 80)
  );
  for (const { text, tag } of labels) {
    for (const pattern of EN_PATTERNS) {
      if (pattern.test(text) && !/\s/.test(text) === false) {
        findings.push({
          severity: 'high',
          type: 'label_en',
          tag,
          text: text.substring(0, 60),
          message: `Label potentiellement non traduit : "${text}"`,
        });
        break;
      }
    }
  }

  // 2. Champs de formulaire sans description
  const fieldsWithoutHelp = await page.$$eval('.form-item', items =>
    items
      .filter(item => !item.querySelector('.form-item__description, .description'))
      .map(item => {
        const label = item.querySelector('label')?.innerText?.trim() || '?';
        const name  = item.querySelector('input, select, textarea')?.getAttribute('name') || '';
        return { label, name };
      })
      .filter(f => f.label !== '?' && f.name && !f.name.includes('[_'))
  );
  for (const { label, name } of fieldsWithoutHelp.slice(0, 10)) {
    findings.push({
      severity: 'medium',
      type: 'missing_help',
      message: `Champ sans description d'aide : "${label}" (name="${name}")`,
    });
  }

  // 3. Champs techniques exposés (vérifie que le champ n'a pas de label métier)
  const labelledInputs = await page.$$eval('.form-item', items =>
    items.map(item => {
      const input = item.querySelector('input, select, textarea');
      const label = item.querySelector('label')?.innerText?.trim() || '';
      const name  = input?.getAttribute('name') || '';
      return { name, label };
    }).filter(f => f.name)
  );
  for (const { name, label } of labelledInputs) {
    for (const tech of TECHNICAL_FIELDS) {
      const isTechField = name === tech || name.endsWith(`[${tech}]`);
      // Un champ uid avec un label métier ("Rechercher", "Auteur", etc.) n'est pas technique
      const hasMetierLabel = label.length > 0 && !['uuid', 'vid', 'changed'].includes(tech);
      if (isTechField && !hasMetierLabel) {
        findings.push({
          severity: 'high',
          type: 'technical_field',
          message: `Champ technique exposé sans label : name="${name}"`,
        });
        break;
      }
    }
  }

  return { page: name, url, findings };
}

function printReport(results) {
  let totalHigh = 0, totalMedium = 0;
  console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');
  console.log('📊  RAPPORT AUDIT UX BACK-OFFICE\n');

  for (const { page, url, findings } of results) {
    const high   = findings.filter(f => f.severity === 'high');
    const medium = findings.filter(f => f.severity === 'medium');
    totalHigh   += high.length;
    totalMedium += medium.length;

    const status = findings.length === 0 ? '✅' : high.length > 0 ? '❌' : '⚠️ ';
    console.log(`${status}  ${page}  (${url})`);
    if (high.length)   high.forEach(f   => console.log(`     🔴 [high]   ${f.message}`));
    if (medium.length) medium.forEach(f => console.log(`     🟡 [medium] ${f.message}`));
    if (findings.length === 0) console.log('     Aucun finding.');
    console.log();
  }

  console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
  console.log(`Total : ${totalHigh} findings HIGH · ${totalMedium} findings MEDIUM`);
  const verdict = totalHigh === 0 && totalMedium === 0 ? '✅ GO' : totalHigh > 0 ? '❌ NO-GO' : '⚠️  À corriger';
  console.log(`Verdict UX : ${verdict}\n`);
}

(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox'] });
  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  const page    = await context.newPage();

  console.log('\n🔍  Démarrage de l\'audit UX…');
  await login(page);

  const pagesToAudit = TARGET === 'all'
    ? Object.entries(AUDIT_PAGES)
    : [[TARGET.replace(/\//g, '_').replace(/^_/, ''), TARGET]];

  const results = [];
  for (const [name, url] of pagesToAudit) {
    if (!url) continue;
    process.stdout.write(`  Analyse : ${name}…`);
    const result = await auditPage(page, name, url);
    results.push(result);
    console.log(` ${result.findings.length} finding(s)`);
  }

  await browser.close();
  printReport(results);
})().catch(err => {
  console.error(err);
  process.exit(1);
});

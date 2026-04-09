/**
 * audit-general.js — Orchestrateur audit global
 * Exécute SEO, RGPD, A11Y, Performance et Lighthouse même si un module échoue.
 */

const { spawnSync } = require('child_process');
const path = require('path');

const SCRIPTS = [
  { key: 'seo', file: 'audit-seo.js' },
  { key: 'rgpd', file: 'audit-rgpd.js' },
  { key: 'a11y', file: 'audit-a11y.js' },
  { key: 'performance', file: 'audit-performance.js' },
  { key: 'lighthouse', file: 'audit-lighthouse.js' },
];

function runScript(entry) {
  const scriptPath = path.join(__dirname, entry.file);
  console.log('\n' + '-'.repeat(70));
  console.log(`▶ Audit ${entry.key.toUpperCase()} (${entry.file})`);
  console.log('-'.repeat(70));

  const result = spawnSync(process.execPath, [scriptPath], {
    stdio: 'inherit',
    env: process.env,
  });

  return result.status || 0;
}

function main() {
  console.log('\n' + '='.repeat(70));
  console.log("AUDIT GLOBAL — O'Design Création");
  console.log('='.repeat(70));

  const summary = [];
  for (const entry of SCRIPTS) {
    const status = runScript(entry);
    summary.push({ key: entry.key, status });
  }

  console.log('\n' + '='.repeat(70));
  console.log('RÉSUMÉ AUDIT GLOBAL');
  console.log('='.repeat(70));

  let hasFailure = false;
  for (const item of summary) {
    const ok = item.status === 0;
    console.log(`${ok ? '✅' : '❌'} ${item.key} (exit ${item.status})`);
    if (!ok) hasFailure = true;
  }

  process.exit(hasFailure ? 1 : 0);
}

main();

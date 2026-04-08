/**
 * Scan des options de personnalisation sur atelierodesign.fr (Shopify live).
 * Extrait les champs Easify Product Options depuis le DOM de chaque produit.
 */
const { chromium } = require('playwright');
const fs = require('fs');

// Handles depuis le CSV Shopify
const CSV_PATH = '../scripts/products_export_1.csv';

function parseHandles(csvPath) {
  const content = fs.readFileSync(csvPath, 'utf-8');
  const lines = content.split('\n');
  const headers = lines[0].split(',');
  const handleIdx = 0; // Handle est toujours en colonne 0
  const titleIdx = 1;
  const seen = new Set();
  const products = [];
  for (let i = 1; i < lines.length; i++) {
    // CSV simple split (les champs avec virgules sont entre guillemets)
    const row = lines[i].match(/(".*?"|[^,]+)(?=,|$)/g) || [];
    const handle = (row[0] || '').replace(/^"|"$/g, '').trim();
    const title = (row[1] || '').replace(/^"|"$/g, '').trim();
    if (handle && !seen.has(handle)) {
      seen.add(handle);
      products.push({ handle, title });
    }
  }
  return products;
}

async function extractOptions(page) {
  return await page.evaluate(() => {
    const options = [];

    // Sélecteurs Easify Product Options typiques
    const containers = document.querySelectorAll(
      '.easify-option, .easify-po-option, [class*="easify"], [data-easify], ' +
      '.product-option, .custom-option, [class*="custom-option"], ' +
      'form[action*="/cart/add"] .option, ' +
      '.product-form__input, .product-form .field'
    );

    containers.forEach(el => {
      const label = el.querySelector('label, .label, .option-label, legend')?.textContent?.trim() || '';
      const input = el.querySelector('input, select, textarea');
      if (!input && !label) return;
      
      const type = input?.tagName?.toLowerCase() === 'select' ? 'select'
        : input?.type === 'file' ? 'file'
        : input?.type === 'checkbox' ? 'checkbox'
        : input?.tagName?.toLowerCase() === 'textarea' ? 'textarea'
        : input?.type === 'text' || input?.type === 'email' ? 'text'
        : input?.type ? input.type
        : 'unknown';

      const required = input?.required || el.textContent?.includes('*') || false;
      const placeholder = input?.placeholder || '';
      
      // Options de select
      const choices = [];
      if (input?.tagName?.toLowerCase() === 'select') {
        el.querySelectorAll('option').forEach(o => {
          if (o.value && o.value !== '') choices.push(o.textContent.trim());
        });
      }

      options.push({ label, type, required, placeholder, choices });
    });

    // Fallback : recherche dans tout le formulaire produit
    if (options.length === 0) {
      const form = document.querySelector('form[action*="/cart/add"]');
      if (form) {
        form.querySelectorAll('input:not([type=hidden]):not([type=submit]), select, textarea').forEach(input => {
          const label = form.querySelector(`label[for="${input.id}"]`)?.textContent?.trim()
            || input.closest('.field, .form-field, .product-option')?.querySelector('label')?.textContent?.trim()
            || input.placeholder
            || input.name
            || '';
          if (!label || ['quantity', 'id', 'form_type'].includes(input.name)) return;
          const type = input.tagName === 'SELECT' ? 'select'
            : input.tagName === 'TEXTAREA' ? 'textarea'
            : input.type || 'text';
          options.push({ label, type, required: input.required, placeholder: input.placeholder || '' });
        });
      }
    }

    return options;
  });
}

(async () => {
  const products = parseHandles(CSV_PATH).slice(0, 84); // tous
  console.log(`Scanning ${products.length} products...`);

  const browser = await chromium.launch({ args: ['--no-sandbox'], ignoreHTTPSErrors: true });
  const ctx = await browser.newContext({
    userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/120',
    locale: 'fr-FR',
  });

  const results = [];
  let ok = 0, noOptions = 0, errors = 0;

  for (const { handle, title } of products) {
    const url = `https://www.atelierodesign.fr/products/${handle}`;
    try {
      const page = await ctx.newPage();
      await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 20000 });
      // Attendre que Easify charge (JS async)
      await page.waitForTimeout(2500);
      
      const options = await extractOptions(page);
      await page.close();

      results.push({ handle, title, url, options });
      if (options.length > 0) {
        ok++;
        console.log(`✅ ${title.substring(0,45).padEnd(45)} → ${options.length} option(s)`);
      } else {
        noOptions++;
        console.log(`⬜ ${title.substring(0,45).padEnd(45)} → aucune option détectée`);
      }
    } catch (e) {
      errors++;
      console.log(`❌ ${handle}: ${e.message.substring(0,60)}`);
      results.push({ handle, title, url, options: [], error: e.message });
    }
  }

  await browser.close();

  fs.writeFileSync('/tmp/perso-scan.json', JSON.stringify(results, null, 2));
  console.log(`\n=== RÉSULTAT ===`);
  console.log(`✅ avec options : ${ok}`);
  console.log(`⬜ sans options : ${noOptions}`);
  console.log(`❌ erreurs      : ${errors}`);
  console.log(`📄 Résultats   : /tmp/perso-scan.json`);
})().catch(e => { console.error(e); process.exit(1); });

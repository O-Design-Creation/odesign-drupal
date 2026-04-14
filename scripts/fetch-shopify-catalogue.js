#!/usr/bin/env node
/**
 * @file fetch-shopify-catalogue.js
 *
 * Génère catalogue.json depuis le site Shopify live, sans CSV.
 *
 * Sources :
 *   1. API publique /products.json → données structurées (titre, prix, SKU,
 *      images, tags, body, statut)
 *   2. Scraping des pages produit  → SEO meta title/desc + options Easify
 *      (champs `properties[*]` injectés par l'app Easify Product Options)
 *
 * Sortie :
 *   scripts/catalogue.json  (ou CATALOGUE_PATH env)
 *
 * Usage :
 *   node scripts/fetch-shopify-catalogue.js
 *   CATALOGUE_PATH=/tmp/catalogue.json node scripts/fetch-shopify-catalogue.js
 *   DRY_RUN=1 node scripts/fetch-shopify-catalogue.js   # API seulement, pas de scraping
 *
 * Dépendances :
 *   playwright (déjà présent dans playwright/)
 *   → lancer depuis la racine du projet ou exporter NODE_PATH si besoin
 *
 * Résultat catalogue.json :
 * [
 *   {
 *     "handle": "arbre-de-vie-en-bois-facon-scrabble",
 *     "title": "Arbre de vie en bois façon Scrabble",
 *     "body": "<p>...</p>",
 *     "tags": "cadeau, personnalisé",
 *     "status": "active",
 *     "seo_title": "Arbre de vie...",
 *     "seo_desc": "...",
 *     "variants": [
 *       {
 *         "sku": "arbre-scrabble-001",   // SKU Shopify si renseigné, sinon {handle}-NNN (index)
 *         "price": "39.90",
 *         "opt1_name": "Taille",         // libellé de l'axe 1 (p.options[0].name), ex: "Format"
 *         "opt1_value": "A4",            // valeur de l'axe 1 pour cette variante (v.option1)
 *         "opt2_name": null,             // libellé axe 2 (null si produit 1-dimension)
 *         "opt2_value": null,
 *         "opt3_name": null,
 *         "opt3_value": null,
 *         "suspect": false               // true si opt1_value est un entier pur ("1","2") — à vérifier
 *       }
 *     ],
 *     "images": ["https://cdn.shopify.com/..."],
 *     "personalization": [
 *       { "property": "Texte",   "type": "text",     "required": false, "placeholder": "ex: Le bonheur de Mamie", "maxlength": null, "choices": [] },
 *       { "property": "Prénoms", "type": "textarea", "required": false, "placeholder": "Ex: Hugo, Melyo...",       "maxlength": null, "choices": [] }
 *     ]
 *   },
 *   ...
 * ]
 */

const https  = require('https');
const path   = require('path');
const fs     = require('fs');

// ---------------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------------

const SHOP_DOMAIN    = 'www.atelierodesign.fr';
const CATALOGUE_PATH = process.env.CATALOGUE_PATH
  || path.join(__dirname, 'catalogue.json');
const DRY_RUN        = process.env.DRY_RUN === '1';
// Champs Easify internes à ignorer
const TPO_INTERNAL   = new Set(['_tpo_add_by', '_tpo_price', '_tpo_custom_options']);
// Noms de champs Easify génériques (pas encore labelisés par le commerçant)
const TPO_GENERIC_RE = /^(text-box-\d+|textarea-\d+|file-upload-[\d-]+|checkbox-\d+|quantity-\d+)$/i;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function fetchJson(url) {
  return new Promise((resolve, reject) => {
    https.get(url, {
      headers: {
        'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120',
        'Accept': 'application/json',
      },
    }, res => {
      let data = '';
      res.on('data', c => data += c);
      res.on('end', () => {
        try { resolve(JSON.parse(data)); }
        catch (e) { reject(new Error(`JSON parse error on ${url}: ${e.message}`)); }
      });
    }).on('error', reject);
  });
}

/** Récupère tous les produits via l'API publique paginée. */
async function fetchAllProducts() {
  const products = [];
  let page = 1;
  while (true) {
    const url = `https://${SHOP_DOMAIN}/products.json?limit=250&page=${page}`;
    const { products: batch } = await fetchJson(url);
    if (!batch || batch.length === 0) break;
    products.push(...batch);
    if (batch.length < 250) break;
    page++;
  }
  return products;
}

/** Extrait les options Easify (properties[*]) et les SEO meta d'une page produit. */
async function scrapePage(ctx, handle) {
  const url = `https://${SHOP_DOMAIN}/products/${handle}`;
  const page = await ctx.newPage();
  try {
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 20000 });
    // Easify est rendu en JS asynchrone — attendre le chargement
    await page.waitForTimeout(4500);

    return await page.evaluate(({ tpoInternal, tpoGenericPattern }) => {
      const internals = new Set(tpoInternal);
      const genericRe = new RegExp(tpoGenericPattern, 'i');
      const readProperty = (name) => {
        const match = name?.match(/^properties\[(.+)\]$/);
        return match ? match[1] : null;
      };
      const optionMap = new Map();
      const upsertOption = (prop, data) => {
        if (!prop || internals.has(prop)) return;
        const current = optionMap.get(prop) || {
          property: prop,
          label: genericRe.test(prop) ? null : prop,
          type: 'text',
          required: false,
          placeholder: null,
          maxlength: null,
          choices: [],
        };

        if (typeof data.label === 'string' && data.label.trim() !== '') {
          current.label = data.label.trim();
        }
        if (typeof data.type === 'string' && data.type !== '') {
          current.type = data.type;
        }
        if (typeof data.required === 'boolean') {
          current.required = data.required;
        }
        if (typeof data.placeholder === 'string' && data.placeholder.trim() !== '') {
          current.placeholder = data.placeholder.trim();
        }
        if (Number.isInteger(data.maxlength) || data.maxlength === null) {
          current.maxlength = data.maxlength;
        }
        if (Array.isArray(data.choices) && data.choices.length > 0) {
          current.choices = data.choices;
        }

        optionMap.set(prop, current);
      };

      document.querySelectorAll('[name^="properties["]').forEach(el => {
        const prop = readProperty(el.getAttribute('name'));
        if (!prop || internals.has(prop)) return;

        const type = el.tagName === 'TEXTAREA' ? 'textarea'
          : el.tagName === 'SELECT'   ? 'select'
          : el.type === 'file'        ? 'file'
          : el.type === 'checkbox'    ? 'checkbox'
          : el.type === 'radio'       ? 'radio'
          : el.type === 'number'      ? 'number'
          : el.type === 'date'        ? 'date'
          : 'text';

        const choices = type === 'select'
          ? Array.from(el.querySelectorAll('option')).filter(o => o.value).map(o => o.textContent.trim())
          : [];

        upsertOption(prop, {
          label: genericRe.test(prop) ? null : prop,
          type,
          required: el.required || false,
          placeholder: el.placeholder || null,
          maxlength: el.maxLength > 0 ? el.maxLength : null,
          choices,
        });
      });

      document.querySelectorAll('.tpo_option-container').forEach(container => {
        const named = container.querySelector('[name^="properties["]');
        const prop = readProperty(named?.getAttribute('name'));
        if (!prop || internals.has(prop)) return;

        const textInput = container.querySelector('input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([type="file"]):not([type="date"]):not([type="number"])');
        const textarea = container.querySelector('textarea');
        const select = container.querySelector('select');
        const fileInput = container.querySelector('input[type="file"]');
        const numberInput = container.querySelector('input[type="number"]');
        const dateInput = container.querySelector('input[type="date"]');
        const checkboxInputs = Array.from(container.querySelectorAll('input[type="checkbox"]'));
        const radioInputs = Array.from(container.querySelectorAll('input[type="radio"]'));

        let type = 'text';
        if (textarea) {
          type = 'textarea';
        } else if (select) {
          type = 'select';
        } else if (fileInput) {
          type = 'file';
        } else if (checkboxInputs.length > 0) {
          type = 'checkbox';
        } else if (radioInputs.length > 0) {
          type = 'radio';
        } else if (numberInput) {
          type = 'number';
        } else if (dateInput) {
          type = 'date';
        }

        const choiceInputs = checkboxInputs.length > 0 ? checkboxInputs : radioInputs;
        const choices = select
          ? Array.from(select.querySelectorAll('option')).filter(o => o.value).map(o => o.textContent.trim())
          : Array.from(new Set(choiceInputs.map(input => (input.value || '').trim()).filter(Boolean)));
        const label = container.querySelector('.form__label')?.textContent?.replace(/\s+/g, ' ').trim() || null;
        const control = textarea || select || fileInput || numberInput || dateInput || textInput || named;

        upsertOption(prop, {
          label,
          type,
          required: Boolean(container.querySelector('.tpo_option-required-indicator')?.textContent?.trim()) || Boolean(control?.required),
          placeholder: textInput?.placeholder || textarea?.placeholder || named?.placeholder || null,
          maxlength: control && control.maxLength > 0 ? control.maxLength : null,
          choices,
        });
      });

      const seoTitle = document.querySelector('title')?.textContent?.trim() || null;
      const seoDesc  = document.querySelector('meta[name="description"]')?.content?.trim() || null;

      return { options: Array.from(optionMap.values()), seoTitle, seoDesc };
    }, { tpoInternal: [...TPO_INTERNAL], tpoGenericPattern: TPO_GENERIC_RE.source });

  } catch (e) {
    return { options: [], seoTitle: null, seoDesc: null, error: e.message };
  } finally {
    await page.close();
  }
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------

(async () => {
  console.log(`=== fetch-shopify-catalogue.js ===`);
  console.log(`Shop   : ${SHOP_DOMAIN}`);
  console.log(`Output : ${CATALOGUE_PATH}`);
  console.log(DRY_RUN ? `Mode   : DRY_RUN (pas de scraping)` : `Mode   : complet (API + scraping)`);
  console.log('');

  // 1. API → données structurées
  console.log('1/2 — Récupération des produits via API publique...');
  const rawProducts = await fetchAllProducts();
  console.log(`    → ${rawProducts.length} produits récupérés`);

  // 2. Normalisation des données API
  // L'API publique Shopify ne retourne que les produits publiés (pas de champ status).
  const catalogue = rawProducts
    .map(p => ({
      handle:  p.handle,
      title:   p.title,
      body:    p.body_html || '',
      tags:    Array.isArray(p.tags) ? p.tags.join(', ') : (p.tags || ''),
      status:  p.published_at ? 'active' : 'draft',
      seo_title: null,  // rempli par scraping
      seo_desc:  null,
      // Axes d'options Shopify (labels) — issus de p.options, pas des variantes.
      // p.options = [{ name: "Taille", values: [...] }, ...]
      // v.option1 = valeur de l'axe 1 pour cette variante (ex: "A4")
      variants: (p.variants || []).map((v, idx) => ({
        // SKU unique par variante : SKU Shopify si renseigné, sinon {handle}-NNN (index 1-based).
        // La valeur '-001' identique pour toutes les variantes d'un produit est un bug corrigé ici.
        sku:        v.sku || `${p.handle}-${String(idx + 1).padStart(3, '0')}`,
        price:      v.price,
        // opt1_name = libellé de l'axe 1 (ex: "Taille", "Format") — issu de p.options[0].name.
        // ATTENTION : v.option1 est la VALEUR, pas le nom. Erreur de nommage précédente corrigée.
        opt1_name:  (p.options && p.options[0]) ? (p.options[0].name || null) : null,
        opt1_value: (v.option1 && v.option1 !== 'Default Title') ? v.option1 : null,
        // Axes 2 et 3 — capturés pour les produits avec plusieurs dimensions de variation.
        opt2_name:  (p.options && p.options[1]) ? (p.options[1].name || null) : null,
        opt2_value: v.option2 || null,
        opt3_name:  (p.options && p.options[2]) ? (p.options[2].name || null) : null,
        opt3_value: v.option3 || null,
        // Variante suspecte : valeur de type entier pur ("1", "2") sans label métier identifiable.
        suspect:    /^\d+$/.test(String(v.option1 || '').trim()),
      })),
      // Filtrer les entrées sans src valide : undefined deviendrait null dans le JSON,
      // ce qui provoquerait un TypeError PHP 8.1 dans download_image (string non-nullable).
      images: (p.images || []).map(i => i.src).filter(src => typeof src === 'string' && src !== ''),
      personalization: [],  // rempli par scraping
    }));

  console.log(`    → ${catalogue.length} produits actifs retenus`);

  if (DRY_RUN) {
    fs.writeFileSync(CATALOGUE_PATH, JSON.stringify(catalogue, null, 2));
    console.log(`\nDRY_RUN — scraping ignoré. catalogue.json écrit (sans perso ni SEO).`);
    return;
  }

  // 3. Scraping pour SEO + Easify
  console.log('\n2/2 — Scraping des pages produit (SEO + options Easify)...');

  // Playwright doit être résolu depuis le dossier playwright/
  const playwrightPath = path.join(__dirname, '..', 'playwright', 'node_modules', 'playwright');
  const { chromium } = require(playwrightPath);

  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const ctx = await browser.newContext({
    locale:    'fr-FR',
    userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120',
  });

  // Accepter les cookies une fois sur la home
  const init = await ctx.newPage();
  await init.goto(`https://${SHOP_DOMAIN}/`, { waitUntil: 'domcontentloaded', timeout: 20000 });
  await init.waitForTimeout(2000);
  const cookieBtn = await init.$('#shopify-pc__banner__btn-accept, [id*="btn-accept"]');
  if (cookieBtn) { await cookieBtn.click(); await init.waitForTimeout(500); }
  await init.close();
  console.log('    → Cookies acceptés');

  let withPerso = 0;

  for (let i = 0; i < catalogue.length; i++) {
    const product = catalogue[i];
    const { options, seoTitle, seoDesc, error } = await scrapePage(ctx, product.handle);

    product.seo_title = seoTitle;
    product.seo_desc  = seoDesc;
    product.personalization = options;

    const userOptions = options.filter(o => !TPO_INTERNAL.has(o.property));
    if (userOptions.length > 0) {
      withPerso++;
      const summary = userOptions.map(o => `[${o.type}] ${o.property}`).join(' | ');
      console.log(`  ✅ [${i+1}/${catalogue.length}] ${product.title.substring(0,40).padEnd(40)} → ${summary}`);
    } else {
      console.log(`  ⬜ [${i+1}/${catalogue.length}] ${product.title.substring(0,55)}`);
    }

    if (error) console.log(`     ⚠ scraping error: ${error.substring(0,80)}`);

    // Rate limit courtois
    if ((i + 1) % 10 === 0) await new Promise(r => setTimeout(r, 800));
  }

  await browser.close();

  // 4. Écriture
  fs.writeFileSync(CATALOGUE_PATH, JSON.stringify(catalogue, null, 2));

  console.log(`\n=== RÉSULTAT ===`);
  console.log(`Produits écrits        : ${catalogue.length}`);
  console.log(`Avec personnalisation  : ${withPerso}`);
  console.log(`Sans personnalisation  : ${catalogue.length - withPerso}`);
  console.log(`Fichier                : ${CATALOGUE_PATH}`);
})().catch(e => {
  console.error('FATAL:', e.message);
  process.exit(1);
});

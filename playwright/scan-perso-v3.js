/**
 * Scan options Easify v3 — sélecteur fiable : name^="properties["
 */
const { chromium } = require('playwright');
const fs = require('fs');

const handles = JSON.parse(fs.readFileSync('/tmp/live-handles.json', 'utf-8'));

async function extractOptions(page) {
  return await page.evaluate(() => {
    const opts = [];

    // Selector fiable : tous les champs Easify ont name="properties[NomChamp]"
    document.querySelectorAll('[name^="properties["]').forEach(el => {
      const match = el.name.match(/^properties\[(.+)\]$/);
      const fieldName = match ? match[1] : el.name;

      // Label : chercher dans les wrappers tpo_
      const wrapper = el.closest('.tpo_option-input-wrapper, .tpo_option, [class*="tpo_"]')?.parentElement
        || el.closest('[class*="tpo_"]')?.parentElement;
      const labelEl = wrapper?.querySelector('label, legend, .tpo_label, [class*="tpo_option-label"], h3, h4, strong, p')
        || el.closest('form')?.querySelector(`label[for="${el.id}"]`);
      const label = labelEl?.textContent?.trim() || fieldName;

      const type = el.tagName === 'TEXTAREA' ? 'textarea'
        : el.tagName === 'SELECT' ? 'select'
        : el.type === 'file' ? 'file'
        : el.type === 'checkbox' ? 'checkbox'
        : el.type === 'radio' ? 'radio'
        : 'text';

      const choices = type === 'select'
        ? Array.from(el.querySelectorAll('option')).filter(o => o.value).map(o => o.textContent.trim())
        : [];

      opts.push({
        property: fieldName,
        label,
        type,
        required: el.required || false,
        placeholder: el.placeholder || '',
        maxlength: el.maxLength > 0 ? el.maxLength : null,
        choices,
      });
    });

    // SEO meta
    const seoTitle = document.querySelector('title')?.textContent?.trim() || '';
    const seoDesc = document.querySelector('meta[name="description"]')?.content?.trim() || '';

    return { options: opts, seoTitle, seoDesc };
  });
}

(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox'], headless: true });
  const ctx = await browser.newContext({ locale: 'fr-FR', userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120' });

  // Cookies
  const init = await ctx.newPage();
  await init.goto('https://www.atelierodesign.fr/', { waitUntil: 'domcontentloaded', timeout: 20000 });
  await init.waitForTimeout(2000);
  const btn = await init.$('#shopify-pc__banner__btn-accept, [id*="btn-accept"]');
  if (btn) { await btn.click(); console.log('✓ Cookies acceptés'); }
  await init.waitForTimeout(500);
  await init.close();

  const results = [];
  let withOpts = 0, noOpts = 0;

  for (let i = 0; i < handles.length; i++) {
    const { handle, title } = handles[i];
    try {
      const page = await ctx.newPage();
      await page.goto(`https://www.atelierodesign.fr/products/${handle}`, { waitUntil: 'domcontentloaded', timeout: 20000 });
      await page.waitForTimeout(4000);
      const { options, seoTitle, seoDesc } = await extractOptions(page);
      await page.close();

      results.push({ handle, title, options, seoTitle, seoDesc });
      if (options.length > 0) {
        withOpts++;
        const summary = options.map(o => `[${o.type}] ${o.property}`).join(' | ');
        console.log(`✅ [${i+1}/${handles.length}] ${title.substring(0,35).padEnd(35)} → ${summary}`);
      } else {
        noOpts++;
        console.log(`⬜ [${i+1}/${handles.length}] ${title.substring(0,50)}`);
      }
    } catch(e) {
      results.push({ handle, title, options: [], error: e.message });
      console.log(`❌ [${i+1}/${handles.length}] ${handle}: ${e.message.substring(0,60)}`);
    }
    if ((i + 1) % 10 === 0) await new Promise(r => setTimeout(r, 800));
  }

  await browser.close();
  fs.writeFileSync('/tmp/perso-scan-v3.json', JSON.stringify(results, null, 2));

  // Analyse des types de champs
  const allProps = {};
  results.forEach(p => p.options?.forEach(o => {
    const key = `${o.type}:${o.property}`;
    allProps[key] = (allProps[key] || 0) + 1;
  }));

  console.log(`\n=== RÉSULTAT ===`);
  console.log(`✅ avec options Easify : ${withOpts}`);
  console.log(`⬜ sans options        : ${noOpts}`);
  console.log(`\n=== TYPES DE CHAMPS (nb occurrences) ===`);
  Object.entries(allProps).sort((a,b) => b[1]-a[1]).forEach(([k,v]) => console.log(`  ${String(v).padStart(3)}×  ${k}`));
})().catch(e => { console.error(e.message); process.exit(1); });

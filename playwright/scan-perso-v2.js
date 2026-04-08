/**
 * Scan des options Easify sur atelierodesign.fr — v2
 * Accepte les cookies une fois, puis scanne tous les produits.
 */
const { chromium } = require('playwright');
const fs = require('fs');

const handles = JSON.parse(fs.readFileSync('/tmp/live-handles.json', 'utf-8'));

async function extractOptions(page) {
  return await page.evaluate(() => {
    const options = [];

    // Stratégie 1 : widgets Easify classiques
    const easifyWrappers = document.querySelectorAll(
      '[class*="easify"], [id*="easify"], .epo-item, .epo-group, ' +
      '[class*="epo-"], [data-option-id], [class*="product-option"]'
    );
    easifyWrappers.forEach(wrap => {
      const label = wrap.querySelector('label, legend, .epo-label, .option-name, h3, h4, strong')?.textContent?.trim() || '';
      const inp = wrap.querySelector('input:not([type=hidden]):not([type=submit]), select, textarea');
      if (!inp && !label) return;
      const type = !inp ? 'label-only'
        : inp.tagName === 'SELECT' ? 'select'
        : inp.tagName === 'TEXTAREA' ? 'textarea'
        : inp.type === 'file' ? 'file'
        : inp.type === 'checkbox' ? 'checkbox'
        : inp.type === 'radio' ? 'radio'
        : 'text';
      const required = inp?.required || wrap.textContent.includes(' *') || false;
      const choices = type === 'select' ? Array.from(wrap.querySelectorAll('option')).filter(o => o.value).map(o => o.textContent.trim()) : [];
      if (label || inp) options.push({ label, type, required, placeholder: inp?.placeholder || '', choices });
    });

    // Stratégie 2 : tous les inputs du form add-to-cart hors Easify
    const form = document.querySelector('form[action*="/cart/add"]');
    if (form && options.length === 0) {
      form.querySelectorAll('input:not([type=hidden]):not([type=submit]):not([type=button]), select:not([name="id"]), textarea').forEach(inp => {
        if (['quantity', 'id', 'form_type', 'utf8'].includes(inp.name)) return;
        const label = document.querySelector(`label[for="${inp.id}"]`)?.textContent?.trim()
          || inp.closest('.field, .form-group, .option, [class*="custom"]')?.querySelector('label, legend')?.textContent?.trim()
          || inp.getAttribute('aria-label') || inp.placeholder || inp.name || '';
        if (!label) return;
        const type = inp.tagName === 'SELECT' ? 'select' : inp.tagName === 'TEXTAREA' ? 'textarea' : inp.type || 'text';
        const choices = type === 'select' ? Array.from(inp.querySelectorAll('option')).filter(o => o.value).map(o => o.textContent.trim()) : [];
        options.push({ label, type, required: inp.required, placeholder: inp.placeholder || '', choices });
      });
    }

    // Stratégie 3 : chercher les labels "Prénom", "Texte", "Message" etc. n'importe où
    if (options.length === 0) {
      const keywords = ['prénom', 'prénoms', 'texte', 'message', 'date', 'photo', 'fichier', 'personnali'];
      document.querySelectorAll('label, legend, .label, .field-label').forEach(el => {
        const text = el.textContent.trim().toLowerCase();
        if (keywords.some(kw => text.includes(kw))) {
          const forId = el.getAttribute('for');
          const inp = forId ? document.getElementById(forId) : el.nextElementSibling?.querySelector?.('input, textarea, select');
          options.push({
            label: el.textContent.trim(),
            type: inp?.tagName === 'TEXTAREA' ? 'textarea' : inp?.type || 'text',
            required: inp?.required || text.includes('*'),
            placeholder: inp?.placeholder || '',
            choices: [],
          });
        }
      });
    }

    return options;
  });
}

(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox'], headless: true });
  const ctx = await browser.newContext({ locale: 'fr-FR', userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120' });

  // Accepter les cookies une fois sur la home
  const init = await ctx.newPage();
  await init.goto('https://www.atelierodesign.fr/', { waitUntil: 'networkidle', timeout: 20000 });
  const cookieBtn = await init.$('#shopify-pc__banner__btn-accept, [id*="btn-accept"]');
  if (cookieBtn) { await cookieBtn.click(); console.log('✓ Cookies acceptés'); await init.waitForTimeout(500); }
  await init.close();

  const results = [];
  let withOptions = 0, noOptions = 0;

  for (let i = 0; i < handles.length; i++) {
    const { handle, title } = handles[i];
    const url = `https://www.atelierodesign.fr/products/${handle}`;
    try {
      const page = await ctx.newPage();
      await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 20000 });
      await page.waitForTimeout(3500); // Easify charge en JS async
      const options = await extractOptions(page);
      await page.close();

      results.push({ handle, title, options });
      if (options.length > 0) {
        withOptions++;
        const summary = options.map(o => `[${o.type}] ${o.label}`).join(' | ');
        console.log(`✅ [${i+1}/${handles.length}] ${title.substring(0,40).padEnd(40)} → ${summary}`);
      } else {
        noOptions++;
        console.log(`⬜ [${i+1}/${handles.length}] ${title.substring(0,40)}`);
      }
    } catch(e) {
      results.push({ handle, title, options: [], error: e.message });
      console.log(`❌ [${i+1}/${handles.length}] ${handle}: ${e.message.substring(0,50)}`);
    }
    // Rate limit courtois
    if ((i + 1) % 10 === 0) await new Promise(r => setTimeout(r, 1000));
  }

  await browser.close();
  fs.writeFileSync('/tmp/perso-scan-v2.json', JSON.stringify(results, null, 2));
  console.log(`\n=== RÉSULTAT ===`);
  console.log(`✅ avec options : ${withOptions}`);
  console.log(`⬜ sans options : ${noOptions}`);
})().catch(e => { console.error(e.message); process.exit(1); });

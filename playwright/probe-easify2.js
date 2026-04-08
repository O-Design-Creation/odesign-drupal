const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox'], headless: true });
  const ctx = await b.newContext({ locale: 'fr-FR' });
  const p = await ctx.newPage();
  
  const url = 'https://www.atelierodesign.fr/products/cadre-definition-papa-ou-maman-en-bois-personnalise';
  await p.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
  
  // Accepter les cookies
  const cookieBtn = await p.$('#shopify-pc__banner__btn-accept, button:has-text("Accepter"), [id*="accept"]');
  if (cookieBtn) { await cookieBtn.click(); await p.waitForTimeout(1000); }

  // Attendre que Easify charge
  await p.waitForTimeout(5000);
  await p.screenshot({ path: '/tmp/product-after-cookies.png', fullPage: true });

  const info = await p.evaluate(() => {
    const results = { forms: [], easify: [], allInputs: [] };

    // Tous les forms de la page
    document.querySelectorAll('form').forEach(form => {
      results.forms.push({
        action: form.action,
        id: form.id,
        class: form.className.substring(0, 60),
        inputCount: form.querySelectorAll('input, select, textarea').length,
      });
    });

    // Chercher le form add-to-cart ou produit
    const form = document.querySelector('form[action*="/cart/add"]') 
      || document.querySelector('form[id*="product"]')
      || document.querySelector('.product-form');

    if (form) {
      form.querySelectorAll('input, select, textarea').forEach(el => {
        if (['hidden', 'submit', 'button'].includes(el.type)) return;
        const label = document.querySelector(`label[for="${el.id}"]`)?.textContent?.trim()
          || el.closest('.field, .form-group, .option, .product-option, .custom-option')?.querySelector('label, legend, .label, .title')?.textContent?.trim()
          || el.dataset?.label || '';
        results.allInputs.push({
          tag: el.tagName, type: el.type, name: el.name, id: el.id,
          label: label.substring(0, 80), placeholder: el.placeholder,
          required: el.required, class: el.className.substring(0, 60),
        });
      });
    }

    // Chercher spécifiquement Easify partout dans la page
    document.querySelectorAll('[class*="easify"], [id*="easify"]').forEach(el => {
      const inputs = Array.from(el.querySelectorAll('input, select, textarea')).map(inp => ({
        tag: inp.tagName, type: inp.type, name: inp.name,
        label: document.querySelector(`label[for="${inp.id}"]`)?.textContent?.trim() || inp.placeholder || '',
        required: inp.required,
      }));
      if (el.classList.contains('easify-option') || inputs.length || el.tagName !== 'SCRIPT') {
        results.easify.push({
          class: el.className.substring(0, 100),
          tag: el.tagName,
          text: el.textContent.trim().substring(0, 120),
          inputs,
        });
      }
    });

    return results;
  });

  console.log(JSON.stringify(info, null, 2));
  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });

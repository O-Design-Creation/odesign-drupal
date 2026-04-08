/**
 * Inspection manuelle d'un produit pour voir ce que Easify rend vraiment dans le DOM.
 */
const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch({ args: ['--no-sandbox'], headless: true });
  const ctx = await b.newContext({ locale: 'fr-FR' });
  const p = await ctx.newPage();
  
  const url = 'https://www.atelierodesign.fr/products/cadre-definition-papa-ou-maman-en-bois-personnalise';
  console.log('Fetching:', url);
  await p.goto(url, { waitUntil: 'networkidle', timeout: 30000 });
  await p.waitForTimeout(4000); // laisser Easify charger

  // Capturer tous les champs du formulaire add-to-cart
  const info = await p.evaluate(() => {
    const form = document.querySelector('form[action*="/cart/add"]');
    if (!form) return { error: 'Pas de form add-to-cart', html: document.body.innerHTML.substring(0, 2000) };
    
    const inputs = [];
    form.querySelectorAll('input, select, textarea').forEach(el => {
      if (['hidden', 'submit', 'button'].includes(el.type)) return;
      const label = document.querySelector(`label[for="${el.id}"]`)?.textContent?.trim()
        || el.closest('[class*="field"], [class*="option"], [class*="input"]')?.querySelector('label, .label')?.textContent?.trim()
        || '';
      inputs.push({
        tag: el.tagName,
        type: el.type || 'N/A',
        name: el.name,
        id: el.id,
        class: el.className.substring(0, 80),
        label,
        placeholder: el.placeholder,
        required: el.required,
      });
    });

    // Extraire aussi tous les class names uniques pour comprendre la structure
    const classes = new Set();
    form.querySelectorAll('*').forEach(el => {
      el.classList.forEach(c => classes.add(c));
    });

    // Chercher spécifiquement Easify
    const easifyEls = document.querySelectorAll('[class*="easify"], [id*="easify"], [data-easify]');
    const easifyInfo = Array.from(easifyEls).slice(0, 5).map(el => ({
      tag: el.tagName,
      class: el.className.substring(0, 100),
      text: el.textContent.trim().substring(0, 100),
    }));

    return {
      inputCount: inputs.length,
      inputs,
      easifyEls: easifyInfo,
      relevantClasses: Array.from(classes).filter(c => 
        c.includes('option') || c.includes('custom') || c.includes('personali') || 
        c.includes('easify') || c.includes('field') || c.includes('input')
      ).slice(0, 30),
    };
  });

  console.log(JSON.stringify(info, null, 2));
  await p.screenshot({ path: '/tmp/shopify-product.png' });
  await b.close();
})().catch(e => { console.error(e.message); process.exit(1); });

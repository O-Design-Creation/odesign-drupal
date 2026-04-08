const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const ctx = await browser.newContext({ locale: 'fr-FR', userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120' });

  // Accepter les cookies
  const init = await ctx.newPage();
  await init.goto('https://www.atelierodesign.fr/', { waitUntil: 'domcontentloaded', timeout: 20000 });
  await init.waitForTimeout(2000);
  const cookieBtn = await init.$('#shopify-pc__banner__btn-accept, [id*="btn-accept"]');
  if (cookieBtn) { await cookieBtn.click(); console.log('Cookies OK'); }
  await init.waitForTimeout(500);
  await init.close();

  const page = await ctx.newPage();
  // domcontentloaded + attente manuelle pour Easify
  await page.goto('https://www.atelierodesign.fr/products/arbre-de-vie-en-bois-facon-scrabble', { waitUntil: 'domcontentloaded', timeout: 20000 });
  await page.waitForTimeout(6000);

  // 1. Lister TOUS les inputs/selects/textareas avec contexte
  const inputs = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('input:not([type=hidden]), select, textarea')).map(el => ({
      tag: el.tagName, type: el.type || '', name: el.name || '', id: el.id || '',
      className: el.className.substring(0, 80), placeholder: el.placeholder || '',
      parentClass: el.parentElement?.className?.substring(0,60) || '',
    }));
  });
  console.log('\n=== ALL VISIBLE INPUTS ===');
  inputs.forEach(i => console.log(JSON.stringify(i)));

  // 2. Chercher iframes
  const iframes = await page.evaluate(() => Array.from(document.querySelectorAll('iframe')).map(f => ({ src: f.src?.substring(0,100), id: f.id, cls: f.className })));
  console.log('\n=== IFRAMES ===', JSON.stringify(iframes, null, 2));

  // 3. Éléments EPO/Easify
  const special = await page.evaluate(() => {
    const sel = '[class*="epo"],[id*="epo"],[class*="easify"],[id*="easify"],[data-epo],[data-option-id]';
    return Array.from(document.querySelectorAll(sel)).slice(0, 20).map(el => ({
      tag: el.tagName, id: el.id, cls: el.className.substring(0,80),
      text: el.textContent.trim().substring(0,80)
    }));
  });
  console.log('\n=== EPO/EASIFY ELEMENTS ===');
  special.forEach(e => console.log(JSON.stringify(e)));

  // 4. Scripts chargés (chercher "easify" ou "epo")
  const scripts = await page.evaluate(() =>
    Array.from(document.querySelectorAll('script[src]'))
      .map(s => s.src)
      .filter(s => s.includes('easify') || s.includes('epo') || s.includes('option'))
  );
  console.log('\n=== SCRIPTS EASIFY ===', scripts);

  // 5. Shadow DOM check
  const shadows = await page.evaluate(() => {
    const withShadow = [];
    document.querySelectorAll('*').forEach(el => { if (el.shadowRoot) withShadow.push({ tag: el.tagName, id: el.id, cls: el.className?.substring?.(0,60) }); });
    return withShadow.slice(0,10);
  });
  console.log('\n=== SHADOW DOM ===', JSON.stringify(shadows));

  await browser.close();
})().catch(e => { console.error(e.message); process.exit(1); });

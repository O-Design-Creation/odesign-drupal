/**
 * generate-favicons.js — Génère les favicons depuis logo.svg
 * Utilise Playwright pour rendre le SVG puis exporte en PNG
 */
const { chromium } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const THEME_DIR = path.join(__dirname, '../web/themes/custom/odesign_base');
const SVG_PATH  = path.join(THEME_DIR, 'logo.svg');
const SVG_OUT   = path.join(THEME_DIR, 'images');

if (!fs.existsSync(SVG_OUT)) fs.mkdirSync(SVG_OUT, { recursive: true });

const svgContent = fs.readFileSync(SVG_PATH, 'utf8');

const SIZES = [
  { size: 16,  name: 'favicon-16x16.png' },
  { size: 32,  name: 'favicon-32x32.png' },
  { size: 48,  name: 'favicon-48x48.png' },
  { size: 180, name: 'apple-touch-icon.png' },
  { size: 192, name: 'android-chrome-192x192.png' },
  { size: 512, name: 'android-chrome-512x512.png' },
];

(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox'] });
  const page = await browser.newPage();

  for (const { size, name } of SIZES) {
    await page.setViewportSize({ width: size, height: size });
    await page.setContent(`<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  html, body { width: ${size}px; height: ${size}px; overflow: hidden; background: transparent; }
  .wrap {
    width: ${size}px; height: ${size}px;
    border-radius: ${Math.round(size * 0.15)}px;
    overflow: hidden;
    display: flex; align-items: center; justify-content: center;
  }
  img { width: 100%; height: 100%; object-fit: contain; }
</style>
</head>
<body>
<div class="wrap">
  <img src="data:image/svg+xml;base64,${Buffer.from(svgContent).toString('base64')}" />
</div>
</body>
</html>`, { waitUntil: 'networkidle' });

    await page.evaluate(() => document.fonts.ready);

    const outPath = path.join(SVG_OUT, name);
    await page.screenshot({
      path: outPath,
      clip: { x: 0, y: 0, width: size, height: size },
      omitBackground: true,
    });
    console.log(`✅ Généré : images/${name} (${size}x${size})`);
  }

  // SVG favicon (copy with correct viewBox)
  const svgFaviconPath = path.join(THEME_DIR, 'images/favicon.svg');
  // Create a clean square version of the SVG
  const cleanSvg = svgContent.replace(
    /width="\d+" height="\d+"/,
    'width="32" height="32"'
  );
  fs.writeFileSync(svgFaviconPath, cleanSvg);
  console.log(`✅ Généré : images/favicon.svg`);

  await browser.close();
  console.log('\n✅ Tous les favicons générés dans web/themes/custom/odesign_base/images/');
})();

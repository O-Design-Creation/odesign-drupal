/**
 * audit-campagne-fete-des-meres.js
 * Audit UX/UI (mobile + desktop) de la page campagne Fête des mères
 * et vérification de l'affichage du widget campagne sur la homepage.
 *
 * Usage : node playwright/scripts/audit-campagne-fete-des-meres.js
 * Cible : https://preprod.atelierodesign.fr
 */

const { chromium } = require('@playwright/test');
const { AxeBuilder } = require('@axe-core/playwright');
const fs = require('fs');
const path = require('path');

const BASE_URL = (process.env.BASE_URL || 'https://preprod.atelierodesign.fr').replace(/\/$/, '');
const CAMPAGNE_PATH = '/campagnes/fete-des-meres';
const SCREENSHOTS_DIR = path.join(__dirname, '..', 'screenshots', 'audit-campagne-fete-des-meres');
fs.mkdirSync(SCREENSHOTS_DIR, { recursive: true });

const VIEWPORTS = [
  { name: 'desktop', width: 1440, height: 900 },
  { name: 'mobile',  width: 390,  height: 844 },
];

const RED    = '\x1b[31m';
const YELLOW = '\x1b[33m';
const GREEN  = '\x1b[32m';
const CYAN   = '\x1b[36m';
const RESET  = '\x1b[0m';

const findings = [];

function log(icon, label, msg) {
  console.log(`  ${icon} ${label} : ${msg}`);
}

function addFinding(severity, section, message) {
  findings.push({ severity, section, message });
  const icon = severity === 'error' ? '❌' : severity === 'warning' ? '⚠️ ' : '✅';
  log(icon, `[${severity.toUpperCase()}]`, `[${section}] ${message}`);
}

function ok(section, message) {
  console.log(`  ${GREEN}✅${RESET} [${section}] ${message}`);
}

async function dismissCookieBanner(page) {
  const btn = page.locator('#sliding-popup .agree-button, .eu-cookie-compliance-agree-button').first();
  if (await btn.isVisible({ timeout: 2000 }).catch(() => false)) {
    await btn.click();
    await page.waitForTimeout(400);
  }
}

async function auditCampagnePage(browser, viewport) {
  const section = `campagne-page [${viewport.name}]`;
  console.log(`\n${CYAN}━━━ Audit page campagne — ${viewport.name} (${viewport.width}×${viewport.height}) ━━━${RESET}`);

  const ctx = await browser.newContext({
    viewport: { width: viewport.width, height: viewport.height },
    ignoreHTTPSErrors: true,
    userAgent: viewport.name === 'mobile'
      ? 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'
      : undefined,
  });
  const page = await ctx.newPage();

  // --- HTTP ---
  const response = await page.goto(BASE_URL + CAMPAGNE_PATH, { waitUntil: 'domcontentloaded', timeout: 20000 });
  const status = response ? response.status() : 0;
  if (!response || status >= 400) {
    addFinding('error', section, `HTTP ${status} sur ${CAMPAGNE_PATH}`);
    await ctx.close();
    return;
  }
  ok(section, `HTTP ${status} — page accessible`);

  await page.waitForLoadState('networkidle').catch(() => {});
  await dismissCookieBanner(page);
  await page.evaluate(() => document.fonts.ready);

  // --- Screenshot pleine page ---
  const shotPath = path.join(SCREENSHOTS_DIR, `${viewport.name}-campagne-fullpage.png`);
  await page.screenshot({ path: shotPath, fullPage: true });
  log('📸', 'screenshot', shotPath.replace(path.join(__dirname, '../..'), ''));

  // --- Screenshot above-the-fold ---
  const foldPath = path.join(SCREENSHOTS_DIR, `${viewport.name}-campagne-fold.png`);
  await page.screenshot({ path: foldPath, fullPage: false });
  log('📸', 'screenshot', foldPath.replace(path.join(__dirname, '../..'), ''));

  // --- Analyse DOM ---
  const dom = await page.evaluate(() => {
    const r = {};

    // Titre page
    r.title = document.title;

    // H1
    const h1 = document.querySelector('h1');
    r.h1Text = h1 ? h1.innerText.trim() : null;
    r.h1Count = document.querySelectorAll('h1').length;

    // Hiérarchie des titres
    const headings = [...document.querySelectorAll('h1,h2,h3,h4,h5,h6')].map(h => ({
      tag: h.tagName.toLowerCase(),
      text: h.innerText.trim().substring(0, 80),
    }));
    r.headings = headings;

    // Présence d'une image hero / bannière
    r.hasHeroImage = !!document.querySelector(
      '.campagne-hero img, .hero img, .field--name-field-image img, .node__content img, article img'
    );
    r.heroImgSrc = document.querySelector(
      '.campagne-hero img, .hero img, .field--name-field-image img, .node__content img, article img'
    )?.getAttribute('src') || null;

    // Images sans alt
    const imgs = [...document.querySelectorAll('img')];
    r.totalImages = imgs.length;
    r.imagesWithoutAlt = imgs.filter(i => !i.getAttribute('alt') && !i.getAttribute('role')).map(i => i.src.split('/').pop());

    // CTAs / liens d'action
    const ctas = [...document.querySelectorAll('a.button, a.btn, .cta a, .campagne-cta a, a[href*="/produits"], a[href*="/products"]')]
      .map(a => ({ text: a.innerText.trim().substring(0, 80), href: a.getAttribute('href') }));
    r.ctas = ctas;

    // Liens internes cassés (href vide)
    const links = [...document.querySelectorAll('a[href]')];
    r.totalLinks = links.length;
    r.emptyLinks = links.filter(a => !a.getAttribute('href') || a.getAttribute('href') === '#').length;

    // Éléments structurels
    r.hasHeader  = !!document.querySelector('header, .site-header');
    r.hasNav     = !!document.querySelector('nav, .main-nav');
    r.hasMain    = !!document.querySelector('main, [role="main"]');
    r.hasFooter  = !!document.querySelector('footer, .site-footer');
    r.hasBreadcrumb = !!document.querySelector('.breadcrumb, nav[aria-label*="retenez"], [role="navigation"]');

    // Boutons/CTA visibles dans le contenu
    r.contentButtons = [...document.querySelectorAll('main a, main button')]
      .map(el => el.innerText.trim())
      .filter(t => t.length > 0 && t.length < 100);

    // Meta description
    r.metaDescription = document.querySelector('meta[name="description"]')?.getAttribute('content') || null;

    // OG image
    r.ogImage = document.querySelector('meta[property="og:image"]')?.getAttribute('content') || null;

    // Campagne-specific classes
    r.campagneClasses = [...document.querySelectorAll('[class*="campagne"]')]
      .map(el => ({ tag: el.tagName.toLowerCase(), classes: el.className }));

    return r;
  });

  // Vérifications H1
  if (!dom.h1Text) {
    addFinding('error', section, 'Aucun H1 présent sur la page');
  } else {
    ok(section, `H1 : "${dom.h1Text}"`);
  }
  if (dom.h1Count > 1) {
    addFinding('warning', section, `${dom.h1Count} H1 sur la page (unicité attendue)`);
  }

  // Hiérarchie titres
  console.log(`     Titres : ${dom.headings.map(h => `<${h.tag}> ${h.text}`).join(' | ').substring(0, 200)}`);

  // Hero image
  if (!dom.hasHeroImage) {
    addFinding('warning', section, 'Aucune image hero/bannière détectée');
  } else {
    ok(section, `Image hero présente : ${dom.heroImgSrc?.split('/').pop() || '?'}`);
  }

  // Images sans alt
  if (dom.imagesWithoutAlt.length > 0) {
    addFinding('warning', section, `${dom.imagesWithoutAlt.length} image(s) sans attribut alt : ${dom.imagesWithoutAlt.slice(0, 3).join(', ')}`);
  } else {
    ok(section, `Toutes les images ont un attribut alt (${dom.totalImages} images)`);
  }

  // CTAs
  if (dom.ctas.length === 0) {
    addFinding('warning', section, 'Aucun bouton/lien CTA détecté dans le contenu');
  } else {
    ok(section, `CTA(s) détecté(s) : ${dom.ctas.map(c => `"${c.text}" → ${c.href}`).join(' | ').substring(0, 200)}`);
  }

  // Liens internes
  if (dom.emptyLinks > 0) {
    addFinding('warning', section, `${dom.emptyLinks} lien(s) avec href vide ou ancre seulement`);
  }

  // Éléments structurels
  if (!dom.hasHeader) addFinding('error', section, 'Header absent');
  else ok(section, 'Header présent');
  if (!dom.hasMain) addFinding('error', section, 'Élément <main> absent');
  else ok(section, 'Main présent');
  if (!dom.hasFooter) addFinding('warning', section, 'Footer absent');
  else ok(section, 'Footer présent');

  // SEO
  if (!dom.metaDescription) {
    addFinding('warning', section, 'Meta description absente');
  } else {
    ok(section, `Meta description : "${dom.metaDescription.substring(0, 80)}…"`);
  }
  if (!dom.ogImage) {
    addFinding('warning', section, 'og:image absent');
  } else {
    ok(section, `og:image : ${dom.ogImage.split('/').pop()}`);
  }

  // Classes campagne
  if (dom.campagneClasses.length > 0) {
    ok(section, `Classes .campagne* trouvées : ${dom.campagneClasses.map(c => c.classes).join(', ').substring(0, 200)}`);
  } else {
    addFinding('warning', section, 'Aucune classe CSS spécifique ".campagne*" détectée');
  }

  // Boutons de contenu
  if (dom.contentButtons.length > 0) {
    console.log(`     Contenus cliquables : ${dom.contentButtons.slice(0, 8).join(' | ')}`);
  }

  // Titre et meta
  console.log(`     <title> : ${dom.title}`);

  // --- Audit axe-core (desktop uniquement pour éviter la redondance) ---
  if (viewport.name === 'desktop') {
    console.log(`\n${CYAN}  ─── Axe-core WCAG 2.1 AA ───${RESET}`);
    try {
      const axeResults = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'best-practice'])
        .analyze();
      const violations = axeResults.violations;
      const criticals = violations.filter(v => ['critical', 'serious'].includes(v.impact));
      if (violations.length === 0) {
        ok(section, 'Axe-core : 0 violation WCAG 2.1 AA');
      } else {
        criticals.forEach(v => addFinding('error', section, `[a11y] ${v.impact.toUpperCase()} — ${v.id} : ${v.description} (${v.nodes.length} élément(s))`));
        violations.filter(v => !['critical', 'serious'].includes(v.impact))
          .forEach(v => addFinding('warning', section, `[a11y] ${v.impact} — ${v.id} : ${v.description}`));
        if (criticals.length === 0) ok(section, `Axe-core : 0 violation critique/sérieuse (${violations.length} totale(s))`);
      }
    } catch (e) {
      addFinding('warning', section, `Axe-core indisponible : ${e.message}`);
    }
  }

  // --- Vérifications responsive mobile ---
  if (viewport.name === 'mobile') {
    const mobileChecks = await page.evaluate(() => {
      const nav = document.querySelector('.site-header nav, header nav');
      const hamburger = document.querySelector(
        '.hamburger, .mobile-menu-toggle, button[aria-controls*="menu"], button[aria-expanded], .admin-nav-mobile-toggle'
      );
      const overflowX = document.documentElement.scrollWidth > document.documentElement.clientWidth;
      const imgs = [...document.querySelectorAll('img')];
      const oversizedImgs = imgs.filter(i => i.offsetWidth > window.innerWidth).map(i => i.src.split('/').pop());
      return { hasHamburger: !!hamburger, overflowX, oversizedImgs };
    });

    if (mobileChecks.overflowX) {
      addFinding('warning', section, 'Débordement horizontal détecté (overflow-x) — possible layout cassé sur mobile');
    } else {
      ok(section, 'Pas de débordement horizontal mobile');
    }
    if (!mobileChecks.hasHamburger) {
      addFinding('warning', section, 'Aucun bouton hamburger/menu mobile détecté dans le header');
    } else {
      ok(section, 'Bouton menu mobile présent');
    }
    if (mobileChecks.oversizedImgs.length > 0) {
      addFinding('warning', section, `Images plus larges que l'écran mobile : ${mobileChecks.oversizedImgs.join(', ')}`);
    } else {
      ok(section, 'Toutes les images respectent la largeur mobile');
    }

    // Screenshot scrollé sur 1/3 de la page
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight / 3));
    await page.waitForTimeout(300);
    const scrollPath = path.join(SCREENSHOTS_DIR, `mobile-campagne-scroll.png`);
    await page.screenshot({ path: scrollPath, fullPage: false });
    log('📸', 'screenshot', scrollPath.replace(path.join(__dirname, '../..'), ''));
  }

  await ctx.close();
}

async function auditHomepageCampaignWidget(browser) {
  console.log(`\n${CYAN}━━━ Vérification widget campagne sur la homepage ━━━${RESET}`);
  const section = 'homepage-widget';

  const ctx = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    ignoreHTTPSErrors: true,
  });
  const page = await ctx.newPage();

  const response = await page.goto(BASE_URL + '/', { waitUntil: 'domcontentloaded', timeout: 20000 });
  if (!response || response.status() >= 400) {
    addFinding('error', section, `HTTP ${response?.status()} sur la homepage`);
    await ctx.close();
    return;
  }
  await page.waitForLoadState('networkidle').catch(() => {});
  await dismissCookieBanner(page);
  await page.evaluate(() => document.fonts.ready);

  const widget = await page.evaluate(() => {
    const el = document.querySelector('.campagne-widget, [class*="campagne-widget"]');
    if (!el) return null;
    return {
      classes: el.className,
      text: el.innerText.trim().substring(0, 300),
      hasLink: !!el.querySelector('a'),
      linkHref: el.querySelector('a')?.getAttribute('href') || null,
      linkText: el.querySelector('a')?.innerText.trim() || null,
      hasImage: !!el.querySelector('img'),
      imgSrc: el.querySelector('img')?.getAttribute('src') || null,
      visible: el.offsetParent !== null,
    };
  });

  if (!widget) {
    addFinding('error', section, 'Widget .campagne-widget introuvable sur la homepage — campagne non affichée');

    // Debug : chercher d'autres indices de campagne
    const debug = await page.evaluate(() => {
      const campagneLinks = [...document.querySelectorAll('a[href*="campagne"]')].map(a => ({
        text: a.innerText.trim(),
        href: a.href,
      }));
      const campagneBlocks = [...document.querySelectorAll('[class*="campagne"]')].map(el => ({
        tag: el.tagName,
        classes: el.className,
        visible: el.offsetParent !== null,
      }));
      return { campagneLinks, campagneBlocks };
    });

    if (debug.campagneLinks.length > 0) {
      console.log(`     Liens /campagne* trouvés : ${JSON.stringify(debug.campagneLinks)}`);
    }
    if (debug.campagneBlocks.length > 0) {
      console.log(`     Blocs .campagne* trouvés (sans .campagne-widget) : ${JSON.stringify(debug.campagneBlocks)}`);
    } else {
      console.log('     Aucun bloc ni lien relatif aux campagnes trouvé sur la homepage.');
    }
  } else {
    if (!widget.visible) {
      addFinding('warning', section, 'Widget .campagne-widget présent mais non visible (offsetParent = null)');
    } else {
      ok(section, `Widget campagne visible sur la homepage (classes : ${widget.classes})`);
    }
    if (widget.hasLink) {
      ok(section, `Lien CTA présent : "${widget.linkText}" → ${widget.linkHref}`);
      if (widget.linkHref && !widget.linkHref.includes('fete-des-meres')) {
        addFinding('warning', section, `Le lien du widget (${widget.linkHref}) ne pointe pas vers /campagnes/fete-des-meres`);
      }
    } else {
      addFinding('warning', section, 'Widget présent mais sans lien CTA');
    }
    if (widget.hasImage) {
      ok(section, `Image du widget : ${widget.imgSrc?.split('/').pop()}`);
    } else {
      addFinding('warning', section, 'Widget présent sans image');
    }
    console.log(`     Contenu texte : ${widget.text.substring(0, 150)}`);
  }

  // Screenshot homepage pleine page (desktop)
  const shotDesktop = path.join(SCREENSHOTS_DIR, 'desktop-homepage-widget.png');
  await page.screenshot({ path: shotDesktop, fullPage: true });
  log('📸', 'screenshot', shotDesktop.replace(path.join(__dirname, '../..'), ''));

  // Screenshot homepage mobile
  await ctx.close();

  const ctxMobile = await browser.newContext({
    viewport: { width: 390, height: 844 },
    ignoreHTTPSErrors: true,
    userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
  });
  const pageMobile = await ctxMobile.newPage();
  await pageMobile.goto(BASE_URL + '/', { waitUntil: 'domcontentloaded', timeout: 20000 });
  await pageMobile.waitForLoadState('networkidle').catch(() => {});
  await dismissCookieBanner(pageMobile);
  await pageMobile.evaluate(() => document.fonts.ready);

  const widgetMobile = await pageMobile.evaluate(() => {
    const el = document.querySelector('.campagne-widget, [class*="campagne-widget"]');
    return el ? { visible: el.offsetParent !== null, classes: el.className } : null;
  });

  if (!widgetMobile) {
    addFinding('error', 'homepage-widget [mobile]', 'Widget .campagne-widget introuvable sur la homepage mobile');
  } else if (!widgetMobile.visible) {
    addFinding('warning', 'homepage-widget [mobile]', 'Widget présent mais non visible sur mobile');
  } else {
    ok('homepage-widget [mobile]', 'Widget campagne visible sur mobile');
  }

  const shotMobile = path.join(SCREENSHOTS_DIR, 'mobile-homepage-widget.png');
  await pageMobile.screenshot({ path: shotMobile, fullPage: true });
  log('📸', 'screenshot', shotMobile.replace(path.join(__dirname, '../..'), ''));

  await ctxMobile.close();
}

function printSummary() {
  console.log(`\n${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${RESET}`);
  console.log('RAPPORT FINAL — Audit campagne Fête des mères\n');

  const errors   = findings.filter(f => f.severity === 'error');
  const warnings = findings.filter(f => f.severity === 'warning');

  errors.forEach(f   => console.log(`  ${RED}❌ [ERROR]   [${f.section}] ${f.message}${RESET}`));
  warnings.forEach(f => console.log(`  ${YELLOW}⚠️  [WARNING] [${f.section}] ${f.message}${RESET}`));

  console.log(`\n${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${RESET}`);
  console.log(`Total : ${RED}${errors.length} ERROR(S)${RESET} · ${YELLOW}${warnings.length} WARNING(S)${RESET}`);
  const verdict = errors.length === 0
    ? `${GREEN}✅ GO — aucun finding bloquant${RESET}`
    : `${RED}❌ NO-GO — corriger les ${errors.length} erreur(s) avant déploiement${RESET}`;
  console.log(`Verdict : ${verdict}\n`);
  console.log(`Screenshots : ${SCREENSHOTS_DIR}\n`);
}

(async () => {
  console.log(`\n${CYAN}════ Audit UX/UI — Page campagne Fête des mères ════${RESET}`);
  console.log(`Cible : ${BASE_URL}${CAMPAGNE_PATH}\n`);

  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage'] });

  for (const viewport of VIEWPORTS) {
    await auditCampagnePage(browser, viewport);
  }

  await auditHomepageCampaignWidget(browser);

  await browser.close();
  printSummary();
})().catch(err => {
  console.error(`\n${RED}Erreur fatale :${RESET}`, err.message);
  process.exit(1);
});

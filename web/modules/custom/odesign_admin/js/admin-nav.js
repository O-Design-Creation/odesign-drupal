/**
 * @file admin-nav.js
 * Comportement des groupes déroulants de la barre de navigation boutique.
 *
 * - Clic sur un bouton de groupe : toggle du panneau
 * - Clic en dehors : fermeture de tous les panneaux ouverts
 * - Touche Escape : fermeture du panneau actif
 * - Touche ArrowDown/ArrowUp : navigation clavier dans le dropdown ouvert
 */
(function () {
  'use strict';

  const OPEN_CLASS   = 'odesign-admin-nav__group--open';
  const TOGGLE_SEL   = '.odesign-admin-nav__group-toggle';
  const GROUP_SEL    = '.odesign-admin-nav__group';
  const DROPDOWN_SEL = '.odesign-admin-nav__dropdown';
  const ITEM_SEL     = '.odesign-admin-nav__dropdown-link';

  function closeAll(except) {
    document.querySelectorAll(GROUP_SEL + '.' + OPEN_CLASS).forEach(function (g) {
      if (g !== except) {
        g.classList.remove(OPEN_CLASS);
        const btn = g.querySelector(TOGGLE_SEL);
        if (btn) btn.setAttribute('aria-expanded', 'false');
      }
    });
  }

  function openGroup(group) {
    group.classList.add(OPEN_CLASS);
    const btn = group.querySelector(TOGGLE_SEL);
    if (btn) btn.setAttribute('aria-expanded', 'true');
  }

  function closeGroup(group) {
    group.classList.remove(OPEN_CLASS);
    const btn = group.querySelector(TOGGLE_SEL);
    if (btn) btn.setAttribute('aria-expanded', 'false');
  }

  // Toggle au clic sur le bouton
  document.addEventListener('click', function (e) {
    const toggle = e.target.closest(TOGGLE_SEL);
    if (toggle) {
      e.stopPropagation();
      const group    = toggle.closest(GROUP_SEL);
      const isOpen   = group.classList.contains(OPEN_CLASS);
      closeAll();
      if (!isOpen) openGroup(group);
      return;
    }
    // Clic en dehors → tout fermer
    if (!e.target.closest(GROUP_SEL)) {
      closeAll();
    }
  });

  // Escape → ferme tout
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    const openGroup = document.querySelector(GROUP_SEL + '.' + OPEN_CLASS);
    if (openGroup) {
      closeGroup(openGroup);
      const btn = openGroup.querySelector(TOGGLE_SEL);
      if (btn) btn.focus();
    }
  });

  // Navigation clavier dans le dropdown
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
    const dropdown = e.target.closest(DROPDOWN_SEL);
    if (!dropdown) return;
    e.preventDefault();
    const links   = Array.from(dropdown.querySelectorAll(ITEM_SEL));
    const current = links.indexOf(document.activeElement);
    let next;
    if (e.key === 'ArrowDown') next = (current + 1) % links.length;
    else next = (current - 1 + links.length) % links.length;
    links[next].focus();
  });

  // Fermer si on sort du groupe par Tab
  document.addEventListener('focusout', function (e) {
    const group = e.target.closest(GROUP_SEL);
    if (!group) return;
    // Délai micro pour laisser le focus se poser
    setTimeout(function () {
      if (!group.contains(document.activeElement)) {
        closeGroup(group);
      }
    }, 50);
  });

})();

/* ─── Drawer hamburger (mobile) ─────────────────────────────────────────── */
(function () {
  'use strict';

  const burger  = document.querySelector('.odesign-admin-nav__burger');
  const drawer  = document.getElementById('boutique-nav-drawer');
  const overlay = document.getElementById('boutique-nav-overlay');

  if (!burger || !drawer || !overlay) return;

  function openDrawer() {
    drawer.classList.add('is-open');
    overlay.classList.add('is-visible');
    burger.setAttribute('aria-expanded', 'true');
    burger.setAttribute('aria-label', 'Fermer le menu');
    burger.querySelector('.ph').classList.replace('ph-list', 'ph-x');
    document.body.style.overflow = 'hidden';
  }

  function closeDrawer() {
    drawer.classList.remove('is-open');
    overlay.classList.remove('is-visible');
    burger.setAttribute('aria-expanded', 'false');
    burger.setAttribute('aria-label', 'Ouvrir le menu');
    burger.querySelector('.ph').classList.replace('ph-x', 'ph-list');
    document.body.style.overflow = '';
  }

  burger.addEventListener('click', function () {
    drawer.classList.contains('is-open') ? closeDrawer() : openDrawer();
  });

  overlay.addEventListener('click', closeDrawer);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && drawer.classList.contains('is-open')) {
      closeDrawer();
      burger.focus();
    }
  });

})();

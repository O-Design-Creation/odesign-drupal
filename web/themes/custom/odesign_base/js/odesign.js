/**
 * @file
 * Atelier O Design — JavaScript thème principal.
 * Accessible, progressive enhancement, no external deps.
 */

(function (Drupal, once) {
  'use strict';

  // -------------------------------------------------------------------------
  // Navigation mobile (hamburger)
  // -------------------------------------------------------------------------
  Drupal.behaviors.odesignNavMobile = {
    attach: function (context) {
      once('odesign-nav-mobile', '#nav-toggle', context).forEach(function (toggle) {
        var nav = document.getElementById('main-navigation');
        if (!nav) return;

        function openMenu() {
          toggle.setAttribute('aria-expanded', 'true');
          toggle.setAttribute('aria-label', Drupal.t('Fermer le menu de navigation'));
          nav.classList.add('is-open');
          document.body.style.overflow = 'hidden';
          // Focus premier lien du menu
          var firstLink = nav.querySelector('a');
          if (firstLink) firstLink.focus();
        }

        function closeMenu() {
          toggle.setAttribute('aria-expanded', 'false');
          toggle.setAttribute('aria-label', Drupal.t('Ouvrir le menu de navigation'));
          nav.classList.remove('is-open');
          document.body.style.overflow = '';
          toggle.focus();
        }

        toggle.addEventListener('click', function () {
          var isOpen = toggle.getAttribute('aria-expanded') === 'true';
          isOpen ? closeMenu() : openMenu();
        });

        // Fermer sur Escape
        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape' && nav.classList.contains('is-open')) {
            closeMenu();
          }
        });

        // Fermer sur clic hors menu (overlay)
        document.addEventListener('click', function (e) {
          if (
            nav.classList.contains('is-open') &&
            !nav.contains(e.target) &&
            !toggle.contains(e.target)
          ) {
            closeMenu();
          }
        });
      });
    }
  };

  // -------------------------------------------------------------------------
  // Header sticky : ombre renforcée au scroll
  // -------------------------------------------------------------------------
  Drupal.behaviors.odesignHeaderScroll = {
    attach: function (context) {
      once('odesign-header-scroll', 'body', context).forEach(function () {
        var header = document.querySelector('.site-header');
        if (!header) return;

        var lastY = 0;

        window.addEventListener('scroll', function () {
          var y = window.scrollY;
          if (y > 10) {
            header.classList.add('is-scrolled');
          } else {
            header.classList.remove('is-scrolled');
          }
          lastY = y;
        }, { passive: true });
      });
    }
  };

  // -------------------------------------------------------------------------
  // Lazy loading images (fallback si IntersectionObserver absent)
  // -------------------------------------------------------------------------
  Drupal.behaviors.odesignLazyImages = {
    attach: function (context) {
      if ('loading' in HTMLImageElement.prototype) return;
      once('lazy-img', 'img[loading="lazy"]', context).forEach(function (img) {
        if (img.dataset.src) {
          img.src = img.dataset.src;
        }
      });
    }
  };

  // -------------------------------------------------------------------------
  // Annonce live region pour add-to-cart (accessibilité)
  // -------------------------------------------------------------------------
  Drupal.behaviors.odesignCartAnnounce = {
    attach: function (context) {
      once('odesign-cart-announce', 'form.commerce-order-item-add-to-cart-form', context).forEach(function (form) {
        // Créer une live region si absente
        if (!document.getElementById('cart-live-region')) {
          var region = document.createElement('div');
          region.id = 'cart-live-region';
          region.setAttribute('role', 'status');
          region.setAttribute('aria-live', 'polite');
          region.setAttribute('aria-atomic', 'true');
          region.classList.add('visually-hidden');
          document.body.appendChild(region);
        }

        form.addEventListener('submit', function () {
          setTimeout(function () {
            var region = document.getElementById('cart-live-region');
            if (region) {
              region.textContent = Drupal.t('Produit ajouté au panier.');
              setTimeout(function () { region.textContent = ''; }, 3000);
            }
          }, 800);
        });
      });
    }
  };

  // -------------------------------------------------------------------------
  // Couleur de la barre d'état du navigateur mobile (theme-color)
  // -------------------------------------------------------------------------
  Drupal.behaviors.odesignThemeColor = {
    attach: function (context) {
      once('odesign-theme-color', 'head', context).forEach(function () {
        if (!document.querySelector('meta[name="theme-color"]')) {
          var meta = document.createElement('meta');
          meta.name = 'theme-color';
          meta.content = '#7a3a44';
          document.head.appendChild(meta);
        }
      });
    }
  };

})(Drupal, once);

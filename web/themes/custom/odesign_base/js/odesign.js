/**
 * @file
 * O'Design Création — JavaScript thème principal.
 * Accessible, progressive enhancement, no external deps.
 */

(function (Drupal, once, drupalSettings) {
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
          document.body.classList.add('nav-is-open');
          document.body.style.overflow = 'hidden';
          // Focus premier lien du menu
          var firstLink = nav.querySelector('a');
          if (firstLink) firstLink.focus();
        }

        function closeMenu() {
          toggle.setAttribute('aria-expanded', 'false');
          toggle.setAttribute('aria-label', Drupal.t('Ouvrir le menu de navigation'));
          nav.classList.remove('is-open');
          document.body.classList.remove('nav-is-open');
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

        nav.querySelectorAll('a').forEach(function (link) {
          link.addEventListener('click', function () {
            if (nav.classList.contains('is-open')) {
              closeMenu();
            }
          });
        });

        window.addEventListener('resize', function () {
          if (window.innerWidth > 900 && nav.classList.contains('is-open')) {
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
  // Galerie produit : thumbnails accessibles
  // -------------------------------------------------------------------------
  Drupal.behaviors.odesignProductGallery = {
    attach: function (context) {
      once('odesign-product-gallery', '.product-gallery__frame', context).forEach(function (gallery) {
        var triggers = gallery.querySelectorAll('[data-gallery-trigger]');
        var slides = gallery.querySelectorAll('[data-gallery-slide]');

        if (!triggers.length || !slides.length) {
          return;
        }

        function activateSlide(targetId, focusTrigger) {
          slides.forEach(function (slide) {
            var isActive = slide.id === targetId;
            slide.hidden = !isActive;
            slide.classList.toggle('is-active', isActive);
          });

          triggers.forEach(function (trigger) {
            var isActive = trigger.getAttribute('data-gallery-target') === targetId;
            trigger.classList.toggle('is-active', isActive);
            trigger.setAttribute('aria-selected', isActive ? 'true' : 'false');
            trigger.setAttribute('tabindex', isActive ? '0' : '-1');

            if (isActive && focusTrigger) {
              trigger.focus();
            }
          });
        }

        triggers.forEach(function (trigger, index) {
          trigger.setAttribute('tabindex', index === 0 ? '0' : '-1');

          trigger.addEventListener('click', function () {
            activateSlide(trigger.getAttribute('data-gallery-target'), false);
          });

          trigger.addEventListener('keydown', function (event) {
            var nextIndex = null;

            if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
              nextIndex = (index + 1) % triggers.length;
            }

            if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
              nextIndex = (index - 1 + triggers.length) % triggers.length;
            }

            if (event.key === 'Home') {
              nextIndex = 0;
            }

            if (event.key === 'End') {
              nextIndex = triggers.length - 1;
            }

            if (nextIndex !== null) {
              event.preventDefault();
              activateSlide(triggers[nextIndex].getAttribute('data-gallery-target'), true);
            }
          });
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

  // -------------------------------------------------------------------------
  // Masquer le bandeau cookies sur les pages d'administration.
  // -------------------------------------------------------------------------
  Drupal.behaviors.odesignHideCookieBannerAdmin = {
    attach: function (context) {
      once('odesign-hide-cookie-admin', 'body', context).forEach(function () {
        if (!drupalSettings.path || !drupalSettings.path.currentPathIsAdmin) {
          return;
        }

        function hideCookieUi() {
          ['#sliding-popup', '.eu-cookie-withdraw-wrapper', '.eu-cookie-withdraw-tab'].forEach(function (selector) {
            document.querySelectorAll(selector).forEach(function (element) {
              element.style.display = 'none';
            });
          });
        }

        hideCookieUi();
        window.setTimeout(hideCookieUi, 250);
        window.setTimeout(hideCookieUi, 1000);
        window.setTimeout(hideCookieUi, 2000);

        var attempts = 0;
        var interval = window.setInterval(function () {
          hideCookieUi();
          attempts += 1;
          if (attempts >= 8) {
            window.clearInterval(interval);
          }
        }, 500);
      });
    }
  };

  // Compense la hauteur du bandeau cookie sur mobile pour éviter qu'il
  // chevauche le contenu bas de page (F-COOKIE-MOBILE).
  Drupal.behaviors.odesignCookiePadding = {
    attach: function (context) {
      once('odesign-cookie-padding', 'body', context).forEach(function (body) {
        var popup = document.getElementById('sliding-popup');
        if (!popup) {
          return;
        }

        function applyPadding() {
          var rect = popup.getBoundingClientRect();
          var visible = rect.height > 0 && getComputedStyle(popup).display !== 'none';
          body.style.paddingBottom = visible ? rect.height + 'px' : '';
        }

        applyPadding();

        var observer = new ResizeObserver(applyPadding);
        observer.observe(popup);

        var mutationObserver = new MutationObserver(applyPadding);
        mutationObserver.observe(popup, { attributes: true, attributeFilter: ['style', 'class'] });
      });
    }
  };

  // Décode le lien email de contact depuis base64 pour éviter l'exposition
  // en clair dans le DOM (F-CONTACT-EMAIL-VISIBLE).
  Drupal.behaviors.odesignContactMailto = {
    attach: function (context) {
      once('odesign-contact-mailto', '.js-contact-mailto', context).forEach(function (el) {
        try {
          var email = atob(el.dataset.b64 || '');
          if (email) {
            el.href = 'mailto:' + email;
          }
        } catch (e) {}
      });
    }
  };

  // Injecte aria-label sur le champ de recherche Chosen (WCAG label critique).
  // Chosen remplace les <select> par un input sans label natif.
  Drupal.behaviors.odesignChosenA11y = {
    attach: function (context) {
      once('odesign-chosen-a11y', '.chosen-search-input', context).forEach(function (input) {
        if (!input.getAttribute('aria-label')) {
          input.setAttribute('aria-label', Drupal.t('Rechercher dans la liste'));
        }
      });
    }
  };

  // Dropdown menu compte client — toggle aria-expanded + fermeture clavier/clic externe.
  Drupal.behaviors.odesignAccountMenu = {
    attach: function (context) {
      once('odesign-account-menu', '[data-account-menu]', context).forEach(function (wrapper) {
        var toggle   = wrapper.querySelector('[aria-controls="account-menu"]');
        var dropdown = wrapper.querySelector('#account-menu');
        if (!toggle || !dropdown) return;

        function openMenu() {
          toggle.setAttribute('aria-expanded', 'true');
          dropdown.removeAttribute('hidden');
          var firstItem = dropdown.querySelector('[role="menuitem"]');
          if (firstItem) firstItem.focus();
        }

        function closeMenu() {
          toggle.setAttribute('aria-expanded', 'false');
          dropdown.setAttribute('hidden', '');
        }

        toggle.addEventListener('click', function () {
          var isOpen = toggle.getAttribute('aria-expanded') === 'true';
          isOpen ? closeMenu() : openMenu();
        });

        // Fermeture Escape
        wrapper.addEventListener('keydown', function (e) {
          if (e.key === 'Escape') { closeMenu(); toggle.focus(); }
        });

        // Fermeture clic hors menu
        document.addEventListener('click', function (e) {
          if (!wrapper.contains(e.target)) closeMenu();
        });

        // Navigation clavier dans le menu (Arrow up/down)
        dropdown.addEventListener('keydown', function (e) {
          var items = Array.from(dropdown.querySelectorAll('[role="menuitem"]'));
          var idx   = items.indexOf(document.activeElement);
          if (e.key === 'ArrowDown') { e.preventDefault(); items[(idx + 1) % items.length].focus(); }
          if (e.key === 'ArrowUp')   { e.preventDefault(); items[(idx - 1 + items.length) % items.length].focus(); }
        });
      });
    }
  };

  /**
   * Behavior : toggle panneau de recherche header (TCK-018)
   */
  Drupal.behaviors.odesignHeaderSearch = {
    attach: function (context, settings) {
      once('header-search', '.header-search__toggle', context).forEach(function (toggle) {
        var panel = document.getElementById('header-search-panel');
        if (!panel) { return; }
        var input = panel.querySelector('.header-search-panel__input');
        var closeBtn = panel.querySelector('[data-search-close]');

        function openSearch() {
          panel.removeAttribute('hidden');
          toggle.setAttribute('aria-expanded', 'true');
          if (input) { input.focus(); }
        }

        function closeSearch() {
          panel.setAttribute('hidden', '');
          toggle.setAttribute('aria-expanded', 'false');
          toggle.focus();
        }

        toggle.addEventListener('click', function () {
          var isOpen = toggle.getAttribute('aria-expanded') === 'true';
          isOpen ? closeSearch() : openSearch();
        });

        if (closeBtn) {
          closeBtn.addEventListener('click', closeSearch);
        }

        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape' && !panel.hasAttribute('hidden')) {
            closeSearch();
          }
        });

        document.addEventListener('click', function (e) {
          if (!panel.hasAttribute('hidden') &&
              !panel.contains(e.target) &&
              !toggle.contains(e.target)) {
            closeSearch();
          }
        });
      });
    }
  };

})(Drupal, once, drupalSettings);

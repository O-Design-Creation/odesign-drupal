/**
 * @file
 * UX améliorée — page documentation gestionnaire.
 *
 * Fonctionnalités :
 *   - TOC interne généré depuis les H2 (scroll-spy)
 *   - Nav mobile collapsible
 *   - Callouts blockquote différenciés (📍 💡 ⚠️)
 *   - Lightbox images
 */
(function (Drupal, once) {
  'use strict';

  // ─────────────────────────────────────────────
  // 1. Nav mobile collapsible
  // ─────────────────────────────────────────────
  Drupal.behaviors.odesignDocNav = {
    attach: function (context) {
      once('doc-nav', '.odesign-documentation__nav-toggle', context).forEach(function (toggle) {
        var nav = toggle.closest('.odesign-documentation__nav');
        if (!nav) return;

        // État initial : fermé sur mobile, ouvert sur desktop
        var isMobile = window.innerWidth < 900;
        nav.classList.toggle('is-collapsed', isMobile);
        toggle.setAttribute('aria-expanded', isMobile ? 'false' : 'true');

        toggle.addEventListener('click', function () {
          var collapsed = nav.classList.toggle('is-collapsed');
          toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        });
      });
    }
  };

  // ─────────────────────────────────────────────
  // 2. TOC interne — généré depuis les H2
  // ─────────────────────────────────────────────
  Drupal.behaviors.odesignDocToc = {
    attach: function (context) {
      once('doc-toc', '.odesign-documentation__toc', context).forEach(function (tocWrapper) {
        var markdown = document.querySelector('.odesign-documentation__markdown');
        var tocList  = tocWrapper.querySelector('.odesign-documentation__toc-list');
        if (!markdown || !tocList) return;

        var headings = Array.from(markdown.querySelectorAll('h2'));
        if (headings.length < 2) {
          tocWrapper.hidden = true;
          return;
        }

        // Ajouter des ancres et peupler le TOC
        headings.forEach(function (h, i) {
          var slug = 'section-' + i + '-' + h.textContent
            .toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '')
            .slice(0, 40);
          h.id = slug;

          var li = document.createElement('li');
          var a  = document.createElement('a');
          a.href = '#' + slug;
          a.textContent = h.textContent;
          a.className = 'odesign-documentation__toc-link';
          a.addEventListener('click', function (e) {
            e.preventDefault();
            h.scrollIntoView({ behavior: 'smooth', block: 'start' });
            // Mise à jour URL sans rechargement
            history.replaceState(null, '', '#' + slug);
          });
          li.appendChild(a);
          tocList.appendChild(li);
        });

        tocWrapper.hidden = false;

        // Toggle du TOC sur mobile
        var tocToggle = tocWrapper.querySelector('.odesign-documentation__toc-toggle');
        var isTocOpen = window.innerWidth >= 900;
        tocWrapper.classList.toggle('is-open', isTocOpen);
        if (tocToggle) {
          tocToggle.setAttribute('aria-expanded', isTocOpen ? 'true' : 'false');
          tocToggle.addEventListener('click', function () {
            var open = tocWrapper.classList.toggle('is-open');
            tocToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
          });
        }

        // Scroll-spy : activer le lien TOC correspondant à la section visible
        var links = tocList.querySelectorAll('.odesign-documentation__toc-link');
        var observer = new IntersectionObserver(function (entries) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) {
              links.forEach(function (a) {
                a.classList.toggle('is-active', a.getAttribute('href') === '#' + entry.target.id);
              });
            }
          });
        }, { rootMargin: '-5% 0px -75% 0px' });

        headings.forEach(function (h) { observer.observe(h); });
      });
    }
  };

  // ─────────────────────────────────────────────
  // 3. Callouts blockquote différenciés
  // ─────────────────────────────────────────────
  Drupal.behaviors.odesignDocCallouts = {
    attach: function (context) {
      once('doc-callouts', '.odesign-documentation__markdown', context).forEach(function (markdown) {
        markdown.querySelectorAll('blockquote').forEach(function (bq) {
          var text = bq.textContent || '';
          if (text.includes('📍')) {
            bq.classList.add('callout--access');
          } else if (text.includes('💡') || text.includes('ℹ️') || text.includes('🔒')) {
            bq.classList.add('callout--tip');
          } else if (text.includes('⚠️') || text.includes('⚠')) {
            bq.classList.add('callout--warning');
          } else if (text.includes('✅')) {
            bq.classList.add('callout--success');
          }
        });
      });
    }
  };

  // ─────────────────────────────────────────────
  // 4. Lightbox images
  // ─────────────────────────────────────────────
  Drupal.behaviors.odesignDocLightbox = {
    attach: function (context) {
      once('doc-lightbox', '.odesign-documentation__markdown', context).forEach(function (markdown) {
        // Créer l'overlay une seule fois
        var overlay = document.createElement('div');
        overlay.className = 'odesign-doc-lightbox';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Aperçu de l\'image');
        // Pas d'attribut hidden : display:none géré par CSS (.is-visible)

        var img = document.createElement('img');
        img.className = 'odesign-doc-lightbox__img';
        var closeBtn = document.createElement('button');
        closeBtn.className = 'odesign-doc-lightbox__close';
        closeBtn.setAttribute('aria-label', 'Fermer');
        closeBtn.innerHTML = '<i class="ph ph-x"></i>';

        overlay.appendChild(closeBtn);
        overlay.appendChild(img);
        document.body.appendChild(overlay);

        function close() {
          overlay.classList.remove('is-visible');
          document.body.classList.remove('lightbox-open');
        }

        closeBtn.addEventListener('click', close);
        overlay.addEventListener('click', function (e) {
          if (e.target === overlay) close();
        });
        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape' && overlay.classList.contains('is-visible')) close();
        });

        // Rendre chaque image cliquable
        markdown.querySelectorAll('img').forEach(function (image) {
          image.classList.add('is-zoomable');
          image.setAttribute('title', 'Cliquer pour agrandir');
          image.addEventListener('click', function () {
            img.src = image.src;
            img.alt = image.alt;
            overlay.classList.add('is-visible');
            document.body.classList.add('lightbox-open');
            closeBtn.focus();
          });
        });
      });
    }
  };

}(Drupal, once));

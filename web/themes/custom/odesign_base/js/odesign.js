/**
 * @file
 * Atelier O Design — JavaScript thème principal.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Navigation mobile.
   */
  Drupal.behaviors.odesignNavMobile = {
    attach: function (context) {
      once('odesign-nav-mobile', '.site-nav', context).forEach(function (nav) {
        var toggle = document.querySelector('.site-nav-toggle');
        if (!toggle) return;
        toggle.addEventListener('click', function () {
          var expanded = toggle.getAttribute('aria-expanded') === 'true';
          toggle.setAttribute('aria-expanded', !expanded);
          nav.classList.toggle('is-open', !expanded);
        });
      });
    }
  };

  /**
   * Lazy loading images (fallback si natif non supporté).
   */
  Drupal.behaviors.odesignLazyImages = {
    attach: function (context) {
      if ('loading' in HTMLImageElement.prototype) return;
      once('lazy-img', 'img[loading="lazy"]', context).forEach(function (img) {
        img.src = img.dataset.src || img.src;
      });
    }
  };

})(Drupal, once);

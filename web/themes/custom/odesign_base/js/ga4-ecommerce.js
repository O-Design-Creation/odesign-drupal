/**
 * @file
 * GA4 e-commerce events pour Drupal Commerce — O'Design Création.
 *
 * Deux niveaux de gating RGPD :
 *  1. Serveur (hook_page_attachments_alter) : supprime drupalSettings.gtag si
 *     cookie-agreed ≠ 2 lors du rendu de la page.
 *  2. Client (ce bloc) : supprime drupalSettings.gtag avant que google_tag/gtag.js
 *     ne soit évalué (weight: -20), en lisant le cookie eu_cookie_compliance.
 *     Couvre les cas où la page est servie depuis le cache interne Drupal.
 *
 * Événements implémentés :
 *  - view_item      : fiche produit
 *  - add_to_cart    : formulaire ajout panier
 *  - purchase       : page /merci (données injectées via drupalSettings.ga4Order)
 */

// --- Gating RGPD client-side (doit tourner avant google_tag/gtag.js) --------
(function () {
  'use strict';
  function _readCookie(name) {
    var match = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
    return match ? match.pop() : null;
  }
  if (_readCookie('cookie-agreed') !== '2') {
    if (window.drupalSettings) {
      delete window.drupalSettings.gtag;
      delete window.drupalSettings.gtm;
    }
  }
})();

(function (Drupal, drupalSettings, once) {
  'use strict';

  /**
   * view_item — déclenché sur chaque fiche produit portant [data-ga4-product].
   *
   * Le thème Drupal doit injecter l'attribut data-ga4-product sur l'élément
   * racine du produit (ex: <article data-ga4-product='{"id":...}'>) via
   * hook_preprocess_commerce_product().
   */
  Drupal.behaviors.ga4ViewItem = {
    attach(context) {
      once('ga4-view-item', '[data-ga4-product]', context).forEach(function (el) {
        if (typeof gtag === 'undefined') { return; }
        try {
          var data = JSON.parse(el.dataset.ga4Product);
          gtag('event', 'view_item', {
            currency: 'EUR',
            value: parseFloat(data.price) || 0,
            items: [{
              item_id:   String(data.id   || ''),
              item_name: String(data.name || ''),
              price:     parseFloat(data.price) || 0,
              quantity:  1
            }]
          });
        }
        catch (e) { /* JSON malformé : on ignore silencieusement */ }
      });
    }
  };

  /**
   * add_to_cart — déclenché au submit du formulaire panier Commerce.
   *
   * Le formulaire porte la classe .product-add-to-cart-form et un champ
   * caché [data-price] injecté via hook_form_alter dans odesign_admin.module.
   */
  Drupal.behaviors.ga4AddToCart = {
    attach(context) {
      once('ga4-add-to-cart', '.product-add-to-cart-form', context).forEach(function (form) {
        form.addEventListener('submit', function () {
          if (typeof gtag === 'undefined') { return; }
          var titleEl   = document.querySelector('h1');
          var priceEl   = form.querySelector('[data-price]');
          var itemName  = titleEl  ? titleEl.textContent.trim() : '';
          var itemPrice = priceEl  ? parseFloat(priceEl.dataset.price) : 0;
          gtag('event', 'add_to_cart', {
            currency: 'EUR',
            value:    itemPrice,
            items: [{
              item_name: itemName,
              price:     itemPrice,
              quantity:  1
            }]
          });
        });
      });
    }
  };

  /**
   * purchase — déclenché une seule fois sur la page /merci après checkout.
   *
   * Les données de commande sont injectées par odesign_admin_page_attachments_alter()
   * via drupalSettings.ga4Order : { id, total, items[] }.
   */
  Drupal.behaviors.ga4Purchase = {
    attach(context) {
      if (!window.location.pathname.startsWith('/merci')) { return; }
      if (typeof gtag === 'undefined') { return; }
      once('ga4-purchase', 'body', context).forEach(function () {
        var order = (drupalSettings && drupalSettings.ga4Order) ? drupalSettings.ga4Order : null;
        if (!order) { return; }
        gtag('event', 'purchase', {
          currency:       'EUR',
          transaction_id: String(order.id),
          value:          parseFloat(order.total) || 0,
          items:          order.items || []
        });
      });
    }
  };

})(Drupal, drupalSettings, once);

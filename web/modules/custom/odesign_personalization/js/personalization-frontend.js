/**
 * ODesign Personalization — frontend JS.
 *
 * - Met à jour le résumé de prix en temps réel quand les champs sont remplis.
 * - Gère les compteurs de caractères (maxlength).
 */
(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.odesignPersonalization = {
    attach: function (context, settings) {
      const config = settings.odesign_personalization || {};
      if (!config.hasPriceModifiers) return;

      once('perso-live-price', '.personalization-fields', context).forEach(function (container) {
        const priceSummary = container.querySelector('.perso-price-summary');
        if (!priceSummary) return;

        function updatePrice() {
          let modifier = 0;
          const hints = [];

          container.querySelectorAll('[data-perso-field]').forEach(function (el) {
            const modType   = el.dataset.priceModType   || 'none';
            const modAmount = parseFloat(el.dataset.priceModAmount || '0');
            if (modType === 'none' || modAmount === 0) return;

            let val = el.value ? el.value.trim() : '';
            if (!val || val === '_none') return;

            if (modType === 'fixed') {
              modifier += modAmount;
              hints.push(el.closest('.form-item')?.querySelector('label')?.textContent?.trim() || '');
            } else if (modType === 'per_unit') {
              const units = Math.max(1, val.split(/[,\n]+/).filter(s => s.trim()).length);
              modifier += modAmount * units;
              hints.push(el.closest('.form-item')?.querySelector('label')?.textContent?.trim() || '');
            }
          });

          if (modifier > 0) {
            const total = (config.basePrice || 0) + modifier;
            priceSummary.textContent = Drupal.t(
              'Prix avec personnalisation : @price €',
              { '@price': total.toFixed(2).replace('.', ',') }
            );
            priceSummary.classList.add('has-modifier');
          } else {
            priceSummary.classList.remove('has-modifier');
          }
        }

        container.querySelectorAll('input, textarea, select').forEach(function (el) {
          el.addEventListener('input', updatePrice);
          el.addEventListener('change', updatePrice);
        });
      });

      // Compteurs de caractères pour les champs avec maxlength
      once('perso-char-count', '.personalization-fields input[maxlength], .personalization-fields textarea[maxlength]', context)
        .forEach(function (el) {
          const max = parseInt(el.getAttribute('maxlength'), 10);
          if (!max) return;
          const counter = document.createElement('span');
          counter.className = 'perso-char-counter';
          counter.style.cssText = 'font-size:0.78rem;color:#8b6151;float:right;';
          el.insertAdjacentElement('afterend', counter);
          function update() {
            const remaining = max - el.value.length;
            counter.textContent = remaining + ' / ' + max;
            counter.style.color = remaining < 10 ? '#c0392b' : '#8b6151';
          }
          el.addEventListener('input', update);
          update();
        });
    }
  };

})(jQuery, Drupal, drupalSettings);

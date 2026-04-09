/**
 * @file
 * Bouton IA pour la rédaction de newsletters O'Design Création.
 *
 * Injecte un bouton "✨ Générer avec l'IA" dans les formulaires newsletter
 * simplenews. Au clic, appelle l'endpoint AJAX /admin/boutique/ai/generate
 * et injecte le texte généré dans les champs titre et corps.
 */
(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.odesignAiGenerator = {
    attach(context) {
      once('odesign-ai-generator', '.odesign-ai-btn', context).forEach(function (btn) {
        btn.addEventListener('click', async function (e) {
          e.preventDefault();

          const titleFieldId = btn.dataset.targetTitle;
          const bodyFieldId  = btn.dataset.targetBody;
          const titleField   = document.getElementById(titleFieldId);
          const bodyField    = document.getElementById(bodyFieldId);
          const status       = btn.parentElement.querySelector('.odesign-ai-status');
          const title        = titleField ? titleField.value.trim() : '';

          if (!title) {
            if (status) { status.textContent = 'Saisissez d\'abord un titre pour la newsletter.'; }
            return;
          }

          btn.disabled = true;
          if (status) { status.textContent = '⏳ Génération en cours…'; }

          // Récupérer le jeton CSRF Drupal.
          let csrfToken = '';
          try {
            const tokenRes = await fetch('/session/token');
            csrfToken = await tokenRes.text();
          }
          catch (err) {
            // Continuer sans jeton — la permission protège l'accès.
          }

          try {
            const res = await fetch('/admin/boutique/ai/generate', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
              },
              body: JSON.stringify({
                context_type: btn.dataset.context || 'newsletter',
                title: title,
              }),
            });

            const data = await res.json();

            if (data.success && data.text) {
              if (btn.dataset.context === 'newsletter') {
                // Extraire l'objet email et le corps.
                const lines    = data.text.split('\n');
                const objetLine = lines.find(function (l) { return l.startsWith('OBJET:'); });

                if (objetLine && titleField) {
                  titleField.value = objetLine.replace(/^OBJET:\s*/i, '').trim();
                  // Déclencher les événements natifs pour que Drupal détecte la modification.
                  titleField.dispatchEvent(new Event('input', { bubbles: true }));
                  titleField.dispatchEvent(new Event('change', { bubbles: true }));
                }

                const corps = lines
                  .filter(function (l) { return !l.startsWith('OBJET:'); })
                  .join('\n')
                  .trim();

                if (bodyField && corps) {
                  bodyField.value = corps;
                  bodyField.dispatchEvent(new Event('input', { bubbles: true }));
                  bodyField.dispatchEvent(new Event('change', { bubbles: true }));
                }
              }
              else {
                // Pour les autres contextes, injecter dans le champ corps.
                if (bodyField) {
                  bodyField.value = data.text;
                  bodyField.dispatchEvent(new Event('input', { bubbles: true }));
                  bodyField.dispatchEvent(new Event('change', { bubbles: true }));
                }
              }

              if (status) {
                status.textContent = '✅ Contenu généré — relisez et adaptez avant envoi.';
                status.style.color = '#2e7d32';
              }
            }
            else {
              if (status) {
                status.textContent = '❌ ' + (data.error || 'Erreur inconnue.');
                status.style.color = '#c62828';
              }
            }
          }
          catch (err) {
            if (status) {
              status.textContent = '❌ Erreur de connexion à l\'assistant IA.';
              status.style.color = '#c62828';
            }
          }

          btn.disabled = false;
        });
      });
    },
  };

})(jQuery, Drupal, once);

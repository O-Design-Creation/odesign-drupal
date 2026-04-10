/**
 * ODesign Personalization - frontend JS.
 *
 * - Live price update for modifier-enabled fields.
 * - Character counters for maxlength fields.
 * - Arbre de vie builder: dynamic, symmetric heart slots with slot->name mapping.
 */
(function ($, Drupal, drupalSettings) {
  'use strict';

  function normalizeNamesFromInputs(inputs) {
    const names = [];
    inputs.forEach((input) => {
      const raw = (input.value || '').trim();
      if (!raw) {
        return;
      }
      raw
        .split(/[,\n\r]+/)
        .map((part) => part.trim())
        .filter(Boolean)
        .forEach((part) => names.push(part));
    });
    return names.slice(0, 25);
  }

  function getSymmetricSlots(count) {
    const leftAnchors = [
      { x: 32, y: 24 },
      { x: 22, y: 36 },
      { x: 38, y: 38 },
      { x: 18, y: 50 },
      { x: 34, y: 52 },
      { x: 16, y: 64 },
      { x: 31, y: 66 },
      { x: 21, y: 78 },
      { x: 36, y: 80 },
      { x: 26, y: 90 },
      { x: 33, y: 92 },
      { x: 29, y: 96 }
    ];
    const centerAnchor = { x: 50, y: 16 };
    const max = Math.max(0, Math.min(25, Number(count) || 0));
    const slots = [];
    let cursor = 1;

    if (max % 2 === 1) {
      slots.push({
        id: `S${String(cursor).padStart(2, '0')}`,
        x: centerAnchor.x,
        y: centerAnchor.y
      });
      cursor += 1;
    }

    const pairCount = Math.floor(max / 2);
    for (let i = 0; i < pairCount; i += 1) {
      const anchor = leftAnchors[i] || leftAnchors[leftAnchors.length - 1];
      slots.push({
        id: `S${String(cursor).padStart(2, '0')}`,
        x: anchor.x,
        y: anchor.y
      });
      cursor += 1;
      slots.push({
        id: `S${String(cursor).padStart(2, '0')}`,
        x: 100 - anchor.x,
        y: anchor.y
      });
      cursor += 1;
    }

    return slots.slice(0, max);
  }

  function getHeartSizeClass(total) {
    if (total <= 2) return 'size-xl';
    if (total <= 4) return 'size-lg';
    if (total <= 8) return 'size-md';
    if (total <= 12) return 'size-sm';
    if (total <= 18) return 'size-xs';
    return 'size-xxs';
  }

  function getNameFontSizePx(name, total) {
    const length = (name || '').trim().length;
    let base = 14;
    let min = 8;

    if (total <= 2) {
      base = 17;
      min = 10;
    } else if (total <= 4) {
      base = 16;
      min = 10;
    } else if (total <= 8) {
      base = 15;
      min = 9;
    } else if (total <= 12) {
      base = 14;
      min = 8;
    } else if (total <= 18) {
      base = 13;
      min = 8;
    } else {
      base = 12;
      min = 7;
    }

    const penalty = Math.max(0, length - 5) * 0.6;
    const computed = Math.max(min, base - penalty);
    return `${computed.toFixed(1)}px`;
  }

  Drupal.behaviors.odesignPersonalization = {
    attach: function (context, settings) {
      const config = settings.odesign_personalization || {};

      once('perso-price', '.personalization-fields', context).forEach((container) => {
        const priceSummary = container.querySelector('.perso-price-summary');
        if (!priceSummary || !config.hasPriceModifiers) {
          return;
        }

        function updatePrice() {
          let modifier = 0;
          container.querySelectorAll('[data-perso-field]').forEach((el) => {
            const modType = el.dataset.priceModType || 'none';
            const modAmount = parseFloat(el.dataset.priceModAmount || '0');
            if (modType === 'none' || modAmount === 0) {
              return;
            }

            const value = (el.value || '').trim();
            if (!value || value === '_none') {
              return;
            }

            if (modType === 'fixed') {
              modifier += modAmount;
              return;
            }
            if (modType === 'per_unit') {
              const units = Math.max(1, value.split(/[,\n]+/).filter((part) => part.trim()).length);
              modifier += modAmount * units;
            }
          });

          if (modifier > 0) {
            const total = (config.basePrice || 0) + modifier;
            priceSummary.classList.add('has-modifier');
            priceSummary.textContent = Drupal.t('Prix avec personnalisation : @price €', {
              '@price': total.toFixed(2).replace('.', ',')
            });
          } else {
            priceSummary.classList.remove('has-modifier');
            priceSummary.textContent = '';
          }
        }

        container.querySelectorAll('input, textarea, select').forEach((el) => {
          el.addEventListener('input', updatePrice);
          el.addEventListener('change', updatePrice);
        });
        updatePrice();
      });

      once('perso-char-count', '.personalization-fields input[maxlength], .personalization-fields textarea[maxlength]', context)
        .forEach((el) => {
          const max = parseInt(el.getAttribute('maxlength'), 10);
          if (!max) {
            return;
          }
          const counter = document.createElement('span');
          counter.className = 'perso-char-counter';
          counter.style.cssText = 'font-size:0.78rem;color:#8b6151;float:right;';
          el.insertAdjacentElement('afterend', counter);
          const update = () => {
            const remaining = max - el.value.length;
            counter.textContent = `${remaining} / ${max}`;
            counter.style.color = remaining < 10 ? '#c0392b' : '#8b6151';
          };
          el.addEventListener('input', update);
          update();
        });

      once('tree-builder', '.tree-personalization-group', context).forEach((group) => {
        const form = group.closest('form');
        if (!form) {
          return;
        }

        const nameInputs = Array.from(group.querySelectorAll('[data-tree-name-source="1"]'));
        const chips = group.querySelector('.js-tree-chips');
        const status = group.querySelector('.js-tree-status');
        const stage = group.querySelector('.js-tree-stage');
        const hiddenMap = form.querySelector('.js-tree-slot-map');
        const hiddenPayload = form.querySelector('.js-tree-data-payload');

        if (!nameInputs.length || !chips || !status || !stage || !hiddenMap) {
          return;
        }

        let selectedName = null;
        let slots = [];
        let assignments = new Map();

        function serializeMap() {
          const lines = [];
          slots.forEach((slot) => {
            const name = assignments.get(slot.id);
            if (name) {
              lines.push(`${slot.id} (${slot.x}%, ${slot.y}%) -> ${name}`);
            }
          });
          hiddenMap.value = lines.join('\n');
        }

        function serializePayload(names) {
          if (!hiddenPayload) {
            return;
          }
          const payload = {};
          if (names.length) {
            payload.Prenoms = names.join(', ');
          }
          if (hiddenMap.value.trim() !== '') {
            payload['Plan de pose prénoms'] = hiddenMap.value;
          }
          hiddenPayload.value = Object.keys(payload).length ? JSON.stringify(payload) : '';
        }

        function renderStatus() {
          const placed = Array.from(assignments.values()).filter(Boolean).length;
          status.textContent = Drupal.t('@placed/@total prénoms placés', {
            '@placed': String(placed),
            '@total': String(slots.length)
          });
        }

        function renderChips(names) {
          chips.innerHTML = '';
          const assignedNames = new Set(Array.from(assignments.values()));

          names.forEach((name) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'tree-personalization-group__chip';
            button.textContent = name;
            if (assignedNames.has(name)) {
              button.classList.add('is-placed');
            }
            if (selectedName === name) {
              button.classList.add('is-selected');
            }
            button.addEventListener('click', () => {
              selectedName = selectedName === name ? null : name;
              renderChips(names);
              renderSlots(names);
            });
            chips.appendChild(button);
          });
        }

        function renderSlots(names) {
          stage.innerHTML = '';
          stage.classList.remove('size-xl', 'size-lg', 'size-md', 'size-sm', 'size-xs', 'size-xxs');
          stage.classList.add(getHeartSizeClass(slots.length));

          slots.forEach((slot) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'tree-personalization-group__slot';
            button.style.left = `${slot.x}%`;
            button.style.top = `${slot.y}%`;
            button.dataset.slotId = slot.id;
            button.title = slot.id;

            const name = assignments.get(slot.id) || '';
            if (name) {
              button.classList.add('is-filled');
            }

            const badge = document.createElement('span');
            badge.className = 'tree-personalization-group__slot-id';
            badge.textContent = slot.id;
            button.appendChild(badge);

            const label = document.createElement('span');
            label.className = 'tree-personalization-group__slot-name';
            label.textContent = name;
            label.style.fontSize = getNameFontSizePx(name, slots.length);
            button.appendChild(label);

            button.addEventListener('click', () => {
              if (!selectedName) {
                if (assignments.get(slot.id)) {
                  assignments.delete(slot.id);
                }
              } else {
                for (const [slotId, value] of assignments.entries()) {
                  if (value === selectedName && slotId !== slot.id) {
                    assignments.delete(slotId);
                  }
                }
                assignments.set(slot.id, selectedName);
              }
              serializeMap();
              renderStatus();
              renderChips(names);
              renderSlots(names);
            });

            stage.appendChild(button);
          });
        }

        function rebuild() {
          const names = normalizeNamesFromInputs(nameInputs);
          slots = getSymmetricSlots(names.length);

          if (!names.length) {
            assignments = new Map();
            selectedName = null;
            chips.innerHTML = '';
            stage.innerHTML = '';
            status.textContent = Drupal.t('Aucun prénom saisi');
            hiddenMap.value = '';
            return;
          }

          const allowedSlots = new Set(slots.map((slot) => slot.id));
          const allowedNames = new Set(names);
          const filtered = new Map();
          assignments.forEach((value, slotId) => {
            if (allowedSlots.has(slotId) && allowedNames.has(value)) {
              filtered.set(slotId, value);
            }
          });
          assignments = filtered;

          names.forEach((name, index) => {
            const alreadyPlaced = Array.from(assignments.values()).includes(name);
            if (!alreadyPlaced && slots[index]) {
              assignments.set(slots[index].id, name);
            }
          });

          serializeMap();
          serializePayload(names);
          renderStatus();
          renderChips(names);
          renderSlots(names);
        }

        nameInputs.forEach((input) => {
          input.addEventListener('input', rebuild);
          input.addEventListener('change', rebuild);
        });
        rebuild();
      });
    }
  };

})(jQuery, Drupal, drupalSettings);

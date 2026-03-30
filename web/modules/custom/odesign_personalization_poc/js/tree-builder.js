(function (Drupal, once) {
  const SLOT_POSITIONS = [
    [50, 11], [62, 14], [72, 19], [80, 27], [86, 37],
    [89, 49], [86, 61], [80, 71], [72, 79], [62, 84],
    [50, 87], [38, 84], [28, 79], [20, 71], [14, 61],
    [11, 49], [14, 37], [20, 27], [28, 19], [38, 14],
    [58, 27], [69, 34], [74, 49], [31, 34], [26, 49],
  ];

  function parseNames(value) {
    return value
      .split(/[\n,]+/)
      .map((item) => item.trim())
      .filter(Boolean)
      .slice(0, SLOT_POSITIONS.length);
  }

  function safeParseLayout(value) {
    if (!value) {
      return {};
    }

    try {
      const parsed = JSON.parse(value);
      return parsed && typeof parsed === 'object' ? parsed : {};
    }
    catch (error) {
      return {};
    }
  }

  function normalizeLayout(layout, names) {
    const allowed = new Set(names);
    const normalized = {};
    Object.keys(layout || {}).forEach((slot) => {
      if (allowed.has(layout[slot])) {
        normalized[slot] = layout[slot];
      }
    });
    return normalized;
  }

  Drupal.behaviors.odesignTreeBuilder = {
    attach(context) {
      once('odesign-tree-builder', '.js-poc-tree-builder', context).forEach((builder) => {
        const form = builder.closest('form');
        if (!form) {
          return;
        }

        const namesInput = form.querySelector('.js-poc-tree-names');
        const layoutInput = form.querySelector('.js-poc-tree-layout');
        const wallMountInput = form.querySelector('.js-poc-tree-wall-mount');
        const chips = builder.querySelector('.js-poc-tree-chips');
        const stage = builder.querySelector('.js-poc-tree-stage');
        const warning = builder.querySelector('.js-poc-tree-warning');
        const summary = builder.querySelector('.js-poc-tree-summary');
        const wallBadge = builder.querySelector('.js-poc-tree-wall');

        if (!namesInput || !layoutInput || !chips || !stage || !summary || !warning || !wallBadge) {
          return;
        }

        let selectedName = null;

        const updateWallBadge = () => {
          wallBadge.hidden = !wallMountInput || !wallMountInput.checked;
        };

        const render = () => {
          const names = parseNames(namesInput.value);
          let layout = normalizeLayout(safeParseLayout(layoutInput.value), names);

          if (!Object.keys(layout).length && names.length) {
            names.forEach((name, index) => {
              layout[`slot_${index + 1}`] = name;
            });
          }

          if (selectedName && !names.includes(selectedName)) {
            selectedName = null;
          }

          chips.innerHTML = '';
          stage.querySelectorAll('.poc-tree-builder__slot').forEach((slot) => slot.remove());

          const placedNames = new Set(Object.values(layout));

          names.forEach((name) => {
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'poc-tree-builder__chip';
            chip.textContent = name;
            chip.dataset.name = name;
            if (selectedName === name) {
              chip.classList.add('is-selected');
            }
            if (placedNames.has(name)) {
              chip.classList.add('is-placed');
            }
            chip.addEventListener('click', () => {
              selectedName = selectedName === name ? null : name;
              render();
            });
            chips.appendChild(chip);
          });

          SLOT_POSITIONS.forEach(([left, top], index) => {
            const slotKey = `slot_${index + 1}`;
            const slot = document.createElement('button');
            slot.type = 'button';
            slot.className = 'poc-tree-builder__slot';
            slot.style.left = `${left}%`;
            slot.style.top = `${top}%`;
            slot.dataset.slot = slotKey;
            slot.textContent = layout[slotKey] || `Emplacement ${index + 1}`;
            if (layout[slotKey]) {
              slot.classList.add('is-filled');
            }

            slot.addEventListener('click', () => {
              if (selectedName) {
                Object.keys(layout).forEach((key) => {
                  if (layout[key] === selectedName) {
                    delete layout[key];
                  }
                });
                layout[slotKey] = selectedName;
                selectedName = null;
              }
              else if (layout[slotKey]) {
                selectedName = layout[slotKey];
                delete layout[slotKey];
              }
              layoutInput.value = JSON.stringify(layout);
              render();
            });

            stage.appendChild(slot);
          });

          layoutInput.value = JSON.stringify(layout);
          warning.textContent = namesInput.value.trim() && !names.length
            ? 'Aucun prénom exploitable détecté.'
            : names.length >= SLOT_POSITIONS.length
              ? `Le PoC affiche au maximum ${SLOT_POSITIONS.length} emplacements.`
              : '';
          summary.textContent = `${Object.keys(layout).length} prénom(s) placé(s) sur ${names.length}.`;
          updateWallBadge();
        };

        namesInput.addEventListener('input', render);
        if (wallMountInput) {
          wallMountInput.addEventListener('change', updateWallBadge);
        }

        render();
      });
    },
  };
}(Drupal, once));

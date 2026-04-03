(function () {
  function isEligibleWrapper(wrapper) {
    return Boolean(
      wrapper.closest('.views-field-operations')
      || wrapper.closest('form.commerce-product-variations td')
    );
  }

  function createMenuItem(link) {
    const item = document.createElement('a');
    item.className = 'odesign-actions__item';
    item.href = link.getAttribute('href') || '#';
    item.textContent = (link.textContent || '').trim();

    const ariaLabel = link.getAttribute('aria-label');
    if (ariaLabel) {
      item.setAttribute('aria-label', ariaLabel);
    }

    const hreflang = link.getAttribute('hreflang');
    if (hreflang) {
      item.setAttribute('hreflang', hreflang);
    }

    return item;
  }

  function enhanceWrapper(wrapper, index) {
    if (wrapper.dataset.odesignActionsReady === 'true') {
      return;
    }

    if (!isEligibleWrapper(wrapper)) {
      return;
    }

    const primaryLink = wrapper.querySelector(':scope > .dropbutton-widget > .dropbutton > .dropbutton-action > a');
    const secondaryLinks = Array.from(
      wrapper.querySelectorAll(':scope > .dropbutton-widget > .dropbutton > .secondary-action > .dropbutton__items > li > a')
    );

    if (!primaryLink) {
      return;
    }

    const primary = document.createElement('a');
    primary.className = 'odesign-actions__primary';
    primary.href = primaryLink.getAttribute('href') || '#';
    primary.textContent = (primaryLink.textContent || '').trim();

    const primaryAria = primaryLink.getAttribute('aria-label');
    if (primaryAria) {
      primary.setAttribute('aria-label', primaryAria);
    }

    const root = document.createElement('div');
    root.className = 'odesign-actions';
    root.appendChild(primary);

    if (secondaryLinks.length > 0) {
      const toggle = document.createElement('button');
      const menu = document.createElement('div');
      const menuId = `odesign-actions-menu-${index}`;

      const positionMenu = () => {
        menu.hidden = false;
        const rootRect = root.getBoundingClientRect();
        const menuRect = menu.getBoundingClientRect();
        const margin = 12;
        const left = Math.max(margin, rootRect.right - menuRect.width);
        const top = rootRect.bottom + 10;

        menu.style.position = 'fixed';
        menu.style.left = `${left}px`;
        menu.style.top = `${top}px`;
        menu.style.right = 'auto';
      };

      toggle.type = 'button';
      toggle.className = 'odesign-actions__toggle';
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-haspopup', 'menu');
      toggle.setAttribute('aria-controls', menuId);
      toggle.innerHTML = '<span class="visually-hidden">Afficher les actions supplémentaires</span>';

      menu.className = 'odesign-actions__menu';
      menu.id = menuId;
      menu.hidden = true;

      secondaryLinks.forEach((link) => {
        menu.appendChild(createMenuItem(link));
      });

      const closeMenu = () => {
        root.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        menu.hidden = true;
      };

      toggle.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        const isOpen = root.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        menu.hidden = !isOpen;
        if (isOpen) {
          positionMenu();
        }
      });

      document.addEventListener('click', function (event) {
        if (!root.contains(event.target)) {
          closeMenu();
        }
      });

      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
          closeMenu();
        }
      });

      window.addEventListener('resize', function () {
        if (root.classList.contains('is-open')) {
          positionMenu();
        }
      });

      window.addEventListener('scroll', function () {
        if (root.classList.contains('is-open')) {
          positionMenu();
        }
      }, true);

      root.appendChild(toggle);
      root.appendChild(menu);
    }

    wrapper.classList.add('odesign-actions-ready');
    wrapper.dataset.odesignActionsReady = 'true';
    wrapper.innerHTML = '';
    wrapper.appendChild(root);
  }

  function initActionButtons(root) {
    const scope = root || document;
    const wrappers = scope.querySelectorAll('.dropbutton-wrapper.dropbutton-multiple');

    wrappers.forEach((wrapper, index) => {
      enhanceWrapper(wrapper, index);
    });
  }

  function moveBulkActions(root) {
    const scope = root || document;
    const blocks = scope.querySelectorAll('.views-bulk-actions');

    blocks.forEach((block) => {
      if (block.dataset.odesignBulkMoved === 'true') {
        return;
      }

      const form = block.closest('form');
      if (!form) {
        return;
      }

      const table = form.querySelector('table');
      if (!table || table.parentElement === block.parentElement && block.compareDocumentPosition(table) & Node.DOCUMENT_POSITION_FOLLOWING) {
        return;
      }

      table.parentElement.insertBefore(block, table);
      block.dataset.odesignBulkMoved = 'true';
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initActionButtons(document);
    moveBulkActions(document);

    const observer = new MutationObserver(function (mutations) {
      for (const mutation of mutations) {
        mutation.addedNodes.forEach((node) => {
          if (!(node instanceof HTMLElement)) {
            return;
          }

          if (node.matches('.views-field-operations .dropbutton-wrapper.dropbutton-multiple')) {
            initActionButtons(node.parentElement || document);
            return;
          }

          if (node.querySelector('.views-field-operations .dropbutton-wrapper.dropbutton-multiple')) {
            initActionButtons(node);
          }

          if (node.matches('.views-bulk-actions') || node.querySelector('.views-bulk-actions')) {
            moveBulkActions(node.parentElement || node);
          }
        });
      }
    });

    observer.observe(document.body, { childList: true, subtree: true });
  });
})();

/* HPM navigation and category selector.
   Works with desktop category links, mobile drawer links and direct hash URLs. */
(() => {
  'use strict';

  const sectionSelector = '.hpm-category-section';
  const sections = () => Array.from(document.querySelectorAll(sectionSelector));

  const normaliseHash = (hash) => {
    const raw = String(hash || '').replace(/^#/, '');
    try { return decodeURIComponent(raw); }
    catch (_) { return raw; }
  };

  const allCategoryLinks = () => Array.from(document.querySelectorAll(
    '[data-hpm-category-link], .hpm-products-dropdown a, .hpm-mobile-product-categories a'
  ));

  const setActiveCategory = (requestedId, writeHistory = false) => {
    const list = sections();
    if (!list.length) return false;

    const selected = list.find(section => section.id === requestedId) || list[0];

    list.forEach(section => {
      const isSelected = section === selected;
      section.hidden = !isSelected;
      section.setAttribute('aria-hidden', isSelected ? 'false' : 'true');
      section.classList.toggle('is-active-category', isSelected);
    });

    allCategoryLinks().forEach(link => {
      const href = new URL(link.getAttribute('href') || '', window.location.href);
      const isSelected = normaliseHash(href.hash) === selected.id;
      link.classList.toggle('is-current', isSelected);
      if (isSelected) link.setAttribute('aria-current', 'page');
      else link.removeAttribute('aria-current');
    });

    if (writeHistory) {
      const nextHash = '#' + encodeURIComponent(selected.id);
      if (window.location.hash !== nextHash) {
        window.history.pushState({ hpmCategory: selected.id }, '', nextHash);
      }
    }
    return true;
  };

  const applyLocationCategory = () => {
    const list = sections();
    if (!list.length) return;
    const requested = normaliseHash(window.location.hash);
    // Products main menu with no hash intentionally selects the first category.
    const match = list.some(section => section.id === requested);
    setActiveCategory(match ? requested : list[0].id, false);
  };

  // Delegate all category anchors, including the mobile drawer submenu.
  document.addEventListener('click', event => {
    const link = event.target.closest(
      '[data-hpm-category-link], .hpm-products-dropdown a, .hpm-mobile-product-categories a'
    );
    if (!link || !sections().length) return;

    const targetUrl = new URL(link.getAttribute('href') || '', window.location.href);
    if (targetUrl.pathname !== window.location.pathname) return;

    const targetId = normaliseHash(targetUrl.hash);
    if (!sections().some(section => section.id === targetId)) return;

    event.preventDefault();
    setActiveCategory(targetId, true);

    const catalogue = document.querySelector('.hpm-catalogue-layout');
    if (catalogue) {
      catalogue.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Close the drawer after a mobile category has been selected.
    const drawer = document.getElementById('side-bar');
    if (link.closest('#hpm-mobile-menu') && drawer) {
      const closeButton = drawer.querySelector('.hpm-mobile-close');
      if (closeButton) closeButton.click();
    }
  });

  // Mobile submenu toggles are handled only by hpm-global-navigation.js.

  // Close the mobile drawer after normal navigation links are selected.
  document.addEventListener('click', event => {
    const link = event.target.closest('#hpm-mobile-menu a:not(.hpm-mobile-product-categories a)');
    if (!link) return;
    const drawer = document.getElementById('side-bar');
    if (drawer && drawer.classList.contains('show')) {
      const closeButton = drawer.querySelector('.hpm-mobile-close');
      if (closeButton) closeButton.click();
    }
  });

  // Keep the category view correct on direct URL entry, reload, back/forward.
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', applyLocationCategory, { once: true });
  } else {
    applyLocationCategory();
  }
  window.addEventListener('hashchange', applyLocationCategory);
  window.addEventListener('popstate', applyLocationCategory);
  window.addEventListener('pageshow', applyLocationCategory);
})();

/*
 * HPM NAVIGATION - the ONE script for the Products menu and the category view.
 *
 * Replaces hpm-global-navigation.js, hpm-products-navigation.js and the empty
 * hpm-site-polish.js. Single source of truth = MySQL table `categories`,
 * delivered by api/categories.php (active categories, ordered by sort_order).
 *
 *  1. Products submenu (desktop dropdown + mobile drawer) is built from the DB.
 *       - products.php already prints the list on the server, so it is there
 *         before the first paint and no request is needed.
 *       - every other page loads the list from api/categories.php. The request
 *         is started by a tiny inline script in <head> (window.__hpmCategories),
 *         so the answer is usually ready before this file even runs.
 *       - nothing is stored in the browser: a category added/hidden/removed in
 *         Admin shows on the very next page load.
 *  2. products.php: shows one category at a time (sidebar, menu links, hash URLs).
 *  3. "current page" highlight + mobile drawer sub-menu toggles.
 */
(() => {
  "use strict";

  const API_URL = new URL("api/categories.php", document.baseURI).toString();
  const LIST_SELECTOR = ".hpm-products-dropdown, .hpm-mobile-product-categories";
  const LINK_SELECTOR =
    "[data-hpm-category-link], .hpm-products-dropdown a, .hpm-mobile-product-categories a";

  /* Old versions saved the menu in the browser (localStorage). Remove it so a
     deleted category can never reappear from an old copy. */
  try {
    ["hpmProductCategoriesV1", "hpmProductCategoriesV2", "hpmProductCategories"].forEach((key) =>
      localStorage.removeItem(key)
    );
  } catch (_) { /* storage blocked: nothing to clean */ }

  /* ------------------------------------------------------------------ *
   * 1. Products submenu from the database
   * ------------------------------------------------------------------ */
  const cleanCategories = (items) =>
    (Array.isArray(items) ? items : [])
      .map((c) => ({
        name: String((c && c.name) || "").trim(),
        slug: String((c && c.slug) || "").trim(),
      }))
      .filter((c) => c.name && c.slug);

  function renderCategoryLists(categories) {
    document.querySelectorAll(LIST_SELECTOR).forEach((list) => {
      const fragment = document.createDocumentFragment();
      categories.forEach((category) => {
        const li = document.createElement("li");
        const a = document.createElement("a");
        a.href = `products.php#category-${encodeURIComponent(category.slug)}`;
        a.textContent = category.name;
        li.appendChild(a);
        fragment.appendChild(li);
      });
      if (!categories.length) {
        const li = document.createElement("li");
        li.className = "hpm-category-empty";
        li.textContent = "Products will be updated soon.";
        fragment.appendChild(li);
      }
      list.replaceChildren(fragment);
      list.setAttribute("data-hpm-rendered", "1");
    });
    markCurrentCategoryLinks();
  }

  function renderUnavailable() {
    document.querySelectorAll(LIST_SELECTOR).forEach((list) => {
      if (list.getAttribute("data-hpm-rendered") === "1") return; // keep what is shown
      const li = document.createElement("li");
      li.className = "hpm-category-empty";
      li.textContent = "Product categories are temporarily unavailable.";
      list.replaceChildren(li);
    });
  }

  async function fetchCategories() {
    // Started early by the inline <head> script when present, otherwise start now.
    const early = window.__hpmCategories;
    window.__hpmCategories = null; // a later refresh must ask the server again
    if (early && typeof early.then === "function") {
      const fromEarly = await early;
      if (fromEarly) return fromEarly;
    }
    const response = await fetch(API_URL, {
      method: "GET",
      headers: { Accept: "application/json" },
      credentials: "same-origin",
      cache: "no-cache", // always re-check with the server; unchanged answers are a tiny 304
    });
    if (!response.ok) throw new Error(`Category API HTTP ${response.status}`);
    return response.json();
  }

  async function loadProductCategories() {
    const lists = document.querySelectorAll(LIST_SELECTOR);
    if (!lists.length) return;

    // products.php prints the menu on the server: already correct, nothing to fetch.
    if (Array.from(lists).every((list) => list.getAttribute("data-hpm-server") === "1")) return;

    try {
      const data = await fetchCategories();
      if (!data || data.success !== true || !Array.isArray(data.categories)) {
        throw new Error("Unexpected categories API response");
      }
      renderCategoryLists(cleanCategories(data.categories));
    } catch (error) {
      console.error("HPM product categories could not be loaded:", error);
      renderUnavailable();
    }
  }

  /* ------------------------------------------------------------------ *
   * 2. products.php: one category visible at a time
   * ------------------------------------------------------------------ */
  const sections = () => Array.from(document.querySelectorAll(".hpm-category-section"));

  const normaliseHash = (hash) => {
    const raw = String(hash || "").replace(/^#/, "");
    try { return decodeURIComponent(raw); } catch (_) { return raw; }
  };

  const allCategoryLinks = () => Array.from(document.querySelectorAll(LINK_SELECTOR));

  function markCurrentCategoryLinks() {
    const current = sections().find((section) => !section.hidden);
    if (!current) return;
    allCategoryLinks().forEach((link) => {
      const href = new URL(link.getAttribute("href") || "", window.location.href);
      const isSelected = normaliseHash(href.hash) === current.id;
      link.classList.toggle("is-current", isSelected);
      if (isSelected) link.setAttribute("aria-current", "page");
      else link.removeAttribute("aria-current");
    });
  }

  function setActiveCategory(requestedId, writeHistory = false) {
    const list = sections();
    if (!list.length) return false;

    const selected = list.find((section) => section.id === requestedId) || list[0];
    list.forEach((section) => {
      const isSelected = section === selected;
      section.hidden = !isSelected;
      section.setAttribute("aria-hidden", isSelected ? "false" : "true");
      section.classList.toggle("is-active-category", isSelected);
    });
    markCurrentCategoryLinks();

    if (writeHistory) {
      const nextHash = "#" + encodeURIComponent(selected.id);
      if (window.location.hash !== nextHash) {
        window.history.pushState({ hpmCategory: selected.id }, "", nextHash);
      }
    }
    return true;
  }

  function applyLocationCategory() {
    const list = sections();
    if (!list.length) return;
    const requested = normaliseHash(window.location.hash);
    // "Products" with no hash opens the first category.
    const match = list.some((section) => section.id === requested);
    setActiveCategory(match ? requested : list[0].id, false);
  }

  // Category links: sidebar, desktop dropdown and mobile drawer.
  document.addEventListener("click", (event) => {
    const link = event.target.closest(LINK_SELECTOR);
    if (!link || !sections().length) return;

    const targetUrl = new URL(link.getAttribute("href") || "", window.location.href);
    if (targetUrl.pathname !== window.location.pathname) return;

    const targetId = normaliseHash(targetUrl.hash);
    if (!sections().some((section) => section.id === targetId)) return;

    event.preventDefault();
    setActiveCategory(targetId, true);

    const catalogue = document.querySelector(".hpm-catalogue-layout");
    if (catalogue) catalogue.scrollIntoView({ behavior: "smooth", block: "start" });

    // Close the mobile drawer after choosing a category.
    const drawer = document.getElementById("side-bar");
    if (link.closest("#hpm-mobile-menu") && drawer) {
      const closeButton = drawer.querySelector(".hpm-mobile-close");
      if (closeButton) closeButton.click();
    }
  });

  // Close the mobile drawer after any normal (non-category) link.
  document.addEventListener("click", (event) => {
    const link = event.target.closest("#hpm-mobile-menu a:not(.hpm-mobile-product-categories a)");
    if (!link) return;
    const drawer = document.getElementById("side-bar");
    if (drawer && drawer.classList.contains("show")) {
      const closeButton = drawer.querySelector(".hpm-mobile-close");
      if (closeButton) closeButton.click();
    }
  });

  /* ------------------------------------------------------------------ *
   * 3. Current page highlight + mobile submenu toggles
   * ------------------------------------------------------------------ */
  function normalisePath(path) {
    const clean = (path || "").split("?")[0].split("#")[0];
    return clean.split("/").filter(Boolean).pop() || "index.html";
  }

  function setCurrentPage() {
    const current = normalisePath(window.location.pathname);
    const servicePages = new Set([
      "services.html",
      "automation-services.html",
      "metal-fabrication-works.html",
      "installation-and-commissioning.html",
      "repair-and-maintenance.html",
      "spare-parts-and-nailing-equipment.html",
      "warehouse-solutions.html",
      "heat-treatment-plant-and-machine-customisation.html",
      "technical-support.html",
      "pallet-product-line-solutions.html",
    ]);

    document.querySelectorAll(".mainmenu > li, .hpm-mobile-menu-list > li").forEach((item) => {
      item.classList.remove("active", "is-current");
    });

    document.querySelectorAll(".mainmenu a[href], .hpm-mobile-menu-list a[href]").forEach((link) => {
      const target = normalisePath(link.getAttribute("href"));
      link.classList.remove("active", "is-current");
      link.removeAttribute("aria-current");

      const isExact = target === current;
      const isServicesParent = target === "services.html" && servicePages.has(current);
      if (isExact || isServicesParent) {
        link.setAttribute("aria-current", "page");
        link.classList.add("is-current");
        link.closest("li")?.classList.add("is-current");
      }
    });

    document.querySelectorAll(".hpm-products-menu").forEach((item) => {
      item.classList.toggle("is-current", current === "products.php");
      item.classList.remove("active");
    });
  }

  function initMobileSubmenus() {
    document.addEventListener("click", (event) => {
      const button = event.target.closest(".hpm-mobile-submenu-toggle");
      if (!button) return;

      const item = button.closest(".hpm-mobile-has-submenu");
      const submenu = item?.querySelector(":scope > .hpm-mobile-submenu");
      if (!submenu) return;

      const next = button.getAttribute("aria-expanded") !== "true";
      button.setAttribute("aria-expanded", String(next));
      button.setAttribute(
        "aria-label",
        `${next ? "Close" : "Open"} ${item.querySelector(".hpm-mobile-main-link")?.textContent.trim() || ""} submenu`
      );
      submenu.hidden = !next;

      const glyph = button.querySelector(".hpm-submenu-glyph");
      if (glyph) glyph.textContent = next ? "−" : "+";
    });
  }

  /* ------------------------------------------------------------------ *
   * Start
   * ------------------------------------------------------------------ */
  const start = () => {
    loadProductCategories();
    setCurrentPage();
    initMobileSubmenus();
    applyLocationCategory();
  };
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start, { once: true });
  } else {
    start();
  }

  window.addEventListener("hashchange", applyLocationCategory);
  window.addEventListener("popstate", applyLocationCategory);
  window.addEventListener("pageshow", (event) => {
    applyLocationCategory();
    // Page restored from the back/forward cache: ask the server again so the
    // menu cannot show categories that were changed in Admin in the meantime.
    if (event.persisted) {
      const serverRendered = document.querySelector("[data-hpm-server='1']");
      if (serverRendered) window.location.reload(); // products.php: re-run the database query
      else loadProductCategories();
    }
  });
})();

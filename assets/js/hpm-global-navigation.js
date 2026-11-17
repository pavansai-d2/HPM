/*
 * HPM NAVIGATION — the ONE script that owns product categories on the public site.
 *
 *  1. Products submenu (desktop dropdown + mobile drawer) is filled from the
 *     `categories` table via api/categories.php.
 *       - products.php renders the submenu on the server (data-hpm-server-rendered),
 *         so nothing is fetched there.
 *       - every other page paints the last known list instantly from localStorage,
 *         then refreshes it from the API (never cached) and repaints if it changed.
 *  2. products.php category selector: sidebar, dropdown and drawer links switch
 *     the visible category section; direct #category-slug URLs, reload and
 *     back/forward all work.
 *  3. Current-page highlighting and mobile submenu toggles.
 *
 * This replaces the former hpm-products-navigation.js and the hard-coded
 * category list that used to live in hpm-site-polish.js.
 */
(() => {
  "use strict";

  const API_URL = new URL("api/categories.php", document.baseURI).toString();
  const CACHE_KEY = "hpm_categories_v2";
  const LIST_SELECTOR = ".hpm-products-dropdown, .hpm-mobile-product-categories";
  const LINK_SELECTOR =
    "[data-hpm-category-link], .hpm-products-dropdown a, .hpm-mobile-product-categories a";
  const SECTION_SELECTOR = ".hpm-category-section";

  /* ------------------------------------------------------------------ */
  /* Category cache (instant first paint on static pages)               */
  /* ------------------------------------------------------------------ */
  function readCache() {
    try {
      const data = JSON.parse(window.localStorage.getItem(CACHE_KEY) || "null");
      return Array.isArray(data) ? data.filter(isValidCategory) : null;
    } catch (_) {
      return null;
    }
  }

  function writeCache(categories) {
    try {
      window.localStorage.setItem(CACHE_KEY, JSON.stringify(categories));
    } catch (_) {
      /* private mode / storage full: the live fetch still works */
    }
  }

  function isValidCategory(category) {
    return !!(category && String(category.name || "").trim() && String(category.slug || "").trim());
  }

  function sameCategories(a, b) {
    return JSON.stringify(a) === JSON.stringify(b);
  }

  /* ------------------------------------------------------------------ */
  /* Products submenu rendering                                         */
  /* ------------------------------------------------------------------ */
  function renderLists(lists, categories) {
    lists.forEach((list) => {
      list.replaceChildren();

      categories.forEach((category) => {
        const slug = String(category.slug).trim();
        const name = String(category.name).trim();
        const li = document.createElement("li");
        const a = document.createElement("a");
        a.href = `products.php#category-${encodeURIComponent(slug)}`;
        a.textContent = name;
        li.appendChild(a);
        list.appendChild(li);
      });

      if (!list.children.length) {
        const li = document.createElement("li");
        li.className = "hpm-category-empty";
        li.textContent = "Products will be updated soon.";
        list.appendChild(li);
      }
    });
    markCurrentCategoryLinks();
  }

  function renderError(lists) {
    lists.forEach((list) => {
      list.replaceChildren();
      const li = document.createElement("li");
      li.className = "hpm-category-empty";
      li.textContent = "Product categories are temporarily unavailable.";
      list.appendChild(li);
    });
  }

  function categoriesFromList(list) {
    return Array.from(list.querySelectorAll(":scope > li > a[href]"))
      .map((a) => {
        const hash = decodeHash(new URL(a.getAttribute("href"), document.baseURI).hash);
        return { name: a.textContent.trim(), slug: hash.replace(/^category-/, "") };
      })
      .filter(isValidCategory);
  }

  async function fetchCategories() {
    // A changing query string defeats any browser, proxy or CDN copy.
    const response = await fetch(`${API_URL}?t=${Date.now()}`, {
      method: "GET",
      headers: { Accept: "application/json" },
      credentials: "same-origin",
      cache: "no-store"
    });
    if (!response.ok) throw new Error(`Category API HTTP ${response.status}`);

    const data = await response.json();
    if (data.success !== true || !Array.isArray(data.categories)) {
      throw new Error("Unexpected categories API response");
    }
    return data.categories
      .filter(isValidCategory)
      .map((c) => ({ name: String(c.name).trim(), slug: String(c.slug).trim() }));
  }

  async function loadProductCategories() {
    const lists = Array.from(document.querySelectorAll(LIST_SELECTOR));
    if (!lists.length) return;

    // products.php already printed the menu from the database on the server.
    const rendered = lists.filter((list) => list.hasAttribute("data-hpm-server-rendered"));
    if (rendered.length === lists.length) {
      const fresh = categoriesFromList(rendered[0]);
      writeCache(fresh); // keeps the instant paint on other pages up to date
      return;
    }

    const cached = readCache();
    if (cached) renderLists(lists, cached);

    try {
      const fresh = await fetchCategories();
      if (!cached || !sameCategories(cached, fresh)) renderLists(lists, fresh);
      writeCache(fresh);
    } catch (error) {
      console.error("HPM product categories could not be loaded:", error);
      if (!cached) renderError(lists);
    }
  }

  /* ------------------------------------------------------------------ */
  /* products.php: one visible category section at a time               */
  /* ------------------------------------------------------------------ */
  const sections = () => Array.from(document.querySelectorAll(SECTION_SELECTOR));

  function decodeHash(hash) {
    const raw = String(hash || "").replace(/^#/, "");
    try {
      return decodeURIComponent(raw);
    } catch (_) {
      return raw;
    }
  }

  function selectedCategoryId() {
    const list = sections();
    if (!list.length) return "";
    const requested = decodeHash(window.location.hash);
    return list.some((section) => section.id === requested) ? requested : list[0].id;
  }

  function markCurrentCategoryLinks() {
    const list = sections();
    if (!list.length) return;
    const selectedId = selectedCategoryId();

    document.querySelectorAll(LINK_SELECTOR).forEach((link) => {
      const href = new URL(link.getAttribute("href") || "", window.location.href);
      const isSelected = decodeHash(href.hash) === selectedId;
      link.classList.toggle("is-current", isSelected);
      if (isSelected) link.setAttribute("aria-current", "page");
      else link.removeAttribute("aria-current");
    });
  }

  function setActiveCategory(requestedId, writeHistory) {
    const list = sections();
    if (!list.length) return false;

    const selected = list.find((section) => section.id === requestedId) || list[0];

    list.forEach((section) => {
      const isSelected = section === selected;
      section.hidden = !isSelected;
      section.setAttribute("aria-hidden", isSelected ? "false" : "true");
      section.classList.toggle("is-active-category", isSelected);
    });

    document.querySelectorAll(LINK_SELECTOR).forEach((link) => {
      const href = new URL(link.getAttribute("href") || "", window.location.href);
      const isSelected = decodeHash(href.hash) === selected.id;
      link.classList.toggle("is-current", isSelected);
      if (isSelected) link.setAttribute("aria-current", "page");
      else link.removeAttribute("aria-current");
    });

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
    // Products with no hash intentionally selects the first category.
    setActiveCategory(selectedCategoryId(), false);
  }

  function initCategorySelector() {
    // Delegated, so it also covers links that are added after page load.
    document.addEventListener("click", (event) => {
      const link = event.target.closest(LINK_SELECTOR);
      if (!link || !sections().length) return;

      const targetUrl = new URL(link.getAttribute("href") || "", window.location.href);
      if (targetUrl.pathname !== window.location.pathname) return;

      const targetId = decodeHash(targetUrl.hash);
      if (!sections().some((section) => section.id === targetId)) return;

      event.preventDefault();
      setActiveCategory(targetId, true);

      const catalogue = document.querySelector(".hpm-catalogue-layout");
      if (catalogue) catalogue.scrollIntoView({ behavior: "smooth", block: "start" });

      // Close the mobile drawer after a category was chosen.
      const drawer = document.getElementById("side-bar");
      if (link.closest("#hpm-mobile-menu") && drawer) {
        drawer.querySelector(".hpm-mobile-close")?.click();
      }
    });

    applyLocationCategory();
    window.addEventListener("hashchange", applyLocationCategory);
    window.addEventListener("popstate", applyLocationCategory);
  }

  /* ------------------------------------------------------------------ */
  /* Current page + mobile drawer                                       */
  /* ------------------------------------------------------------------ */
  function normalisePath(path) {
    const clean = (path || "").split("?")[0].split("#")[0];
    return clean.split("/").filter(Boolean).pop() || "index.html";
  }

  function setCurrentPage() {
    const current = normalisePath(window.location.pathname);
    const servicePages = new Set([
      "services.html",
      "installation-and-commissioning.html",
      "repair-and-maintenance.html",
      "spare-parts-and-nailing-equipment.html",
      "warehouse-solutions.html",
      "heat-treatment-plant-and-machine-customisation.html",
      "technical-support.html",
      "pallet-product-line-solutions.html"
    ]);

    document.querySelectorAll(".mainmenu > li, .hpm-mobile-menu-list > li").forEach((item) => {
      item.classList.remove("active", "is-current");
    });

    document.querySelectorAll(".mainmenu a[href], .hpm-mobile-menu-list a[href]").forEach((link) => {
      // Category links are highlighted by markCurrentCategoryLinks().
      if (link.closest(".hpm-products-dropdown, .hpm-mobile-product-categories")) return;

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

    // Do not leave stale template active classes on Products or any other item.
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

    // Close the drawer after any ordinary (non-category) link is chosen.
    document.addEventListener("click", (event) => {
      const link = event.target.closest("#hpm-mobile-menu a");
      if (!link || link.closest(".hpm-mobile-product-categories")) return;
      const drawer = document.getElementById("side-bar");
      if (drawer && drawer.classList.contains("show")) {
        drawer.querySelector(".hpm-mobile-close")?.click();
      }
    });
  }

  /* ------------------------------------------------------------------ */
  /* Start                                                              */
  /* ------------------------------------------------------------------ */
  function start() {
    initMobileSubmenus();
    initCategorySelector();
    setCurrentPage();
    loadProductCategories();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start, { once: true });
  } else {
    start();
  }

  // Back/forward restore from the browser's page cache: re-check the list.
  window.addEventListener("pageshow", (event) => {
    if (event.persisted) {
      loadProductCategories();
      applyLocationCategory();
    }
  });
})();

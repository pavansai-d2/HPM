/*
 * HPM GLOBAL NAVIGATION — single source of truth for mobile submenu toggles.
 * Loads product categories on every public page and sets current-page semantics.
 */
(() => {
  "use strict";

  const categoryApi = new URL("api/categories.php", document.baseURI).toString();

  async function loadProductCategories() {
    const lists = document.querySelectorAll(
      ".hpm-products-dropdown, .hpm-mobile-product-categories"
    );
    if (!lists.length) return;

    try {
      const response = await fetch(categoryApi, {
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

      lists.forEach((list) => {
        list.replaceChildren();
        data.categories.forEach((category) => {
          if (!category || !category.name || !category.slug) return;
          const li = document.createElement("li");
          const a = document.createElement("a");
          const slug = String(category.slug).trim();
          const name = String(category.name).trim();
          if (!slug || !name) return;
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
    } catch (error) {
      console.error("HPM product categories could not be loaded:", error);
      lists.forEach((list) => {
        list.replaceChildren();
        const li = document.createElement("li");
        li.className = "hpm-category-empty";
        li.textContent = "Product categories are temporarily unavailable.";
        list.appendChild(li);
      });
    }
  }

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

    document.querySelectorAll(
      ".mainmenu a[href], .hpm-mobile-menu-list a[href]"
    ).forEach((link) => {
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

      const expanded = button.getAttribute("aria-expanded") === "true";
      const next = !expanded;

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

  document.addEventListener("DOMContentLoaded", () => {
    loadProductCategories();
    setCurrentPage();
    initMobileSubmenus();
  });
})();

(() => {
  "use strict";

  const API_URL = window.HPM_PRODUCT_API || "api/product.php";
  const modal = document.getElementById("productModal");
  const modalPanel = modal?.querySelector(".hpm-modal-panel");
  const modalContent = document.getElementById("modalContent");

  if (!modal || !modalPanel || !modalContent) {
    console.error("HPM product modal elements were not found.");
    return;
  }

  let lastFocusedElement = null;
  let activeLightbox = null;

  const escapeHTML = (value) =>
    String(value ?? "").replace(/[&<>"']/g, (character) => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;"
    })[character]);

  const resolveImagePath = (path) => {
    const value = String(path ?? "").trim();
    if (!value) return "";
    // Keep same-origin relative paths as supplied by the database.
    // Permit absolute HTTPS image URLs if an administrator intentionally uses one.
    if (/^https:\/\/[^\s]+$/i.test(value)) return value;
    if (/^(javascript|data):/i.test(value)) return "";
    return value.replace(/^\/+/, "");
  };

  const openModal = () => {
    lastFocusedElement = document.activeElement;
    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");
    document.body.classList.add("hpm-modal-open");
    modal.querySelector("[data-close-modal]")?.focus();
  };

  const closeModal = () => {
    closeLightbox();
    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("hpm-modal-open");
    if (lastFocusedElement && typeof lastFocusedElement.focus === "function") {
      lastFocusedElement.focus();
    }
  };

  const closeLightbox = () => {
    if (activeLightbox) {
      activeLightbox.remove();
      activeLightbox = null;
      document.body.classList.remove("hpm-lightbox-open");
    }
  };

  const openLightbox = (src, alt) => {
    if (!src) return;

    closeLightbox();

    const lightbox = document.createElement("div");
    lightbox.className = "hpm-image-lightbox";
    lightbox.setAttribute("role", "dialog");
    lightbox.setAttribute("aria-modal", "true");
    lightbox.setAttribute("aria-label", "Product image viewer");

    lightbox.innerHTML = `
      <button class="hpm-lightbox-close" type="button" aria-label="Close image viewer">×</button>
      <div class="hpm-lightbox-stage">
        <img class="hpm-lightbox-image" src="${escapeHTML(src)}" alt="${escapeHTML(alt)}" draggable="false">
      </div>
    `;

    document.body.appendChild(lightbox);
    document.body.classList.add("hpm-lightbox-open");
    activeLightbox = lightbox;

    const image = lightbox.querySelector(".hpm-lightbox-image");
    const stage = lightbox.querySelector(".hpm-lightbox-stage");
    let scale = 1;
    let offsetX = 0;
    let offsetY = 0;
    let dragging = false;
    let startX = 0;
    let startY = 0;
    let startOffsetX = 0;
    let startOffsetY = 0;

    const applyTransform = () => {
      image.style.transform = `translate(${offsetX}px, ${offsetY}px) scale(${scale})`;
      image.classList.toggle("is-zoomed", scale > 1);
    };

    const setZoom = (nextScale) => {
      scale = Math.min(4, Math.max(1, nextScale));
      if (scale === 1) {
        offsetX = 0;
        offsetY = 0;
      }
      applyTransform();
    };

    lightbox.querySelector(".hpm-lightbox-close").addEventListener("click", closeLightbox);

    // The image opens at its natural fitted size. No click-to-zoom toggle
    // and no visible zoom buttons or instruction text. Mouse-wheel zoom
    // remains available for users who want to inspect image details.

    stage.addEventListener("wheel", (event) => {
      event.preventDefault();
      setZoom(scale + (event.deltaY < 0 ? 0.2 : -0.2));
    }, { passive: false });

    image.addEventListener("pointerdown", (event) => {
      if (scale <= 1) return;
      dragging = true;
      image.setPointerCapture(event.pointerId);
      startX = event.clientX;
      startY = event.clientY;
      startOffsetX = offsetX;
      startOffsetY = offsetY;
    });

    image.addEventListener("pointermove", (event) => {
      if (!dragging) return;
      offsetX = startOffsetX + (event.clientX - startX);
      offsetY = startOffsetY + (event.clientY - startY);
      applyTransform();
    });

    const stopDragging = () => { dragging = false; };
    image.addEventListener("pointerup", stopDragging);
    image.addEventListener("pointercancel", stopDragging);

    lightbox.addEventListener("click", (event) => {
      if (event.target === lightbox) closeLightbox();
    });

    lightbox.querySelector(".hpm-lightbox-close").focus();
  };

  const youtubeEmbedUrl = (value) => {
    const raw = String(value ?? "").trim();
    if (!raw) return "";
    try {
      const url = new URL(raw);
      const host = url.hostname.replace(/^www\./i, "").toLowerCase();
      let videoId = "";
      if (host === "youtu.be") videoId = url.pathname.split("/").filter(Boolean)[0] || "";
      else if (["youtube.com", "m.youtube.com", "youtube-nocookie.com"].includes(host)) {
        if (url.pathname === "/watch") videoId = url.searchParams.get("v") || "";
        else {
          const match = url.pathname.match(/^\/(?:embed|shorts|live)\/([A-Za-z0-9_-]+)/);
          if (match) videoId = match[1];
        }
      }
      return /^[A-Za-z0-9_-]{6,}$/.test(videoId)
        ? `https://www.youtube-nocookie.com/embed/${videoId}?rel=0`
        : "";
    } catch (_) { return ""; }
  };

  const renderOptionalImages = (product) => {
    const items = [
      { label: "Before", path: resolveImagePath(product.before_image) },
      { label: "After", path: resolveImagePath(product.after_image) }
    ].filter((item) => item.path);
    if (!items.length) return "";
    return `
      <section class="hpm-optional-media" aria-label="Product before and after images">
        ${items.map((item) => `
          <button class="hpm-optional-image" type="button" data-open-image="${escapeHTML(item.path)}" data-image-alt="${escapeHTML(product.name || "HPM machinery")} - ${item.label}" aria-label="View ${item.label.toLowerCase()} image">
            <img loading="lazy" src="${escapeHTML(item.path)}" alt="${escapeHTML(product.name || "HPM machinery")} - ${item.label}">
            <span>${item.label}</span>
          </button>`).join("")}
      </section>`;
  };

  const renderGallery = (product) => {
    const photos = (Array.isArray(product.gallery) ? product.gallery : [])
      .map((photo) => ({ path: resolveImagePath(photo && photo.path), alt: String((photo && photo.alt) || "").trim() }))
      .filter((photo) => photo.path);
    if (!photos.length) return "";
    return `
      <section class="hpm-optional-media hpm-gallery-media" aria-label="More photos of ${escapeHTML(product.name || "this machine")}">
        ${photos.map((photo, index) => `
          <button class="hpm-optional-image" type="button" data-open-image="${escapeHTML(photo.path)}" data-image-alt="${escapeHTML(photo.alt || product.name || "HPM machinery")}" aria-label="View photo ${index + 1}">
            <img loading="lazy" src="${escapeHTML(photo.path)}" alt="${escapeHTML(photo.alt || product.name || "HPM machinery")}">
            <span>Photo ${index + 1}</span>
          </button>`).join("")}
      </section>`;
  };

  const renderProductVideo = (value) => {
    const raw = String(value ?? "").trim();
    if (!raw) return "";
    const embed = youtubeEmbedUrl(raw);
    if (embed) return `
      <section class="hpm-product-video" aria-label="Product demonstration video">
        <h3>Product Video</h3>
        <div class="hpm-video-frame"><iframe src="${escapeHTML(embed)}" title="HPM product demonstration" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe></div>
      </section>`;
    return "";
  };

  const renderSpecifications = (value) => {
    // Admin saves one specification per line. Normalize common newline styles.
    // Remove existing bullet/number prefixes to avoid duplicate markers.
    const lines = String(value ?? "")
      .replace(/\r\n?/g, "\n")
      .split("\n")
      .map((line) => line.trim())
      .map((line) => line.replace(/^(?:[•●▪◦‣\-–—]\s*|\d+[.)]\s*)/, "").trim())
      .filter(Boolean);

    if (!lines.length) return "";

    return `
      <section class="hpm-modal-specifications" aria-labelledby="hpm-modal-specifications-title">
        <h3 id="hpm-modal-specifications-title">Specifications</h3>
        <ul class="hpm-specification-list">
          ${lines.map((line) => `<li>${escapeHTML(line)}</li>`).join("")}
        </ul>
      </section>
    `;
  };

  const renderProduct = (product) => {
    const image = resolveImagePath(product.image);
    const imageAlt = product.alt_text || product.name || "HPM machinery";
    const description = String(product.description ?? "").trim();
    const shortDescription = String(product.short_description ?? "").trim();
    const whatsappUrl = String(product.whatsapp_url ?? "").trim();

    const imageMarkup = image
      ? `
        <button class="hpm-modal-image-button" type="button"
                data-open-image="${escapeHTML(image)}"
                data-image-alt="${escapeHTML(imageAlt)}"
                aria-label="Open full-size image of ${escapeHTML(product.name)}">
          <img class="hpm-modal-main-image"
               src="${escapeHTML(image)}"
               alt="${escapeHTML(imageAlt)}">
        </button>
      `
      : `
        <div class="hpm-modal-image-placeholder">
          <span>No product image available</span>
        </div>
      `;

    const whatsappMarkup = whatsappUrl
      ? `<a class="hpm-modal-whatsapp" href="${escapeHTML(whatsappUrl)}"
             target="_blank" rel="noopener noreferrer">
           <i class="fab fa-whatsapp" aria-hidden="true"></i>
           Enquire on WhatsApp
         </a>`
      : "";

    modalContent.innerHTML = `
      <div class="hpm-modal-layout">
        <div class="hpm-modal-media">
          ${imageMarkup}
          ${renderOptionalImages(product)}
          ${renderGallery(product)}
        </div>

        <div class="hpm-modal-details">
          <span class="hpm-modal-category">${escapeHTML(product.category || "HPM Machinery")}</span>
          <h2 id="modalTitle">${escapeHTML(product.name || "Product details")}</h2>

          ${shortDescription ? `<p class="hpm-modal-short-description">${escapeHTML(shortDescription)}</p>` : ""}

          ${description ? `
            <section class="hpm-modal-description">
              <h3>Description</h3>
              <p>${escapeHTML(description).replace(/\r?\n/g, "<br>")}</p>
            </section>
          ` : ""}

          ${renderSpecifications(product.specifications)}

          ${renderProductVideo(product.video_url)}

          ${whatsappMarkup}
        </div>
      </div>
    `;

    modalContent.querySelectorAll("[data-open-image]").forEach((imageButton) => {
      imageButton.addEventListener("click", () => {
        openLightbox(imageButton.dataset.openImage, imageButton.dataset.imageAlt || "");
      });
    });

    const mainImage = modalContent.querySelector(".hpm-modal-main-image");
    mainImage?.addEventListener("error", () => {
      mainImage.closest("button")?.remove();
      const placeholder = document.createElement("div");
      placeholder.className = "hpm-modal-image-placeholder";
      placeholder.textContent = "Product image could not be loaded";
      modalContent.querySelector(".hpm-modal-media")?.appendChild(placeholder);
    }, { once: true });
  };

  const loadProduct = async (id) => {
    if (!id) return;

    openModal();
    modalContent.innerHTML = `<div class="hpm-modal-loading" role="status">Loading product details…</div>`;

    try {
      const response = await fetch(`${API_URL}?id=${encodeURIComponent(id)}`, {
        headers: { "Accept": "application/json" },
        credentials: "same-origin"
      });

      const data = await response.json().catch(() => ({}));

      if (!response.ok) {
        throw new Error(data.error || "Unable to load product details.");
      }

      renderProduct(data);
    } catch (error) {
      modalContent.innerHTML = `
        <div class="hpm-modal-error" role="alert">
          ${escapeHTML(error.message || "Unable to load product details. Please try again.")}
        </div>
      `;
    }
  };

  document.addEventListener("click", (event) => {
    const trigger = event.target.closest("[data-product-id]");
    if (trigger) {
      event.preventDefault();
      loadProduct(trigger.dataset.productId);
      return;
    }

    if (event.target.closest("[data-close-modal]")) {
      closeModal();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      if (activeLightbox) {
        closeLightbox();
      } else if (modal.classList.contains("is-open")) {
        closeModal();
      }
    }
  });
})();

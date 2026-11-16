(() => {
  "use strict";
  document.addEventListener("DOMContentLoaded", () => {
    const params = new URLSearchParams(window.location.search);
    const status = params.get("contact");
    const modal = document.getElementById("hpmContactModal");
    if (!modal || !status) return;

    const title = document.getElementById("hpmContactModalTitle");
    const message = document.getElementById("hpmContactModalMessage");
    const icon = document.getElementById("hpmContactModalIcon");
    const content = {
      success: {
        title: "Thank you for contacting HPM!",
        message: "Your enquiry has been submitted successfully. Our team will review your requirements and get back to you shortly.",
        icon: "✓", type: "success"
      },
      saved: {
        title: "Thank you! We received your enquiry.",
        message: "Your enquiry has been saved successfully. Our team will follow up with you soon.",
        icon: "✓", type: "success"
      },
      invalid: {
        title: "Please check your details",
        message: "Some information appears to be missing or invalid. Please review the form and submit it again.",
        icon: "!", type: "error"
      },
      captcha: {
        title: "Verification required",
        message: "Please complete the CAPTCHA verification and submit your enquiry again.",
        icon: "!", type: "error"
      },
      ratelimit: {
        title: "Too many enquiries sent",
        message: "We have received several submissions from your connection in a short time. Please wait a few minutes and try again, or contact HPM by phone or WhatsApp.",
        icon: "!", type: "error"
      },
      captcha_config: {
        title: "Form temporarily unavailable",
        message: "The website verification service is not configured correctly. Please contact HPM by phone or email.",
        icon: "!", type: "error"
      },
      error: {
        title: "We couldn't process your enquiry",
        message: "Please try again in a little while, or contact HPM directly by phone or email.",
        icon: "!", type: "error"
      }
    }[status];

    if (!content) return;
    title.textContent = content.title;
    message.textContent = content.message;
    icon.textContent = content.icon;
    modal.classList.add(`is-${content.type}`);
    modal.hidden = false;
    document.body.classList.add("hpm-contact-modal-open");

    const close = () => {
      modal.hidden = true;
      document.body.classList.remove("hpm-contact-modal-open");
    };
    modal.querySelectorAll("[data-close-contact-modal]").forEach((button) => {
      button.addEventListener("click", close);
    });
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && !modal.hidden) close();
    });
    modal.querySelector(".hpm-contact-modal__button")?.focus();

    params.delete("contact");
    const query = params.toString();
    history.replaceState({}, document.title,
      window.location.pathname + (query ? `?${query}` : "") + window.location.hash);
  });
})();

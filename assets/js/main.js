/* =========================
   Vinpack - Main JavaScript
========================= */

document.addEventListener("DOMContentLoaded", () => {
  initMobileMenu();
  setActiveNavLink();
  initScrollAnimations();
  initInquiryForm(); // Web3Forms enabled
});

/* =========================
   Mobile Menu Toggle
========================= */
function initMobileMenu() {
  const menuBtn = document.querySelector(".mobile-menu-btn");
  const navLinks = document.querySelector(".nav-links");

  if (!menuBtn || !navLinks) return;

  menuBtn.addEventListener("click", () => {
    navLinks.classList.toggle("mobile-open");
    menuBtn.classList.toggle("active");

    const expanded = menuBtn.getAttribute("aria-expanded") === "true";
    menuBtn.setAttribute("aria-expanded", !expanded);
  });

  navLinks.querySelectorAll("a").forEach(link => {
    link.addEventListener("click", () => {
      navLinks.classList.remove("mobile-open");
      menuBtn.classList.remove("active");
      menuBtn.setAttribute("aria-expanded", "false");
    });
  });
}

/* =========================
   Active Navigation Link
========================= */
function setActiveNavLink() {
  const currentPage =
    window.location.pathname.split("/").pop() || "index.html";

  document.querySelectorAll(".nav-links a").forEach(link => {
    if (link.getAttribute("href") === currentPage) {
      link.classList.add("active");
    }
  });
}

/* =========================
   Scroll Animations
========================= */
function initScrollAnimations() {
  const elements = document.querySelectorAll(
    ".features article, .product-card, .contact-card, .about-offerings li"
  );

  if (!("IntersectionObserver" in window)) {
    elements.forEach(el => el.classList.add("animated"));
    return;
  }

  const observer = new IntersectionObserver(
    entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add("animated");
          observer.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.15 }
  );

  elements.forEach(el => observer.observe(el));
}

/* =========================
   Inquiry Form (Web3Forms)
========================= */
function initInquiryForm() {
  const form = document.getElementById("inquiryForm");
  const status = document.getElementById("formStatus");

  if (!form || !status) return;

  form.addEventListener("submit", async e => {
    e.preventDefault();

    status.textContent = "Sending...";
    status.style.color = "#555";

    const formData = new FormData(form);

    // Required Web3Forms fields
    formData.append(
      "access_key",
      document.getElementById("access_key").value
    );
    formData.append("subject", "New Product Inquiry - Vinpack");
    formData.append("from_name", "Vinpack Website");

    try {
      const response = await fetch("https://api.web3forms.com/submit", {
        method: "POST",
        body: formData
      });

      const result = await response.json();

      if (result.success) {
        status.textContent = "✅ Inquiry sent successfully!";
        status.style.color = "green";
        form.reset();
      } else {
        status.textContent = "❌ Something went wrong. Please try again.";
        status.style.color = "red";
      }
    } catch (error) {
      status.textContent = "❌ Network error. Please try again later.";
      status.style.color = "red";
      console.error(error);
    }
  });
}

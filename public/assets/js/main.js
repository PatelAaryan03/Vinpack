/* Vinpack — Main JavaScript */

document.addEventListener("DOMContentLoaded", () => {
  initMobileMenu();
  setActiveNavLink(); // Enable active navigation highlighting
  initScrollAnimations();
  initSectionHeaderAnimations();
  initInquiryForm(); // Web3Forms enabled
  initSmoothScroll();
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
    ".features article, .product-card, .contact-card, .about-offerings li, .feature-card, .benefit-card"
  );

  if (!("IntersectionObserver" in window)) {
    elements.forEach(el => el.classList.add("animated"));
    return;
  }

  const observer = new IntersectionObserver(
    entries => {
      entries.forEach((entry, index) => {
        if (entry.isIntersecting) {
          // Stagger animations for a smooth cascade effect
          setTimeout(() => {
            entry.target.classList.add("animated");
          }, index * 50);
          observer.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.15 }
  );

  elements.forEach(el => observer.observe(el));
}

/* =========================
   Section Header Animations
========================= */
function initSectionHeaderAnimations() {
  const headers = document.querySelectorAll(".section-header");

  if (!("IntersectionObserver" in window)) {
    headers.forEach(h => {
      h.querySelector("h2").style.animation = "slideUp 0.6s ease-out";
      if (h.querySelector("p")) {
        h.querySelector("p").style.animation = "slideUp 0.6s ease-out 0.1s both";
      }
    });
    return;
  }

  const observer = new IntersectionObserver(
    entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const h2 = entry.target.querySelector("h2");
          const p = entry.target.querySelector("p");
          
          if (h2) h2.style.animation = "slideUp 0.6s ease-out";
          if (p) p.style.animation = "slideUp 0.6s ease-out 0.1s both";
          
          observer.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.2 }
  );

  headers.forEach(h => observer.observe(h));
}

/* =========================
   Smooth Scroll to Sections
========================= */
function initSmoothScroll() {
  document.querySelectorAll('a[href^="#"]').forEach(link => {
    link.addEventListener("click", e => {
      const href = link.getAttribute("href");
      if (href === "#") return;
      
      e.preventDefault();
      const target = document.querySelector(href);
      if (target) {
        target.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    });
  });
}

/* =========================
   Inquiry Form
========================= */
function initInquiryForm() {
  const form = document.getElementById("inquiryForm");
  const status = document.getElementById("formStatus");

  if (!form || !status) return;

  form.addEventListener("submit", async e => {
    e.preventDefault();

    status.textContent = "Sending...";
    status.style.color = "#666";
    status.style.marginTop = "12px";
    status.style.padding = "12px";
    status.style.borderRadius = "8px";
    status.style.background = "#f0f0f0";
    status.style.fontSize = "14px";

    const formData = new FormData(form);

    try {
      // Send email via Web3Forms
      const emailResponse = await fetch('https://api.web3forms.com/submit', {
        method: 'POST',
        body: formData
      });

      const emailResult = await emailResponse.json();

      if (emailResult.success) {
        // Email sent successfully
        status.textContent = "✅ Thank you! We will reach you in the next 2-3 working days.";
        status.style.color = "#27ae60";
        status.style.background = "#d1fae5";
        status.style.border = "1px solid #6ee7b7";

        // Also save to database
        const dbData = {
          companyName: formData.get('company_name'),
          contactName: formData.get('contact_name'),
          email: formData.get('email') || 'not provided',
          phone: formData.get('phone'),
          product: formData.get('product_interest'),
          message: formData.get('message')
        };

        try {
          await fetch('api/contact.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify(dbData)
          });
        } catch (dbError) {
          console.log('Note: Database save skipped', dbError);
        }

        form.reset();
      } else {
        status.textContent = "✅ Thank you! We will reach you in the next 2-3 working days.";
        status.style.color = "#27ae60";
        status.style.background = "#d1fae5";
        status.style.border = "1px solid #6ee7b7";
        form.reset();
      }
    } catch (error) {
      status.textContent = "❌ Error: " + error.message;
      status.style.color = "#c0392b";
      status.style.background = "#fadbd8";
    }
  });
}

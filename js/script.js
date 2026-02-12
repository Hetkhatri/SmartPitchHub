// Mobile Navigation Toggle
const hamburger = document.querySelector(".hamburger");
const navMenu = document.querySelector(".nav-menu");

if (hamburger && navMenu) {
  hamburger.addEventListener("click", () => {
    navMenu.classList.toggle("active");
    hamburger.classList.toggle("active");
  });
}

// Close mobile menu when clicking outside
document.addEventListener("click", (e) => {
  if (
    navMenu &&
    hamburger &&
    navMenu.classList.contains("active") &&
    !e.target.closest(".nav-menu") &&
    !e.target.closest(".hamburger")
  ) {
    navMenu.classList.remove("active");
    hamburger.classList.remove("active");
  }
});

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
  anchor.addEventListener("click", function (e) {
    e.preventDefault();
    const target = document.querySelector(this.getAttribute("href"));
    if (target) {
      target.scrollIntoView({
        behavior: "smooth",
        block: "start",
      });
    }
  });
});

// Dashboard Statistics Animation
function animateCounter(element, target, duration = 2000) {
  const start = 0;
  const increment = target / (duration / 16);
  let current = start;

  const timer = setInterval(() => {
    current += increment;
    if (current >= target) {
      element.textContent = Math.round(target).toLocaleString();
      clearInterval(timer);
    } else {
      element.textContent = Math.round(current).toLocaleString();
    }
  }, 16);
}

// Initialize counters when they come into view
const observer = new IntersectionObserver(
  (entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        const statNumber = entry.target.querySelector(".stat-number");
        if (statNumber) {
          const targetValue = parseInt(statNumber.getAttribute("data-target"));
          animateCounter(statNumber, targetValue);
          observer.unobserve(entry.target);
        }
      }
    });
  },
  { threshold: 0.5 }
);

// Observe all stat cards
document.querySelectorAll(".stat-card").forEach((card) => {
  observer.observe(card);
});

// Form Validation
function validateForm(form) {
  const inputs = form.querySelectorAll(
    "input[required], textarea[required], select[required]"
  );
  let isValid = true;

  inputs.forEach((input) => {
    if (!input.value.trim()) {
      input.style.borderColor = "#ef4444";
      isValid = false;
    } else {
      input.style.borderColor = "#e5e7eb";
    }
  });

  return isValid;
}

// Initialize form validation
document.querySelectorAll("form").forEach((form) => {
  form.addEventListener("submit", (e) => {
    if (!validateForm(form)) {
      e.preventDefault();
      alert("Please fill in all required fields.");
    }
  });
});

// Modal functionality
function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.style.display = "block";
    document.body.style.overflow = "hidden";
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.style.display = "none";
    document.body.style.overflow = "auto";
  }
}

// Close modal when clicking outside
document.addEventListener("click", (e) => {
  if (e.target.classList.contains("modal")) {
    closeModal(e.target.id);
  }
});

// Tab switching functionality for dashboards
function switchTab(tabId, tabContentId) {
  // Hide all tab contents
  document.querySelectorAll(".tab-content").forEach((content) => {
    content.style.display = "none";
  });

  // Remove active class from all tabs
  document.querySelectorAll(".tab-button").forEach((tab) => {
    tab.classList.remove("active");
  });

  // Show selected tab content and activate tab
  const content = document.getElementById(tabContentId);
  const tab = document.getElementById(tabId);

  if (content) content.style.display = "block";
  if (tab) tab.classList.add("active");
}

// Initialize tabs
document.addEventListener("DOMContentLoaded", () => {
  // Activate first tab by default
  const firstTab = document.querySelector(".tab-button");
  if (firstTab) {
    const tabContentId = firstTab.getAttribute("data-tab");
    switchTab(firstTab.id, tabContentId);
  }
});

// Search functionality
function filterTable(tableId, searchId) {
  const search = document.getElementById(searchId);
  const table = document.getElementById(tableId);

  if (search && table) {
    search.addEventListener("input", () => {
      const filter = search.value.toLowerCase();
      const rows = table.querySelectorAll("tbody tr");

      rows.forEach((row) => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? "" : "none";
      });
    });
  }
}

// Initialize search for all tables with search inputs
document.querySelectorAll("input[data-table]").forEach((input) => {
  const tableId = input.getAttribute("data-table");
  filterTable(tableId, input.id);
});

// Notification system
function showNotification(message, type = "info") {
  const notification = document.createElement("div");
  notification.className = `notification notification-${type}`;
  notification.innerHTML = `
        <span>${message}</span>
        <button onclick="this.parentElement.remove()">&times;</button>
    `;

  notification.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        padding: 1rem;
        background: ${
          type === "success"
            ? "#10b981"
            : type === "error"
            ? "#ef4444"
            : "#3b82f6"
        };
        color: white;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 1rem;
    `;

  document.body.appendChild(notification);

  setTimeout(() => {
    if (notification.parentElement) {
      notification.remove();
    }
  }, 5000);
}

// Export functions to global scope for use in HTML
window.openModal = openModal;
window.closeModal = closeModal;
window.switchTab = switchTab;
window.showNotification = showNotification;

// Initialize tooltips
document.addEventListener("DOMContentLoaded", () => {
  const tooltips = document.querySelectorAll("[data-tooltip]");

  tooltips.forEach((tooltip) => {
    tooltip.addEventListener("mouseenter", (e) => {
      const tooltipText = e.target.getAttribute("data-tooltip");
      const tooltipEl = document.createElement("div");
      tooltipEl.className = "tooltip";
      tooltipEl.textContent = tooltipText;
      tooltipEl.style.cssText = `
                position: absolute;
                background: #374151;
                color: white;
                padding: 0.5rem 1rem;
                border-radius: 4px;
                font-size: 0.875rem;
                z-index: 1000;
                white-space: nowrap;
            `;

      document.body.appendChild(tooltipEl);

      const rect = e.target.getBoundingClientRect();
      tooltipEl.style.top = rect.top - tooltipEl.offsetHeight - 10 + "px";
      tooltipEl.style.left =
        rect.left + rect.width / 2 - tooltipEl.offsetWidth / 2 + "px";

      e.target.addEventListener(
        "mouseleave",
        () => {
          tooltipEl.remove();
        },
        { once: true }
      );
    });
  });
});

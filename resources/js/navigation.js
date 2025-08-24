/**
 * Navigation functionality for header component
 * Handles mobile menu toggle, scroll effects, and animations
 */

class Navigation {
    constructor() {
        this.toggleBtn = null;
        this.navbar = null;
        this.nav = null;
        this.navInner = null;
        this.menuIcon = null;
        this.closeIcon = null;
        this.navLinks = null;
        this.buttonContainer = null;
        this.scrollPosition = 0;
        this.isTransparent = false;
        this.servicesDropdown = null;
        this.servicesButton = null;

        this.init();
    }

    init() {
        // Wait for DOM to be ready
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", () =>
                this.setupElements()
            );
        } else {
            this.setupElements();
        }
    }

    setupElements() {
        this.toggleBtn = document.getElementById("navbar-toggle");
        this.navbar = document.getElementById("navbar");
        this.nav = document.getElementById("main-nav");
        this.navInner = document.getElementById("nav-inner");
        this.menuIcon = document.getElementById("menu-icon");
        this.closeIcon = document.getElementById("close-icon");
        this.navLinks = document.querySelectorAll(".nav-link");
        this.buttonContainer = this.navbar?.querySelector(
            ".flex.flex-col.space-y-2"
        );
        this.servicesDropdown = document.getElementById("services-dropdown");
        this.servicesButton = document.getElementById(
            "services-dropdown-button"
        );
        this.megaMenuOverlay = null;

        // Check if navigation is transparent variant
        this.isTransparent = this.nav?.dataset.variant === "transparent";

        this.bindEvents();
    }

    bindEvents() {
        if (!this.toggleBtn || !this.navbar) return;

        // Toggle mobile nav
        this.toggleBtn.addEventListener("click", (e) => {
            e.stopPropagation();

            if (this.navbar.classList.contains("hidden")) {
                this.showMenu();
            } else {
                this.hideMenu();
            }
        });

        // Close menu when clicking outside
        document.addEventListener("click", (e) => {
            if (window.innerWidth < 768) {
                if (
                    !this.navbar.contains(e.target) &&
                    !this.toggleBtn.contains(e.target)
                ) {
                    this.navbar.classList.add("hidden");
                    this.navbar.classList.remove("flex");
                }
            }
        });

        // Scroll effect for navigation
        window.addEventListener("scroll", () => this.handleNavScroll());

        // Close menu on window resize/display change
        window.addEventListener("resize", () => this.handleWindowResize());

        // Handle escape key for mega menu
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape") {
                this.closeMegaMenu();
            }
        });
    }

    showMenu() {
        if (!this.navbar) return;

        this.navbar.classList.remove("hidden");
        this.navbar.classList.add("flex");

        // Store current scroll position
        this.scrollPosition =
            window.pageYOffset || document.documentElement.scrollTop;

        // Disable body scroll and preserve position
        document.body.style.overflow = "hidden";
        document.body.style.position = "fixed";
        document.body.style.top = `-${this.scrollPosition}px`;
        document.body.style.width = "100%";

        // Fade in background
        setTimeout(() => {
            this.navbar.classList.remove("opacity-0");
            this.navbar.classList.add("opacity-100");
        }, 10);

        // Animate menu items
        setTimeout(() => {
            this.navLinks.forEach((link) => {
                link.classList.remove("translate-y-4");
                link.classList.add("translate-y-0");
            });

            if (this.buttonContainer) {
                this.buttonContainer.classList.remove("translate-y-4");
                this.buttonContainer.classList.add("translate-y-0");
            }
        }, 100);

        // Toggle icons
        if (this.menuIcon && this.closeIcon) {
            this.menuIcon.classList.add("hidden");
            this.closeIcon.classList.remove("hidden");
        }
    }

    hideMenu() {
        if (!this.navbar) return;

        // Animate out menu items
        this.navLinks.forEach((link) => {
            link.classList.add("translate-y-4");
            link.classList.remove("translate-y-0");
        });

        // Re-enable body scroll and restore position
        document.body.style.overflow = "";
        document.body.style.position = "";
        document.body.style.top = "";
        document.body.style.width = "";

        // Restore scroll position
        window.scrollTo(0, this.scrollPosition);

        if (this.buttonContainer) {
            this.buttonContainer.classList.add("translate-y-4");
            this.buttonContainer.classList.remove("translate-y-0");
        }

        // Fade out background
        this.navbar.classList.add("opacity-0");
        this.navbar.classList.remove("opacity-100");

        // Hide completely after animation
        setTimeout(() => {
            this.navbar.classList.add("hidden");
            this.navbar.classList.remove("flex");
        }, 500);

        // Reset icons
        if (this.menuIcon && this.closeIcon) {
            this.menuIcon.classList.remove("hidden");
            this.closeIcon.classList.add("hidden");
        }
    }

    handleNavScroll() {
        if (!this.nav || !this.navInner) return;

        const isScrolled = window.scrollY > 50;

        // Apply shadow effect for all variants
        this.nav.classList.toggle("shadow-md", isScrolled);

        // Transparent variant specific effects
        if (this.isTransparent) {
            if (isScrolled) {
                this.nav.classList.add("bg-background!");
                this.navInner.classList.replace("py-4", "py-2");
                document.querySelectorAll(".nav-link").forEach((link) => {
                    link.classList.add("text-foreground!");
                });
            } else {
                this.nav.classList.remove("bg-background!");
                this.navInner.classList.replace("py-2", "py-4");
                document.querySelectorAll(".nav-link").forEach((link) => {
                    link.classList.remove("text-foreground!");
                });
            }
        }
    }

    handleWindowResize() {
        // Close mobile menu if it's open when window is resized
        if (!this.navbar) return;

        if (!this.navbar.classList.contains("hidden")) {
            this.hideMenu();
        }

        // Ensure proper state on desktop view
        if (window.innerWidth >= 768) {
            // Reset any mobile-specific body styles
            document.body.style.overflow = "";
            document.body.style.position = "";
            document.body.style.top = "";
            document.body.style.width = "";

            // Reset navbar to default desktop state (remove mobile-specific classes)
            this.navbar.classList.remove("flex", "opacity-0", "opacity-100");

            // Reset nav links to desktop state
            this.navLinks.forEach((link) => {
                link.classList.remove("translate-y-4", "translate-y-0");
            });

            // Reset button container
            if (this.buttonContainer) {
                this.buttonContainer.classList.remove(
                    "translate-y-4",
                    "translate-y-0"
                );
            }

            // Reset icons
            if (this.menuIcon && this.closeIcon) {
                this.menuIcon.classList.remove("hidden");
                this.closeIcon.classList.add("hidden");
            }

            // Close mega menu if open
            this.closeMegaMenu();
        }
    }
}

// Initialize navigation when script loads
new Navigation();

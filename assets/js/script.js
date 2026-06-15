/**
 * LibGerant — Premium Interactions Engine
 * Handles: Theme, Search Overlay, Sidebar, Scroll Animations, Smooth Scroll
 */
(function () {
    "use strict";

    document.addEventListener("DOMContentLoaded", function () {

        // ─── 1. Theme Management ───────────────────────────────
        const themeBtn = document.getElementById("themeToggle");
        const html = document.documentElement;
        const body = document.body;

        // Apply saved theme immediately
        const saved = localStorage.getItem("libgerant-theme") || "light";
        body.setAttribute("data-theme", saved);
        setIcon(saved);

        if (themeBtn) {
            themeBtn.addEventListener("click", function (e) {
                e.preventDefault();
                e.stopPropagation();
                const cur = body.getAttribute("data-theme") || "light";
                const next = cur === "light" ? "dark" : "light";
                body.setAttribute("data-theme", next);
                localStorage.setItem("libgerant-theme", next);
                setIcon(next);
            });
        }

        function setIcon(theme) {
            if (!themeBtn) return;
            const i = themeBtn.querySelector("i");
            if (!i) return;
            i.className = theme === "dark" ? "fas fa-sun" : "fas fa-moon";
        }

        // ─── 2. Search Overlay ─────────────────────────────────
        const openBtn = document.getElementById("openSearch");
        const closeBtn = document.getElementById("closeSearch");
        const overlay = document.getElementById("searchOverlay");

        function openSearch(e) {
            if (e) { e.preventDefault(); e.stopPropagation(); }
            if (!overlay) return;
            overlay.style.display = "flex";
            body.style.overflow = "hidden";
            // force reflow then animate
            void overlay.offsetWidth;
            overlay.classList.add("active");
            const input = overlay.querySelector("input");
            if (input) setTimeout(function() { input.focus(); }, 100);
        }

        function closeSearch(e) {
            if (e) { e.preventDefault(); e.stopPropagation(); }
            if (!overlay) return;
            overlay.classList.remove("active");
            body.style.overflow = "";
            setTimeout(function () { overlay.style.display = "none"; }, 400);
        }

        if (openBtn) openBtn.addEventListener("click", openSearch);
        if (closeBtn) closeBtn.addEventListener("click", closeSearch);

        // Click outside search modal to close
        if (overlay) {
            overlay.addEventListener("click", function (e) {
                if (e.target === overlay) closeSearch(e);
            });
        }

        // ─── 3. Keyboard Shortcuts ─────────────────────────────
        document.addEventListener("keydown", function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === "k") {
                e.preventDefault();
                openSearch();
            }
            if (e.key === "Escape" && overlay && overlay.classList.contains("active")) {
                closeSearch();
            }
        });

        // ─── 4. Navbar Scroll Effect ───────────────────────────
        var nav = document.querySelector(".navbar");
        if (nav) {
            function checkScroll() {
                if (window.scrollY > 40) {
                    nav.classList.add("scrolled");
                } else {
                    nav.classList.remove("scrolled");
                }
            }
            window.addEventListener("scroll", checkScroll, { passive: true });
            checkScroll();
        }

        // ─── 5. Sidebar Toggle (Dashboard pages) ──────────────
        var sidebarBtn = document.getElementById("sidebarToggle");
        var wrapper = document.getElementById("wrapper");
        if (sidebarBtn && wrapper) {
            sidebarBtn.addEventListener("click", function (e) {
                e.preventDefault();
                wrapper.classList.toggle("toggled");
            });
        }

        // ─── 6. Scroll Reveal Animations ───────────────────────
        if ("IntersectionObserver" in window) {
            var revealObs = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add("fade-in-visible");
                        // Stagger children
                        var items = entry.target.querySelectorAll(".stagger-item");
                        items.forEach(function (item, idx) {
                            setTimeout(function () { item.classList.add("visible"); }, idx * 120);
                        });
                        revealObs.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.08, rootMargin: "0px 0px -40px 0px" });

            document.querySelectorAll(".fade-in-hidden, section, .hero-section").forEach(function (el) {
                revealObs.observe(el);
            });
        }

        // ─── 7. Smooth Scroll for anchor links ─────────────────
        document.querySelectorAll('a[href^="#"]').forEach(function (link) {
            // Skip functional buttons
            if (link.id === "openSearch" || link.id === "themeToggle" || link.id === "sidebarToggle") return;
            // Skip Bootstrap components
            if (link.hasAttribute("data-bs-toggle")) return;

            var target = link.getAttribute("href");
            if (target && target.length > 1) {
                link.addEventListener("click", function (e) {
                    e.preventDefault();
                    var el = document.querySelector(target);
                    if (el) {
                        el.scrollIntoView({ behavior: "smooth", block: "start" });
                    }
                });
            }
        });

        // ─── 8. Download button handler (handled by cart system below) ─────

        // ─── 9. Toast System ────────────────────────────────────
        function showToast(title, message) {
            var container = document.getElementById("toast-container");
            if (!container) {
                container = document.createElement("div");
                container.id = "toast-container";
                container.style.cssText = "position:fixed;bottom:24px;right:24px;z-index:99999;display:flex;flex-direction:column;gap:12px;";
                document.body.appendChild(container);
            }
            var toast = document.createElement("div");
            toast.style.cssText = "background:var(--bg-card);border:1px solid var(--border-color);border-left:4px solid var(--accent);padding:16px 20px;border-radius:12px;box-shadow:var(--shadow-xl);max-width:380px;opacity:0;transform:translateX(40px);transition:all 0.4s ease;";
            toast.innerHTML = '<div style="font-weight:700;font-size:0.85rem;color:var(--accent);margin-bottom:4px;">' + title + '</div><div style="font-size:0.82rem;color:var(--text-body);">' + message + '</div>';
            container.appendChild(toast);

            // Animate in
            requestAnimationFrame(function () {
                toast.style.opacity = "1";
                toast.style.transform = "translateX(0)";
            });

            // Auto-remove
            setTimeout(function () {
                toast.style.opacity = "0";
                toast.style.transform = "translateX(40px)";
                setTimeout(function () { toast.remove(); }, 400);
            }, 4000);
        }

        // ─── 10. Buy button handler (handled by cart system below) ─────────

        // ─── 11. Newsletter form ─────────────────────────────────
        var nlForm = document.getElementById("newsletter-form");
        if (nlForm) {
            nlForm.addEventListener("submit", function (e) {
                e.preventDefault();
                var input = nlForm.querySelector("input");
                if (input && input.value) {
                    showToast("Inscription réussie !", "Vous recevrez nos recommandations à " + input.value);
                    input.value = "";
                }
            });
        }

        // ─── 12. Catalogue Filters ──────────────────────────────
        var filterBtns = document.querySelectorAll(".filter-btn");
        var bookItems = document.querySelectorAll(".book-item");

        filterBtns.forEach(function(btn) {
            btn.addEventListener("click", function() {
                filterBtns.forEach(function(b) { b.classList.remove("active"); });
                this.classList.add("active");
                var filter = this.getAttribute("data-filter");

                bookItems.forEach(function(item) {
                    if (filter === "all" || item.getAttribute("data-genre") === filter) {
                        item.style.display = "";
                        item.style.animation = "fadeSlideIn 0.35s ease both";
                    } else {
                        item.style.display = "none";
                    }
                });
            });
        });

        // ─── 13. Cart System ────────────────────────────────────
        var cart = [];
        var cartBadge = document.getElementById("cart-badge");
        var cartItemsList = document.getElementById("cart-items-list");
        var cartEmptyMsg = document.getElementById("cart-empty-msg");
        var cartFooter = document.getElementById("cart-footer");
        var cartTotal = document.getElementById("cart-total");
        var cartToggleBtn = document.getElementById("cartToggle");

        function updateCartUI() {
            if (!cartBadge) return;

            if (cart.length > 0) {
                cartBadge.textContent = cart.length;
                cartBadge.style.display = "";
            } else {
                cartBadge.style.display = "none";
            }

            if (!cartItemsList) return;

            // Rebuild list
            var existingItems = cartItemsList.querySelectorAll(".cart-item");
            existingItems.forEach(function(el) { el.remove(); });

            if (cart.length === 0) {
                if (cartEmptyMsg) cartEmptyMsg.style.display = "";
                if (cartFooter) cartFooter.style.display = "none";
            } else {
                if (cartEmptyMsg) cartEmptyMsg.style.display = "none";
                if (cartFooter) cartFooter.style.display = "";

                var total = 0;
                cart.forEach(function(item, idx) {
                    // Extraire la partie numérique en supprimant "FCFA", espaces (espace insécable inclus) et autres caractères
                    var priceNum = parseFloat(String(item.price).replace(/FCFA/gi, "").replace(/[\s  ]/g, "").replace(",", "."));
                    if (!isNaN(priceNum)) total += priceNum;

                    var el = document.createElement("div");
                    el.className = "cart-item";
                    el.innerHTML =
                        '<div class="cart-item-info">' +
                            '<div class="cart-item-title">' + item.title + '</div>' +
                            '<div class="cart-item-price">' + item.type + ' — ' + item.price + '</div>' +
                        '</div>' +
                        '<i class="fas fa-times cart-item-remove" data-idx="' + idx + '"></i>';
                    cartItemsList.appendChild(el);
                });

                // Formatage avec séparateur de milliers à l'française : "13 500 FCFA"
                if (cartTotal) {
                    cartTotal.textContent = Math.round(total).toLocaleString("fr-FR").replace(/ /g, " ") + " FCFA";
                }

                // Remove buttons
                cartItemsList.querySelectorAll(".cart-item-remove").forEach(function(btn) {
                    btn.addEventListener("click", function() {
                        var i = parseInt(this.getAttribute("data-idx"));
                        cart.splice(i, 1);
                        updateCartUI();
                    });
                });
            }
        }

        // Override buy button to add to cart
        document.querySelectorAll(".btn-buy").forEach(function(btn) {
            btn.addEventListener("click", function(e) {
                e.preventDefault();
                var title = btn.getAttribute("data-book") || "livre";
                var price = btn.getAttribute("data-price") || "—";
                var isbn = btn.getAttribute("data-isbn") || "";
                cart.push({ title: title, price: price, type: "Papier", isbn: isbn });
                updateCartUI();
                showToast("Ajouté au panier", "«" + title + "» (Papier — " + price + ")");
            });
        });

        // Override download to also track
        document.querySelectorAll(".btn-download").forEach(function(btn) {
            btn.addEventListener("click", function(e) {
                e.preventDefault();
                var title = btn.getAttribute("data-book") || "livre";
                var price = btn.getAttribute("data-price") || "—";
                var isbn = btn.getAttribute("data-isbn") || "";
                cart.push({ title: title, price: price, type: "E-book", isbn: isbn });
                updateCartUI();
                showToast("E-book ajouté", "«" + title + "» (" + price + ") prêt au téléchargement.");
            });
        });

        // Open cart offcanvas
        if (cartToggleBtn) {
            cartToggleBtn.addEventListener("click", function(e) {
                e.preventDefault();
                var offcanvas = document.getElementById("cartOffcanvas");
                if (offcanvas && window.bootstrap) {
                    var bsOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvas);
                    bsOffcanvas.show();
                }
            });
        }

        // Handle checkout button click
        var btnCheckout = document.getElementById("btn-checkout");
        if (btnCheckout) {
            btnCheckout.addEventListener("click", function(e) {
                e.preventDefault();
                if (cart.length === 0) return;

                // Close the cart offcanvas
                var offcanvasEl = document.getElementById("cartOffcanvas");
                if (offcanvasEl && window.bootstrap) {
                    var bsOffcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
                    if (bsOffcanvas) bsOffcanvas.hide();
                }

                // Populate checkout modal
                var summaryList = document.getElementById("checkout-summary-list");
                var summaryTotal = document.getElementById("checkout-summary-total");

                if (summaryList) {
                    summaryList.innerHTML = "";
                    var total = 0;
                    cart.forEach(function(item) {
                        var priceNum = parseFloat(String(item.price).replace(/FCFA/gi, "").replace(/[\s  ]/g, "").replace(",", "."));
                        if (!isNaN(priceNum)) total += priceNum;

                        var itemEl = document.createElement("div");
                        itemEl.className = "d-flex justify-content-between align-items-center small py-1 border-bottom border-light";
                        itemEl.innerHTML = 
                            '<div>' +
                                '<span style="font-weight:600; color:var(--text-heading)">' + item.title + '</span>' +
                                '<span class="badge bg-light text-dark ms-2" style="font-size:0.7rem">' + item.type + '</span>' +
                            '</div>' +
                            '<span style="font-weight:700; color:var(--text-heading)">' + item.price + '</span>';
                        summaryList.appendChild(itemEl);
                    });

                    if (summaryTotal) {
                        summaryTotal.textContent = Math.round(total).toLocaleString("fr-FR").replace(/ /g, " ") + " FCFA";
                    }
                }

                // Show checkout modal
                var checkoutModalEl = document.getElementById("checkoutModal");
                if (checkoutModalEl && window.bootstrap) {
                    var bsModal = bootstrap.Modal.getOrCreateInstance(checkoutModalEl);
                    bsModal.show();
                }
            });
        }

        // Handle checkout form submission
        var checkoutForm = document.getElementById("checkout-form");
        if (checkoutForm) {
            checkoutForm.addEventListener("submit", function(e) {
                e.preventDefault();
                if (cart.length === 0) return;

                // Gather form data
                var formData = new FormData(checkoutForm);
                var payload = {
                    cart_json: JSON.stringify(cart),
                    mode_reglement: formData.get("mode_reglement"),
                    nom: formData.get("nom") || "",
                    email: formData.get("email") || "",
                    telephone: formData.get("telephone") || ""
                };

                // Disable submit button during request
                var submitBtn = checkoutForm.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Traitement...';
                }

                // Detect app base path dynamically
                var basePath = "";
                if (window.LibGerantConfig && window.LibGerantConfig.appBasePath !== undefined) {
                    basePath = window.LibGerantConfig.appBasePath;
                }

                fetch(basePath + 'api/checkout.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.success) {
                        showToast("Commande confirmée !", data.message);
                        
                        // Clear cart
                        cart = [];
                        updateCartUI();

                        // Close modal
                        var checkoutModalEl = document.getElementById("checkoutModal");
                        if (checkoutModalEl && window.bootstrap) {
                            var bsModal = bootstrap.Modal.getInstance(checkoutModalEl);
                            if (bsModal) bsModal.hide();
                        }

                        // Reset form
                        checkoutForm.reset();

                        // Redirect based on user role or display receipt link
                        setTimeout(function() {
                            if (window.LibGerantConfig && window.LibGerantConfig.isLoggedIn) {
                                if (window.LibGerantConfig.userRole === 'adherent') {
                                    window.location.href = basePath + 'pages/dashboard-adherent.php';
                                } else if (window.LibGerantConfig.userRole === 'admin' || window.LibGerantConfig.userRole === 'libraire') {
                                    window.location.href = basePath + 'api/receipt.php?id=' + data.id_vente;
                                } else {
                                    window.location.href = basePath;
                                }
                            } else {
                                // For guest, direct to receipt page
                                window.location.href = basePath + 'api/receipt.php?id=' + data.id_vente;
                            }
                        }, 1500);

                    } else {
                        showToast("Erreur", data.message || "Impossible de finaliser la commande.");
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="fas fa-check-circle me-1"></i>Confirmer & Régler';
                        }
                    }
                })
                .catch(function(err) {
                    console.error("Checkout Error:", err);
                    showToast("Erreur de connexion", "Impossible de contacter le serveur.");
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-check-circle me-1"></i>Confirmer & Régler';
                    }
                });
            });
        }

        // ─── 14b. SVG Cover Fallback ───────────────────────────
        function makeCoverSVG(opts) {
            var bg = opts.bg || "#1e293b";
            var fg = opts.fg || "#fbbf24";
            var text = opts.text || "#ffffff";
            var esc = function(s){return String(s||"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;");};
            var title = esc(opts.title);
            var author = esc(opts.author);
            var pub = esc(opts.pub).toUpperCase();
            var svg =
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 560">' +
                    '<rect width="400" height="560" fill="' + bg + '"/>' +
                    '<rect x="0" y="0" width="400" height="42" fill="' + fg + '"/>' +
                    '<rect x="0" y="518" width="400" height="42" fill="' + fg + '"/>' +
                    '<text x="200" y="28" font-family="Georgia,serif" font-size="13" fill="' + bg + '" text-anchor="middle" font-weight="700" letter-spacing="3">' + pub + '</text>' +
                    '<foreignObject x="22" y="170" width="356" height="180">' +
                        '<div xmlns="http://www.w3.org/1999/xhtml" style="font-family:Georgia,Cambria,serif;color:' + text + ';font-weight:700;font-size:28px;text-align:center;line-height:1.18;letter-spacing:-0.4px;text-shadow:0 2px 4px rgba(0,0,0,.35)">' + title + '</div>' +
                    '</foreignObject>' +
                    '<line x1="130" y1="400" x2="270" y2="400" stroke="' + fg + '" stroke-width="2"/>' +
                    '<text x="200" y="442" font-family="Helvetica,Arial,sans-serif" font-size="17" fill="' + text + '" text-anchor="middle" font-style="italic">' + author + '</text>' +
                '</svg>';
            return "data:image/svg+xml;charset=utf-8," + encodeURIComponent(svg);
        }

        function attachCoverFallback(img) {
            var altQueue = (img.dataset.altIsbn || "").split(",")
                .map(function(s){return s.trim();}).filter(Boolean);
            var fallbackUsed = false;

            function applyFallback() {
                if (fallbackUsed) return;
                fallbackUsed = true;
                img.removeEventListener("error", onErr);
                img.removeEventListener("load", onLoad);
                img.src = makeCoverSVG({
                    title: img.dataset.coverTitle,
                    author: img.dataset.coverAuthor,
                    bg: img.dataset.coverBg,
                    fg: img.dataset.coverFg,
                    text: img.dataset.coverText,
                    pub: img.dataset.coverPub
                });
            }

            function tryNextOrFallback() {
                if (altQueue.length) {
                    var nextIsbn = altQueue.shift();
                    img.src = "https://covers.openlibrary.org/b/isbn/" + nextIsbn + "-L.jpg";
                    return;
                }
                applyFallback();
            }

            function onErr() { tryNextOrFallback(); }

            function onLoad() {
                // Open Library returns a 1x1 (or very small) placeholder when no cover exists.
                if (img.naturalWidth < 50 || img.naturalHeight < 80) {
                    tryNextOrFallback();
                }
            }

            img.addEventListener("error", onErr);
            img.addEventListener("load", onLoad);

            // Already loaded/failed before listener attached?
            if (!img.getAttribute("src")) {
                applyFallback();
            } else if (img.complete) {
                if (img.naturalWidth === 0) onErr();
                else if (img.naturalWidth < 50 || img.naturalHeight < 80) onLoad();
            }
        }

        document.querySelectorAll("img[data-cover-title]").forEach(attachCoverFallback);

        // ─── 14d. Dynamic Genre Counts ─────────────────────────
        (function updateGenreCounts() {
            var counts = {};
            document.querySelectorAll(".book-item[data-genre]").forEach(function(it) {
                var g = it.getAttribute("data-genre");
                counts[g] = (counts[g] || 0) + 1;
            });
            document.querySelectorAll(".genre-card[data-filter]").forEach(function(card) {
                var f = card.getAttribute("data-filter");
                var n = counts[f] || 0;
                var p = card.querySelector("p");
                if (p) p.textContent = n + " titre" + (n > 1 ? "s" : "") + " disponible" + (n > 1 ? "s" : "");
            });
        })();

        // ─── 14c. Clickable Genre Cards ────────────────────────
        document.querySelectorAll(".genre-card[data-filter]").forEach(function(card) {
            card.style.cursor = "pointer";
            card.addEventListener("click", function() {
                var f = this.getAttribute("data-filter");
                var btn = document.querySelector('.filter-btn[data-filter="' + f + '"]');
                if (btn) btn.click();
                var cat = document.getElementById("catalogue");
                if (cat) cat.scrollIntoView({ behavior: "smooth", block: "start" });
            });
        });

        // ─── 14. Back to Top ────────────────────────────────────
        var backToTopBtn = document.getElementById("backToTop");
        if (backToTopBtn) {
            window.addEventListener("scroll", function() {
                if (window.scrollY > 400) {
                    backToTopBtn.classList.add("visible");
                } else {
                    backToTopBtn.classList.remove("visible");
                }
            }, { passive: true });

            backToTopBtn.addEventListener("click", function() {
                window.scrollTo({ top: 0, behavior: "smooth" });
            });
        }

        console.log("✅ LibGerant Premium Engine loaded.");
    });
})();

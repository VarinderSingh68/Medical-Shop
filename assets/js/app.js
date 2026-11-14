/**
 * HomeMed - Interactive Micro-Interactions & Animation Engine
 */

document.addEventListener('DOMContentLoaded', () => {
    initLiveSearch();
    initAjaxCart();
    initPrescriptionPreview();
    createToastContainer();
});

/* ==========================================================================
   1. TOAST NOTIFICATION ENGINE
   ========================================================================== */
function createToastContainer() {
    if (!document.getElementById('toast-container')) {
        const container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }
}

function showToast(title, message, icon = 'fa-check') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = 'toast-message';
    toast.innerHTML = `
        <div class="toast-icon">
            <i class="fa-solid ${icon}"></i>
        </div>
        <div>
            <strong style="display:block; font-size:0.92rem; color:var(--text-heading);">${title}</strong>
            <span style="font-size:0.82rem; color:var(--text-muted);">${message}</span>
        </div>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('hide');
        setTimeout(() => toast.remove(), 400);
    }, 3500);
}

/* ==========================================================================
   2. INSTANT LIVE SEARCH AUTOCOMPLETE WITH SMOOTH POPUP
   ========================================================================== */
function initLiveSearch() {
    const searchInput = document.getElementById('hero-search-input');
    const searchWrapper = document.querySelector('.hero-search-wrapper');

    if (!searchInput || !searchWrapper) return;

    // Create dropdown container
    let dropdown = document.createElement('div');
    dropdown.className = 'search-autocomplete-dropdown';
    dropdown.id = 'search-dropdown';
    searchWrapper.appendChild(dropdown);

    let debounceTimer;

    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const query = this.value.trim();

        if (query.length < 2) {
            dropdown.style.display = 'none';
            return;
        }

        debounceTimer = setTimeout(() => {
            const searchEndpoint = window.location.pathname.includes('/frontend/') ? 'ajax/search.php' : 'frontend/ajax/search.php';
            fetch(`${searchEndpoint}?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.results && data.results.length > 0) {
                        let html = '';
                        data.results.forEach(m => {
                            html += `
                                <a href="${m.url}" class="search-item-row">
                                    <div>
                                        <strong style="color:var(--text-heading); font-size:0.95rem;">${m.name}</strong>
                                        <span style="font-size:0.78rem; color:var(--text-muted); margin-left:6px;">(${m.dosage_form} &bull; ${m.strength})</span>
                                        <div style="font-size:0.8rem; color:var(--primary-blue);">${m.category}</div>
                                    </div>
                                    <div style="text-align:right;">
                                        <strong style="font-size:1rem; color:var(--text-heading);">${m.price}</strong>
                                        <span style="display:block; font-size:0.75rem; color:var(--brand-teal); font-weight:700;">In Stock</span>
                                    </div>
                                </a>
                            `;
                        });
                        dropdown.innerHTML = html;
                        dropdown.style.display = 'block';
                    } else {
                        dropdown.innerHTML = `
                            <div style="padding:12px; text-align:center; color:var(--text-muted); font-size:0.85rem;">
                                No exact match found. <a href="upload-prescription.php" style="color:var(--brand-teal); font-weight:700;">Upload prescription</a> to request this medicine.
                            </div>
                        `;
                        dropdown.style.display = 'block';
                    }
                })
                .catch(() => {
                    dropdown.style.display = 'none';
                });
        }, 220);
    });

    // Close on click outside
    document.addEventListener('click', (e) => {
        if (!searchWrapper.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
}

/* ==========================================================================
   3. SEAMLESS AJAX CART WITH BUTTON MORPHING & COUNTER BOUNCE
   ========================================================================= */
function initAjaxCart() {
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-add-teal');
        if (!btn) return;

        const form = btn.closest('form');
        if (!form) return;

        e.preventDefault();

        // Button Morphing Animation
        const originalContent = btn.innerHTML;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        btn.disabled = true;

        const formData = new FormData(form);
        formData.append('ajax', '1');

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Morph to Checkmark
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Added';
                btn.style.background = '#059669';

                // Navbar Cart Count Update with Pop Bounce
                const cartNavBtn = document.querySelector('.btn-cart-nav');
                if (cartNavBtn) {
                    cartNavBtn.innerHTML = `<i class="fa-solid fa-bag-shopping"></i> <span>Cart (${data.cartCount} items)</span>`;
                    cartNavBtn.classList.remove('cart-bounce-trigger');
                    void cartNavBtn.offsetWidth; // Trigger reflow
                    cartNavBtn.classList.add('cart-bounce-trigger');
                }

                // Trigger Toast Slide
                showToast('Added to Home Delivery Cart', `${data.name} (${data.strength}) &bull; ${data.price}`);

                setTimeout(() => {
                    btn.innerHTML = originalContent;
                    btn.style.background = '';
                    btn.disabled = false;
                }, 1600);
            }
        })
        .catch(() => {
            form.submit(); // Graceful fallback
        });
    });
}

/* ==========================================================================
   4. PRESCRIPTION FILE UPLOAD PREVIEW & DRAG-AND-DROP
   ========================================================================== */
function initPrescriptionPreview() {
    const rxInput = document.getElementById('prescription-input');
    const rxPreview = document.getElementById('prescription-preview');
    const rxFilename = document.getElementById('rx-filename');

    if (!rxInput) return;

    const dropzone = rxInput.closest('.rx-dropzone') || rxInput.parentElement;
    if (dropzone) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('dragover');
            }, false);
        });

        dropzone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files && files.length > 0) {
                rxInput.files = files;
                const event = new Event('change');
                rxInput.dispatchEvent(event);
            }
        });
    }

    rxInput.addEventListener('change', function () {
        if (this.files && this.files[0]) {
            const file = this.files[0];
            if (rxFilename) {
                rxFilename.innerHTML = `
                    <div style="background:#dcfce7; border:1px solid #86efac; color:#15803d; padding:8px 14px; border-radius:8px; display:inline-flex; align-items:center; gap:8px; font-size:0.85rem; animation:scaleUp 0.3s ease;">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>${file.name} (${(file.size / (1024 * 1024)).toFixed(2)} MB)</span>
                    </div>
                `;
            }

            if (rxPreview && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    rxPreview.src = e.target.result;
                    rxPreview.style.display = 'block';
                    rxPreview.style.animation = 'scaleUp 0.3s cubic-bezier(0.16, 1, 0.3, 1)';
                };
                reader.readAsDataURL(file);
            } else if (rxPreview) {
                rxPreview.style.display = 'none';
            }
        }
    });
}

// Interactive Radio Card Selection Animation in Checkout
document.addEventListener('change', (e) => {
    if (e.target.name === 'payment_method') {
        document.querySelectorAll('.payment-card-label').forEach(label => {
            label.classList.toggle('selected', label.contains(e.target));
        });
    }
});

/* ==========================================================================
   5. DELIVERY LOCATION SELECTOR MODAL
   ========================================================================== */
window.changeDeliveryLocation = function() {
    let modal = document.getElementById('location-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'location-modal';
        modal.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(13,34,58,0.6); backdrop-filter:blur(4px); z-index:10001; display:flex; align-items:center; justify-content:center; animation:fadeIn 0.25s ease;';
        
        modal.innerHTML = `
            <div style="background:#fff; border-radius:20px; padding:32px; width:100%; max-width:440px; box-shadow:var(--shadow-float); animation:scaleUp 0.3s cubic-bezier(0.16,1,0.3,1);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                    <h3 style="font-size:1.2rem; font-weight:800; color:var(--text-heading); margin:0;">
                        <i class="fa-solid fa-location-dot" style="color:var(--brand-teal); margin-right:6px;"></i> Choose Delivery Location
                    </h3>
                    <button onclick="document.getElementById('location-modal').style.display='none'" style="background:none; border:none; color:#94a3b8; font-size:1.2rem; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:18px;">Select your city to check home delivery timings and licensed pharmacy availability.</p>
                
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:20px;">
                    ${['Mumbai, India', 'Delhi NCR, India', 'Bangalore, India', 'Hyderabad, India', 'Chennai, India', 'Kolkata, India', 'Pune, India', 'Ahmedabad, India'].map(c => `
                        <button onclick="selectCity('${c}')" style="background:#f8fafc; border:1px solid #e2e8f0; padding:10px; border-radius:10px; font-weight:700; font-size:0.85rem; color:var(--text-heading); cursor:pointer; text-align:left; transition:all 0.2s;" onmouseover="this.style.background='var(--brand-teal-light)'; this.style.borderColor='var(--brand-teal)';" onmouseout="this.style.background='#f8fafc'; this.style.borderColor='#e2e8f0';">
                            <i class="fa-solid fa-city" style="color:var(--brand-teal); margin-right:6px; font-size:0.8rem;"></i>${c.split(',')[0]}
                        </button>
                    `).join('')}
                </div>
                
                <div style="position:relative;">
                    <input type="text" id="custom-city-input" placeholder="Or enter PIN code or other city..." style="width:100%; padding:12px 14px; border:1px solid #cbd5e1; border-radius:10px; font-size:0.9rem; outline:none; font-family:inherit;">
                    <button onclick="selectCustomCity()" style="position:absolute; right:6px; top:6px; bottom:6px; background:var(--brand-teal); color:#fff; border:none; padding:0 16px; border-radius:8px; font-weight:700; font-size:0.85rem; cursor:pointer;">Set</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    modal.style.display = 'flex';
};

window.selectCity = function(city) {
    document.getElementById('user-location').innerText = city;
    document.getElementById('location-modal').style.display = 'none';
    showToast('Delivery Location Set', `Delivering to ${city}`);
};

window.selectCustomCity = function() {
    const input = document.getElementById('custom-city-input');
    if (input && input.value.trim() !== '') {
        const city = input.value.trim();
        document.getElementById('user-location').innerText = city;
        document.getElementById('location-modal').style.display = 'none';
        showToast('Delivery Location Set', `Delivering to ${city}`);
    }
};

/* ==========================================================================
   6. RESPONSIVE MOBILE DRAWER TOGGLE
   ========================================================================== */
window.toggleMobileMenu = function() {
    const drawer = document.getElementById('mobile-drawer');
    const icon = document.getElementById('mobile-menu-icon');
    if (!drawer) return;
    drawer.classList.toggle('open');
    if (icon) {
        if (drawer.classList.contains('open')) {
            icon.className = 'fa-solid fa-xmark';
        } else {
            icon.className = 'fa-solid fa-bars';
        }
    }
};

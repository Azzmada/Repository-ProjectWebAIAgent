/* ============================================================
   TerbangLebih — shared front-end behaviour
   ============================================================ */
(function () {
    'use strict';

    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
    const csrf = () => (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    const apiBase = (window.TBL && window.TBL.base) || '';

    /* ------------------------------------------------------------
       Toasts
       ------------------------------------------------------------ */
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const colors = {
            success: 'bg-green-600',
            error: 'bg-red-600',
            info: 'bg-primary'
        };
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            info: 'fa-info-circle'
        };

        const toast = document.createElement('div');
        toast.className = `toast ${colors[type] || colors.info} text-white px-5 py-3 rounded-xl shadow-lg flex items-center gap-2 min-w-[280px] max-w-md`;
        const icon = document.createElement('i');
        icon.className = `fa-solid ${icons[type] || icons.info}`;
        const span = document.createElement('span');
        span.className = 'flex-1';
        span.textContent = message;
        toast.append(icon, span);
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity .3s';
            setTimeout(() => toast.remove(), 300);
        }, 3200);
    }
    window.showToast = showToast;

    function renderFlashMessages() {
        const el = document.getElementById('flash-data');
        if (!el) return;
        try {
            const items = JSON.parse(el.textContent || '[]');
            items.forEach((item, index) => {
                setTimeout(() => showToast(item.message, item.type || 'info'), index * 250);
            });
        } catch (e) {
            /* ignore malformed payload */
        }
    }

    function copyCode(code) {
        const fallback = () => {
            const ta = document.createElement('textarea');
            ta.value = code;
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); } catch (e) {}
            ta.remove();
            showToast(`Kode promo ${code} berhasil disalin!`, 'success');
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(code).then(
                () => showToast(`Kode promo ${code} berhasil disalin!`, 'success'),
                fallback
            );
        } else {
            fallback();
        }
    }
    window.copyCode = copyCode;

    /* ------------------------------------------------------------
       Navbar / menus
       ------------------------------------------------------------ */
    function initNavbar() {
        const navbar = document.getElementById('navbar');
        if (navbar) {
            const onScroll = () => {
                if (window.scrollY > 50) {
                    navbar.classList.add('shadow-lg', 'bg-white/95', 'backdrop-blur-md', 'py-3');
                    navbar.classList.remove('py-4');
                } else {
                    navbar.classList.remove('shadow-lg', 'bg-white/95', 'backdrop-blur-md', 'py-3');
                    navbar.classList.add('py-4');
                }
            };
            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();
        }

        const mobileBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        if (mobileBtn && mobileMenu) {
            mobileBtn.addEventListener('click', () => mobileMenu.classList.toggle('hidden'));
        }

        const userBtn = document.getElementById('user-menu-btn');
        const userMenu = document.getElementById('user-menu');
        if (userBtn && userMenu) {
            userBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                userMenu.classList.toggle('hidden');
            });
        }
        document.addEventListener('click', (e) => {
            if (userMenu && !userMenu.classList.contains('hidden') && !e.target.closest('#user-menu-wrap')) {
                userMenu.classList.add('hidden');
            }
            $$('.autocomplete-list').forEach((list) => {
                if (!e.target.closest('.city-autocomplete-wrap')) list.remove();
            });
        });
    }

    /* ------------------------------------------------------------
       Counters
       ------------------------------------------------------------ */
    function animateCounter(el) {
        const target = parseInt(el.dataset.target || '0', 10);
        const isDecimal = el.dataset.decimal === 'true';
        const duration = 1500;
        const startTime = performance.now();

        function update(t) {
            const progress = Math.min((t - startTime) / duration, 1);
            const val = Math.floor(target * progress);
            el.textContent = isDecimal ? (val / 10).toFixed(1) : val;
            if (progress < 1) requestAnimationFrame(update);
            else el.textContent = isDecimal ? (target / 10).toFixed(1) : target;
        }
        requestAnimationFrame(update);
    }

    function initCounters() {
        const counters = $$('.counter');
        if (!counters.length) return;
        if (!('IntersectionObserver' in window)) {
            counters.forEach(animateCounter);
            return;
        }
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    animateCounter(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.4 });
        counters.forEach((c) => {
            c.textContent = '0';
            observer.observe(c);
        });
    }

    /* ------------------------------------------------------------
       Testimonials
       ------------------------------------------------------------ */
    let testimonialIndex = 0;
    let testimonialTimer = null;

    function goToTestimonial(index) {
        const slides = $$('.testimonial-slide');
        const dots = $$('.testimonial-dot');
        if (!slides.length) return;
        index = ((index % slides.length) + slides.length) % slides.length;
        slides.forEach((slide, i) => {
            slide.classList.toggle('opacity-100', i === index);
            slide.classList.toggle('opacity-0', i !== index);
            slide.classList.toggle('pointer-events-none', i !== index);
        });
        dots.forEach((dot, i) => {
            dot.classList.toggle('bg-primary', i === index);
            dot.classList.toggle('w-6', i === index);
            dot.classList.toggle('bg-gray-300', i !== index);
        });
        testimonialIndex = index;
    }
    window.goToTestimonial = goToTestimonial;

    function initTestimonials() {
        const slides = $$('.testimonial-slide');
        if (!slides.length) return;
        goToTestimonial(0);
        if (slides.length > 1) {
            clearInterval(testimonialTimer);
            testimonialTimer = setInterval(() => goToTestimonial(testimonialIndex + 1), 6000);
        }
    }

    /* ------------------------------------------------------------
       FAQ accordion
       ------------------------------------------------------------ */
    function initFAQ() {
        const items = $$('.faq-item');
        items.forEach((item) => {
            const header = item.querySelector('.faq-header');
            if (!header) return;
            header.addEventListener('click', () => {
                items.forEach((other) => { if (other !== item) other.classList.remove('active'); });
                item.classList.toggle('active');
            });
        });
    }

    /* ------------------------------------------------------------
       Modals
       ------------------------------------------------------------ */
    function openModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.remove('hidden');
        requestAnimationFrame(() => {
            modal.classList.remove('opacity-0');
            const box = modal.querySelector(':scope > div');
            if (box) box.classList.remove('scale-95');
        });
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('opacity-0');
        const box = modal.querySelector(':scope > div');
        if (box) box.classList.add('scale-95');
        setTimeout(() => modal.classList.add('hidden'), 300);
    }
    window.openModal = openModal;
    window.closeModal = closeModal;

    function initModalBackdrops() {
        document.addEventListener('click', (e) => {
            if (e.target.classList && e.target.classList.contains('fixed') && e.target.id && e.target.id.startsWith('modal-')) {
                closeModal(e.target.id);
            }
        });
    }

    /* ------------------------------------------------------------
       Flight search widget
       ------------------------------------------------------------ */
    const passengerCounts = { adult: 1, child: 0, infant: 0 };

    function updatePassenger(type, change) {
        const limits = { adult: [1, 9], child: [0, 9], infant: [0, 9] };
        const next = passengerCounts[type] + change;
        if (next < limits[type][0] || next > limits[type][1]) return;

        if (type === 'adult') {
            const max = 9 - passengerCounts.child - passengerCounts.infant;
            if (change > 0 && passengerCounts.adult >= max) return;
        }

        passengerCounts[type] = next;
        const el = document.getElementById(`count-${type}`);
        if (el) el.textContent = String(next);
    }
    window.updatePassenger = updatePassenger;

    function savePassenger() {
        const classSelect = document.getElementById('select-class');
        const selectedClass = classSelect ? classSelect.value : 'Ekonomi';
        let label = `${passengerCounts.adult} Dewasa`;
        if (passengerCounts.child > 0) label += `, ${passengerCounts.child} Anak`;
        if (passengerCounts.infant > 0) label += `, ${passengerCounts.infant} Bayi`;
        label += `, ${selectedClass}`;

        const labelEl = document.getElementById('passenger-label');
        if (labelEl) labelEl.textContent = label;

        ['adults', 'children', 'infants'].forEach((name) => {
            const hidden = document.getElementById('pax-' + name);
            if (hidden) hidden.value = String(passengerCounts[name]);
        });
        const cabinHidden = document.getElementById('pax-class');
        if (cabinHidden) cabinHidden.value = selectedClass;

        closeModal('modal-passenger');
        showToast('Data penumpang diperbarui', 'success');
    }
    window.savePassenger = savePassenger;

    function openPassengerModal() {
        openModal('modal-passenger');
    }
    window.openPassengerModal = openPassengerModal;

    function initSearchTabs() {
        const tabs = $$('.tab-btn');
        const tripInput = document.getElementById('trip-type');
        const singleFields = document.getElementById('single-route-fields');
        const returnWrap = document.getElementById('return-date-container');
        const multiFields = document.getElementById('multi-city-fields');
        const swapBtn = document.getElementById('swap-cities');

        function setMode(mode) {
            tabs.forEach((tab) => {
                const active = tab.dataset.target === mode;
                tab.classList.toggle('text-primary', active);
                tab.classList.toggle('border-primary', active);
                tab.classList.toggle('text-gray-500', !active);
                tab.classList.toggle('border-transparent', !active);
            });
            if (tripInput) tripInput.value = mode;
            if (singleFields) singleFields.classList.toggle('hidden', mode === 'multi-city');
            if (multiFields) multiFields.classList.toggle('hidden', mode !== 'multi-city');
            if (returnWrap) returnWrap.classList.toggle('hidden', mode !== 'round-trip');
            const returnInput = document.getElementById('input-date-return');
            if (returnInput) returnInput.required = mode === 'round-trip';
            if (swapBtn) swapBtn.classList.toggle('hidden', mode === 'multi-city');
        }

        tabs.forEach((tab) => tab.addEventListener('click', () => setMode(tab.dataset.target)));
        if (tripInput && tripInput.value) setMode(tripInput.value);
    }

    function initMultiCity() {
        const box = document.getElementById('multi-legs');
        const addBtn = document.getElementById('add-leg-btn');
        if (!box || !addBtn) return;

        const renumber = () => {
            $$('.leg-row', box).forEach((row, i) => {
                const label = row.querySelector('.leg-label');
                if (label) label.textContent = `Rute ${i + 1}`;
                row.querySelectorAll('input').forEach((input) => {
                    const field = input.dataset.field;
                    if (field) input.name = `legs[${i}][${field}]`;
                });
                const remove = row.querySelector('.remove-leg');
                if (remove) remove.classList.toggle('hidden', $$('.leg-row', box).length <= 2);
            });
        };

        addBtn.addEventListener('click', () => {
            const count = $$('.leg-row', box).length;
            if (count >= 5) {
                showToast('Maksimal 5 rute dalam satu pencarian.', 'info');
                return;
            }
            const row = document.createElement('div');
            row.className = 'leg-row grid grid-cols-1 md:grid-cols-12 gap-3 items-end bg-gray-50 p-3 rounded-xl border border-gray-200';
            row.innerHTML = `
                <div class="md:col-span-2"><label class="block text-xs font-semibold text-gray-500 mb-1 leg-label">Rute ${count + 1}</label>
                    <button type="button" class="remove-leg text-xs text-red-500 hover:underline hidden"><i class="fa-solid fa-trash-can mr-1"></i>Hapus</button>
                </div>
                <div class="md:col-span-4 city-autocomplete-wrap relative">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Dari</label>
                    <input type="text" data-field="from" name="legs[${count}][from]" required placeholder="Kota Asal" class="city-autocomplete w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div class="md:col-span-4 city-autocomplete-wrap relative">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Ke</label>
                    <input type="text" data-field="to" name="legs[${count}][to]" required placeholder="Kota Tujuan" class="city-autocomplete w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Tanggal</label>
                    <input type="date" data-field="date" name="legs[${count}][date]" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary outline-none">
                </div>`;
            box.appendChild(row);
            renumber();
            attachDateConstraints();
        });

        box.addEventListener('click', (e) => {
            const remove = e.target.closest('.remove-leg');
            if (!remove) return;
            const row = remove.closest('.leg-row');
            if (row && $$('.leg-row', box).length > 2) {
                row.remove();
                renumber();
            }
        });

        renumber();
    }

    function swapCities() {
        const from = document.getElementById('input-from');
        const to = document.getElementById('input-to');
        if (!from || !to) return;
        [from.value, to.value] = [to.value, from.value];
        showToast('Kota berhasil ditukar!', 'info');
    }
    window.swapCities = swapCities;

    function initSearchValidation() {
        const form = document.getElementById('search-form');
        if (!form) return;

        form.addEventListener('submit', (e) => {
            const trip = (document.getElementById('trip-type') || {}).value || 'round-trip';
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const fromInput = document.getElementById('input-from');
            const toInput = document.getElementById('input-to');
            if (trip !== 'multi-city' && fromInput && toInput) {
                if (!fromInput.value.trim() || !toInput.value.trim()) {
                    e.preventDefault();
                    showToast('Lengkapi kota asal dan tujuan.', 'error');
                    return;
                }
                if (fromInput.value.trim().toLowerCase() === toInput.value.trim().toLowerCase()) {
                    e.preventDefault();
                    showToast('Kota asal dan tujuan tidak boleh sama.', 'error');
                    return;
                }
            }

            const depart = document.getElementById('input-date-depart');
            if (trip !== 'multi-city' && depart) {
                if (!depart.value) {
                    e.preventDefault();
                    showToast('Pilih tanggal keberangkatan.', 'error');
                    return;
                }
                if (new Date(depart.value + 'T00:00:00') < today) {
                    e.preventDefault();
                    showToast('Tanggal keberangkatan tidak boleh di masa lalu.', 'error');
                    return;
                }
            }

            if (trip === 'round-trip') {
                const ret = document.getElementById('input-date-return');
                if (!ret || !ret.value) {
                    e.preventDefault();
                    showToast('Pilih tanggal pulang.', 'error');
                    return;
                }
                if (depart && ret.value < depart.value) {
                    e.preventDefault();
                    showToast('Tanggal pulang tidak boleh lebih awal dari keberangkatan.', 'error');
                    return;
                }
            }

            if (trip === 'multi-city') {
                const dates = $$('#multi-legs .leg-row input[type="date"]').map((i) => i.value);
                if (dates.some((d) => !d)) {
                    e.preventDefault();
                    showToast('Lengkapi tanggal setiap rute.', 'error');
                    return;
                }
                if (dates.some((d) => new Date(d + 'T00:00:00') < today)) {
                    e.preventDefault();
                    showToast('Tanggal rute tidak boleh di masa lalu.', 'error');
                    return;
                }
                for (let i = 1; i < dates.length; i++) {
                    if (dates[i] < dates[i - 1]) {
                        e.preventDefault();
                        showToast('Tanggal rute harus berurutan.', 'error');
                        return;
                    }
                }
            }
        });
    }

    function attachDateConstraints() {
        const today = new Date().toISOString().slice(0, 10);
        $$('input[type="date"]').forEach((input) => {
            if (!input.min) input.min = today;
        });
        const depart = document.getElementById('input-date-depart');
        const ret = document.getElementById('input-date-return');
        if (depart && ret) {
            const sync = () => {
                if (depart.value) ret.min = depart.value;
                if (ret.value && ret.value < depart.value) ret.value = depart.value;
            };
            depart.addEventListener('change', sync);
            sync();
        }
    }

    /* ------------------------------------------------------------
       City autocomplete
       ------------------------------------------------------------ */
    function initAutocomplete() {
        let timer = null;

        document.addEventListener('input', (e) => {
            const input = e.target.closest('.city-autocomplete');
            if (!input) return;
            const wrap = input.closest('.city-autocomplete-wrap') || input.parentElement;
            clearTimeout(timer);
            const q = input.value.trim();

            const old = wrap.querySelector('.autocomplete-list');
            if (old) old.remove();

            if (q.length < 2) return;

            timer = setTimeout(() => {
                fetch(`${window.TBL.autocomplete}?q=${encodeURIComponent(q)}`, {
                    headers: { 'Accept': 'application/json' }
                })
                    .then((res) => res.json())
                    .then((data) => {
                        if (!data.ok || !Array.isArray(data.items) || !data.items.length) return;
                        const list = document.createElement('div');
                        list.className = 'autocomplete-list';
                        data.items.forEach((item) => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'autocomplete-item w-full text-left block';
                            btn.innerHTML = `<i class="fa-solid fa-location-dot text-primary mr-2"></i><strong>${escapeHtml(item.name)}</strong> (${escapeHtml(item.iata_code)}) <small>· ${escapeHtml(item.country)}</small>`;
                            btn.addEventListener('click', () => {
                                input.value = `${item.name} (${item.iata_code})`;
                                list.remove();
                                input.dispatchEvent(new Event('change', { bubbles: true }));
                            });
                            list.appendChild(btn);
                        });
                        wrap.appendChild(list);
                    })
                    .catch(() => {});
            }, 220);
        });

        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            $$('.autocomplete-list').forEach((list) => list.remove());
        });
    }

    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, (m) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[m]));
    }

    /* ------------------------------------------------------------
       Results: filters, sorting, multi-leg selection
       ------------------------------------------------------------ */
    function initFilters() {
        const filterInputs = $$('.filter-price, .filter-airline, .filter-time, .filter-stops, .filter-facility');
        if (!filterInputs.length) return;

        filterInputs.forEach((cb) => cb.addEventListener('change', applyFilters));
        const price = document.getElementById('price-range');
        if (price) {
            price.addEventListener('input', () => {
                const valueEl = document.getElementById('price-range-value');
                if (valueEl) valueEl.textContent = `Rp ${(parseFloat(price.value) / 1000000).toFixed(1)}JT`;
                applyFilters();
            });
        }
    }

    function applyFilters() {
        const cards = $$('.flight-card');
        if (!cards.length) return;

        const activePrices = $$('.filter-price:checked').map((cb) => cb.value);
        const activeAirlines = $$('.filter-airline:checked').map((cb) => cb.value);
        const activeTimes = $$('.filter-time:checked').map((cb) => cb.value);
        const activeStops = $$('.filter-stops:checked').map((cb) => cb.value);
        const activeFacilities = $$('.filter-facility:checked').map((cb) => cb.value);
        const priceRange = document.getElementById('price-range');
        const maxPrice = priceRange ? parseInt(priceRange.value, 10) : Infinity;

        cards.forEach((card) => {
            const price = parseInt(card.dataset.price || '0', 10);
            const airline = card.dataset.airline || '';
            const timeCat = card.dataset.timeCategory || '';
            const stops = card.dataset.stops || '0';

            let priceMatch = price <= maxPrice;
            if (activePrices.length > 0) {
                priceMatch = false;
                if (activePrices.includes('under-1m') && price < 1000000) priceMatch = true;
                if (activePrices.includes('1m-2m') && price >= 1000000 && price <= 2000000) priceMatch = true;
                if (activePrices.includes('above-2m') && price > 2000000) priceMatch = true;
            }

            const airlineMatch = activeAirlines.length === 0 || activeAirlines.includes(airline);
            const timeMatch = activeTimes.length === 0 || activeTimes.includes(timeCat);
            const stopsMatch = activeStops.length === 0 || activeStops.includes(stops);

            let facilityMatch = true;
            if (activeFacilities.includes('baggage') && parseInt(card.dataset.baggage || '0', 10) <= 0) facilityMatch = false;
            if (activeFacilities.includes('meal') && card.dataset.meal !== '1') facilityMatch = false;
            if (activeFacilities.includes('wifi') && card.dataset.wifi !== '1') facilityMatch = false;
            if (activeFacilities.includes('entertainment') && card.dataset.ent !== '1') facilityMatch = false;

            card.style.display = (priceMatch && airlineMatch && timeMatch && stopsMatch && facilityMatch) ? '' : 'none';
        });

        // Update counters/empty states per result group.
        $$('.leg-results').forEach((group) => {
            const visible = $$('.flight-card', group).filter((c) => c.style.display !== 'none').length;
            const counter = group.querySelector('[data-result-count]');
            if (counter) counter.textContent = String(visible);
            const empty = group.querySelector('.empty-state');
            if (empty) empty.classList.toggle('hidden', visible > 0);
        });

        updateActiveFilterChips();
    }
    window.applyFilters = applyFilters;

    function updateActiveFilterChips() {
        const container = document.getElementById('active-filters');
        if (!container) return;
        const chips = [];

        $$('.filter-price:checked').forEach((cb) => chips.push({ label: cb.parentElement.innerText.trim(), type: 'price', value: cb.value }));
        $$('.filter-airline:checked').forEach((cb) => chips.push({ label: cb.value, type: 'airline', value: cb.value }));
        $$('.filter-time:checked').forEach((cb) => chips.push({ label: cb.parentElement.innerText.trim(), type: 'time', value: cb.value }));
        $$('.filter-stops:checked').forEach((cb) => chips.push({ label: cb.value === '0' ? 'Langsung' : cb.value + ' Transit', type: 'stops', value: cb.value }));
        $$('.filter-facility:checked').forEach((cb) => chips.push({ label: cb.parentElement.innerText.trim(), type: 'facility', value: cb.value }));

        if (!chips.length) {
            container.classList.add('hidden');
            container.innerHTML = '';
            return;
        }
        container.classList.remove('hidden');
        container.className = 'flex flex-wrap gap-2 mb-4';
        container.innerHTML = chips.map((c) =>
            `<button type="button" onclick="removeFilter('${c.type}', '${escapeHtml(c.value)}')" class="bg-primary/10 text-primary border border-primary/30 text-xs font-medium px-3 py-1.5 rounded-full hover:bg-primary/20 transition flex items-center gap-1">
                ${escapeHtml(c.label)} <i class="fa-solid fa-times text-[10px]"></i>
            </button>`
        ).join('') + `<button type="button" onclick="resetFilters()" class="bg-gray-100 text-gray-600 text-xs font-medium px-3 py-1.5 rounded-full hover:bg-gray-200 transition">Hapus Semua</button>`;
    }

    function removeFilter(type, value) {
        const map = { price: '.filter-price', airline: '.filter-airline', time: '.filter-time', stops: '.filter-stops', facility: '.filter-facility' };
        $$(map[type] || '.filter-price').forEach((cb) => { if (cb.value === value) cb.checked = false; });
        applyFilters();
    }
    window.removeFilter = removeFilter;

    function resetFilters() {
        $$('.filter-price, .filter-airline, .filter-time, .filter-stops, .filter-facility').forEach((cb) => { cb.checked = false; });
        const price = document.getElementById('price-range');
        if (price) price.value = price.max;
        const valueEl = document.getElementById('price-range-value');
        if (valueEl) valueEl.textContent = `Rp ${(parseInt(price ? price.max : '2000000', 10) / 1000000).toFixed(1)}JT`;
        applyFilters();
        showToast('Filter berhasil direset', 'info');
    }
    window.resetFilters = resetFilters;

    function initSort() {
        $$('.sort-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                const group = btn.closest('.leg-results');
                if (!group) return;
                const list = group.querySelector('.flight-list');
                if (!list) return;

                $$('.sort-btn', group).forEach((b) => {
                    b.classList.remove('active', 'bg-primary', 'text-white');
                    b.classList.add('bg-gray-100', 'text-gray-600');
                });
                btn.classList.add('active', 'bg-primary', 'text-white');
                btn.classList.remove('bg-gray-100', 'text-gray-600');

                const mode = btn.dataset.sort || 'best';
                const cards = Array.from(list.querySelectorAll('.flight-card'));
                cards.sort((a, b) => {
                    const num = (el, key) => parseFloat(el.dataset[key] || '0');
                    switch (mode) {
                        case 'cheapest': return num(a, 'price') - num(b, 'price');
                        case 'fastest': return num(a, 'duration') - num(b, 'duration');
                        case 'earliest': return num(a, 'depMin') - num(b, 'depMin');
                        default: return num(a, 'score') - num(b, 'score');
                    }
                });
                cards.forEach((card) => list.appendChild(card));
            });
        });
    }

    function initLegSelection() {
        const form = document.getElementById('multi-leg-form');
        if (!form) return;
        const needed = parseInt(form.dataset.legs || '0', 10);
        if (!needed) return;

        function refresh() {
            let count = 0;
            for (let i = 0; i < needed; i++) {
                const input = document.getElementById(`selected-flight-${i}`);
                if (input && input.value) count++;
            }
            const progress = document.getElementById('leg-progress');
            if (progress) progress.textContent = `${count} dari ${needed} rute dipilih`;
            const bar = document.getElementById('leg-progress-bar');
            if (bar) bar.style.width = `${(count / needed) * 100}%`;
            const progressLabel = document.getElementById('leg-progress-label');
            if (progressLabel) {
                progressLabel.textContent = count === needed
                    ? 'Semua rute sudah dipilih. Lanjutkan untuk mengisi data penumpang.'
                    : 'Pilih satu penerbangan untuk setiap rute di atas.';
            }
            const submit = document.getElementById('leg-submit');
            if (submit) {
                submit.disabled = count !== needed;
                submit.classList.toggle('opacity-50', count !== needed);
                submit.classList.toggle('cursor-not-allowed', count !== needed);
            }
        }

        $$('.select-leg-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                const leg = btn.dataset.leg;
                const flightId = btn.dataset.flightId;
                const hidden = document.getElementById(`selected-flight-${leg}`);
                if (!hidden) return;

                const section = btn.closest('.leg-results');
                $$('.flight-card', section).forEach((card) => card.classList.remove('selected'));
                btn.closest('.flight-card').classList.add('selected');
                hidden.value = flightId;

                $$('.select-leg-btn', section).forEach((b) => {
                    b.innerHTML = 'Pilih';
                    b.classList.remove('bg-green-600');
                    b.classList.add('bg-primary');
                });
                btn.innerHTML = '<i class="fa-solid fa-check mr-1"></i> Dipilih';
                btn.classList.remove('bg-primary');
                btn.classList.add('bg-green-600');

                refresh();
            });
        });

        form.addEventListener('submit', (e) => {
            for (let i = 0; i < needed; i++) {
                const input = document.getElementById(`selected-flight-${i}`);
                if (!input || !input.value) {
                    e.preventDefault();
                    showToast('Pilih satu penerbangan untuk setiap rute.', 'error');
                    return;
                }
            }
        });

        refresh();
    }

    /* ------------------------------------------------------------
       Payment page countdown
       ------------------------------------------------------------ */
    function initCountdowns() {
        const els = $$('.payment-countdown');
        if (!els.length) return;
        const tick = () => {
            const now = Date.now();
            els.forEach((el) => {
                const until = new Date(el.dataset.until.replace(' ', 'T')).getTime();
                const diff = Math.max(0, until - now);
                const h = String(Math.floor(diff / 3600000)).padStart(2, '0');
                const m = String(Math.floor((diff % 3600000) / 60000)).padStart(2, '0');
                const s = String(Math.floor((diff % 60000) / 1000)).padStart(2, '0');
                el.textContent = `${h}:${m}:${s}`;
            });
        };
        tick();
        setInterval(tick, 1000);
    }

    /* ------------------------------------------------------------
       Upload preview
       ------------------------------------------------------------ */
    function initUploadPreview() {
        const input = document.getElementById('proof-input');
        if (!input) return;
        input.addEventListener('change', () => {
            const label = document.getElementById('proof-filename');
            if (label) label.textContent = input.files && input.files[0] ? input.files[0].name : '';
        });
    }

    /* ------------------------------------------------------------
       Boot
       ------------------------------------------------------------ */
    document.addEventListener('DOMContentLoaded', () => {
        // Initialise passenger counters from server-rendered values.
        ['adult', 'child', 'infant'].forEach((type) => {
            const el = document.getElementById(`count-${type}`);
            const value = el ? parseInt(el.textContent || '0', 10) : NaN;
            if (!Number.isNaN(value)) passengerCounts[type] = value;
        });

        initNavbar();
        renderFlashMessages();
        initCounters();
        initTestimonials();
        initFAQ();
        initSearchTabs();
        initMultiCity();
        initSearchValidation();
        initAutocomplete();
        initFilters();
        initSort();
        initLegSelection();
        initModalBackdrops();
        initCountdowns();
        initUploadPreview();
        attachDateConstraints();

        const passengerBtn = document.getElementById('btn-passenger');
        if (passengerBtn) passengerBtn.addEventListener('click', openPassengerModal);

        // If results were rendered with filters, reflect initial state.
        if ($$('.flight-card').length) applyFilters();
    });
})();

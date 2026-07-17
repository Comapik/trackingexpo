(() => {
    let selectedProduct = null;
    let selectedHourDay = 'all';

    const $ = (sel) => document.querySelector(sel);
    const $$ = (sel) => document.querySelectorAll(sel);

    function showToast(msg) {
        const toast = $('#toast');
        toast.textContent = msg;
        toast.classList.add('show');
        clearTimeout(showToast._t);
        showToast._t = setTimeout(() => toast.classList.remove('show'), 1800);
    }

    function initTabs() {
        $$('.tab-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                $$('.tab-btn').forEach((b) => b.classList.remove('active'));
                $$('.tab-panel').forEach((p) => p.classList.remove('active'));
                btn.classList.add('active');
                $('#tab-' + btn.dataset.tab).classList.add('active');
            });
        });
    }

    function initProducts() {
        const grid = $('#products-grid');
        Object.entries(window.PRODUCTS).forEach(([key, label]) => {
            const btn = document.createElement('button');
            btn.className = 'product-btn';
            btn.textContent = label;
            btn.dataset.key = key;
            btn.addEventListener('click', () => {
                selectedProduct = key;
                $$('.product-btn').forEach((b) => b.classList.remove('selected'));
                btn.classList.add('selected');
                $('#selected-product').textContent = label;
            });
            grid.appendChild(btn);
        });
    }

    function initWeather() {
        const grid = $('#weather-grid');
        Object.entries(window.WEATHER).forEach(([key, w]) => {
            const btn = document.createElement('button');
            btn.className = 'weather-btn';
            btn.dataset.key = key;
            btn.innerHTML = `<span class="weather-icon">${w.icon}</span><span>${w.label}</span>`;
            btn.addEventListener('click', async () => {
                try {
                    const { stats } = await api('set_weather', { weather: key });
                    renderStats(stats);
                    showToast('Météo enregistrée : ' + w.label);
                } catch (e) { showToast(e.message); }
            });
            grid.appendChild(btn);
        });
    }

    function weatherIcon(key) {
        return key && window.WEATHER[key] ? window.WEATHER[key].icon : '';
    }

    async function api(action, params = {}) {
        const body = new URLSearchParams({ action, ...params });
        const res = await fetch('api.php', { method: 'POST', body });
        const data = await res.json();
        if (!data.ok) throw new Error(data.error || 'Erreur');
        return data;
    }

    function renderStats(stats) {
        $('#today-visitors').textContent = stats.todayVisitors;
        $('#stat-total-visitors').textContent = stats.totalVisitors;
        $('#stat-today-visitors').textContent = stats.todayVisitors;
        $('#stat-total-revenue').textContent = stats.totalRevenue.toFixed(2) + ' €';
        $('#stat-today-revenue').textContent = stats.todayRevenue.toFixed(2) + ' €';

        $$('.weather-btn').forEach((b) => b.classList.toggle('selected', b.dataset.key === stats.todayWeather));

        const salesBars = $('#sales-bars');
        salesBars.innerHTML = '';
        const maxQty = Math.max(1, ...Object.values(stats.sales).map((s) => s.qty));
        Object.values(stats.sales).forEach((s) => {
            salesBars.appendChild(makeBar(s.label, s.qty, maxQty));
        });

        const visitsBars = $('#visits-bars');
        visitsBars.innerHTML = '';
        const days = stats.byDay;
        const maxDay = Math.max(1, ...days.map((d) => Number(d.total)));
        days.forEach((d) => {
            const label = new Date(d.day).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' })
                + (d.weather ? ' ' + weatherIcon(d.weather) : '');
            visitsBars.appendChild(makeBar(label, Number(d.total), maxDay));
        });
        if (days.length === 0) {
            visitsBars.innerHTML = '<p class="empty-note">Aucune donnée pour le moment.</p>';
        }

        renderColumns('#last7-columns', stats.last7Days.map((d) => ({
            label: new Date(d.day).toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit' }),
            value: d.total,
            icon: weatherIcon(d.weather),
        })));

        renderHourDayOptions(stats);
        if (selectedHourDay === 'all') {
            renderColumns('#hours-columns', stats.byHour.map((h) => ({
                label: h.hour + 'h',
                value: h.total,
            })));
        } else {
            refreshHourColumns(selectedHourDay);
        }
    }

    function renderHourDayOptions(stats) {
        const select = $('#hour-day-select');
        select.querySelectorAll('option:not([value="all"])').forEach((o) => o.remove());
        [...stats.byDay].reverse().forEach((d) => {
            const opt = document.createElement('option');
            opt.value = d.day;
            const label = new Date(d.day).toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit', month: '2-digit' });
            opt.textContent = label + (d.weather ? ' ' + weatherIcon(d.weather) : '');
            select.appendChild(opt);
        });
        if ([...select.options].some((o) => o.value === selectedHourDay)) {
            select.value = selectedHourDay;
        } else {
            selectedHourDay = 'all';
            select.value = 'all';
        }
    }

    async function refreshHourColumns(day) {
        try {
            const { byHour } = await api('hours', { day });
            renderColumns('#hours-columns', byHour.map((h) => ({
                label: h.hour + 'h',
                value: h.total,
            })));
        } catch (e) { showToast(e.message); }
    }

    function initHourFilter() {
        $('#hour-day-select').addEventListener('change', (e) => {
            selectedHourDay = e.target.value;
            refreshHourColumns(selectedHourDay);
        });
    }

    function renderColumns(selector, items) {
        const container = $(selector);
        container.innerHTML = '';
        const max = Math.max(1, ...items.map((i) => i.value));
        items.forEach((item) => {
            const col = document.createElement('div');
            col.className = 'col';
            const pct = Math.max(2, Math.round((item.value / max) * 100));
            col.innerHTML = `
                ${item.icon ? `<span class="col-weather">${item.icon}</span>` : ''}
                <span class="col-value">${item.value || ''}</span>
                <span class="col-bar" style="height:${pct}%"></span>
                <span class="col-label">${item.label}</span>
            `;
            container.appendChild(col);
        });
    }

    function makeBar(label, value, max) {
        const row = document.createElement('div');
        row.className = 'bar-row';
        const pct = Math.round((value / max) * 100);
        row.innerHTML = `
            <span class="bar-label">${label}</span>
            <span class="bar-track"><span class="bar-fill" style="width:${pct}%"></span></span>
            <span class="bar-value">${value}</span>
        `;
        return row;
    }

    const COLORS = {
        primary: '#2b6cb0',
        primaryDark: '#1a4971',
        bg: '#f4f6f8',
        card: '#ffffff',
        text: '#1a202c',
        muted: '#718096',
        track: '#edf2f7',
    };
    const FONT = '-apple-system, "Segoe UI", Roboto, sans-serif';

    function roundRect(ctx, x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }

    function drawHeader(ctx, width, title, subtitle) {
        ctx.fillStyle = COLORS.primary;
        ctx.fillRect(0, 0, width, 150);
        ctx.fillStyle = '#ffffff';
        ctx.textAlign = 'center';
        ctx.font = `bold 42px ${FONT}`;
        ctx.fillText(title, width / 2, 70);
        ctx.font = `26px ${FONT}`;
        ctx.fillText(subtitle, width / 2, 115);
    }

    function drawFooter(ctx, width, height) {
        ctx.fillStyle = '#a0aec0';
        ctx.font = `18px ${FONT}`;
        ctx.textAlign = 'center';
        ctx.fillText('Généré le ' + new Date().toLocaleString('fr-FR'), width / 2, height - 25);
    }

    function drawHourColumns(ctx, hours, x, y, width, height) {
        const first = hours.findIndex((h) => h.total > 0);
        if (first === -1) {
            ctx.fillStyle = COLORS.muted;
            ctx.font = `24px ${FONT}`;
            ctx.textAlign = 'center';
            ctx.fillText('Aucune visite enregistrée', x + width / 2, y + height / 2);
            return;
        }
        let last = -1;
        for (let i = hours.length - 1; i >= 0; i--) {
            if (hours[i].total > 0) { last = i; break; }
        }
        const from = Math.max(0, first - 1);
        const to = Math.min(23, last + 1);
        const slice = hours.slice(from, to + 1);
        const max = Math.max(1, ...slice.map((h) => h.total));
        const gap = 8;
        const colWidth = (width - gap * (slice.length - 1)) / slice.length;
        const barAreaTop = y + 40;
        const barAreaHeight = height - 70;

        slice.forEach((h, i) => {
            const cx = x + i * (colWidth + gap);
            const barH = Math.max(3, Math.round((h.total / max) * barAreaHeight));
            const barY = barAreaTop + barAreaHeight - barH;
            ctx.fillStyle = COLORS.primary;
            ctx.fillRect(cx, barY, colWidth, barH);
            if (h.total > 0) {
                ctx.fillStyle = COLORS.text;
                ctx.font = `bold 16px ${FONT}`;
                ctx.textAlign = 'center';
                ctx.fillText(String(h.total), cx + colWidth / 2, barY - 8);
            }
            ctx.fillStyle = COLORS.muted;
            ctx.font = `15px ${FONT}`;
            ctx.textAlign = 'center';
            ctx.fillText(h.hour + 'h', cx + colWidth / 2, barAreaTop + barAreaHeight + 25);
        });
    }

    function drawBarList(ctx, items, x, y, width) {
        const max = Math.max(1, ...items.map((i) => i.qty));
        const rowH = 64;
        items.forEach((item, i) => {
            const ry = y + i * rowH;
            ctx.fillStyle = COLORS.text;
            ctx.font = `22px ${FONT}`;
            ctx.textAlign = 'left';
            ctx.fillText(item.label, x, ry + 22);

            const trackY = ry + 32;
            const trackH = 20;
            ctx.fillStyle = COLORS.track;
            ctx.fillRect(x, trackY, width, trackH);
            const pct = item.qty / max;
            ctx.fillStyle = COLORS.primary;
            ctx.fillRect(x, trackY, Math.max(4, width * pct), trackH);

            ctx.fillStyle = COLORS.text;
            ctx.font = `bold 20px ${FONT}`;
            ctx.textAlign = 'right';
            ctx.fillText(String(item.qty), x + width, ry + 22);
        });
    }

    function downloadCanvas(canvas, filename) {
        canvas.toBlob((blob) => {
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            setTimeout(() => URL.revokeObjectURL(url), 2000);
        }, 'image/jpeg', 0.92);
    }

    function todayDateInfo() {
        const now = new Date();
        const label = now.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        const key = now.toLocaleDateString('sv-SE'); // YYYY-MM-DD
        return { label: label.charAt(0).toUpperCase() + label.slice(1), key };
    }

    function formatDayLabel(dayKey) {
        const label = new Date(dayKey).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        return label.charAt(0).toUpperCase() + label.slice(1);
    }

    function formatShortDay(dayKey) {
        return new Date(dayKey).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
    }

    // Convertit la valeur d'un <input type="week"> ("2026-W29") en plage lundi->dimanche.
    function weekInputToRange(weekStr) {
        const [yearStr, weekNumStr] = weekStr.split('-W');
        const year = Number(yearStr);
        const week = Number(weekNumStr);
        const jan4 = new Date(year, 0, 4);
        const jan4Day = (jan4.getDay() + 6) % 7; // lundi = 0
        const week1Monday = new Date(jan4);
        week1Monday.setDate(jan4.getDate() - jan4Day);
        const monday = new Date(week1Monday);
        monday.setDate(week1Monday.getDate() + (week - 1) * 7);
        const sunday = new Date(monday);
        sunday.setDate(monday.getDate() + 6);
        const fmt = (d) => d.toLocaleDateString('sv-SE');
        return { from: fmt(monday), to: fmt(sunday) };
    }

    // Lit le sélecteur de période de la section export et renvoie { mode, from, to, label },
    // ou null (avec un toast) si la sélection est incomplète.
    function getExportPeriod() {
        const mode = document.querySelector('input[name="export-period"]:checked').value;
        const today = todayDateInfo().key;

        if (mode === 'today') {
            return { mode, from: today, to: today, label: formatDayLabel(today) };
        }
        if (mode === 'day') {
            const d = $('#export-day-input').value;
            if (!d) { showToast('Choisis une date'); return null; }
            return { mode, from: d, to: d, label: formatDayLabel(d) };
        }
        if (mode === 'week') {
            const w = $('#export-week-input').value;
            if (!w) { showToast('Choisis une semaine'); return null; }
            const { from, to } = weekInputToRange(w);
            return { mode, from, to, label: `Semaine du ${formatShortDay(from)} au ${formatShortDay(to)}` };
        }
        return { mode: 'all', from: null, to: null, label: 'Toute la période' };
    }

    function initExportPeriod() {
        $('#export-day-input').value = todayDateInfo().key;
        $('#export-period').addEventListener('change', (e) => {
            if (e.target.name !== 'export-period') return;
            $('#export-day-input').classList.toggle('hidden', e.target.value !== 'day');
            $('#export-week-input').classList.toggle('hidden', e.target.value !== 'week');
        });
    }

    function initExportCsv() {
        $('#btn-export-visits').addEventListener('click', () => downloadCsv('visits'));
        $('#btn-export-sales').addEventListener('click', () => downloadCsv('sales'));
    }

    function downloadCsv(type) {
        const period = getExportPeriod();
        if (!period) return;
        const params = new URLSearchParams({ type });
        if (period.from) {
            params.set('from', period.from);
            params.set('to', period.to);
        }
        window.location.href = 'export.php?' + params.toString();
    }

    function drawDayColumns(ctx, days, x, y, width, height) {
        const max = Math.max(1, ...days.map((d) => d.total));
        const gap = 14;
        const colWidth = (width - gap * (days.length - 1)) / days.length;
        const barAreaTop = y + 40;
        const barAreaHeight = height - 70;

        days.forEach((d, i) => {
            const cx = x + i * (colWidth + gap);
            const barH = Math.max(3, Math.round((d.total / max) * barAreaHeight));
            const barY = barAreaTop + barAreaHeight - barH;
            ctx.fillStyle = COLORS.primary;
            ctx.fillRect(cx, barY, colWidth, barH);
            ctx.fillStyle = COLORS.text;
            ctx.font = `bold 18px ${FONT}`;
            ctx.textAlign = 'center';
            ctx.fillText(String(d.total), cx + colWidth / 2, barY - 8);
            ctx.fillStyle = COLORS.muted;
            ctx.font = `15px ${FONT}`;
            const label = new Date(d.day).toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit' });
            ctx.fillText(label, cx + colWidth / 2, barAreaTop + barAreaHeight + 25);
        });
    }

    async function exportDayImage() {
        const period = getExportPeriod();
        if (!period) return;
        if (period.mode === 'all') { showToast('Choisis un jour ou une semaine pour l’image'); return; }
        try {
            const { period: p } = await api('period_stats', { from: period.from, to: period.to });
            const isWeek = p.from !== p.to;

            const canvas = document.createElement('canvas');
            canvas.width = 1000;
            canvas.height = isWeek ? 900 : 1080;
            const ctx = canvas.getContext('2d');

            ctx.fillStyle = COLORS.bg;
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            drawHeader(ctx, canvas.width, '📸 Suivi de l’expo', period.label);

            ctx.fillStyle = COLORS.card;
            roundRect(ctx, 60, 190, canvas.width - 120, 220, 20);
            ctx.fill();
            ctx.fillStyle = COLORS.primaryDark;
            ctx.textAlign = 'center';
            ctx.font = `bold 130px ${FONT}`;
            ctx.fillText(String(p.visitors), canvas.width / 2, 340);
            ctx.fillStyle = COLORS.muted;
            ctx.font = `28px ${FONT}`;
            ctx.fillText(isWeek ? 'visiteurs cette semaine' : 'visiteurs', canvas.width / 2, 385);

            let y = 440;
            if (!isWeek) {
                ctx.fillStyle = COLORS.card;
                roundRect(ctx, 60, y, canvas.width - 120, 150, 20);
                ctx.fill();
                const w = p.weather ? window.WEATHER[p.weather] : null;
                ctx.fillStyle = COLORS.text;
                ctx.font = `70px ${FONT}`;
                ctx.fillText(w ? w.icon : '—', canvas.width / 2, y + 90);
                ctx.fillStyle = COLORS.muted;
                ctx.font = `26px ${FONT}`;
                ctx.fillText(w ? w.label : 'Météo non renseignée', canvas.width / 2, y + 130);
                y += 200;
            } else {
                y += 30;
            }

            ctx.textAlign = 'left';
            ctx.fillStyle = COLORS.muted;
            ctx.font = `bold 30px ${FONT}`;
            ctx.fillText(isWeek ? 'Répartition par jour' : 'Fréquentation par heure', 60, y);
            y += 30;

            const chartH = 340;
            ctx.fillStyle = COLORS.card;
            roundRect(ctx, 60, y, canvas.width - 120, chartH, 20);
            ctx.fill();
            if (isWeek) {
                drawDayColumns(ctx, p.byDay, 90, y + 20, canvas.width - 180, chartH - 40);
            } else {
                drawHourColumns(ctx, p.byHour, 90, y + 20, canvas.width - 180, chartH - 40);
            }

            drawFooter(ctx, canvas.width, canvas.height);
            downloadCanvas(canvas, `expo_${p.from}${isWeek ? '_au_' + p.to : ''}.jpg`);
            showToast('Image exportée');
        } catch (e) { showToast(e.message); }
    }

    async function exportSalesImage() {
        const period = getExportPeriod();
        if (!period) return;
        if (period.mode === 'all') { showToast('Choisis un jour ou une semaine pour l’image'); return; }
        try {
            const { period: p } = await api('period_stats', { from: period.from, to: period.to });
            const items = Object.values(p.sales);

            const canvas = document.createElement('canvas');
            canvas.width = 1000;
            canvas.height = 550 + items.length * 64;
            const ctx = canvas.getContext('2d');

            ctx.fillStyle = COLORS.bg;
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            drawHeader(ctx, canvas.width, '📸 Ventes de l’expo', period.label);

            const cardW = (canvas.width - 140) / 2;
            ctx.fillStyle = COLORS.card;
            roundRect(ctx, 60, 190, cardW, 180, 20);
            ctx.fill();
            roundRect(ctx, 80 + cardW, 190, cardW, 180, 20);
            ctx.fill();

            ctx.textAlign = 'center';
            ctx.fillStyle = COLORS.primaryDark;
            ctx.font = `bold 60px ${FONT}`;
            ctx.fillText(p.revenue.toFixed(2) + ' €', 60 + cardW / 2, 290);
            ctx.fillText(String(p.salesQty), 80 + cardW + cardW / 2, 290);
            ctx.fillStyle = COLORS.muted;
            ctx.font = `24px ${FONT}`;
            ctx.fillText('chiffre d’affaires', 60 + cardW / 2, 335);
            ctx.fillText('articles vendus', 80 + cardW + cardW / 2, 335);

            let y = 420;
            ctx.textAlign = 'left';
            ctx.fillStyle = COLORS.muted;
            ctx.font = `bold 30px ${FONT}`;
            ctx.fillText('Ventes par produit', 60, y);
            y += 30;

            const listH = items.length * 64 + 30;
            ctx.fillStyle = COLORS.card;
            roundRect(ctx, 60, y, canvas.width - 120, listH, 20);
            ctx.fill();
            drawBarList(ctx, items, 90, y + 25, canvas.width - 180);

            drawFooter(ctx, canvas.width, canvas.height);
            downloadCanvas(canvas, `ventes_${p.from}${p.from !== p.to ? '_au_' + p.to : ''}.jpg`);
            showToast('Image des ventes exportée');
        } catch (e) { showToast(e.message); }
    }

    const FIREWORK_COLORS = ['#ff9f1c', '#ff4d6d', '#2ec4b6', '#3a86ff', '#ff70a6', '#ffe066', '#a374ff'];
    let fwCanvas, fwCtx, fwParticles = [], fwRunning = false;

    function initFireworks() {
        fwCanvas = $('#firework-canvas');
        fwCtx = fwCanvas.getContext('2d');
        const resize = () => {
            fwCanvas.width = window.innerWidth;
            fwCanvas.height = window.innerHeight;
        };
        resize();
        window.addEventListener('resize', resize);
    }

    function burstAt(x, y) {
        const count = 40;
        for (let i = 0; i < count; i++) {
            const angle = (Math.PI * 2 * i) / count + Math.random() * 0.3;
            const speed = 3 + Math.random() * 5;
            fwParticles.push({
                x, y,
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed,
                color: FIREWORK_COLORS[Math.floor(Math.random() * FIREWORK_COLORS.length)],
                life: 1,
                size: 5 + Math.random() * 4,
            });
        }
        if (!fwRunning) {
            fwRunning = true;
            requestAnimationFrame(animateFireworks);
        }
    }

    function animateFireworks() {
        fwCtx.clearRect(0, 0, fwCanvas.width, fwCanvas.height);

        fwParticles.forEach((p) => {
            p.vy += 0.08; // gravité
            p.vx *= 0.98;
            p.x += p.vx;
            p.y += p.vy;
            p.life -= 0.011;
        });
        fwParticles = fwParticles.filter((p) => p.life > 0);
        fwParticles.forEach((p) => {
            const alpha = Math.max(0, p.life);
            fwCtx.globalAlpha = alpha;
            fwCtx.shadowColor = p.color;
            fwCtx.shadowBlur = 18;
            fwCtx.fillStyle = p.color;
            fwCtx.beginPath();
            fwCtx.arc(p.x, p.y, p.size * alpha + 1, 0, Math.PI * 2);
            fwCtx.fill();
        });
        fwCtx.shadowBlur = 0;
        fwCtx.globalAlpha = 1;

        if (fwParticles.length > 0) {
            requestAnimationFrame(animateFireworks);
        } else {
            fwRunning = false;
        }
    }

    function launchFireworks() {
        const bursts = 9;
        for (let i = 0; i < bursts; i++) {
            setTimeout(() => {
                const x = Math.random() * window.innerWidth;
                const y = Math.random() * window.innerHeight * 0.75 + window.innerHeight * 0.05;
                burstAt(x, y);
            }, i * 220);
        }
    }

    function initExportImages() {
        $('#btn-export-day-image').addEventListener('click', exportDayImage);
        $('#btn-export-sales-image').addEventListener('click', exportSalesImage);
    }

    async function refreshStats() {
        try {
            const { stats } = await api('stats');
            renderStats(stats);
        } catch (e) {
            showToast(e.message);
        }
    }

    function initVisitors() {
        $('#btn-add-visit').addEventListener('click', async () => {
            try {
                const { stats } = await api('add_visit', { count: 1 });
                renderStats(stats);
                launchFireworks();
                showToast('Visiteur ajouté');
            } catch (e) { showToast(e.message); }
        });

        $('#btn-add-group').addEventListener('click', async () => {
            const count = Math.max(1, parseInt($('#group-count').value, 10) || 1);
            try {
                const { stats } = await api('add_visit', { count });
                renderStats(stats);
                launchFireworks();
                showToast(count + ' visiteurs ajoutés');
            } catch (e) { showToast(e.message); }
        });

        $('#btn-undo-visit').addEventListener('click', async () => {
            try {
                const { stats } = await api('undo_visit');
                renderStats(stats);
                showToast('Dernière entrée annulée');
            } catch (e) { showToast(e.message); }
        });
    }

    function initSales() {
        $('#btn-add-sale').addEventListener('click', async () => {
            if (!selectedProduct) {
                showToast('Choisis un produit');
                return;
            }
            const quantity = Math.max(1, parseInt($('#sale-quantity').value, 10) || 1);
            const price = $('#sale-price').value;
            try {
                const { stats } = await api('add_sale', { product: selectedProduct, quantity, price });
                renderStats(stats);
                launchFireworks();
                showToast('Vente enregistrée');
            } catch (e) { showToast(e.message); }
        });

        $('#btn-undo-sale').addEventListener('click', async () => {
            try {
                const { stats } = await api('undo_sale');
                renderStats(stats);
                showToast('Dernière vente annulée');
            } catch (e) { showToast(e.message); }
        });
    }

    initTabs();
    initProducts();
    initWeather();
    initVisitors();
    initSales();
    initExportPeriod();
    initExportCsv();
    initExportImages();
    initFireworks();
    initHourFilter();
    refreshStats();
})();

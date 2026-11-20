/* Sebaran Usaha SE2026: logika filter, grafik, peta, dan titik admin.
 * Dipakai oleh tampilan guest (/SebaranUsaha) dan tampilan admin (/backend/sebaranusaha).
 * Konfigurasi dikirim dari Blade lewat window.SEBARAN_CFG. */
(function() {
    'use strict';
    const CFG = window.SEBARAN_CFG || {};
    const $ = (id) => document.getElementById(id);
    const fmt = (n) => Number(n || 0).toLocaleString('id-ID');
    // SEMUA teks dari server (nama usaha dsb.) wajib di-escape: banyak nama memuat tanda < >
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    } [c]));

    const WARNA = {
        'UMKM': '#fd7e14',
        'UMK': '#ffc078',
        'UM': '#2a2541',
        'UB': '#c92a2a',
        'Lainnya / Tidak Terisi': '#adb5bd',
        'Jumlah usaha': '#fd7e14'
    };
    const KELAS = ['#ffe8cc', '#ffc078', '#ff922b', '#f76707', '#c2410c']; // oranye bertingkat

    const state = {
        kdkec: '',
        kddesa: '',
        idsls: '',
        kbli: ''
    };
    let DATA = null;
    let seq = 0;

    const selKec = $('fKec'),
        selDesa = $('fDesa'),
        selSls = $('fSls'),
        inKbli = $('fKbli');

    // ---------------------------------------------------------------- filter
    const opt = (v, t) => {
        const o = document.createElement('option');
        o.value = v;
        o.textContent = t;
        return o;
    };
    const cariKec = () => CFG.wilayah.find((k) => k.k === state.kdkec);
    const cariDesa = () => (cariKec() || {
        d: []
    }).d.find((d) => d.k === state.kddesa);

    function isiKec() {
        selKec.replaceChildren(opt('', 'Semua kecamatan'));
        CFG.wilayah.forEach((k) => selKec.appendChild(opt(k.k, k.n)));
    }

    function isiDesa() {
        const k = cariKec();
        selDesa.replaceChildren(opt('', 'Semua kelurahan'));
        selDesa.disabled = !k;
        if (k) k.d.forEach((d) => selDesa.appendChild(opt(d.k, d.n)));
    }

    function isiSls() {
        const d = cariDesa();
        selSls.replaceChildren(opt('', 'Semua RT/SLS'));
        selSls.disabled = !d;
        if (d) d.s.forEach((s) => selSls.appendChild(opt(s.k, s.n)));
    }

    function syncUi() {
        isiDesa();
        isiSls();
        selKec.value = state.kdkec;
        selDesa.value = state.kddesa;
        selSls.value = state.idsls;
        if (inKbli.value !== state.kbli) inKbli.value = state.kbli;
        const cb = $('cbTanpa');
        if (cb) {
            cb.disabled = !!state.kbli;
            cb.title = state.kbli ? 'Tidak berlaku saat filter KBLI aktif' : '';
        }
    }

    function bacaUrl() {
        const q = new URLSearchParams(location.search);
        let idsls = q.get('idsls') || '',
            kec = q.get('kdkec') || '',
            desa = q.get('kddesa') || '';
        if (idsls) {
            kec = idsls.substr(4, 3);
            desa = idsls.substr(7, 3);
        }
        const k = CFG.wilayah.find((x) => x.k === kec);
        const d = k && k.d.find((x) => x.k === desa);
        const s = d && d.s.find((x) => x.k === idsls);
        state.kdkec = k ? kec : '';
        state.kddesa = d ? desa : '';
        state.idsls = s ? idsls : '';
        const kb = q.get('kbli') || '';
        state.kbli = /^(\d{2,5}|-)$/.test(kb) ? kb : '';
    }

    function tulisUrl() {
        const q = new URLSearchParams();
        ['kdkec', 'kddesa', 'idsls', 'kbli'].forEach((k) => {
            if (state[k]) q.set(k, state[k]);
        });
        const s = q.toString();
        history.replaceState(null, '', location.pathname + (s ? '?' + s : ''));
    }

    const level = () => state.idsls ? 'sls' : state.kddesa ? 'kelurahan' : state.kdkec ? 'kecamatan' : 'kota';

    selKec.addEventListener('change', () => {
        state.kdkec = selKec.value;
        state.kddesa = '';
        state.idsls = '';
        syncUi();
        terapkan(true);
    });
    selDesa.addEventListener('change', () => {
        state.kddesa = selDesa.value;
        state.idsls = '';
        syncUi();
        terapkan(true);
    });
    selSls.addEventListener('change', () => {
        state.idsls = selSls.value;
        syncUi();
        terapkan(true);
    });

    let tKbli;
    inKbli.addEventListener('input', () => {
        clearTimeout(tKbli);
        const v = inKbli.value.trim();
        const ok = v === '' || /^(\d{2,5}|-)$/.test(v);
        inKbli.classList.toggle('is-invalid', !ok && !/^\d$/.test(v));
        if (!ok) return;
        tKbli = setTimeout(() => {
            if (state.kbli !== v) {
                state.kbli = v;
                syncUi();
                terapkan(false);
            }
        }, 350);
    });
    $('btnKbli').addEventListener('click', () => {
        inKbli.value = '';
        inKbli.classList.remove('is-invalid');
        if (state.kbli) {
            state.kbli = '';
            syncUi();
            terapkan(false);
        }
    });
    $('btnReset').addEventListener('click', () => {
        state.kdkec = state.kddesa = state.idsls = state.kbli = '';
        inKbli.classList.remove('is-invalid');
        syncUi();
        terapkan(true);
    });

    // ---------------------------------------------------------------- ajax
    async function ambil(url, p, signal) {
        const qs = new URLSearchParams();
        Object.entries(p || {}).forEach(([k, v]) => {
            if (v) qs.set(k, v);
        });
        const r = await fetch(url + (qs.toString() ? '?' + qs : ''), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            signal
        });
        let j = null;
        try {
            j = await r.json();
        } catch (_) {}
        if (!r.ok) {
            const e = new Error((j && j.message) || 'Gagal memuat data (HTTP ' + r.status + ')');
            e.status = r.status;
            throw e;
        }
        return j;
    }
    const params = () => ({
        kdkec: state.kdkec,
        kddesa: state.kddesa,
        idsls: state.idsls,
        kbli: state.kbli
    });

    function tampilError(msg) {
        const e = $('suError');
        e.textContent = msg || '';
        e.classList.toggle('d-none', !msg);
    }

    // ---------------------------------------------------------------- statistik + grafik
    function renderKpi(d) {
        ['total', 'UMKM', 'UMK', 'UM', 'UB', 'LAINNYA'].forEach((k) => {
            const el = $(k === 'total' ? 'kTotal' : 'k' + k);
            if (el) el.textContent = fmt(d.ringkasan[k]);
        });
    }

    let chart = null;

    function renderChart(d) {
        if (typeof Chart === 'undefined') { // pustaka grafik gagal dimuat: KPI dan peta tetap jalan
            $('hintGrafik').textContent = 'Grafik tidak dapat ditampilkan: Chart.js tidak termuat (template/backend/plugins/chart.js).';
            return;
        }
        const g = d.grafik,
            n = g.label.length;
        const horizontal = g.bertumpuk && n > 6;
        // di layar sempit, awalan "LUBUK LINGGAU " dibuang agar label tidak terpotong (judul sudah menyebut kota)
        const labelTampil = window.innerWidth < 576 ? g.label.map((l) => l.replace(/^LUBUK LINGGAU /, '')) : g.label;
        $('chartBox').style.height = horizontal ? Math.max(340, 100 + n * 30) + 'px' : '380px';
        if (chart) chart.destroy();
        const datasets = Object.keys(g.seri).map((k) => ({
            label: k,
            data: g.seri[k],
            backgroundColor: WARNA[k] || '#fd7e14',
            borderWidth: 0,
            maxBarThickness: 44
        }));
        const angka = {
            stacked: g.bertumpuk,
            ticks: {
                beginAtZero: true,
                callback: (v) => fmt(v)
            }
        };
        const kategori = {
            stacked: g.bertumpuk,
            gridLines: {
                display: false
            },
            ticks: {
                autoSkip: false
            }
        };
        const dapatDrill = d.level !== 'sls';
        chart = new Chart($('chart').getContext('2d'), {
            type: horizontal ? 'horizontalBar' : 'bar',
            data: {
                labels: labelTampil,
                datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 250
                },
                legend: {
                    display: g.bertumpuk,
                    position: 'bottom'
                },
                scales: horizontal ? {
                    xAxes: [angka],
                    yAxes: [kategori]
                } : {
                    xAxes: [kategori],
                    yAxes: [angka]
                },
                tooltips: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: (it, dt) => ' ' + dt.datasets[it.datasetIndex].label + ': ' + fmt(it.value),
                        footer: (items) => g.bertumpuk ? 'Total: ' + fmt(items.reduce((s, i) => s + Number(i.value), 0)) : ''
                    }
                },
                onHover: (e, els) => {
                    e.target.style.cursor = (dapatDrill && els.length) ? 'pointer' : 'default';
                },
                onClick: (e, els) => {
                    if (dapatDrill && els && els.length) drill(g.kode[els[0]._index]);
                }
            }
        });
        $('hintGrafik').textContent = dapatDrill ? 'Klik sebuah batang untuk melihat rinciannya.' : '';
    }

    function drill(kode) {
        if (!DATA) return;
        if (DATA.level === 'kota') {
            state.kdkec = kode;
            state.kddesa = state.idsls = '';
        } else if (DATA.level === 'kecamatan') {
            state.kddesa = kode;
            state.idsls = '';
        } else if (DATA.level === 'kelurahan') {
            state.idsls = kode;
        } else return;
        syncUi();
        terapkan(true);
    }

    function renderTeks(d) {
        const judul = 'Jumlah Usaha Menurut ' + d.unit;
        $('judulGrafik').textContent = judul + (d.kbli ? (d.kbli === '-' ? ' · tanpa KBLI' : ' · KBLI ' + d.kbli + (d.kbli.length < 5 ? '*' : '')) : '');
        $('lokasiGrafik').textContent = d.lokasi;
        $('lokasiPeta').textContent = d.lokasi;
    }

    // ---------------------------------------------------------------- peta
    const renderer = L.canvas({
        padding: 0.5,
        tolerance: 5
    });
    const map = L.map('peta', {
        renderer,
        preferCanvas: true,
        zoomSnap: 0.25
    }).setView([-3.2975, 102.862], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    let slsLayer = null,
        COUNTS = {},
        BREAKS = [];
    const layerById = {};

    const dalamScope = (id) => state.idsls ? id === state.idsls :
        state.kddesa ? (id.substr(4, 3) === state.kdkec && id.substr(7, 3) === state.kddesa) :
        state.kdkec ? id.substr(4, 3) === state.kdkec : true;

    function kelasUntuk(n) {
        return BREAKS.filter((t) => n > t).length;
    }

    function hitungBreaks(vals) {
        if (!vals.length) return [];
        const q = [0.2, 0.4, 0.6, 0.8].map((p) => vals[Math.floor(p * (vals.length - 1))]);
        return [...new Set(q)];
    }

    function styleSls(f) {
        const id = f.properties.idsls,
            n = COUNTS[id] || 0;
        if (!dalamScope(id)) return {
            color: '#ced4da',
            weight: 0.6,
            fillColor: '#f1f3f5',
            fillOpacity: 0.3
        };
        return {
            color: n ? '#c2410c' : '#adb5bd',
            weight: id === state.idsls ? 3 : 1,
            fillColor: n ? KELAS[Math.min(kelasUntuk(n), KELAS.length - 1)] : '#f8f9fa',
            fillOpacity: n ? 0.7 : 0.25
        };
    }

    const tip = (p) => '<b>' + esc(p.nmsls) + '</b><br>' + esc(p.nmdesa) + ' · ' + esc(p.nmkec) +
        '<br>' + (dalamScope(p.idsls) ? fmt(COUNTS[p.idsls] || 0) + ' usaha' : '<i>di luar filter</i>');

    const petaSiap = fetch(CFG.urlPeta).then((r) => {
        if (!r.ok) throw new Error('Peta wilayah tidak dapat dimuat');
        return r.json();
    }).then((gj) => {
        slsLayer = L.geoJSON(gj, {
            style: styleSls,
            onEachFeature: (f, layer) => {
                layerById[f.properties.idsls] = layer;
                layer.bindTooltip(() => tip(f.properties), {
                    sticky: true,
                    className: 'su-tip'
                });
            }
        }).addTo(map);
        map.fitBounds(slsLayer.getBounds(), {
            padding: [10, 10]
        });
    });

    const legendaEl = L.DomUtil.create('div', 'su-legend');
    const legenda = L.control({
        position: 'bottomright'
    });
    legenda.onAdd = () => legendaEl;
    legenda.addTo(map);

    function buatLegenda() {
        const el = legendaEl;
        let h = '<b>Usaha per RT/SLS</b>';
        if (!BREAKS.length && !Object.values(COUNTS).some((v) => v > 0)) {
            h += 'Tidak ada usaha';
        } else {
            const maks = Math.max(...Object.values(COUNTS), 0);
            const t = BREAKS;
            for (let i = 0; i <= t.length; i++) {
                const lo = i === 0 ? 1 : t[i - 1] + 1,
                    hi = i < t.length ? t[i] : maks;
                if (lo > hi) continue;
                h += '<div><i style="background:' + KELAS[Math.min(i, KELAS.length - 1)] + '"></i>' + fmt(lo) + (hi > lo ? ' – ' + fmt(hi) : '') + '</div>';
            }
        }
        if (CFG.bolehTitik) {
            h += '<div class="mt-1"><i class="bulat" style="background:#d9480f"></i>Titik usaha</div>' +
                '<div><i class="bulat" style="background:#adb5bd"></i>Titik tanpa data usaha</div>';
        }
        el.innerHTML = h;
    }

    function fitScope() {
        let b = null;
        Object.keys(layerById).forEach((id) => {
            if (!dalamScope(id)) return;
            const lb = layerById[id].getBounds();
            b = b ? b.extend(lb) : L.latLngBounds(lb.getSouthWest(), lb.getNorthEast());
        });
        if (b && b.isValid()) map.fitBounds(b, {
            padding: [24, 24],
            maxZoom: 18
        });
    }

    async function updatePeta(d, fit) {
        await petaSiap.catch(() => null);
        if (!slsLayer) return;
        COUNTS = d.sls || {};
        BREAKS = hitungBreaks(Object.values(COUNTS).filter((v) => v > 0).sort((a, b) => a - b));
        slsLayer.setStyle(styleSls);
        buatLegenda();
        if (fit) fitScope();
    }

    // ---------------------------------------------------------------- titik (admin)
    let ptsGroup = null,
        ctlTitik = null,
        seqT = 0,
        seqPop = 0;

    function setInfo(teks, jenis) {
        const el = $('infoTitik');
        if (!el) return;
        el.className = 'small mt-2 text-' + (jenis || 'muted');
        el.textContent = teks;
    }

    // penghitung ringkas di panel admin (hanya ada di halaman admin; aman bila elemennya tidak ada)
    function setCounter(id, v) {
        const el = $(id);
        if (el) el.textContent = v;
    }

    function resetCounter() {
        ['aTitikUsaha', 'aTitikTanpa', 'aUsahaTanpa'].forEach((id) => setCounter(id, '–'));
    }

    function hapusTitik() {
        if (ptsGroup) {
            map.removeLayer(ptsGroup);
            ptsGroup = null;
        }
        map.closePopup();
        resetCounter();
    }

    async function muatTitik() {
        const my = ++seqT;
        if (ctlTitik) ctlTitik.abort();
        hapusTitik();
        if (level() === 'kota') {
            setInfo('Pilih kecamatan, kelurahan, atau RT/SLS untuk menampilkan titik.');
            return;
        }
        if (!$('swTitik').checked) {
            setInfo('Titik disembunyikan.');
            return;
        }
        setInfo('Memuat titik…');
        ctlTitik = new AbortController();
        try {
            const d = await ambil(CFG.urlTitik, {
                ...params(),
                tanpa_usaha: $('cbTanpa').checked && !state.kbli ? 1 : ''
            }, ctlTitik.signal);
            if (my !== seqT) return;
            gambarTitik(d);
        } catch (e) {
            if (e.name === 'AbortError' || my !== seqT) return;
            setInfo((e.status === 401 || e.status === 403) ? 'Sesi berakhir atau tidak punya akses. Silakan login ulang.' : e.message, 'danger');
        }
    }

    function gambarTitik(d) {
        const arr = d.titik,
            layers = new Array(arr.length);
        for (let i = 0; i < arr.length; i++) {
            const [id, la, lo, m] = arr[i];
            layers[i] = L.circleMarker([la, lo], {
                radius: m ? 5 : 3.5,
                weight: m ? 1 : 0.5,
                color: m ? '#ffffff' : '#495057',
                fillColor: m ? '#d9480f' : '#adb5bd',
                fillOpacity: m ? 0.95 : 0.7,
                pid: id
            });
        }
        ptsGroup = L.featureGroup(layers).addTo(map);
        ptsGroup.on('click', (e) => {
            L.DomEvent.stopPropagation(e);
            bukaPopup(e.layer);
        });

        let t = 'Menampilkan ' + fmt(d.dengan_usaha) + ' titik usaha';
        if (d.tanpa_ditampilkan) t += ' + ' + fmt(d.tanpa_usaha) + ' titik tanpa data usaha (abu-abu)';
        t += '.';
        if (!d.tanpa_ditampilkan && d.tanpa_usaha) t += ' ' + fmt(d.tanpa_usaha) + ' titik lain belum punya data usaha (centang untuk menampilkan).';
        if (d.usaha_tanpa_titik) t += ' ' + fmt(d.usaha_tanpa_titik) + ' usaha di wilayah ini belum memiliki titik.';
        setInfo(t);
        setCounter('aTitikUsaha', fmt(d.dengan_usaha));
        setCounter('aTitikTanpa', d.tanpa_usaha === null ? 'n/a' : fmt(d.tanpa_usaha));
        setCounter('aUsahaTanpa', fmt(d.usaha_tanpa_titik));
    }

    async function bukaPopup(layer) {
        const my = ++seqPop;
        const pop = L.popup({
                maxWidth: 340,
                minWidth: 270,
                autoPanPadding: [20, 20]
            })
            .setLatLng(layer.getLatLng()).setContent('<div class="small text-muted">Memuat keterangan…</div>').openOn(map);
        try {
            const d = await ambil(CFG.urlDetail + '/' + layer.options.pid);
            if (my === seqPop && map.hasLayer(pop)) pop.setContent(htmlPopup(d));
        } catch (e) {
            if (my === seqPop && map.hasLayer(pop)) pop.setContent('<div class="text-danger small">' + esc(e.message) + '</div>');
        }
    }

    const PIN = '<svg width="14" height="14" viewBox="0 0 16 16" fill="#d9480f" aria-hidden="true"><path d="M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10m0-7a3 3 0 1 1 0-6 3 3 0 0 1 0 6"/></svg>';
const strip = (v) => (v === null || v === undefined || v === '') ? '–' : esc(v);

    function htmlPopup(d) {
        const koor = d.latitude.toFixed(6) + ', ' + d.longitude.toFixed(6);
        let h = '<div class="su-pop">';
        if (!d.usaha.length) {
            h += '<div class="su-kosong"><b>Belum ada data usaha</b><br>Titik ini belum memiliki data usaha pada hasil SE2026.' +
                '<dl><dt>Kecamatan</dt><dd>' + strip(d.kecamatan) + '</dd><dt>Kelurahan</dt><dd>' + strip(d.kelurahan) + '</dd><dt>RT (SLS)</dt><dd>' + strip(d.rt) + '</dd></dl></div>';
        } else {
            if (d.usaha.length > 1) h += '<div class="mb-1 text-muted">' + d.usaha.length + ' usaha pada titik ini' + (d.usaha.length > 2 ? ' · gulir untuk melihat semua' : '') + '</div>';
            h += '<div class="su-scroll">';
            d.usaha.forEach((u) => {
                h += '<div class="su-u"><div class="su-u-nama">' + esc(u.nama) + '<span class="su-badge">' + esc(u.skala) + '</span></div><dl>' +
                    '<dt>Kode KBLI</dt><dd>' + (u.kbli === '-' ? 'Tidak terisi' : esc(u.kbli)) + '</dd>' +
                    '<dt>Tahun mulai</dt><dd>' + strip(u.tahun_mulai) + '</dd>' +
                    '<dt>Kegiatan utama</dt><dd>' + strip(u.kegiatan) + '</dd>' +
                    '<dt>Kecamatan</dt><dd>' + strip(u.kecamatan) + '</dd>' +
                    '<dt>Kelurahan</dt><dd>' + strip(u.kelurahan) + '</dd></dl></div>';
            });
            h += '</div>';
        }
        h += '<div class="su-koor">' + PIN + '<span>' + esc(koor) + '</span>' +
            '<button type="button" class="su-copy" data-koor="' + esc(koor) + '">Salin</button></div>' +
            '<div class="su-id">assignment_id: ' + esc(d.assignment_id) + '</div></div>';
        return h;
    }

    async function salin(teks, btn) {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(teks);
            } else { // halaman http biasa: clipboard API tidak tersedia
                const ta = document.createElement('textarea');
                ta.value = teks;
                ta.style.cssText = 'position:fixed;opacity:0';
                document.body.appendChild(ta);
                ta.select();
                const ok = document.execCommand('copy');
                ta.remove();
                if (!ok) throw new Error('gagal');
            }
            btn.textContent = 'Tersalin ✓';
            btn.classList.add('ok');
            setTimeout(() => {
                btn.textContent = 'Salin';
                btn.classList.remove('ok');
            }, 1500);
        } catch (_) {
            window.prompt('Salin koordinat (Ctrl+C):', teks);
        }
    }
    map.getContainer().addEventListener('click', (ev) => {
        const b = ev.target.closest && ev.target.closest('.su-copy');
        if (b) salin(b.dataset.koor, b);
    });

    ['swTitik', 'cbTanpa'].forEach((id) => {
        const el = $(id);
        if (el) el.addEventListener('change', () => CFG.bolehTitik && muatTitik());
    });

    // ---------------------------------------------------------------- alur utama
    async function terapkan(fit) {
        const my = ++seq;
        tulisUrl();
        tampilError('');
        $('suRoot').classList.add('is-loading');
        try {
            const d = await ambil(CFG.urlData, params());
            if (my !== seq) return;
            DATA = d;
            renderKpi(d);
            renderChart(d);
            renderTeks(d);
            await updatePeta(d, fit);
        } catch (e) {
            if (my === seq) tampilError(e.message);
        } finally {
            if (my === seq) $('suRoot').classList.remove('is-loading');
        }
        if (CFG.bolehTitik && my === seq) muatTitik();
    }

    petaSiap.catch((e) => tampilError(e.message));
    isiKec();
    bacaUrl();
    syncUi();
    terapkan(true);
})();

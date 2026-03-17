// ============================================================
//  GPS.js — Logic trang cài đặt GPS Admin
// ============================================================

const GPS_ADMIN_API = '/PHP/ELite_GYM/Internal/Layout/Setting/GPS/GPS_function.php';

// ── Leaflet map ──────────────────────────────────────────────
let map, marker, circle;

// ── Init ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    initMap();
    loadStats();

    // Sync radius input → bản đồ
    document.getElementById('inpRadius').addEventListener('input', function() {
        const r = parseInt(this.value) || 100;
        document.getElementById('radiusDisplay').textContent = r + 'm';
        if (circle) circle.setRadius(r);
    });
});

// ════════════════════════════════════════════════════════════
// INIT MAP (Leaflet + CartoDB/OSM — luôn hoạt động, không cần API key)
// ════════════════════════════════════════════════════════════
function initMap() {
    const lat = GPS_INIT.lat;
    const lng = GPS_INIT.lng;
    const r   = GPS_INIT.radius;

    map = L.map('map', {
        center: [lat, lng],
        zoom:   17,
        zoomControl: true,
    });

    // Invalidate sau khi layout render xong (fix tile trống trong 2-col layout)
    setTimeout(() => map.invalidateSize(), 300);

    // ── CartoDB Dark Matter — hợp theme tối của admin, luôn hoạt động
    const cartoDark = L.tileLayer(
        'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
        {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> © <a href="https://carto.com/attributions">CARTO</a>',
            subdomains: 'abcd',
            maxZoom: 20,
        }
    );

    // ── CartoDB Voyager — đường phố màu sắc đẹp
    const cartoVoyager = L.tileLayer(
        'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
        {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> © <a href="https://carto.com/attributions">CARTO</a>',
            subdomains: 'abcd',
            maxZoom: 20,
        }
    );

    // ── OpenStreetMap tiêu chuẩn — luôn hoạt động
    const osmStandard = L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            subdomains: 'abc',
            maxZoom: 19,
        }
    );

    // Dùng CartoDB Dark làm mặc định (hợp theme admin)
    cartoDark.addTo(map);

    // Layer switcher
    L.control.layers({
        '🌑 Dark (CartoDB)':     cartoDark,
        '🗺️ Voyager (CartoDB)': cartoVoyager,
        '🌍 OpenStreetMap':      osmStandard,
    }, {}, { position: 'topright', collapsed: true }).addTo(map);

    // Sau khi chuyển layer, invalidate để tile load lại đúng
    map.on('baselayerchange', () => {
        setTimeout(() => map.invalidateSize(), 150);
    });

    // Custom gold marker icon
    const goldIcon = L.divIcon({
        html: `<div style="
            width:32px;height:32px;border-radius:50% 50% 50% 0;
            background:linear-gradient(135deg,#d4a017,#f0c040);
            border:3px solid #fff;
            transform:rotate(-45deg);
            box-shadow:0 4px 12px rgba(212,160,23,.6);
        "></div>`,
        iconSize:   [32, 32],
        iconAnchor: [16, 32],
        className:  '',
    });

    marker = L.marker([lat, lng], { icon: goldIcon, draggable: true }).addTo(map);
    circle = L.circle([lat, lng], {
        radius:      r,
        color:       '#d4a017',
        fillColor:   '#d4a017',
        fillOpacity: 0.12,
        weight:      2,
    }).addTo(map);

    // Popup
    marker.bindPopup(`<strong style="color:#d4a017">📍 ${GPS_INIT.name}</strong><br>
        Bán kính: ${r}m<br>Lat: ${lat}, Lng: ${lng}`).openPopup();

    // Kéo marker → cập nhật
    marker.on('dragend', e => {
        const pos = e.target.getLatLng();
        setCoords(pos.lat, pos.lng);
    });

    // Nhấp bản đồ → đặt vị trí
    map.on('click', e => {
        setCoords(e.latlng.lat, e.latlng.lng);
        showToast('ok', `Đã đặt vị trí: ${e.latlng.lat.toFixed(6)}, ${e.latlng.lng.toFixed(6)}`);
    });
}

// ════════════════════════════════════════════════════════════
// SET COORDS (cập nhật tất cả UI + bản đồ)
// ════════════════════════════════════════════════════════════
function setCoords(lat, lng) {
    lat = parseFloat(lat.toFixed(7));
    lng = parseFloat(lng.toFixed(7));

    // Update hidden inputs
    document.getElementById('inpLat').value = lat;
    document.getElementById('inpLng').value = lng;

    // Update display
    document.getElementById('dispLat').textContent = lat;
    document.getElementById('dispLng').textContent = lng;
    document.getElementById('coordDisplay').textContent = `${lat}, ${lng}`;

    // Update map
    if (marker) marker.setLatLng([lat, lng]);
    if (circle) circle.setLatLng([lat, lng]);
    if (map)    map.panTo([lat, lng]);

    const radius = parseInt(document.getElementById('inpRadius').value) || 100;
    const name   = document.getElementById('inpGymName').value || 'Elite Gym';
    if (marker) marker.setPopupContent(`<strong style="color:#d4a017">📍 ${name}</strong><br>
        Bán kính: ${radius}m<br>Lat: ${lat}, Lng: ${lng}`);
}

// ════════════════════════════════════════════════════════════
// USE MY LOCATION
// ════════════════════════════════════════════════════════════
function useMyLocation() {
    if (!navigator.geolocation) {
        showToast('err', 'Trình duyệt không hỗ trợ GPS!'); return;
    }
    const loader = document.getElementById('gpsLoading');
    loader.classList.remove('hidden');

    navigator.geolocation.getCurrentPosition(
        pos => {
            loader.classList.add('hidden');
            setCoords(pos.coords.latitude, pos.coords.longitude);
            map.setZoom(18);
            showToast('ok', `Đã lấy vị trí GPS! Độ chính xác: ±${Math.round(pos.coords.accuracy)}m`);
        },
        err => {
            loader.classList.add('hidden');
            const msgs = {1:'GPS bị từ chối', 2:'Không lấy được vị trí', 3:'Hết thời gian'};
            showToast('err', msgs[err.code] || 'Lỗi GPS');
        },
        { enableHighAccuracy: true, timeout: 12000 }
    );
}

// ════════════════════════════════════════════════════════════
// FOCUS MAP
// ════════════════════════════════════════════════════════════
function focusMap() {
    if (map) {
        document.getElementById('map').scrollIntoView({ behavior: 'smooth', block: 'center' });
        showToast('ok', 'Nhấp vào bản đồ để đặt vị trí phòng tập');
    }
}

// ════════════════════════════════════════════════════════════
// MANUAL INPUT
// ════════════════════════════════════════════════════════════
function openManualInput() {
    const row = document.getElementById('manualRow');
    row.classList.toggle('hidden');
    if (!row.classList.contains('hidden')) {
        document.getElementById('manLat').value = document.getElementById('inpLat').value;
        document.getElementById('manLng').value = document.getElementById('inpLng').value;
        document.getElementById('manLat').focus();
    }
}

function applyManual() {
    const lat = parseFloat(document.getElementById('manLat').value);
    const lng = parseFloat(document.getElementById('manLng').value);

    if (isNaN(lat) || isNaN(lng) || lat < -90 || lat > 90 || lng < -180 || lng > 180) {
        showToast('err', 'Tọa độ không hợp lệ! Lat: -90 đến 90, Lng: -180 đến 180');
        return;
    }

    setCoords(lat, lng);
    document.getElementById('manualRow').classList.add('hidden');
    showToast('ok', `Đã áp dụng tọa độ: ${lat.toFixed(6)}, ${lng.toFixed(6)}`);
}

// ════════════════════════════════════════════════════════════
// SAVE SETTINGS
// ════════════════════════════════════════════════════════════
async function saveSettings() {
    const btn    = document.getElementById('btnSave');
    const lat    = parseFloat(document.getElementById('inpLat').value);
    const lng    = parseFloat(document.getElementById('inpLng').value);
    const radius = parseInt(document.getElementById('inpRadius').value);
    const name   = document.getElementById('inpGymName').value.trim();
    const check  = document.getElementById('toggleLocCheck').checked ? 1 : 0;

    if (!lat || !lng) { showToast('err', 'Vui lòng đặt tọa độ phòng tập trên bản đồ!'); return; }
    if (radius < 50)  { showToast('err', 'Bán kính tối thiểu là 50m!'); return; }
    if (!name)        { showToast('err', 'Vui lòng nhập tên phòng tập!'); return; }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang lưu...';

    try {
        const fd = new FormData();
        fd.append('action',         'save_gym_location');
        fd.append('lat',            lat);
        fd.append('lng',            lng);
        fd.append('radius',         radius);
        fd.append('name',           name);
        fd.append('location_check', check);

        const res  = await fetch(GPS_ADMIN_API, { method: 'POST', body: fd });
        const text = await res.text();
        let data;
        try { data = JSON.parse(text); } catch(e) { throw new Error('Server error'); }
        if (data.ok) {
            showToast('ok', '✓ Đã lưu cài đặt GPS thành công!');
        } else {
            showToast('err', data.msg || 'Lưu thất bại!');
        }
    } catch(e) {
        showToast('err', 'Lỗi kết nối máy chủ!');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> LƯU CÀI ĐẶT GPS';
    }
}

// ════════════════════════════════════════════════════════════
// LOAD STATS
// ════════════════════════════════════════════════════════════
async function loadStats() {
    try {
        const today = new Date().toISOString().split('T')[0];
        const res   = await fetch(`${GPS_ADMIN_API}?action=get_stats&date=${today}`);
        const text  = await res.text();
        let d;
        try { d = JSON.parse(text); } catch(e) { return; }
        if (!d.ok) return;
        document.getElementById('sTotalEmp').textContent = d.total     ?? '0';
        document.getElementById('sPresent').textContent  = d.present   ?? '0';
        document.getElementById('sAbsent').textContent   = d.not_yet   ?? '0';
        document.getElementById('sGpsCount').textContent = d.gps_count ?? '0';
    } catch(e) {}
}

// ════════════════════════════════════════════════════════════
// TOAST
// ════════════════════════════════════════════════════════════
function showToast(type, msg) {
    const el = document.getElementById('toast');
    const icons = { ok: 'fa-check-circle', err: 'fa-times-circle' };
    el.className = `toast toast--${type} show`;
    el.innerHTML = `<i class="fas ${icons[type]||'fa-info-circle'}"></i> ${msg}`;
    clearTimeout(el._t);
    el._t = setTimeout(() => { el.classList.remove('show'); }, 4000);
}

// ════════════════════════════════════════════════════════════
// LOCATE ON MAP — nút định vị trên bản đồ
// ════════════════════════════════════════════════════════════
function locateOnMap() {
    if (!navigator.geolocation) {
        showToast('err', 'Trình duyệt không hỗ trợ GPS!'); return;
    }
    const btn = document.querySelector('.map-locate-btn');
    if (btn) btn.classList.add('locating');

    navigator.geolocation.getCurrentPosition(
        pos => {
            if (btn) btn.classList.remove('locating');
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            setCoords(lat, lng);
            map.setView([lat, lng], 18);
            // Hiện vòng tròn accuracy tạm thời
            const acc = pos.coords.accuracy;
            const accCircle = L.circle([lat, lng], {
                radius: acc, color: '#60a5fa',
                fillColor: '#60a5fa', fillOpacity: 0.06, weight: 1,
            }).addTo(map);
            setTimeout(() => map.removeLayer(accCircle), 4000);
            showToast('ok', `Đã định vị! Độ chính xác: ±${Math.round(acc)}m`);
        },
        err => {
            if (btn) btn.classList.remove('locating');
            const msgs = {1: 'GPS bị từ chối — hãy cho phép trong cài đặt trình duyệt', 2: 'Không lấy được vị trí', 3: 'Hết thời gian chờ'};
            showToast('err', msgs[err.code] || 'Lỗi GPS');
        },
        { enableHighAccuracy: true, timeout: 12000 }
    );
}

// ════════════════════════════════════════════════════════════
// SEARCH LOCATION — tìm kiếm địa điểm bằng Nominatim
// ════════════════════════════════════════════════════════════
let searchDebounce = null;

// Gọi khi nhấn Enter hoặc nhấn nút tìm
async function searchLocation() {
    const q = document.getElementById('mapSearchInput').value.trim();
    if (!q) return;
    await doSearch(q);
}

// Gọi khi nhấn Enter trong input
document.addEventListener('DOMContentLoaded', () => {
    const inp = document.getElementById('mapSearchInput');
    if (!inp) return;

    inp.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); searchLocation(); }
        if (e.key === 'Escape') closeSearchResults();
    });

    // Auto-suggest khi gõ (debounce 600ms)
    inp.addEventListener('input', () => {
        clearTimeout(searchDebounce);
        const q = inp.value.trim();
        if (q.length < 3) { closeSearchResults(); return; }
        searchDebounce = setTimeout(() => doSearch(q), 600);
    });
});

async function doSearch(q) {
    const box = document.getElementById('searchResults');
    box.classList.remove('hidden');
    box.innerHTML = `<div class="search-loading"><i class="fas fa-spinner fa-spin"></i> Đang tìm "${q}"...</div>`;

    try {
        // Dùng PHP proxy để tránh bị WAMP/firewall chặn external request từ browser
        const url = `${GPS_ADMIN_API}?action=search_location&q=${encodeURIComponent(q)}`;
        const res  = await fetch(url);
        const data = await res.json();

        if (!data.ok || !data.results || data.results.length === 0) {
            box.innerHTML = `<div class="search-no-result"><i class="fas fa-search-minus" style="color:var(--text-3);margin-right:6px"></i>Không tìm thấy địa điểm</div>`;
            return;
        }

        box.innerHTML = data.results.map((item) => {
            const parts  = item.display_name.split(',');
            const name   = parts.slice(0, 2).join(',').trim();
            const detail = parts.slice(2).join(',').trim();
            return `
            <div class="search-result-item" onclick="pickSearchResult(${item.lat}, ${item.lon}, '${escapeAttr(item.display_name)}')">
                <i class="fas fa-map-marker-alt sri-icon"></i>
                <div>
                    <div class="sri-name">${escHtml(name)}</div>
                    ${detail ? `<div class="sri-detail">${escHtml(detail)}</div>` : ''}
                </div>
            </div>`;
        }).join('');

    } catch(e) {
        box.innerHTML = `<div class="search-no-result" style="color:var(--red)"><i class="fas fa-wifi" style="margin-right:6px"></i>Lỗi kết nối — kiểm tra internet</div>`;
    }
}

function pickSearchResult(lat, lng, displayName) {
    setCoords(parseFloat(lat), parseFloat(lng));
    map.setView([lat, lng], 17);
    closeSearchResults();
    document.getElementById('mapSearchInput').value = displayName.split(',').slice(0,2).join(',').trim();
    showToast('ok', `Đã chọn: ${displayName.split(',')[0].trim()}`);
}

function closeSearchResults() {
    const box = document.getElementById('searchResults');
    if (box) { box.classList.add('hidden'); box.innerHTML = ''; }
}

// Đóng khi click ra ngoài
document.addEventListener('click', e => {
    if (!e.target.closest('.map-search-wrap') && !e.target.closest('#searchResults')) {
        closeSearchResults();
    }
});

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escapeAttr(str) {
    return String(str).replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

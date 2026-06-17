// Admin SPA routing — MVC public/
const ELITE_BASE = window.ELITE_BASE || '';

// Global error logging to aid debugging in development
window.addEventListener('error', event => {
    try { console.error('Uncaught error:', event.error || event.message, event); } catch (e) {}
    if (typeof showToast === 'function') showToast('Lỗi nội bộ - kiểm tra console', 'error');
});
window.addEventListener('unhandledrejection', event => {
    try { console.error('Unhandled promise rejection:', event.reason); } catch (e) {}
    if (typeof showToast === 'function') showToast('Lỗi bất ngờ - kiểm tra console', 'error');
});

const menuRoutes = {
    'overview.php':              ELITE_BASE + '/admin/module/overview',
    'facilities.php':            ELITE_BASE + '/admin/module/facility',
    'promotion.php':             ELITE_BASE + '/admin/module/promotion',
    'management_invoice.php':    ELITE_BASE + '/admin/module/invoice',
    'Account_Management.php':    ELITE_BASE + '/admin/module/account',
    'management_statistics.php': ELITE_BASE + '/admin/module/statistics',
    'Schedule_Management.php':   ELITE_BASE + '/admin/module/schedule',
    'management_package.php':    ELITE_BASE + '/admin/module/package',
    'customer.php':              ELITE_BASE + '/admin/module/customer',
    'review.php':                ELITE_BASE + '/admin/module/review',
    'management_staff.php':      ELITE_BASE + '/admin/module/employee',
    'management_branch.php':     ELITE_BASE + '/admin/module/gym',
    'System.php':                ELITE_BASE + '/admin/module/setting-system',
    'landing_image.php':         ELITE_BASE + '/admin/module/setting-landing',
    'GPS.php':                   ELITE_BASE + '/admin/module/setting-gps',
};

const apiRoutes = {
    customer: ELITE_BASE + '/api/admin/customer',
    review: ELITE_BASE + '/api/admin/review',
    employee: ELITE_BASE + '/api/admin/employee',
    attendance: ELITE_BASE + '/api/admin/employee-attendance',
    facility: ELITE_BASE + '/api/admin/facility',
    gym: ELITE_BASE + '/api/admin/gym',
    invoice: ELITE_BASE + '/api/admin/invoice',
    package: ELITE_BASE + '/api/admin/package',
    promotion: ELITE_BASE + '/api/admin/promotion',
    schedule: ELITE_BASE + '/api/admin/schedule',
    statistics: ELITE_BASE + '/api/admin/statistics',
    account: ELITE_BASE + '/api/admin/account',
    system: ELITE_BASE + '/api/admin/setting/system',
    gps: ELITE_BASE + '/api/admin/setting/gps',
    landing: ELITE_BASE + '/api/admin/setting/landing',
};

const pageMeta = {
    'overview.php':              { title: 'TỔNG QUAN',               breadcrumb: 'Trang chủ / Tổng quan',        icon: 'fa-th-large',      color: 'rgba(59,130,246,0.25)',   iconColor: '#60a5fa' },
    'customer.php':              { title: 'QUẢN LÝ KHÁCH HÀNG',      breadcrumb: 'Trang chủ / Khách hàng',       icon: 'fa-users',         color: 'rgba(90,50,180,0.25)',    iconColor: '#a78bfa' },
    'review.php':                { title: 'QUẢN LÝ ĐÁNH GIÁ',        breadcrumb: 'Trang chủ / Đánh giá',         icon: 'fa-star',          color: 'rgba(245,158,11,0.2)',    iconColor: '#f59e0b' },
    'management_staff.php':      { title: 'QUẢN LÝ NHÂN VIÊN',       breadcrumb: 'Trang chủ / Nhân viên',        icon: 'fa-user-tie',      color: 'rgba(34,197,94,0.2)',     iconColor: '#4ade80' },
    'management_branch.php':     { title: 'QUẢN LÝ PHÒNG TẬP',       breadcrumb: 'Trang chủ / Phòng tập',        icon: 'fa-chess-board',   color: 'rgba(249,115,22,0.2)',    iconColor: '#fb923c' },
    'management_invoice.php':    { title: 'QUẢN LÝ HOÁ ĐƠN',         breadcrumb: 'Trang chủ / Hoá đơn',          icon: 'fa-receipt',       color: 'rgba(234,179,8,0.2)',     iconColor: '#facc15' },
    'management_package.php':    { title: 'QUẢN LÝ GÓI TẬP',         breadcrumb: 'Trang chủ / Gói tập',          icon: 'fa-times-circle',  color: 'rgba(239,68,68,0.2)',     iconColor: '#f87171' },
    'Schedule_Management.php':   { title: 'QUẢN LÝ LỊCH TẬP',        breadcrumb: 'Trang chủ / Lịch tập',         icon: 'fa-calendar-alt',  color: 'rgba(20,184,166,0.2)',    iconColor: '#2dd4bf' },
    'facilities.php':            { title: 'CƠ SỞ VẬT CHẤT',          breadcrumb: 'Trang chủ / Cơ sở vật chất',  icon: 'fa-tools',         color: 'rgba(168,85,247,0.2)',    iconColor: '#c084fc' },
    'promotion.php':             { title: 'QUẢN LÝ KHUYẾN MÃI',      breadcrumb: 'Trang chủ / Khuyến mãi',       icon: 'fa-tag',           color: 'rgba(236,72,153,0.2)',    iconColor: '#f472b6' },
    'Account_Management.php':    { title: 'QUẢN LÝ HỆ THỐNG',        breadcrumb: 'Trang chủ / Hệ thống',         icon: 'fa-cog',           color: 'rgba(100,116,139,0.25)', iconColor: '#94a3b8' },
    'management_statistics.php': { title: 'THỐNG KÊ BÁO CÁO',        breadcrumb: 'Trang chủ / Thống kê',          icon: 'fa-chart-bar',     color: 'rgba(234,179,8,0.2)',     iconColor: '#fbbf24' },
    'System.php':                { title: 'Hệ thống',                breadcrumb: 'Trang chủ / Cài đặt / Hệ Thống', icon: 'fa-cogs',        color: 'rgba(212,160,23,0.2)',    iconColor: '#d4a017' },
    'landing_image.php':         { title: 'ẢNH SLIDESHOW TRANG CHỦ', breadcrumb: 'Trang chủ / Cài đặt / Landing', icon: 'fa-images',        color: 'rgba(212,160,23,0.2)',    iconColor: '#d4a017' },
    'GPS.php':                   { title: 'GPS',                     breadcrumb: 'Trang chủ / Cài đặt / GPS',     icon: 'fa-map-marker-alt', color: 'rgba(212,160,23,0.2)',    iconColor: '#d4a017' }
};

var contentWrapper = document.getElementById('content-wrapper');

function resolveAssetCss(href) {
    if (!href || href.startsWith('http') || href.startsWith('//') || href.includes('fonts.googleapis')) return href;
    if (href.startsWith(ELITE_BASE)) return href;
    const name = href.split('/').pop();
    return ELITE_BASE + '/assets/css/' + name;
}

function resolveAssetJs(src) {
    if (!src || src.startsWith('http') || src.startsWith('//')) return src;
    if (src.startsWith(ELITE_BASE)) return src;
    const name = src.split('/').pop();
    return ELITE_BASE + '/assets/js/' + name;
}

function injectModuleHtml(html) {
    const doc = new DOMParser().parseFromString(html, 'text/html');

    // ✅ FIX: Xóa CSS của module cũ trước khi inject module mới.
    // Nếu không xóa, CSS tích lũy từ tất cả module đã từng mở
    // và đè lên nhau gây sai style (màu nút, border, layout...).
    document.querySelectorAll('link[data-module-href]').forEach(el => el.remove());

    doc.querySelectorAll('link[rel="stylesheet"]').forEach(link => {
        const href = resolveAssetCss(link.getAttribute('href'));
        if (!href || href.includes('font-awesome')) return;
        if (document.querySelector(`link[data-module-href="${href}"]`)) return;
        const l = document.createElement('link');
        l.rel = 'stylesheet';
        l.href = href;
        l.dataset.moduleHref = href;
        document.head.appendChild(l);
    });

    contentWrapper.innerHTML = doc.body ? doc.body.innerHTML : html;
}

const _cdnScriptsLoaded = window.__eliteCdnScripts || new Set();
window.__eliteCdnScripts = _cdnScriptsLoaded;

function isCdnScript(url) {
    return /^https?:\/\//i.test(url) && !url.includes('/assets/js/');
}

async function runModuleScript(src, inlineCode) {
    if (inlineCode) {
        const s = document.createElement('script');
        // Wrap trong IIFE để const/let không bị "already declared" khi SPA navigate lại.
        // Các biến cần global (IS_ADMIN, IS_HLV...) phải dùng window.X = ... trong view PHP.
        s.textContent = ';(function(){\n' + inlineCode + '\n})();';
        document.body.appendChild(s);
        return;
    }
    const url = resolveAssetJs(src);
    if (isCdnScript(url)) {
        if (_cdnScriptsLoaded.has(url)) return;
        // ✅ FIX: CSP chặn script-src từ CDN (cdn.jsdelivr.net, cdnjs...).
        // Giải pháp: fetch nội dung script về, inject dưới dạng inline.
        // CSP có 'unsafe-inline' nên inline script được phép.
        try {
            const res = await fetch(url);
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const code = await res.text();
            const s = document.createElement('script');
            s.textContent = code;
            document.body.appendChild(s);
            _cdnScriptsLoaded.add(url);
        } catch(e) {
            console.warn('[adm] CDN script failed, trying src fallback:', url, e);
            // Fallback: thử load bằng src bình thường (hoạt động nếu không có CSP)
            await new Promise(resolve => {
                const s = document.createElement('script');
                s.src = url;
                s.onload = () => { _cdnScriptsLoaded.add(url); resolve(); };
                s.onerror = resolve;
                document.body.appendChild(s);
            });
        }
        return;
    }
    await new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = url + (url.includes('?') ? '&' : '?') + '_=' + Date.now();
        // ensure execution order for module scripts
        s.async = false;
        s.onload = resolve;
        s.onerror = () => reject(new Error('Failed loading ' + url));
        document.body.appendChild(s);
    });
}

async function rebindModuleScripts() {
    const scripts = [...contentWrapper.querySelectorAll('script')];
    for (const old of scripts) {
        const src = old.getAttribute('src');
        const inline = old.textContent?.trim();
        old.remove();
        try {
            await runModuleScript(src, src ? null : inline);
        } catch (e) {
            console.error('Script load failed:', src, e);
        }
    }
}

function updateClock() {
    const el = document.getElementById('topbar-time');
    if (!el) return;
    const now = new Date();
    el.textContent = [now.getHours(), now.getMinutes(), now.getSeconds()]
        .map(n => String(n).padStart(2, '0')).join(':');
}
setInterval(updateClock, 1000);
updateClock();

async function loadCustomerBadge() {
    try {
        const res = await fetch(apiRoutes.customer + '?action=get_stats');
        const d = await res.json();
        if (!d.success) return;
        const badge = document.getElementById('customerBadge');
        if (!badge) return;
        const count = d.new_month || 0;
        badge.style.display = count > 0 ? 'flex' : 'none';
        if (count > 0) badge.textContent = count > 99 ? '99+' : count;
    } catch (e) {
        const badge = document.getElementById('customerBadge');
        if (badge) badge.style.display = 'none';
    }
}
loadCustomerBadge();

function updateTopbar(page) {
    const meta = pageMeta[page];
    if (!meta) return;
    const titleEl = document.getElementById('page-title');
    const breadEl = document.getElementById('breadcrumb');
    const iconEl  = document.getElementById('page-icon');
    const wrapEl  = document.getElementById('page-icon-wrap');
    if (titleEl) titleEl.textContent = meta.title;
    if (breadEl) breadEl.textContent = meta.breadcrumb;
    if (iconEl)  iconEl.className = 'fas ' + meta.icon;
    if (wrapEl)  wrapEl.style.background = meta.color;
    if (iconEl)  iconEl.style.color = meta.iconColor;
}

async function loadPage(page) {
    const url = menuRoutes[page];
    if (!url || !contentWrapper) return;

    // Clone để xóa tất cả event listener của module cũ (System.js, Image_landing.js...)
    const fresh = contentWrapper.cloneNode(false);
    contentWrapper.parentNode.replaceChild(fresh, contentWrapper);
    contentWrapper = fresh;

    contentWrapper.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>';
    try {
        const res = await fetch(url);
        const html = await res.text();
        injectModuleHtml(html);
        updateTopbar(page);
        await rebindModuleScripts();
    } catch (e) {
        console.error(e);
        contentWrapper.innerHTML = '<div class="error-msg">Không thể tải module</div>';
    }
}

document.querySelectorAll('.nav-item[data-page]').forEach(item => {
    item.addEventListener('click', e => {
        e.preventDefault();
        const page = item.dataset.page;
        if (!page) return;
        setActiveNav(page);
        loadPage(page);
        if (history.replaceState) {
            history.replaceState(null, '', '#' + page);
        }
    });
});

const DEFAULT_PAGE = 'overview.php';

function setActiveNav(page) {
    document.querySelectorAll('.nav-item[data-page]').forEach(n => {
        n.classList.toggle('active', n.dataset.page === page);
    });
}

/** Vào /admin luôn mở Tổng quan (không giữ #review.php / #customer.php cũ trong URL). */
function bootAdmin(page) {
    if (!menuRoutes[page]) page = DEFAULT_PAGE;
    if (history.replaceState) {
        history.replaceState(null, '', '#' + page);
    } else {
        location.hash = page;
    }
    setActiveNav(page);
    loadPage(page);
}

bootAdmin(DEFAULT_PAGE);

window.addEventListener('hashchange', () => {
    const page = location.hash.replace('#', '');
    if (!page || !menuRoutes[page]) return;
    setActiveNav(page);
    loadPage(page);
});

document.getElementById('groupSetting')?.querySelector('.nav-parent')?.addEventListener('click', e => {
    e.preventDefault();
    document.getElementById('groupSetting')?.classList.toggle('open');
});

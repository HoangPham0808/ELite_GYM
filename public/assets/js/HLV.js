// ── DOM-ready helper (chạy đúng dù script inject tĩnh hay động) ──
if (typeof runWhenReady === 'undefined') {
    window.runWhenReady = function(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    };
}

// ============ ROUTING ============
var BASE = window.ELITE_BASE || '';

var menuRoutes = {
    'customer.php':            BASE + '/admin/module/customer',
    'Schedule_Management.php': BASE + '/admin/module/schedule',
    'facilities.php':          BASE + '/admin/module/facility',
    'Profile.php':             BASE + '/admin/module/profile'
};

// Page meta: title, breadcrumb, icon, color
var pageMeta = {
    'customer.php':            { title: 'QUẢN LÝ KHÁCH HÀNG', breadcrumb: 'Trang chủ / Khách hàng',      icon: 'fa-users',        color: 'rgba(124,58,237,.08)', iconColor: '#7c3aed' },
    'Schedule_Management.php': { title: 'QUẢN LÝ LỊCH TẬP',   breadcrumb: 'Trang chủ / Lịch tập',       icon: 'fa-calendar-alt', color: 'rgba(15,118,110,.08)', iconColor: '#0f766e' },
    'facilities.php':          { title: 'CƠ SỞ VẬT CHẤT',      breadcrumb: 'Trang chủ / Cơ sở vật chất', icon: 'fa-tools',        color: 'rgba(124,58,237,.08)', iconColor: '#7c3aed' },
    'Profile.php':             { title: 'HỒ SƠ CÁ NHÂN',       breadcrumb: 'Trang chủ / Hồ sơ cá nhân', icon: 'fa-user',         color: 'rgba(0,0,0,.05)',      iconColor: '#4b5563' }
};

var contentWrapper = document.getElementById('content-wrapper');

// ============ TOPBAR CLOCK ============
function updateClock() {
    const el = document.getElementById('topbar-time');
    if (!el) return;
    const now = new Date();
    el.textContent = [now.getHours(), now.getMinutes(), now.getSeconds()]
        .map(n => String(n).padStart(2,'0')).join(':');
}
setInterval(updateClock, 1000);
updateClock();

// ============ UPDATE TOPBAR ============
function updateTopbar(page) {
    const meta = pageMeta[page] || pageMeta['customer.php'];
    document.getElementById('page-title').textContent     = meta.title;
    document.getElementById('breadcrumb').textContent     = meta.breadcrumb;
    const iconWrap = document.getElementById('page-icon-wrap');
    const icon     = document.getElementById('page-icon');
    iconWrap.style.background  = meta.color;
    iconWrap.style.borderColor = meta.iconColor + '33';
    icon.className = 'fas ' + meta.icon;
    icon.style.color = meta.iconColor;
}

// ============ ACTIVE MENU ============
function updateActiveMenu(page) {
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.toggle('active', item.getAttribute('data-page') === page);
    });
}

// ============ LOAD CONTENT ============
async function loadContent(page) {
    const url = menuRoutes[page];
    if (!url) { console.error('Route not found:', page); return; }

    updateTopbar(page);
    contentWrapper.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>';

    try {
        const res = await fetch(url, { credentials: 'include' });
        if (!res.ok) throw new Error('HTTP ' + res.status);

        const html = await res.text();
        const doc  = new DOMParser().parseFromString(html, 'text/html');

        // Inject CSS — xoá CSS module cũ, inject CSS mới tránh xung đột
        document.querySelectorAll('link[data-module-css]').forEach(l => l.remove());
        doc.querySelectorAll('link[rel="stylesheet"]').forEach(link => {
            const href = link.getAttribute('href');
            if (href) {
                const el = document.createElement('link');
                el.rel = 'stylesheet'; el.href = href;
                el.setAttribute('data-module-css', '1');
                document.head.appendChild(el);
            }
        });

        // Inject JS — xoá module cũ, inject inline scripts trước, rồi external scripts
        document.querySelectorAll('script[data-module]').forEach(s => s.remove());

        // 1. Inline scripts trước (window.IS_HLV, IS_ADMIN, HLV_EMP_ID...)
        doc.querySelectorAll('script:not([src])').forEach(s => {
            const el = document.createElement('script');
            el.setAttribute('data-module', '1');
            el.textContent = s.textContent;
            document.body.appendChild(el);
        });

        // 2. External scripts sau
        doc.querySelectorAll('script[src]').forEach(s => {
            const el = document.createElement('script');
            el.src = s.getAttribute('src');
            el.setAttribute('data-module', '1');
            document.body.appendChild(el);
        });

        contentWrapper.style.opacity = '0';
        contentWrapper.innerHTML = doc.body.innerHTML;
        setTimeout(() => {
            contentWrapper.style.transition = 'opacity 0.25s ease';
            contentWrapper.style.opacity = '1';
        }, 10);

    } catch (err) {
        contentWrapper.innerHTML = `
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Không thể tải nội dung</h3>
                <p>Đã xảy ra lỗi khi tải trang.</p>
                <p style="font-size:11px;color:var(--tm)">Lỗi: ${err.message}</p>
                <button onclick="location.reload()">Tải lại trang</button>
            </div>`;
    }
}

// ============ CLICK EVENTS ============
document.querySelectorAll('.nav-item a').forEach(link => {
    link.addEventListener('click', e => {
        e.preventDefault();
        const href = link.getAttribute('href').replace(/^#/, '');
        updateActiveMenu(href);
        loadContent(href);
        window.location.hash = href;
    });
});

// ============ INIT ============
runWhenReady(() => {
    const hash        = window.location.hash.replace(/^#/, '');
    const defaultPage = 'customer.php';
    const page        = (hash && menuRoutes[hash]) ? hash : defaultPage;
    updateActiveMenu(page);
    loadContent(page);
    if (!hash) window.location.hash = page;
});

// ============ HASHCHANGE ============
window.addEventListener('hashchange', () => {
    const hash = window.location.hash.replace(/^#/, '');
    if (hash && menuRoutes[hash]) {
        updateActiveMenu(hash);
        loadContent(hash);
    } else {
        window.location.hash = 'customer.php';
    }
});

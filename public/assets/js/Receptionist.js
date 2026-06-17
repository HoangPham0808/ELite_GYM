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
    'management_invoice.php': BASE + '/admin/module/invoice',
    'management_package.php': BASE + '/admin/module/package',
    'promotion.php':          BASE + '/admin/module/promotion',
    'customer.php':           BASE + '/admin/module/customer',
    'facilities.php':         BASE + '/admin/module/facility',
    'profile.php':            BASE + '/admin/module/profile'
};

var pageMeta = {
    'management_invoice.php': { title: 'QUẢN LÝ HOÁ ĐƠN',   breadcrumb: 'Trang chủ / Hoá đơn',         icon: 'fa-receipt',     color: 'rgba(181,137,15,.1)',  iconColor: '#d4a017' },
    'management_package.php': { title: 'QUẢN LÝ GÓI TẬP',   breadcrumb: 'Trang chủ / Gói tập',         icon: 'fa-box-open',    color: 'rgba(220,38,38,.08)',  iconColor: '#dc2626' },
    'promotion.php':          { title: 'QUẢN LÝ KHUYẾN MÃI', breadcrumb: 'Trang chủ / Khuyến mãi',      icon: 'fa-tag',         color: 'rgba(219,39,119,.08)', iconColor: '#ec4899' },
    'customer.php':           { title: 'QUẢN LÝ KHÁCH HÀNG', breadcrumb: 'Trang chủ / Khách hàng',      icon: 'fa-users',       color: 'rgba(124,58,237,.08)', iconColor: '#7c3aed' },
    'facilities.php':         { title: 'CƠ SỞ VẬT CHẤT',     breadcrumb: 'Trang chủ / Cơ sở vật chất', icon: 'fa-tools',       color: 'rgba(194,65,12,.08)',  iconColor: '#c2410c' },
    'profile.php':            { title: 'HỒ SƠ CÁ NHÂN',      breadcrumb: 'Trang chủ / Hồ sơ',           icon: 'fa-user-circle', color: 'rgba(29,78,216,.08)',  iconColor: '#1d4ed8' }
};

var contentWrapper = document.getElementById('content-wrapper');

// ============ CLOCK ============
function updateClock() {
    const el = document.getElementById('topbar-time');
    if (!el) return;
    const now = new Date();
    el.textContent = [now.getHours(), now.getMinutes(), now.getSeconds()]
        .map(n => String(n).padStart(2,'0')).join(':');
}
setInterval(updateClock, 1000);
updateClock();

// ============ TOPBAR UPDATE ============
function updateTopbar(page) {
    const meta = pageMeta[page] || pageMeta['management_invoice.php'];
    document.getElementById('page-title').textContent = meta.title;
    const bc = document.getElementById('breadcrumb');
    if (bc) bc.textContent = meta.breadcrumb;
    const iconWrap = document.getElementById('page-icon-wrap');
    const icon     = document.getElementById('page-icon');
    if (iconWrap) {
        iconWrap.style.background  = meta.color;
        iconWrap.style.borderColor = meta.iconColor + '44';
    }
    if (icon) {
        icon.className = 'fas ' + meta.icon;
        icon.style.color = meta.iconColor;
    }
}

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

        // Inject JS
        document.querySelectorAll('script[data-module]').forEach(s => s.remove());
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
                <p>${err.message}</p>
                <button onclick="location.reload()">Tải lại trang</button>
            </div>`;
    }
    closeSidebar();
}

// ============ NAV CLICK ============
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
    injectMobileSidebarControls();
    const hash = window.location.hash.replace(/^#/, '');
    const page = (hash && menuRoutes[hash]) ? hash : 'management_invoice.php';
    updateActiveMenu(page);
    loadContent(page);
    if (!hash) window.location.hash = page;
});

window.addEventListener('hashchange', () => {
    const hash = window.location.hash.replace(/^#/, '');
    if (hash && menuRoutes[hash]) { updateActiveMenu(hash); loadContent(hash); }
    else window.location.hash = 'management_invoice.php';
});

// ============ MOBILE SIDEBAR ============
function injectMobileSidebarControls() {
    const topbarLeft = document.querySelector('.topbar-left');
    if (topbarLeft && !document.getElementById('hamburger-btn')) {
        const btn = document.createElement('button');
        btn.id = 'hamburger-btn';
        btn.className = 'topbar-hamburger';
        btn.setAttribute('aria-label', 'Mở menu');
        btn.innerHTML = '<i class="fas fa-bars"></i>';
        btn.addEventListener('click', toggleSidebar);
        topbarLeft.insertBefore(btn, topbarLeft.firstChild);
    }
    if (!document.getElementById('sidebar-backdrop')) {
        const bd = document.createElement('div');
        bd.id = 'sidebar-backdrop';
        bd.className = 'sidebar-backdrop';
        bd.addEventListener('click', closeSidebar);
        document.body.appendChild(bd);
    }
}

function toggleSidebar() {
    const sidebar  = document.querySelector('.sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    if (!sidebar) return;
    const isOpen = sidebar.classList.toggle('open');
    if (backdrop) backdrop.classList.toggle('visible', isOpen);
    const icon = document.getElementById('hamburger-btn')?.querySelector('i');
    if (icon) { icon.classList.toggle('fa-times', isOpen); icon.classList.toggle('fa-bars', !isOpen); }
}

function closeSidebar() {
    const sidebar  = document.querySelector('.sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    if (!sidebar) return;
    sidebar.classList.remove('open');
    if (backdrop) backdrop.classList.remove('visible');
    const icon = document.getElementById('hamburger-btn')?.querySelector('i');
    if (icon) { icon.classList.remove('fa-times'); icon.classList.add('fa-bars'); }
}

var mq = window.matchMedia('(min-width: 601px)');
function handleMQ(e) {
    const btn = document.getElementById('hamburger-btn');
    if (btn) btn.style.display = e.matches ? 'none' : '';
    if (e.matches) closeSidebar();
}
mq.addEventListener('change', handleMQ);
runWhenReady(() => handleMQ(mq));

// ============ ROUTING ============
const menuRoutes = {
    'overview.php':              '../layout/overview/overview.php',
    'facilities.php':            '../layout/Facilities_Management/Facilities_Management.php',
    'promotion.php':             '../layout/Promotion_Management/Promotion_Management.php',
    'management_invoice.php':    '../layout/Invoice_Management/Invoice_Management.php',
    'management_support.php':    '../layout/management_support/management_support.php',
    'Account_Management.php':    '../layout/Account_Management/Account_Management.php',
    'management_statistics.php': '../layout/management_statistics/management_statistics.php',
    'Schedule_Management.php':   '../layout/Schedule_Management/Schedule_Management.php',
    'management_package.php':    '../layout/Package_Management/Package_Management.php',
    'customer.php':              '../layout/Customer_Management/Customer_Management.php',
    'review.php':                '../layout/Review_Management/Review_Management.php',
    'management_staff.php':      '../layout/Employee_Management/Employee_Management.php',
    'management_branch.php':     '../layout/Gym_Management/Gym_Management.php',
    'System.php':                '../Layout/Setting/System/System.php',
    'landing_image.php':         '../Layout/Setting/Image_landing/Image_landing.php',
    'GPS.php':                   '../Layout/Setting/GPS/GPS.php'
};

// Page meta: title, breadcrumb, icon, icon-bg-color
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
    'management_support.php':    { title: 'HỖ TRỢ NGƯỜI DÙNG',       breadcrumb: 'Trang chủ / Hỗ trợ',           icon: 'fa-headset',       color: 'rgba(14,165,233,0.2)',    iconColor: '#38bdf8' },
    'Account_Management.php':    { title: 'QUẢN LÝ HỆ THỐNG',        breadcrumb: 'Trang chủ / Hệ thống',         icon: 'fa-cog',           color: 'rgba(100,116,139,0.25)', iconColor: '#94a3b8' },
    'management_statistics.php': { title: 'THỐNG KÊ BÁO CÁO',        breadcrumb: 'Trang chủ / Thống kê',          icon: 'fa-chart-bar',     color: 'rgba(234,179,8,0.2)',     iconColor: '#fbbf24' },
    'System.php':                { title: 'Hệ thống',                breadcrumb: 'Trang chủ / Cài đặt / Hệ Thống', icon: 'fa-cogs',        color: 'rgba(212,160,23,0.2)',    iconColor: '#d4a017' },
    'landing_image.php':         { title: 'ẢNH SLIDESHOW TRANG CHỦ', breadcrumb: 'Trang chủ / Cài đặt / Landing', icon: 'fa-images',        color: 'rgba(212,160,23,0.2)',    iconColor: '#d4a017' },
    'GPS.php':                   { title: 'GPS',                     breadcrumb: 'Trang chủ / Cài đặt / GPS',     icon: 'fa-map-marker-alt', color: 'rgba(212,160,23,0.2)',    iconColor: '#d4a017' }
};

const contentWrapper = document.getElementById('content-wrapper');

// ============ TOPBAR CLOCK ============
function updateClock() {
    const el = document.getElementById('topbar-time');
    if (!el) return;
    const now = new Date();
    const h = String(now.getHours()).padStart(2, '0');
    const m = String(now.getMinutes()).padStart(2, '0');
    const s = String(now.getSeconds()).padStart(2, '0');
    el.textContent = `${h}:${m}:${s}`;
}
setInterval(updateClock, 1000);
updateClock();

// ============ LOAD CUSTOMER BADGE (new members this month) ============
async function loadCustomerBadge() {
    try {
        const res = await fetch('../layout/Customer_Management/Customer_Management_function.php?action=get_stats');
        const d = await res.json();
        if (!d.success) return;
        const badge = document.getElementById('customerBadge');
        if (!badge) return;
        const count = d.new_month || 0;
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    } catch(e) {
        // Hide badge if unable to load
        const badge = document.getElementById('customerBadge');
        if (badge) badge.style.display = 'none';
    }
}
loadCustomerBadge();

// ============ UPDATE TOPBAR ============
function updateTopbar(page) {
    const meta = pageMeta[page] || pageMeta['overview.php'];
    document.getElementById('page-title').textContent = meta.title;
    document.getElementById('breadcrumb').textContent = meta.breadcrumb;

    const iconWrap = document.getElementById('page-icon-wrap');
    const icon = document.getElementById('page-icon');

    iconWrap.style.background = meta.color;
    iconWrap.style.borderColor = meta.iconColor + '33';

    // Remove all fa- classes
    icon.className = icon.className.replace(/fa-\S+/g, '').trim();
    icon.classList.add('fas', meta.icon);
    icon.style.color = meta.iconColor;
}

// ============ ACTIVE MENU ============
function updateActiveMenu(page) {
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
        if (item.getAttribute('data-page') === page) {
            item.classList.add('active');
        }
    });
}

// ============ LOAD CONTENT ============
async function loadContent(page) {
    const filePath = menuRoutes[page];
    if (!filePath) {
        console.error('Route not found for:', page);
        return;
    }

    updateTopbar(page);

    if (filePath.endsWith('.php')) {
        contentWrapper.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

        const iframe = document.createElement('iframe');
        iframe.src = filePath;
        iframe.onload = () => console.log('PHP loaded:', filePath);
        iframe.onerror = () => console.error('Failed:', filePath);

        contentWrapper.innerHTML = '';
        contentWrapper.appendChild(iframe);
        return;
    }

    // HTML content
    contentWrapper.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';

    try {
        const res = await fetch(filePath);
        if (!res.ok) throw new Error('HTTP ' + res.status);

        const html = await res.text();
        const doc = new DOMParser().parseFromString(html, 'text/html');

        // Dynamic CSS
        const oldCss = document.getElementById('dynamic-css');
        if (oldCss) oldCss.remove();
        const cssLink = document.createElement('link');
        cssLink.rel = 'stylesheet';
        cssLink.href = filePath.replace('.html', '.css');
        cssLink.id = 'dynamic-css';
        document.head.appendChild(cssLink);

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
                <h3>Unable to Load Content</h3>
                <p>An error occurred while loading the page. Please try again.</p>
                <p style="font-size:11px;color:rgba(255,255,255,0.3);">Error: ${err.message}</p>
                <button onclick="location.reload()">Reload Page</button>
            </div>`;
    }
}

// ============ CLICK EVENTS ============
document.querySelectorAll('.nav-item a').forEach(link => {
    link.addEventListener('click', e => {
        e.preventDefault();
        let href = link.getAttribute('href').replace(/^#/, '');
        updateActiveMenu(href);
        loadContent(href);
        window.location.hash = href;
    });
});

// ============ SUBMENU TOGGLE (Setting) ============
document.querySelectorAll('.nav-parent').forEach(parent => {
    parent.addEventListener('click', e => {
        e.preventDefault();
        const group = parent.closest('.nav-group-sub');
        group.classList.toggle('open');
    });
});

// ============ INIT ============
window.addEventListener('DOMContentLoaded', () => {
    const hash = window.location.hash.replace(/^#/, '');
    const defaultPage = 'overview.php';
    const page = (hash && menuRoutes[hash]) ? hash : defaultPage;

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
        window.location.hash = 'overview.php';
    }
});

// ============ MOBILE SIDEBAR TOGGLE ============
(function() {
    // Inject hamburger button vào topbar-left
    const topbarLeft = document.querySelector('.topbar-left');
    if (topbarLeft) {
        const btn = document.createElement('button');
        btn.className = 'btn-hamburger';
        btn.id = 'btnHamburger';
        btn.innerHTML = '<i class="fas fa-bars"></i>';
        btn.setAttribute('aria-label', 'Toggle menu');
        topbarLeft.insertBefore(btn, topbarLeft.firstChild);
    }

    // Inject overlay
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    overlay.id = 'sidebarOverlay';
    document.body.appendChild(overlay);

    const sidebar  = document.querySelector('.sidebar');
    const hamburger = document.getElementById('btnHamburger');

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    function isMobile() { return window.innerWidth <= 900; }

    if (hamburger) {
        hamburger.addEventListener('click', () => {
            if (sidebar.classList.contains('open')) closeSidebar();
            else openSidebar();
        });
    }

    // Click overlay → đóng sidebar
    overlay.addEventListener('click', closeSidebar);

    // Click nav item trên mobile → đóng sidebar sau khi load
    document.querySelectorAll('.nav-item a').forEach(link => {
        link.addEventListener('click', () => {
            if (isMobile()) {
                setTimeout(closeSidebar, 150);
            }
        });
    });

    // Resize → reset nếu chuyển sang desktop
    window.addEventListener('resize', () => {
        if (!isMobile()) closeSidebar();
    });
})();

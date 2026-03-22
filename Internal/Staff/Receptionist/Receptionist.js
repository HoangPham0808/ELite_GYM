// ============ ROUTING ============
const menuRoutes = {
    'management_invoice.php':  '/PHP/ELite_GYM/Internal/Layout/Invoice_Management/Invoice_Management.php',
    'management_package.php':  '/PHP/ELite_GYM/Internal/Layout/Package_Management/Package_Management.php',
    'promotion.php':           '/PHP/ELite_GYM/Internal/Layout/Promotion_Management/Promotion_Management.php',
    'customer.php':            '/PHP/ELite_GYM/Internal/Layout/Customer_Management/Customer_Management.php',
    'facilities.php':          '/PHP/ELite_GYM/Internal/Layout/Facilities_Management/Facilities_Management.php',
    'profile.php':             '/PHP/ELite_GYM/Internal/Layout/Profile/Profile.php'
};

const pageMeta = {
    'management_invoice.php':  { title: 'QUẢN LÝ HOÁ ĐƠN',    breadcrumb: 'Trang chủ / Hoá đơn',    icon: 'fa-receipt',      color: 'rgba(234,179,8,0.2)',    iconColor: '#facc15' },
    'management_package.php':  { title: 'QUẢN LÝ GÓI TẬP',    breadcrumb: 'Trang chủ / Gói tập',    icon: 'fa-box-open',     color: 'rgba(239,68,68,0.2)',    iconColor: '#f87171' },
    'promotion.php':           { title: 'QUẢN LÝ KHUYẾN MÃI',  breadcrumb: 'Trang chủ / Khuyến mãi', icon: 'fa-tag',          color: 'rgba(236,72,153,0.2)',   iconColor: '#f472b6' },
    'customer.php':            { title: 'QUẢN LÝ KHÁCH HÀNG',  breadcrumb: 'Trang chủ / Khách hàng', icon: 'fa-users',        color: 'rgba(90,50,180,0.25)',   iconColor: '#a78bfa' },
    'facilities.php':          { title: 'CƠ SỞ VẬT CHẤT',      breadcrumb: 'Trang chủ / Cơ sở vật chất', icon: 'fa-tools',   color: 'rgba(251,146,60,0.2)',   iconColor: '#fb923c' },
    'profile.php':             { title: 'HỒ SƠ CÁ NHÂN',       breadcrumb: 'Trang chủ / Hồ sơ',      icon: 'fa-user-circle', color: 'rgba(96,165,250,0.2)',   iconColor: '#60a5fa' }
};

const contentWrapper = document.getElementById('content-wrapper');

function updateClock() {
    const el = document.getElementById('topbar-time');
    if (!el) return;
    const now = new Date();
    el.textContent = `${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}:${String(now.getSeconds()).padStart(2,'0')}`;
}
setInterval(updateClock, 1000);
updateClock();

async function loadCustomerBadge() {
    try {
        const res = await fetch('/PHP/ELITE_GYM/Internal/Layout/Customer_Management/Customer_Management_function.php?action=get_stats');
        const d = await res.json();
        const badge = document.getElementById('customerBadge');
        if (!badge) return;
        const count = d.success ? (d.new_month || 0) : 0;
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = count > 0 ? 'flex' : 'none';
    } catch(e) {
        const badge = document.getElementById('customerBadge');
        if (badge) badge.style.display = 'none';
    }
}
loadCustomerBadge();

function updateTopbar(page) {
    const meta = pageMeta[page] || pageMeta['management_invoice.php'];
    document.getElementById('page-title').textContent = meta.title;
    document.getElementById('breadcrumb').textContent = meta.breadcrumb;
    const iconWrap = document.getElementById('page-icon-wrap');
    const icon = document.getElementById('page-icon');
    iconWrap.style.background = meta.color;
    iconWrap.style.borderColor = meta.iconColor + '33';
    icon.className = icon.className.replace(/fa-\S+/g, '').trim();
    icon.classList.add('fas', meta.icon);
    icon.style.color = meta.iconColor;
}

function updateActiveMenu(page) {
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
        if (item.getAttribute('data-page') === page) item.classList.add('active');
    });
}

async function loadContent(page) {
    const filePath = menuRoutes[page];
    if (!filePath) { console.error('Route not found for:', page); return; }
    updateTopbar(page);
    contentWrapper.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>';
    const iframe = document.createElement('iframe');
    iframe.src = filePath;
    iframe.onload = () => console.log('PHP loaded:', filePath);
    iframe.onerror = () => console.error('Failed:', filePath);
    contentWrapper.innerHTML = '';
    contentWrapper.appendChild(iframe);
}

document.querySelectorAll('.nav-item a').forEach(link => {
    link.addEventListener('click', e => {
        e.preventDefault();
        const href = link.getAttribute('href').replace(/^#/, '');
        updateActiveMenu(href);
        loadContent(href);
        window.location.hash = href;
    });
});

window.addEventListener('DOMContentLoaded', () => {
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

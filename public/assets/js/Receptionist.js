// ============ ROUTING ============
var menuRoutes = {
    'management_invoice.php':  '/PHP/ELite_GYM/Internal/Layout/Invoice_Management/Invoice_Management.php',
    'management_package.php':  '/PHP/ELite_GYM/Internal/Layout/Package_Management/Package_Management.php',
    'promotion.php':           '/PHP/ELite_GYM/Internal/Layout/Promotion_Management/Promotion_Management.php',
    'customer.php':            '/PHP/ELite_GYM/Internal/Layout/Customer_Management/Customer_Management.php',
    'facilities.php':          '/PHP/ELite_GYM/Internal/Layout/Facilities_Management/Facilities_Management.php',
    'profile.php':             '/PHP/ELite_GYM/Internal/Layout/Profile/Profile.php'
};

var pageMeta = {
    'management_invoice.php':  { title: 'QUẢN LÝ HOÁ ĐƠN',    breadcrumb: 'Trang chủ / Hoá đơn',         icon: 'fa-receipt',      color: 'rgba(181,137,15,.1)',   iconColor: '#d4a017' },
    'management_package.php':  { title: 'QUẢN LÝ GÓI TẬP',    breadcrumb: 'Trang chủ / Gói tập',         icon: 'fa-box-open',     color: 'rgba(220,38,38,.08)',   iconColor: '#dc2626' },
    'promotion.php':           { title: 'QUẢN LÝ KHUYẾN MÃI',  breadcrumb: 'Trang chủ / Khuyến mãi',      icon: 'fa-tag',          color: 'rgba(219,39,119,.08)',  iconColor: '#ec4899' },
    'customer.php':            { title: 'QUẢN LÝ KHÁCH HÀNG',  breadcrumb: 'Trang chủ / Khách hàng',      icon: 'fa-users',        color: 'rgba(124,58,237,.08)',  iconColor: '#7c3aed' },
    'facilities.php':          { title: 'CƠ SỞ VẬT CHẤT',      breadcrumb: 'Trang chủ / Cơ sở vật chất', icon: 'fa-tools',        color: 'rgba(194,65,12,.08)',   iconColor: '#c2410c' },
    'profile.php':             { title: 'HỒ SƠ CÁ NHÂN',       breadcrumb: 'Trang chủ / Hồ sơ',           icon: 'fa-user-circle',  color: 'rgba(29,78,216,.08)',   iconColor: '#1d4ed8' }
};

var contentWrapper = document.getElementById('content-wrapper');

// ============ CLOCK ============
function updateClock() {
    const el = document.getElementById('topbar-time');
    if (!el) return;
    const now = new Date();
    el.textContent = `${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}:${String(now.getSeconds()).padStart(2,'0')}`;
}
setInterval(updateClock, 1000);
updateClock();

// ============ CUSTOMER BADGE ============
async function loadCustomerBadge() {
    try {
        const res = await fetch('/PHP/ELITE_GYM/Internal/Layout/Customer_Management/window.ELITE_BASE + '/api/admin/customer'?action=get_stats');
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

// ============ TOPBAR UPDATE ============
function updateTopbar(page) {
    const meta = pageMeta[page] || pageMeta['management_invoice.php'];
    document.getElementById('page-title').textContent = meta.title;
    const bc = document.getElementById('breadcrumb');
    if (bc) bc.textContent = meta.breadcrumb;

    const iconWrap = document.getElementById('page-icon-wrap');
    const icon = document.getElementById('page-icon');
    if (iconWrap) {
        iconWrap.style.background = meta.color;
        iconWrap.style.borderColor = meta.iconColor + '44';
        iconWrap.style.color = meta.iconColor;
    }
    if (icon) {
        icon.className = '';
        icon.classList.add('fas', meta.icon);
        icon.style.color = meta.iconColor;
    }
}

function updateActiveMenu(page) {
    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
        if (item.getAttribute('data-page') === page) item.classList.add('active');
    });
}

// ============ LOAD CONTENT ============
async function loadContent(page) {
    const filePath = menuRoutes[page];
    if (!filePath) { console.error('Route not found for:', page); return; }
    updateTopbar(page);
    contentWrapper.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>';
    const iframe = document.createElement('iframe');
    iframe.src = filePath;
    iframe.onload  = () => console.log('PHP loaded:', filePath);
    iframe.onerror = () => console.error('Failed:', filePath);
    contentWrapper.innerHTML = '';
    contentWrapper.appendChild(iframe);
    closeSidebar(); // close mobile sidebar on nav
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
runWhenReady( () => {
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
    // Inject hamburger button into topbar-left
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

    // Inject backdrop
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
    document.getElementById('hamburger-btn')?.querySelector('i')
        ?.classList.toggle('fa-times', isOpen);
    document.getElementById('hamburger-btn')?.querySelector('i')
        ?.classList.toggle('fa-bars', !isOpen);
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

// Hide hamburger on wide screens
var mq = window.matchMedia('(min-width: 601px)');
function handleMQ(e) {
    const btn = document.getElementById('hamburger-btn');
    if (btn) btn.style.display = e.matches ? 'none' : '';
    if (e.matches) closeSidebar();
}
mq.addEventListener('change', handleMQ);
runWhenReady( () => handleMQ(mq));

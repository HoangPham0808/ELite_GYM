// ============ ROUTING ============
const menuRoutes = {
    'customer.php':           '/PHP/DATN/Internal/Staff/layout/Customer_Management/Customer_Management.php',
    'Schedule_Management.php':'/PHP/DATN/Internal/Staff/layout/Schedule_Management/Schedule_Management.php',
    'facilities.php':         '/PHP/DATN/Internal/Staff/layout/Facilities_Management/Facilities_Management.php',
    'Profile.php':         '/PHP/DATN/Internal/Staff/Layout/Profile/Profile.php'
};

// Page meta: title, breadcrumb, icon, color
const pageMeta = {
    'customer.php':            { title: 'QUẢN LÝ KHÁCH HÀNG', breadcrumb: 'Trang chủ / Khách hàng',      icon: 'fa-users',        color: 'rgba(90,50,180,0.25)',  iconColor: '#a78bfa' },
    'Schedule_Management.php': { title: 'QUẢN LÝ LỊCH TẬP',   breadcrumb: 'Trang chủ / Lịch tập',       icon: 'fa-calendar-alt', color: 'rgba(20,184,166,0.2)',  iconColor: '#2dd4bf' },
    'facilities.php':          { title: 'CƠ SỞ VẬT CHẤT',      breadcrumb: 'Trang chủ / Cơ sở vật chất', icon: 'fa-tools',        color: 'rgba(168,85,247,0.2)', iconColor: '#c084fc' },
    'Profile.php':             { title: 'HỒ SƠ CÁ NHÂN',      breadcrumb: 'Trang chủ / Hồ sơ cá nhân', icon: 'fa-user',         color: 'rgba(100,100,100,0.2)', iconColor: '#6b7280' }
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

// ============ UPDATE TOPBAR ============
function updateTopbar(page) {
    const meta = pageMeta[page] || pageMeta['customer.php'];
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
        contentWrapper.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>';

        const iframe = document.createElement('iframe');
        iframe.src = filePath;
        iframe.onload = () => console.log('PHP loaded:', filePath);
        iframe.onerror = () => console.error('Failed:', filePath);

        contentWrapper.innerHTML = '';
        contentWrapper.appendChild(iframe);
        return;
    }

    contentWrapper.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Đang tải...</div>';

    try {
        const res = await fetch(filePath);
        if (!res.ok) throw new Error('HTTP ' + res.status);

        const html = await res.text();
        const doc = new DOMParser().parseFromString(html, 'text/html');

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
                <h3>Không thể tải nội dung</h3>
                <p>Đã xảy ra lỗi khi tải trang. Vui lòng thử lại.</p>
                <p style="font-size:11px;color:rgba(255,255,255,0.3);">Lỗi: ${err.message}</p>
                <button onclick="location.reload()">Tải lại trang</button>
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

// ============ INIT ============
window.addEventListener('DOMContentLoaded', () => {
    const hash = window.location.hash.replace(/^#/, '');
    const defaultPage = 'customer.php';
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
        window.location.hash = 'customer.php';
    }
});

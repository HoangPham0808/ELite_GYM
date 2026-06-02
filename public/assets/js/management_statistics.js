/**
 * management_statistics.js  — Elite Gym (Redesign)
 * Gọi API thực từ window.ELITE_BASE + '/api/admin/statistics'
 * Không dùng mock data — mọi số liệu từ DB
 */

var API = window.ELITE_BASE + '/api/admin/statistics';
var MONTHS = ['T1','T2','T3','T4','T5','T6','T7','T8','T9','T10','T11','T12'];

/* ══════════════════════════════════════════
   REPORT CARDS CONFIG (metadata only, data = DB)
══════════════════════════════════════════ */
var REPORTS_CONFIG = [
  // ─ Doanh thu ─
  { id:'revenue-monthly',  cat:'revenue',   icon:'fas fa-chart-line',         ic_color:'#d4a017', ic_bg:'rgba(181,137,15,.1)', badge:'Tài chính',  badge_c:'rgba(181,137,15,.12)', badge_t:'#d4a017', title:'Doanh Thu Theo Tháng',      desc:'Tổng hợp doanh thu từ hóa đơn, phân tích xu hướng từng tháng.' },
  { id:'revenue-plan',     cat:'revenue',   icon:'fas fa-receipt',            ic_color:'#15803d', ic_bg:'rgba(21,128,61,.08)',  badge:'Tài chính',  badge_c:'rgba(21,128,61,.1)', badge_t:'#15803d',  title:'Doanh Thu Theo Gói',        desc:'Chi tiết doanh thu phân theo từng loại gói tập (Basic → VIP).' },
  // ─ Hội viên ─
  { id:'member-new',       cat:'member',    icon:'fas fa-user-plus',          ic_color:'#1d4ed8', ic_bg:'rgba(29,78,216,.08)', badge:'Hội viên',   badge_c:'rgba(29,78,216,.1)',badge_t:'#1d4ed8',  title:'Hội Viên Mới Đăng Ký',     desc:'Danh sách khách hàng đăng ký mới, gói chọn, nguồn giới thiệu.' },
  { id:'member-active',    cat:'member',    icon:'fas fa-id-badge',           ic_color:'#7c3aed', ic_bg:'rgba(124,58,237,.08)', badge:'Hội viên',   badge_c:'rgba(124,58,237,.1)',badge_t:'#7c3aed',  title:'Hội Viên Đang Hoạt Động',   desc:'Trạng thái gói, ngày hết hạn, cảnh báo sắp hết hạn 30 ngày.' },
  // ─ Nhân sự ─
  { id:'employee-payroll', cat:'employee',  icon:'fas fa-wallet',             ic_color:'#d4a017', ic_bg:'rgba(181,137,15,.1)', badge:'Lương',      badge_c:'rgba(181,137,15,.12)',badge_t:'#d4a017',  title:'Bảng Lương Nhân Viên',      desc:'Lương cơ bản, phụ cấp, thưởng, khấu trừ và lương thực nhận.' },
  // ─ Thiết bị ─
  { id:'equipment-status', cat:'equipment', icon:'fas fa-tools',              ic_color:'#c2410c', ic_bg:'rgba(194,65,12,.08)', badge:'Thiết bị',   badge_c:'rgba(194,65,12,.1)',badge_t:'#c2410c',  title:'Tình Trạng Thiết Bị',       desc:'Danh sách thiết bị, tình trạng hiện tại, lần bảo trì gần nhất.' },
  { id:'equipment-maint',  cat:'equipment', icon:'fas fa-wrench',             ic_color:'#dc2626', ic_bg:'rgba(220,38,38,.08)',  badge:'Bảo trì',    badge_c:'rgba(220,38,38,.1)', badge_t:'#dc2626',  title:'Lịch Sử Bảo Trì Thiết Bị',  desc:'Chi phí bảo trì, đơn vị thực hiện, lịch sử theo thiết bị.' },
  // ─ Check-in ─
  { id:'checkin-class',    cat:'checkin',   icon:'fas fa-chalkboard-teacher', ic_color:'#7c3aed', ic_bg:'rgba(124,58,237,.08)', badge:'Lớp học',    badge_c:'rgba(124,58,237,.1)',badge_t:'#7c3aed',  title:'Tham Gia Lớp Học',          desc:'Số học viên đăng ký và tham dự thực tế từng buổi lớp.' },
];

/* ══════════════════════════════════════════
   INIT
══════════════════════════════════════════ */
runWhenReady( () => {
  loadBarChartFromAPI();
  renderReportCards('all');
  setDefaultDates();
  initModalClose();
});

/* ══════════════════════════════════════════
   BAR CHART — tải dữ liệu thực từ API
══════════════════════════════════════════ */
async function loadBarChartFromAPI() {
  const year = new Date().getFullYear();
  try {
    const res  = await fetch(`${API}?action=revenue&year=${year}`);
    const data = await res.json();   // [0..11] values
    renderBarChart(data);
  } catch (e) {
    console.warn('Bar chart API error:', e);
  }
}

function renderBarChart(data) {
  const wrap = document.getElementById('barChartWrap');
  if (!wrap || !Array.isArray(data)) return;
  const max  = Math.max(...data, 1);
  const curM = new Date().getMonth(); // 0-based

  wrap.innerHTML = data.map((v, i) => {
    const h   = v > 0 ? Math.max(3, Math.round(v / max * 100)) : 0;
    const cur = (i === curM);
    const lbl = fmtM(v);
    return `
    <div class="bar-col ${cur ? 'bar-col--current' : ''}" title="${MONTHS[i]}: ${lbl} ₫">
      <div class="bar-tooltip">${lbl}</div>
      <div class="bar-fill" style="height:${h}%"></div>
      <div class="bar-lbl">${MONTHS[i]}</div>
    </div>`;
  }).join('');
}

/* ══════════════════════════════════════════
   REPORT CARDS
══════════════════════════════════════════ */
function renderReportCards(cat) {
  const list = (cat === 'all') ? REPORTS_CONFIG : REPORTS_CONFIG.filter(r => r.cat === cat);
  const grid = document.getElementById('reportGrid');
  if (!grid) return;
  grid.innerHTML = list.map((r, i) => `
    <div class="report-card" id="rc-${r.id}" style="animation-delay:${i * 0.04}s"
         onclick="quickView('${r.id}')">
      <div class="rc-top">
        <div class="rc-icon" style="background:${r.ic_bg};color:${r.ic_color}">
          <i class="${r.icon}"></i>
        </div>
        <div class="rc-badge" style="background:${r.badge_c};color:${r.badge_t}">${r.badge}</div>
      </div>
      <div class="rc-title">${r.title}</div>
      <div class="rc-desc">${r.desc}</div>
      <div class="rc-foot">
        <div class="rc-meta">
          <i class="fas fa-database" style="color:${r.ic_color}"></i> Dữ liệu thực
        </div>
        <div class="rc-actions">
          <button class="btn-sm btn-view"
                  onclick="event.stopPropagation(); quickView('${r.id}')">
            <i class="fas fa-eye"></i> Xem
          </button>
          <button class="btn-sm btn-export"
                  onclick="event.stopPropagation(); openExportModal('${r.id}')">
            <i class="fas fa-download"></i> Xuất
          </button>
        </div>
      </div>
    </div>`).join('');
}

function switchTab(cat, btn) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  renderReportCards(cat);
}

function quickView(id) {
  // highlight card
  document.querySelectorAll('.report-card').forEach(c => c.classList.remove('active'));
  const card = document.getElementById('rc-' + id);
  if (card) {
    card.classList.add('active');
    card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
  openViewPanel(id);
}

/* ══════════════════════════════════════════
   VIEW PANEL — hiển thị dữ liệu trực tiếp
══════════════════════════════════════════ */

// Tên cột tiếng Anh → tiếng Việt
var COL_VI = {
  period:'Kỳ', revenue:'Doanh thu (₫)', total_invoices:'Số HĐ',
  original_amount:'Gốc (₫)', discount_amount:'Giảm giá (₫)', final_amount:'Thanh toán (₫)',
  customer_id:'ID KH', full_name:'Họ tên', phone:'SĐT', email:'Email',
  gender:'Giới tính', registered_at:'Ngày đăng ký', plan_name:'Gói tập',
  package_type:'Loại gói', qty:'SL', start_date:'Bắt đầu', end_date:'Kết thúc',
  days_remaining:'Còn (ngày)', days_expired:'Hết hạn (ngày)', status:'Trạng thái',
  employee_id:'ID NV', position:'Chức vụ', present:'Đúng giờ', late:'Đi muộn',
  absent:'Vắng', on_leave:'Nghỉ phép', total_days:'Tổng ngày',
  month:'Tháng', year:'Năm', base_salary:'Lương CB (₫)', allowance:'Phụ cấp (₫)',
  bonus:'Thưởng (₫)', deduction:'Khấu trừ (₫)', net_salary:'Thực lĩnh (₫)',
  equipment_id:'ID TB', equipment_name:'Tên thiết bị', condition_status:'Tình trạng',
  room_name:'Phòng', type_name:'Loại', purchase_date:'Ngày mua',
  purchase_price:'Giá mua (₫)', last_maintenance_date:'BT cuối', days_since_maint:'Từ BT cuối',
  maintenance_id:'ID BT', maintenance_date:'Ngày BT', description:'Mô tả',
  cost:'Chi phí (₫)', performed_by:'Thực hiện bởi',
  total_checkins:'Lượt CI', unique_members:'Thành viên',
  promotion_name:'Tên KM', discount_percent:'% Giảm', usage_count:'Lượt dùng',
  total_discount:'Tổng giảm (₫)', revenue_after_discount:'DT sau giảm (₫)',
  class_name:'Tên lớp', trainer:'Huấn luyện viên', start_time:'Giờ bắt đầu',
  end_time:'Giờ kết thúc', registered:'Đăng ký',
  invoice_id:'#HĐ', created_by:'Tạo bởi',
};

// Các cột tiền tệ cần format ₫
var MONEY_COLS = new Set([
  'revenue','original_amount','discount_amount','final_amount',
  'net_salary','base_salary','allowance','bonus','deduction',
  'cost','purchase_price','revenue_after_discount','total_discount',
  'subtotal',
]);

var _currentViewId = null;

function openViewPanel(id) {
  const r = REPORTS_CONFIG.find(x => x.id === id);
  if (!r) return;
  _currentViewId = id;

  // Điền thông tin header
  const iconEl = document.getElementById('vpIcon');
  const titleEl = document.getElementById('vpTitle');
  const descEl  = document.getElementById('vpDesc');
  if (iconEl) {
    iconEl.style.background = r.ic_bg;
    iconEl.style.color      = r.ic_color;
    iconEl.innerHTML = `<i class="${r.icon}"></i>`;
  }
  if (titleEl) titleEl.textContent = r.title;
  if (descEl)  descEl.textContent  = r.desc;

  // Đồng bộ ngày từ ô dateFrom/dateTo chính (nếu có)
  const from = document.getElementById('dateFrom')?.value;
  const to   = document.getElementById('dateTo')?.value;
  if (from) { const el = document.getElementById('vpFrom'); if (el) el.value = from; }
  if (to)   { const el = document.getElementById('vpTo');   if (el) el.value = to;   }

  // Reset trạng thái body
  _vpSetState('loading');
  document.getElementById('viewPanel')?.classList.add('open');

  // Tải dữ liệu ngay
  loadViewData();
}

function closeViewPanel() {
  document.getElementById('viewPanel')?.classList.remove('open');
  document.querySelectorAll('.report-card').forEach(c => c.classList.remove('active'));
  _currentViewId = null;
}

async function loadViewData() {
  if (!_currentViewId) return;
  _vpSetState('loading');

  const from  = document.getElementById('vpFrom')?.value  || document.getElementById('dateFrom')?.value;
  const to    = document.getElementById('vpTo')?.value    || document.getElementById('dateTo')?.value;
  const group = document.getElementById('vpGroup')?.value || 'month';

  if (!from || !to) { _vpSetState('empty'); return; }

  try {
    const url = `${API}?action=view&report=${encodeURIComponent(_currentViewId)}&from=${from}&to=${to}&group=${group}`;
    const res  = await fetch(url);
    const json = await res.json();

    if (!json.data || json.data.length === 0) {
      _vpSetState('empty');
      const cnt = document.getElementById('vpCount');
      if (cnt) cnt.textContent = '0 bản ghi';
      return;
    }

    _vpSetState('table');
    _renderViewTable(json.data);

    const cnt = document.getElementById('vpCount');
    if (cnt) cnt.textContent = `${json.count} bản ghi · ${_fmtDateVi(from)} → ${_fmtDateVi(to)}`;
  } catch (e) {
    console.error('View panel error:', e);
    _vpSetState('empty');
  }
}

function _renderViewTable(rows) {
  const wrap = document.getElementById('vpTableWrap');
  if (!wrap || !rows.length) return;

  const cols = Object.keys(rows[0]);
  const thead = `<thead><tr>${cols.map(c =>
    `<th>${COL_VI[c] || c}</th>`
  ).join('')}</tr></thead>`;

  const tbody = `<tbody>${rows.map(row => `<tr>${cols.map(c => {
    const v = row[c];
    if (v === null || v === undefined || v === '') return `<td style="color:var(--tm)">—</td>`;
    if (MONEY_COLS.has(c) && !isNaN(v))
      return `<td class="td-money td-num">${Number(v).toLocaleString('vi-VN')} ₫</td>`;
    if (typeof v === 'number' && !isNaN(v))
      return `<td class="td-num">${v.toLocaleString('vi-VN')}</td>`;
    // status badge
    if (c === 'status' || c === 'condition_status') return `<td>${_statusBadge(v)}</td>`;
    return `<td>${String(v)}</td>`;
  }).join('')}</tr>`).join('')}</tbody>`;

  wrap.innerHTML = `<table>${thead}${tbody}</table>`;
}

function _vpSetState(state) {
  const loading  = document.getElementById('vpLoading');
  const tableWrap = document.getElementById('vpTableWrap');
  const empty    = document.getElementById('vpEmpty');
  if (!loading || !tableWrap || !empty) return;
  loading.style.display   = state === 'loading' ? 'flex' : 'none';
  tableWrap.style.display = state === 'table'   ? 'block' : 'none';
  empty.style.display     = state === 'empty'   ? 'flex'  : 'none';
}

function _statusBadge(v) {
  const map = {
    'active':      ['rgba(21,128,61,.08)',  '#15803d', 'Đang hoạt động'],
    'inactive':    ['rgba(220,38,38,.08)',  '#dc2626', 'Hết hạn'],
    'Paid':        ['rgba(21,128,61,.08)',  '#15803d', 'Đã thanh toán'],
    'Pending':     ['rgba(181,137,15,.1)', '#b5890f', 'Chờ TT'],
    'Cancelled':   ['rgba(220,38,38,.08)', '#dc2626', 'Đã huỷ'],
    'Good':        ['rgba(21,128,61,.08)',  '#15803d', 'Tốt'],
    'In Progress': ['rgba(181,137,15,.1)', '#b5890f', 'Đang BT'],
    'Damaged':     ['rgba(220,38,38,.08)', '#dc2626', 'Hỏng'],
    'Maintenance': ['rgba(194,65,12,.08)', '#c2410c', 'Bảo trì'],
  };
  const [bg, cl, lb] = map[v] || ['rgba(156,163,175,.1)', '#6b7280', v];
  return `<span class="status-badge" style="background:${bg};color:${cl}">${lb}</span>`;
}

function _fmtDateVi(d) {
  if (!d) return '';
  const [y, m, day] = d.split('-');
  return `${day}/${m}/${y}`;
}

/* Chọn format ngay trong footer view panel */
function vpSelectFmt(el) {
  document.querySelectorAll('.vd-fmt').forEach(b => b.classList.remove('selected'));
  el.classList.add('selected');
}

/* Xuất thẳng từ view panel — dùng đúng report + ngày + nhóm đang xem */
function doExportFromView() {
  if (!_currentViewId) return;

  const fmtEl = document.querySelector('.vd-fmt.selected');
  const fmt   = fmtEl?.dataset.fmt || 'pdf';
  const from  = document.getElementById('vpFrom')?.value;
  const to    = document.getElementById('vpTo')?.value;
  const group = document.getElementById('vpGroup')?.value || 'month';

  if (!from || !to) { showToast('Vui lòng chọn khoảng thời gian!', true); return; }

  const url = `${API}?action=export`
    + `&report=${encodeURIComponent(_currentViewId)}`
    + `&format=${encodeURIComponent(fmt)}`
    + `&from=${from}&to=${to}&group=${group}`;

  if (fmt === 'csv' || fmt === 'json') {
    fetch(url).then(async res => {
      const ct = res.headers.get('Content-Type') || '';
      if (ct.includes('application/json')) {
        const err = await res.json();
        showToast(err.error || 'Không có dữ liệu để xuất', true);
      } else {
        window.location.href = url;
        showToast(`Đang tải xuống ${fmt.toUpperCase()}…`);
      }
    }).catch(() => showToast('Lỗi kết nối server', true));
  } else {
    window.open(url, '_blank');
    showToast(`Đang xuất ${fmt.toUpperCase()} — kiểm tra tab mới`);
  }
}

/* ══════════════════════════════════════════
   EXPORT MODAL
══════════════════════════════════════════ */
var _currentReport = null;

function openExportModal(reportId) {
  _currentReport = REPORTS_CONFIG.find(r => r.id === reportId)
                || { title: 'Tất cả báo cáo', id: reportId };
  document.getElementById('modalTitle').textContent = 'Xuất: ' + _currentReport.title;
  document.getElementById('modalDesc').textContent  = 'Chọn định dạng và khoảng thời gian xuất';
  document.querySelectorAll('.export-opt').forEach(e => e.classList.remove('selected'));
  document.querySelector('.export-opt[data-fmt="pdf"]')?.classList.add('selected');
  document.getElementById('exportModal').classList.add('open');
}

function closeModal() {
  document.getElementById('exportModal').classList.remove('open');
}

function selectFormat(el) {
  document.querySelectorAll('.export-opt').forEach(e => e.classList.remove('selected'));
  el.classList.add('selected');
}

function doExport() {
  const fmtEl = document.querySelector('.export-opt.selected');
  const fmt   = fmtEl?.dataset.fmt || 'pdf';
  const from  = document.getElementById('dateFrom').value;
  const to    = document.getElementById('dateTo').value;
  const group = document.getElementById('groupBy').value;
  const rid   = _currentReport?.id || 'all';

  if (!from || !to) { showToast('Vui lòng chọn khoảng thời gian!', true); return; }
  closeModal();

  const url = `${API}?action=export`
    + `&report=${encodeURIComponent(rid)}`
    + `&format=${fmt}`
    + `&from=${from}&to=${to}&group=${group}`;

  if (fmt === 'csv' || fmt === 'json') {
    // Với CSV/JSON: fetch trước để check lỗi rỗng dữ liệu
    fetch(url).then(async res => {
      const ct = res.headers.get('Content-Type') || '';
      if (ct.includes('application/json')) {
        // Server trả JSON = có lỗi
        const err = await res.json();
        showToast(err.error || 'Không có dữ liệu để xuất', true);
      } else {
        // Có dữ liệu thật — download
        window.location.href = url;
        showToast(`Đang tải xuống ${fmt.toUpperCase()}…`);
      }
    }).catch(() => showToast('Lỗi kết nối server', true));
  } else {
    window.open(url, '_blank');
    showToast(`Đang xuất ${fmt.toUpperCase()} — kiểm tra tab mới`);
  }
}

/* ── Export bảng giao dịch gần đây ── */
function exportRecentTable() {
  const table = document.getElementById('recentTable');
  if (!table) return;
  const rows = [...table.querySelectorAll('tr')]
    .map(tr => [...tr.querySelectorAll('th,td')]
      .map(c => '"' + c.innerText.replace(/"/g,'""') + '"').join(','));
  const blob = new Blob(['\uFEFF' + rows.join('\n')], { type: 'text/csv;charset=utf-8' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = `elite_gym_invoices_${todayStr()}.csv`;
  a.click();
  showToast('Đã tải về file CSV giao dịch gần đây');
}

/* ══════════════════════════════════════════
   HELPERS
══════════════════════════════════════════ */
function setDefaultDates() {
  const now   = new Date();
  const first = new Date(now.getFullYear(), now.getMonth(), 1);
  const df = document.getElementById('dateFrom');
  const dt = document.getElementById('dateTo');
  if (df) df.value = first.toISOString().slice(0,10);
  if (dt) dt.value = now.toISOString().slice(0,10);
}

function initModalClose() {
  const overlay = document.getElementById('exportModal');
  if (overlay) overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });
  const viewOverlay = document.getElementById('viewPanel');
  if (viewOverlay) viewOverlay.addEventListener('click', e => { if (e.target === viewOverlay) closeViewPanel(); });
}

function todayStr() { return new Date().toISOString().slice(0,10); }

function fmtM(n) {
  n = Number(n);
  if (n >= 1e9)  return (n/1e9).toFixed(1).replace(/\.0$/,'')  + 'B';
  if (n >= 1e6)  return (n/1e6).toFixed(1).replace(/\.0$/,'')  + 'M';
  if (n >= 1e3)  return (n/1e3).toFixed(0) + 'K';
  return n.toLocaleString('vi-VN');
}

function showToast(msg, isErr = false) {
  const t = document.getElementById('toast');
  const s = document.getElementById('toastMsg');
  if (!t || !s) return;
  s.textContent = msg;
  t.style.borderLeftColor = isErr ? 'var(--red)' : 'var(--green)';
  t.querySelector('i').style.color = isErr ? 'var(--red)' : 'var(--green)';
  t.classList.add('show');
  clearTimeout(t._timer);
  t._timer = setTimeout(() => t.classList.remove('show'), 3500);
}

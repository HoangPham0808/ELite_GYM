/**
 * management_statistics.js  — Elite Gym (Redesign)
 * Gọi API thực từ management_statistics_function.php
 * Không dùng mock data — mọi số liệu từ DB
 */

const API = 'management_statistics_function.php';
const MONTHS = ['T1','T2','T3','T4','T5','T6','T7','T8','T9','T10','T11','T12'];

/* ══════════════════════════════════════════
   REPORT CARDS CONFIG (metadata only, data = DB)
══════════════════════════════════════════ */
const REPORTS_CONFIG = [
  // ─ Doanh thu ─
  { id:'revenue-monthly',  cat:'revenue',   icon:'fas fa-chart-line',         ic_color:'#d4a017', ic_bg:'rgba(212,160,23,.12)', badge:'Tài chính',  badge_c:'rgba(212,160,23,.15)', badge_t:'#d4a017', title:'Doanh Thu Theo Tháng',      desc:'Tổng hợp doanh thu từ hóa đơn, phân tích xu hướng từng tháng.' },
  { id:'revenue-plan',     cat:'revenue',   icon:'fas fa-receipt',            ic_color:'#22c55e', ic_bg:'rgba(34,197,94,.12)',  badge:'Tài chính',  badge_c:'rgba(34,197,94,.15)', badge_t:'#22c55e',  title:'Doanh Thu Theo Gói',        desc:'Chi tiết doanh thu phân theo từng loại gói tập (Basic → VIP).' },
  { id:'revenue-promo',    cat:'revenue',   icon:'fas fa-percent',            ic_color:'#f97316', ic_bg:'rgba(249,115,22,.12)', badge:'Khuyến mãi', badge_c:'rgba(249,115,22,.15)',badge_t:'#f97316',  title:'Hiệu Quả Khuyến Mãi',       desc:'Lượt dùng, giá trị giảm và doanh thu ảnh hưởng bởi mã giảm giá.' },
  // ─ Hội viên ─
  { id:'member-new',       cat:'member',    icon:'fas fa-user-plus',          ic_color:'#3b82f6', ic_bg:'rgba(59,130,246,.12)', badge:'Hội viên',   badge_c:'rgba(59,130,246,.15)',badge_t:'#3b82f6',  title:'Hội Viên Mới Đăng Ký',     desc:'Danh sách khách hàng đăng ký mới, gói chọn, nguồn giới thiệu.' },
  { id:'member-active',    cat:'member',    icon:'fas fa-id-badge',           ic_color:'#a855f7', ic_bg:'rgba(168,85,247,.12)', badge:'Hội viên',   badge_c:'rgba(168,85,247,.15)',badge_t:'#a855f7',  title:'Hội Viên Đang Hoạt Động',   desc:'Trạng thái gói, ngày hết hạn, cảnh báo sắp hết hạn 30 ngày.' },
  { id:'member-expire',    cat:'member',    icon:'fas fa-calendar-times',     ic_color:'#ef4444', ic_bg:'rgba(239,68,68,.12)',  badge:'Hội viên',   badge_c:'rgba(239,68,68,.15)', badge_t:'#ef4444',  title:'Hội Viên Hết Hạn / Rời Bỏ', desc:'Danh sách đã hết gói chưa gia hạn — cơ sở tái kích hoạt.' },
  // ─ Nhân sự ─
  { id:'employee-attend',  cat:'employee',  icon:'fas fa-clipboard-check',    ic_color:'#22c55e', ic_bg:'rgba(34,197,94,.12)',  badge:'Nhân sự',    badge_c:'rgba(34,197,94,.15)', badge_t:'#22c55e',  title:'Chuyên Cần Nhân Viên',      desc:'Bảng công: đúng giờ, đi muộn, vắng, ngày phép theo nhân viên.' },
  { id:'employee-payroll', cat:'employee',  icon:'fas fa-wallet',             ic_color:'#d4a017', ic_bg:'rgba(212,160,23,.12)', badge:'Lương',      badge_c:'rgba(212,160,23,.15)',badge_t:'#d4a017',  title:'Bảng Lương Nhân Viên',      desc:'Lương cơ bản, phụ cấp, thưởng, khấu trừ và lương thực nhận.' },
  // ─ Thiết bị ─
  { id:'equipment-status', cat:'equipment', icon:'fas fa-tools',              ic_color:'#f97316', ic_bg:'rgba(249,115,22,.12)', badge:'Thiết bị',   badge_c:'rgba(249,115,22,.15)',badge_t:'#f97316',  title:'Tình Trạng Thiết Bị',       desc:'Danh sách thiết bị, tình trạng hiện tại, lần bảo trì gần nhất.' },
  { id:'equipment-maint',  cat:'equipment', icon:'fas fa-wrench',             ic_color:'#ef4444', ic_bg:'rgba(239,68,68,.12)',  badge:'Bảo trì',    badge_c:'rgba(239,68,68,.15)', badge_t:'#ef4444',  title:'Lịch Sử Bảo Trì Thiết Bị',  desc:'Chi phí bảo trì, đơn vị thực hiện, lịch sử theo thiết bị.' },
  // ─ Check-in ─
  { id:'checkin-daily',    cat:'checkin',   icon:'fas fa-door-open',          ic_color:'#3b82f6', ic_bg:'rgba(59,130,246,.12)', badge:'Check-in',   badge_c:'rgba(59,130,246,.15)',badge_t:'#3b82f6',  title:'Check-in Theo Ngày',        desc:'Số lượt vào/ra mỗi ngày, giờ cao điểm, hội viên thường xuyên.' },
  { id:'checkin-class',    cat:'checkin',   icon:'fas fa-chalkboard-teacher', ic_color:'#a855f7', ic_bg:'rgba(168,85,247,.12)', badge:'Lớp học',    badge_c:'rgba(168,85,247,.15)',badge_t:'#a855f7',  title:'Tham Gia Lớp Học',          desc:'Số học viên đăng ký và tham dự thực tế từng buổi lớp.' },
];

/* ══════════════════════════════════════════
   INIT
══════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
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
  const r = REPORTS_CONFIG.find(r => r.id === id);
  if (!r) return;
  document.querySelectorAll('.report-card').forEach(c => c.classList.remove('active'));
  const card = document.getElementById('rc-' + id);
  if (card) {
    card.classList.add('active');
    card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }
  openExportModal(id);
}

/* ══════════════════════════════════════════
   EXPORT MODAL
══════════════════════════════════════════ */
let _currentReport = null;

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
    window.location.href = url;
    showToast(`Đang tải xuống ${fmt.toUpperCase()}…`);
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

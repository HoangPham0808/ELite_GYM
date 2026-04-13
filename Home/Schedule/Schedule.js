/* ══════════════════════════════════
   CURSOR GLOW
══════════════════════════════════ */
const cursorGlow = document.getElementById('cursorGlow');
if (cursorGlow) {
  document.addEventListener('mousemove', e => {
    cursorGlow.style.left = e.clientX + 'px';
    cursorGlow.style.top  = e.clientY + 'px';
  });
}

/* ══════════════════════════════════
   NAVBAR SCROLL
══════════════════════════════════ */
const nav = document.getElementById('nav');
if (nav) {
  window.addEventListener('scroll', () => {
    nav.classList.add('scrolled');
  }, { passive: true });
}

/* ══════════════════════════════════
   TABS
══════════════════════════════════ */
const tabs   = document.querySelectorAll('.sch-tab');
const panels = document.querySelectorAll('.sch-panel');

const hashTab = window.location.hash === '#my' ? 'my' : 'week';
tabs.forEach(tab     => tab.classList.toggle('active',   tab.dataset.tab === hashTab));
panels.forEach(panel => panel.classList.toggle('active', panel.id === 'panel-' + hashTab));

tabs.forEach(tab => {
  tab.addEventListener('click', () => {
    const targetTab = tab.dataset.tab;

    if (targetTab === 'my') {
      const weekParam = new URLSearchParams(window.location.search).get('week') || '0';
      window.location.replace(`Schedule.php?week=${weekParam}&_r=${Date.now()}#my`);
      return;
    }

    tabs.forEach(t => t.classList.remove('active'));
    panels.forEach(p => p.classList.remove('active'));
    tab.classList.add('active');
    const target = document.getElementById('panel-' + targetTab);
    if (target) target.classList.add('active');
    history.replaceState(null, '', '#' + targetTab);
  });
});

/* ══════════════════════════════════
   REVEAL ANIMATION
══════════════════════════════════ */
document.querySelectorAll('.sch-list-card, .sch-day').forEach((el, i) => {
  el.style.opacity    = '0';
  el.style.transform  = 'translateY(16px)';
  el.style.transition = `opacity .45s ease ${i * 0.04}s, transform .45s ease ${i * 0.04}s`;

  const obs = new IntersectionObserver((entries) => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.style.opacity   = '1';
        e.target.style.transform = 'translateY(0)';
        obs.unobserve(e.target);
      }
    });
  }, { threshold: 0.06 });
  obs.observe(el);
});

/* ══════════════════════════════════
   TOAST
══════════════════════════════════ */
function showToast(msg, isError = false) {
  const toast = document.getElementById('schToast');
  const icon  = document.getElementById('schToastIcon');
  const msgEl = document.getElementById('schToastMsg');
  if (!toast) return;
  msgEl.textContent = msg;
  toast.className = 'sch-toast show ' + (isError ? 'toast--error' : 'toast--success');
  icon.className  = isError ? 'fas fa-exclamation-circle' : 'fas fa-check-circle';
  clearTimeout(toast._t);
  toast._t = setTimeout(() => { toast.classList.remove('show'); }, 3000);
}

/* ══════════════════════════════════
   ĐĂNG KÝ / HỦY — AJAX
══════════════════════════════════ */
async function doClassAction(classId, action, btn) {
  btn.disabled   = true;
  const origHTML = btn.innerHTML;
  btn.innerHTML  = '<i class="fas fa-spinner fa-spin"></i>';

  try {
    const fd = new FormData();
    fd.append('action',   action);
    fd.append('class_id', classId);

    const res  = await fetch('Schedule_function.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
      showToast(data.message, false);

      const card = btn.closest('.sch-class-card');
      if (card) {
        if (data.action === 'registered') {
          card.classList.add('sch-class-card--mine');
          btn.className      = 'sch-btn-cancel';
          btn.dataset.action = 'cancel';
          btn.innerHTML      = '<i class="fas fa-times"></i> Hủy';
          btn.disabled = false;

          // Ẩn tất cả card cùng slot_key nhưng khác lớp này
          const slotKey = card.dataset.slotKey;
          if (slotKey) {
            document.querySelectorAll(`.sch-class-card[data-slot-key="${slotKey}"]`).forEach(c => {
              if (c !== card) {
                c.style.display = 'none';
                c.classList.add('sch-class-card--conflict');
              }
            });
          }
        } else {
          card.classList.remove('sch-class-card--mine');
          btn.className      = 'sch-btn-register';
          btn.dataset.action = 'register';
          btn.innerHTML      = '<i class="fas fa-plus"></i> Đăng ký';
          btn.disabled = false;

          // Hiện lại các card cùng slot_key đang bị ẩn do conflict
          const slotKey = card.dataset.slotKey;
          if (slotKey) {
            document.querySelectorAll(`.sch-class-card[data-slot-key="${slotKey}"]`).forEach(c => {
              if (c !== card && c.classList.contains('sch-class-card--conflict')) {
                c.style.display = '';
                c.classList.remove('sch-class-card--conflict');
              }
            });
          }
        }
      }

      const listCard = btn.closest('.sch-list-card');
      if (listCard && data.action === 'cancelled') {
        listCard.style.transition = 'opacity .3s, transform .3s';
        listCard.style.opacity    = '0';
        listCard.style.transform  = 'translateY(-8px)';
        setTimeout(() => listCard.remove(), 300);
      }

    } else {
      showToast(data.message, true);
      btn.innerHTML = origHTML;
      btn.disabled  = false;
    }
  } catch (err) {
    showToast('Lỗi kết nối, vui lòng thử lại', true);
    btn.innerHTML = origHTML;
    btn.disabled  = false;
  }
}

document.addEventListener('click', e => {
  const btnReg = e.target.closest('.sch-btn-register');
  if (btnReg) { doClassAction(btnReg.dataset.classId, 'register', btnReg); return; }

  const btnCan = e.target.closest('.sch-btn-cancel');
  if (btnCan) { doClassAction(btnCan.dataset.classId, 'cancel', btnCan); return; }

  const btnCanList = e.target.closest('.sch-btn-cancel-list');
  if (btnCanList) { doClassAction(btnCanList.dataset.classId, 'cancel', btnCanList); }
});


/* ══════════════════════════════════
   AI WORKOUT PLANNER MODAL
══════════════════════════════════ */

let _aiCurrentClass = null;

// Mở modal khi click vào class card (không phải click vào nút đăng ký/hủy/conflict)
document.addEventListener('click', e => {
  const card = e.target.closest('.sch-class-card');
  if (!card) return;
  if (e.target.closest('button, .sch-btn-conflict')) return;

  const classId = card.dataset.classId;
  if (!classId) return;

  // Chỉ cho phép mở AI nếu lớp đã đăng ký
  if (!card.classList.contains('sch-class-card--mine')) {
    showToast('Bạn cần đăng ký lớp này trước khi sử dụng AI lên lịch tập', true);
    return;
  }

  const name     = card.querySelector('.sch-class-name')?.textContent?.trim() || '—';
  const time     = card.querySelector('.sch-class-time')?.textContent?.trim() || '—';
  const trainer  = card.querySelector('.sch-meta-row:nth-child(1)')?.textContent?.trim() || '';
  const room     = card.querySelector('.sch-meta-row:nth-child(2)')?.textContent?.trim() || '';
  const pkgTag   = card.querySelector('.sch-class-pkg-tag');
  const pkgText  = pkgTag?.textContent?.trim() || '';
  const pkgColor = pkgTag?.style?.color || '#d4a017';
  const stripe   = card.querySelector('.sch-card-pkg-stripe');
  const stripeBg = stripe?.style?.background || '';

  _aiCurrentClass = { classId, name, time, trainer, room, pkgText, pkgColor, stripeBg };
  openAIModal(_aiCurrentClass);
});

function openAIModal(cls) {
  document.getElementById('aiClassName').textContent   = cls.name;
  document.getElementById('aiClassTime').innerHTML     = '<i class="fas fa-clock"></i> ' + cls.time;
  document.getElementById('aiClassTrainer').innerHTML  = '<i class="fas fa-user-tie"></i> ' + (cls.trainer || '—');
  document.getElementById('aiClassRoom').innerHTML     = '<i class="fas fa-door-open"></i> ' + (cls.room || '—');
  document.getElementById('aiPkgStripe').style.background = cls.stripeBg;

  const pkgTag = document.getElementById('aiPkgTag');
  if (cls.pkgText) {
    pkgTag.style.display     = 'inline-block';
    pkgTag.textContent       = cls.pkgText;
    pkgTag.style.background  = cls.pkgColor + '18';
    pkgTag.style.color       = cls.pkgColor;
    pkgTag.style.border      = '1px solid ' + cls.pkgColor + '44';
  } else {
    pkgTag.style.display = 'none';
  }

  // Reset output về trạng thái ban đầu
  document.getElementById('aiOutputBox').innerHTML = `
    <div class="ai-output-empty">
      <i class="fas fa-dumbbell"></i>
      <p>Nhập thông số cơ thể và nhấn<br><strong>Tạo lịch tập AI</strong> để bắt đầu</p>
    </div>`;

  aiCalcBMI();

  document.getElementById('aiPlannerOverlay').classList.add('active');
  document.body.style.overflow = 'hidden';
}

function closeAIModal() {
  document.getElementById('aiPlannerOverlay').classList.remove('active');
  document.body.style.overflow = '';
}

document.getElementById('aiModalClose')?.addEventListener('click', closeAIModal);
document.getElementById('aiPlannerOverlay')?.addEventListener('click', e => {
  if (e.target === document.getElementById('aiPlannerOverlay')) closeAIModal();
});

/* ── BMI CALC ─────────────────────────────────────────────────── */
function aiCalcBMI() {
  const h      = parseFloat(document.getElementById('aiHeight')?.value) / 100;
  const w      = parseFloat(document.getElementById('aiWeight')?.value);
  const age    = parseFloat(document.getElementById('aiAge')?.value) || 25;
  const gender = document.getElementById('aiGender')?.value || 'male';

  const chips  = document.getElementById('aiBmiChips');
  const genBtn = document.getElementById('aiGenBtn');

  if (!h || !w || h < 1 || h > 2.5 || w < 30) {
    chips.style.display = 'none';
    genBtn.disabled = true;
    return null;
  }

  const bmi = w / (h * h);
  let cat = '', color = '';
  if      (bmi < 18.5) { cat = 'Thiếu cân';  color = '#3b82f6'; }
  else if (bmi < 23)   { cat = 'Bình thường'; color = '#22c55e'; }
  else if (bmi < 25)   { cat = 'Thừa cân';    color = '#f59e0b'; }
  else if (bmi < 30)   { cat = 'Béo phì I';   color = '#ef4444'; }
  else                 { cat = 'Béo phì II';  color = '#991b1b'; }

  // TDEE Mifflin-St Jeor
  let bmr;
  if (gender === 'male') bmr = 10*w + 6.25*(h*100) - 5*age + 5;
  else                   bmr = 10*w + 6.25*(h*100) - 5*age - 161;
  const tdee = Math.round(bmr * 1.55);

  const burnPct    = bmi < 18.5 ? 0.12 : bmi < 23 ? 0.18 : bmi < 25 ? 0.22 : bmi < 30 ? 0.28 : 0.32;
  const burnTarget = Math.round(tdee * burnPct);

  document.getElementById('aiBmiVal').textContent     = bmi.toFixed(1);
  document.getElementById('aiBmiCat').textContent     = cat;
  document.getElementById('aiBmiCat').style.color     = color;
  document.getElementById('aiBurnTarget').textContent = burnTarget.toLocaleString('vi-VN') + ' kcal';
  chips.style.display = 'flex';
  genBtn.disabled = false;

  return { bmi: bmi.toFixed(1), bmi_cat: cat, tdee, burnTarget, color, age, gender };
}

['aiHeight','aiWeight','aiAge','aiGender'].forEach(id => {
  document.getElementById(id)?.addEventListener('input', aiCalcBMI);
  document.getElementById(id)?.addEventListener('change', aiCalcBMI);
});

/* ── SNAP BURN TARGET về preset cố định theo BMI + gender + duration ──
   Mục đích: để cache JSONL hit được, tránh mỗi người TDEE khác → miss cache
   Preset khớp với generate_data.py (Nam gốc, Nữ × 0.85)
─────────────────────────────────────────────────────────────────────── */
function snapBurnTarget(bmi, gender, durationMin) {
  // Preset Nam theo [dur45, dur60, dur90]
  const presets = [
    { maxBmi: 18.5, burns: { 45: 150, 60: 200, 90: 280 } },  // Thiếu cân
    { maxBmi: 23.0, burns: { 45: 280, 60: 380, 90: 520 } },  // Bình thường
    { maxBmi: 25.0, burns: { 45: 320, 60: 430, 90: 580 } },  // Thừa cân
    { maxBmi: 30.0, burns: { 45: 360, 60: 480, 90: 640 } },  // Béo phì I
    { maxBmi: 99.0, burns: { 45: 380, 60: 500, 90: 660 } },  // Béo phì II
  ];
  // Làm tròn duration về mốc gần nhất [45, 60, 90]
  const durKey = [45, 60, 90].reduce((a, b) =>
    Math.abs(b - durationMin) < Math.abs(a - durationMin) ? b : a
  );
  const bucket = presets.find(p => bmi < p.maxBmi) || presets[presets.length - 1];
  let burn = bucket.burns[durKey] || bucket.burns[60];
  if (gender === 'female' || gender === 'Nữ') burn = Math.round(burn * 0.85);
  return burn;
}

/* ── GENERATE ──────────────────────────────────────────────────── */
document.getElementById('aiGenBtn')?.addEventListener('click', () => {
  const bmiData = aiCalcBMI();
  if (!bmiData || !_aiCurrentClass) return;

  const goalRaw = document.getElementById('aiGoal').value;
  const goalMap = {
    lose_fat: 'Giảm mỡ', build_muscle: 'Tăng cơ',
    endurance: 'Tăng sức bền', maintain: 'Duy trì thể hình'
  };
  const goalText  = goalMap[goalRaw] || goalRaw;
  const genderMap = { male: 'Nam', female: 'Nữ' };

  const bmiFloat = parseFloat(bmiData.bmi);

  // Parse duration từ time string "12:00–14:00"
  let durationMin = 60;
  if (_aiCurrentClass.time) {
    const m = _aiCurrentClass.time.match(/(\d{1,2}):(\d{2})\s*[–\-]\s*(\d{1,2}):(\d{2})/);
    if (m) {
      durationMin = (parseInt(m[3]) * 60 + parseInt(m[4])) - (parseInt(m[1]) * 60 + parseInt(m[2]));
      if (durationMin <= 0) durationMin = 60;
    }
  }
  const burnSnapped = snapBurnTarget(bmiFloat, bmiData.gender, durationMin);

  const btn       = document.getElementById('aiGenBtn');
  const outputBox = document.getElementById('aiOutputBox');

  btn.disabled  = true;
  btn.innerHTML = '<span class="ai-spinner" style="width:16px;height:16px;border-width:2px;display:inline-block"></span> Đang tạo...';

  // Xoá equipment tag cũ nếu có
  document.getElementById('aiEquipmentTag')?.remove();

  // ── Gọi streaming ─────────────────────────────────────────
  fetchWorkoutStream(
    {
      class_id:    parseInt(_aiCurrentClass.classId),
      bmi:         bmiFloat,
      bmi_cat:     bmiData.bmi_cat,
      goal:        goalText,
      burn_target: burnSnapped,
      age:         bmiData.age,
      gender:      genderMap[bmiData.gender] || 'Nam',
    },
    outputBox,

    // onMeta: hiện equipment tag ngay khi nhận được
    (meta) => {
      if (meta.equipment) {
        const tag     = document.createElement('div');
        tag.id        = 'aiEquipmentTag';
        tag.className = 'ai-equipment-tag';
        tag.innerHTML = `<i class="fas fa-tools"></i>
                         <span>Thiết bị phòng ${meta.room}: ${meta.equipment}</span>`;
        outputBox.insertAdjacentElement('beforebegin', tag);
      }
    },

    // onDone: kiểm tra kcal + re-enable nút
    (fullText) => {
      clearTimeout(safetyTimer);

      const kcalMatch = fullText.match(/tổng\s*kcal\s*[:\-~≈]*\s*~?(\d+)/i);
      const aiKcal    = kcalMatch ? parseInt(kcalMatch[1]) : null;
      const kcalDiff  = aiKcal ? Math.abs(aiKcal - burnSnapped) / burnSnapped : 0;

      if (aiKcal && kcalDiff > 0.15) {
        const icon  = aiKcal > burnSnapped ? '⚠️' : 'ℹ️';
        const color = aiKcal > burnSnapped ? '#ef4444' : '#f59e0b';
        const warn  = document.createElement('div');
        warn.style.cssText = `background:${color}18;border:1px solid ${color}44;border-radius:8px;
                               padding:10px 14px;margin-bottom:10px;font-size:12.5px;color:${color}`;
        warn.innerHTML = `${icon} Lưu ý: AI tạo lịch ~${aiKcal.toLocaleString('vi-VN')} kcal,
          mục tiêu của bạn là ${burnSnapped.toLocaleString('vi-VN')} kcal
          (lệch ${Math.round(kcalDiff*100)}%). Nhấn <strong>Tạo lại</strong> nếu muốn kết quả sát hơn.`;
        outputBox.insertAdjacentElement('afterbegin', warn);
      }

      btn.disabled  = false;
      btn.innerHTML = '<i class="fas fa-bolt"></i> Tạo lại';
    }
  );

  // Safety net: re-enable nút sau 120s nếu stream không kết thúc bình thường
  const safetyTimer = setTimeout(() => {
    btn.disabled  = false;
    btn.innerHTML = '<i class="fas fa-bolt"></i> Tạo lại';
  }, 120_000);
});

/* ══════════════════════════════════
   MARKDOWN → HTML (universal parser v2)
   Xử lý được TẤT CẢ format Ollama 1.5b có thể sinh ra:
   F1. Cache:   "Tên [thiết bị] • sets×reps • nghỉ Xs • ~N kcal"
   F2. Bold:    "**KHỞI ĐỘNG (5-8 phút) — tổng 61 kcal**" làm heading
   F3. Dash:    "- Tên bài: sets×reps • nghỉ Xs • ~N kcal"
   F4. Colon:   "Tên bài: 4 sets × 12 reps, nghỉ 60s, ~80 kcal"
   F5. Mixed:   "**Tên bài** • chi tiết"
══════════════════════════════════ */
function aiMarkdownToHTML(md) {
  const SECTION_CFG = {
    '🔥': { bg: 'rgba(239,68,68,0.08)',  border: '#ef4444' },
    '💪': { bg: 'rgba(212,160,23,0.08)', border: '#d4a017' },
    '🧘': { bg: 'rgba(34,197,94,0.08)',  border: '#22c55e' },
    '📊': { bg: 'rgba(99,102,241,0.08)', border: '#6366f1' },
  };

  // Detect heading — nhận ### / ## / **TEXT** / TEXT viết hoa hoàn toàn có emoji section
  function detectHeading(raw) {
    let t = raw.trim();

    // ### 🔥 KHỞI ĐỘNG ...  hoặc  ## TỔNG KẾT
    if (/^#{2,4}\s/.test(t))
      return t.replace(/^#+\s*/, '').replace(/\*\*/g, '').trim();

    // **🔥 KHỞI ĐỘNG (5-8 phút) — tổng 61 kcal**
    const boldFull = t.match(/^\*\*([^*]+)\*\*\s*$/);
    if (boldFull) {
      const inner = boldFull[1].trim();
      if (/KHỞI ĐỘNG|BÀI TẬP CHÍNH|GIÃN CƠ|TỔNG KẾT|🔥|💪|🧘|📊/i.test(inner))
        return inner;
    }

    // **KHỞI ĐỘNG** ở đầu dòng (không đóng ngay cuối)
    const boldStart = t.match(/^\*\*(KHỞI ĐỘNG|BÀI TẬP CHÍNH|GIÃN CƠ|TỔNG KẾT)[^*]*\*\*/i);
    if (boldStart) return t.replace(/\*\*/g, '').trim();

    return null;
  }

  /* ── Parse một dòng bài tập → {name, equip, details[]} ── */
  function parseLine(raw) {
    // Strip prefix: bullet, số, #### heading phụ
    let line = raw
      .replace(/^#{2,4}\s+/, '')        // bỏ #### Set 1:
      .replace(/^[-*•·]\s*/, '')        // bỏ bullet
      .replace(/^\d+\.\s*/, '')         // bỏ 1.
      .replace(/\*\*/g, '')             // bỏ bold **
      .trim();

    if (!line) return null;

    // Bỏ qua dòng mô tả / hướng dẫn format
    if (/^(mỗi bài|lưu ý|note|ghi chú|\(.*\)$)/i.test(line)) return null;
    if (/^set\s+\d+\s*:/i.test(line)) return null;
    // Bỏ qua dòng chỉ là số kcal tổng
    if (/^(tổng|total)\s*(kcal|calories)/i.test(line)) return null;

    // Chuẩn hoá 'Ví dụ 1:' → bỏ prefix
    line = line.replace(/^ví dụ\s*\d*\s*:\s*/i, '').trim();
    if (!line) return null;

    /* ── FORMAT 1: có dấu • phân cách (cache JSONL) ── */
    if (/[•·]/.test(line)) {
      const parts     = line.split(/\s*[•·]\s*/);
      const namePart  = parts[0].trim();
      const nameClean = namePart.replace(/\[.*?\]/g, '').replace(/:\s*$/, '').trim();
      const equip     = (namePart.match(/\[(.+?)\]/) || [])[1] || '';
      const details   = parts.slice(1).map(d => d.trim()).filter(Boolean);
      if (nameClean) return { name: nameClean, equip, details };
    }

    /* ── FORMAT 2: "Tên bài [equip]: detail, detail" hoặc "Tên (equip): detail" ── */
    // Tìm colon đầu tiên không nằm trong [ ] hay ( )
    const colonMatch = line.match(/^([^\[\(:]{2,55})(?:\s*[\[\(]([^\]\)]+)[\]\)])?\s*:\s*(.*)$/);
    if (colonMatch) {
      const namePart = colonMatch[1].trim();
      const equip    = colonMatch[2]?.trim() || '';
      const rest     = colonMatch[3]?.trim() || '';
      // Không phải label tổng kết
      if (!/^(tổng|total|lời khuyên|đạt|đảm)/i.test(namePart) && namePart.length > 1) {
        const details = rest
          ? rest.split(/[,;•·|]/).map(d => d.trim()).filter(d => d.length > 0)
          : [];
        return { name: namePart, equip, details };
      }
    }

    /* ── FORMAT 3: plain text, không dấu phân cách ── */
    const nameClean = line.replace(/\[.*?\]/g, '').replace(/\(.*?\)/g, '').replace(/:\s*$/, '').trim();
    const equip     = (line.match(/\[(.+?)\]/) || [])[1]
                   || (line.match(/\(([A-Z][^)]{3,40})\)/) || [])[1]  // chỉ lấy tên thiết bị viết hoa
                   || '';
    if (nameClean.length < 2) return null;
    return { name: nameClean, equip, details: [] };
  }

  /* ── Render card bài tập ── */
  function renderCard(item) {
    // Phân loại chip: kcal chip có màu vàng, chip còn lại màu xám
    const chipHTML = item.details.map(d => {
      const isKcal = /kcal/i.test(d);
      const style  = isKcal
        ? 'background:rgba(212,160,23,0.12);border-color:rgba(212,160,23,0.3);color:#d4a017'
        : '';
      return `<span class="ai-detail-chip" style="${style}">${d}</span>`;
    }).join('');

    return `<div class="ai-exercise-card">
      <div class="ai-exercise-name">${item.name}</div>
      ${item.equip
        ? `<div class="ai-exercise-equip"><i class="fas fa-dumbbell"></i> ${item.equip}</div>`
        : ''}
      ${item.details.length
        ? `<div class="ai-exercise-details">${chipHTML}</div>`
        : ''}
    </div>`;
  }

  /* ── Flush section ra HTML ── */
  let html = '';
  let currentTitle = '', currentColor = null, isSummary = false;
  let sectionItems = [];

  function flushSection() {
    if (!currentTitle) return;
    const c = currentColor || { bg: 'rgba(255,255,255,0.04)', border: '#555' };

    if (isSummary) {
      // TỔNG KẾT: mỗi phần tách bởi | hoặc newline → 1 chip
      const allText = sectionItems.join(' ');
      const parts   = allText
        .split(/[|\n]/)
        .map(s => s.replace(/\*\*/g, '').trim())
        .filter(Boolean);
      html += `<div class="ai-section" style="background:${c.bg};border-left:3px solid ${c.border}">
        <div class="ai-section-title">${currentTitle}</div>
        <div class="ai-summary-chips">${parts.map(p => `<div class="ai-chip">${p}</div>`).join('')}</div>
      </div>`;
    } else {
      const cards = sectionItems
        .map(raw => parseLine(raw))
        .filter(Boolean)
        .map(renderCard)
        .join('');
      html += `<div class="ai-section" style="background:${c.bg};border-left:3px solid ${c.border}">
        <div class="ai-section-title">${currentTitle}</div>
        <div class="ai-exercise-list">${cards || '<p style="color:#888;font-size:13px;padding:8px 0">Không có dữ liệu</p>'}</div>
      </div>`;
    }
    sectionItems = []; currentColor = null; currentTitle = ''; isSummary = false;
  }

  /* ── Duyệt từng dòng ── */
  for (const rawLine of md.split('\n')) {
    const line = rawLine.trim();
    if (!line) continue;

    const heading = detectHeading(line);
    if (heading) {
      flushSection();
      currentTitle = heading;
      isSummary    = /📊|tổng kết/i.test(heading);
      currentColor = null;
      for (const [emoji, cfg] of Object.entries(SECTION_CFG)) {
        if (heading.includes(emoji)) { currentColor = cfg; break; }
      }
      // Fallback màu nếu không có emoji nhưng là section quen thuộc
      if (!currentColor) {
        if (/khởi động/i.test(heading))    currentColor = SECTION_CFG['🔥'];
        else if (/bài tập chính/i.test(heading)) currentColor = SECTION_CFG['💪'];
        else if (/giãn cơ/i.test(heading)) currentColor = SECTION_CFG['🧘'];
        else if (/tổng kết/i.test(heading)) currentColor = SECTION_CFG['📊'];
      }
      continue;
    }

    if (currentTitle) sectionItems.push(line);
  }
  flushSection();

  return html || `<div style="padding:12px;color:#ccc;white-space:pre-wrap;font-size:13px;line-height:1.7">${md}</div>`;
}
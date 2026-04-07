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

/* ── GENERATE ──────────────────────────────────────────────────── */
document.getElementById('aiGenBtn')?.addEventListener('click', async () => {
  const bmiData = aiCalcBMI();
  if (!bmiData || !_aiCurrentClass) return;

  const goalRaw = document.getElementById('aiGoal').value;
  const goalMap = {
    lose_fat: 'Giảm mỡ', build_muscle: 'Tăng cơ',
    endurance: 'Tăng sức bền', maintain: 'Duy trì thể hình'
  };
  const goalText = goalMap[goalRaw] || goalRaw;

  const genderMap = { male: 'Nam', female: 'Nữ' };

  const btn = document.getElementById('aiGenBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="ai-spinner" style="width:16px;height:16px;border-width:2px;display:inline-block"></span> Đang tạo...';

  const outputBox = document.getElementById('aiOutputBox');
  outputBox.innerHTML = '<div class="ai-loading"><div class="ai-spinner"></div>AI đang phân tích thiết bị phòng và tạo lịch tập...</div>';

  try {
    const res = await fetch('ai_proxy.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        class_id:    parseInt(_aiCurrentClass.classId),
        bmi:         parseFloat(bmiData.bmi),
        bmi_cat:     bmiData.bmi_cat,
        goal:        goalText,
        burn_target: bmiData.burnTarget,
        age:         bmiData.age,
        gender:      genderMap[bmiData.gender] || 'Nam'
      })
    });

    const data = await res.json();

    if (!data.success) throw new Error(data.message || 'Lỗi từ server');

    const equipHTML = data.equipment
      ? `<div class="ai-equipment-tag">
           <i class="fas fa-tools"></i>
           <span>Thiết bị phòng ${data.room}: ${data.equipment}</span>
         </div>`
      : '';

    outputBox.innerHTML = equipHTML + '<div class="ai-plan">' + aiMarkdownToHTML(data.text) + '</div>';

  } catch(e) {
    outputBox.innerHTML = `<div style="color:#ef4444;padding:16px;font-size:13px">
      ⚠️ Lỗi AI: ${e.message}
      <br><small style="color:#888;margin-top:8px;display:block">
        Kiểm tra: Ollama đang chạy? (ollama serve)<br>
        Model đã pull? (ollama pull qwen2.5:3b)
      </small>
    </div>`;
  }

  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-bolt"></i> Tạo lại';
});

/* ══════════════════════════════════
   MARKDOWN → HTML (universal parser)
   Xử lý được cả 2 format:
   1. Cache/JSONL: "Tên bài [thiết bị] • sets×reps • nghỉ Xs • ~N kcal"
   2. Ollama raw:  "**Tên bài:** 3×5", "SET 1:", "- Tên bài: chi tiết"
══════════════════════════════════ */
function aiMarkdownToHTML(md) {
  const sectionColors = {
    '🔥': { bg: 'rgba(239,68,68,0.08)',  border: '#ef4444' },
    '💪': { bg: 'rgba(212,160,23,0.08)', border: '#d4a017' },
    '🧘': { bg: 'rgba(34,197,94,0.08)',  border: '#22c55e' },
    '📊': { bg: 'rgba(99,102,241,0.08)', border: '#6366f1' },
  };

  const lines = md.split('\n');
  let html = '';
  let inSection = false, isSummary = false;
  let currentTitle = '', currentColor = null;
  let sectionItems = [];

  /* ── Normalize một dòng bài tập về object {name, equip, details[]} ── */
  function parseLine(raw) {
    // Bỏ prefix bullet / số thứ tự
    let line = raw
      .replace(/^[-*•·]\s*/, '')
      .replace(/^\d+\.\s*/, '')
      .replace(/\*\*/g, '')   // bỏ bold markdown
      .trim();

    if (!line) return null;

    // Bỏ qua các dòng chú thích / hướng dẫn
    // Bỏ qua dòng hướng dẫn/chú thích, KHÔNG lọc 'Ví dụ' vì cache dùng format đó
    if (/^(mỗi bài|lưu ý|note|set \d+:)/i.test(line)) return null;
    if (line.startsWith('(') && line.endsWith(')')) return null;

    // Chuẩn hoá 'Ví dụ 1:', 'Ví dụ:' → bỏ prefix, giữ phần bài tập thực
    line = line.replace(/^ví dụ\s*\d*\s*:\s*/i, '').trim();
    if (!line) return null;

    /* Format 1 (JSONL cache): "Tên [thiết bị] • chi tiết • chi tiết"
       Dấu phân cách: • hoặc · */
    const hasBulletSep = /[•·]/.test(line);

    if (hasBulletSep) {
      const parts     = line.split(/\s*[•·]\s*/);
      const namePart  = parts[0].trim();
      const nameClean = namePart.replace(/\[.*?\]/g, '').replace(/:\s*$/, '').trim();
      const equip     = (namePart.match(/\[(.+?)\]/) || [])[1] || '';
      const details   = parts.slice(1).map(d => d.trim()).filter(Boolean);
      return { name: nameClean, equip, details };
    }

    /* Format 2 (Ollama raw): "Tên bài: sets×reps • nghỉ" hoặc "Tên bài (thiết bị): ..."  */
    const colonIdx = line.indexOf(':');
    if (colonIdx > 0 && colonIdx < 60) {
      const namePart  = line.slice(0, colonIdx).trim();
      const restPart  = line.slice(colonIdx + 1).trim();
      // Kiểm tra có phải tên bài thật không (không phải label như "SET 1", "Tổng kcal")
      if (!/^(set|tổng|total|lời khuyên|đạt)/i.test(namePart)) {
        const nameClean = namePart.replace(/\(.*?\)/g, '').replace(/\[.*?\]/g, '').trim();
        const equip     = (namePart.match(/[\(\[](.+?)[\)\]]/) || [])[1] || '';
        const details   = restPart
          ? restPart.split(/[,;•·|]/).map(d => d.trim()).filter(Boolean)
          : [];
        return { name: nameClean, equip, details };
      }
    }

    /* Format 3: dòng thuần text, không có dấu phân cách */
    const nameClean = line.replace(/\[.*?\]/g, '').replace(/\(.*?\)/g, '').replace(/:\s*$/, '').trim();
    const equip     = (line.match(/\[(.+?)\]/) || [])[1] || '';
    return { name: nameClean, equip, details: [] };
  }

  /* ── Render một card bài tập ── */
  function renderCard(item) {
    return `<div class="ai-exercise-card">
      <div class="ai-exercise-name">${item.name}</div>
      ${item.equip ? `<div class="ai-exercise-equip"><i class="fas fa-dumbbell"></i> ${item.equip}</div>` : ''}
      ${item.details.length ? `<div class="ai-exercise-details">${item.details.map(d => `<span class="ai-detail-chip">${d}</span>`).join('')}</div>` : ''}
    </div>`;
  }

  /* ── Flush section hiện tại ra HTML ── */
  function flushSection() {
    if (!currentTitle) return;
    const c = currentColor || { bg: 'rgba(255,255,255,0.04)', border: '#555' };

    if (isSummary) {
      // Phần TỔNG KẾT: gộp tất cả text, split theo | hoặc xuống dòng
      const joined = sectionItems.join(' | ');
      const parts  = joined
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

    sectionItems = []; isSummary = false; currentColor = null; currentTitle = '';
  }

  /* ── Duyệt từng dòng ── */
  for (const rawLine of lines) {
    const line = rawLine.trim();
    if (!line) continue;

    // Heading section (### hoặc ##)
    if (/^#{2,3}\s/.test(line)) {
      flushSection();
      currentTitle = line.replace(/^#+\s*/, '').trim();
      inSection    = true;
      isSummary    = /📊|tổng kết/i.test(currentTitle);
      currentColor = null;
      for (const [emoji, cfg] of Object.entries(sectionColors)) {
        if (currentTitle.includes(emoji)) { currentColor = cfg; break; }
      }
      continue;
    }

    // Dòng "SET N:" của Ollama → bỏ qua (không phải bài tập)
    if (/^set\s+\d+\s*:/i.test(line)) continue;

    if (inSection) {
      sectionItems.push(line);
    }
  }
  flushSection();

  return html || `<div style="padding:12px;color:#ccc;white-space:pre-wrap;font-size:13px">${md}</div>`;
}

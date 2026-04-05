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
        } else {
          card.classList.remove('sch-class-card--mine');
          btn.className      = 'sch-btn-register';
          btn.dataset.action = 'register';
          btn.innerHTML      = '<i class="fas fa-plus"></i> Đăng ký';
        }
        btn.disabled = false;
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
    // ── Gửi class_id + thông số user lên PHP proxy ──
    // PHP sẽ tự query DB lấy thiết bị phòng rồi gọi Ollama
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

    // Hiển thị thiết bị phòng phía trên lịch tập
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
        Model đã pull? (ollama pull llama3.2:3b)
      </small>
    </div>`;
  }

  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-bolt"></i> Tạo lại';
});

/* ── MARKDOWN → HTML ───────────────────────────────────────────── */
function aiMarkdownToHTML(md) {
  return md
    .replace(/^### (.+)$/gm, '<h3>$1</h3>')
    .replace(/^## (.+)$/gm,  '<h3>$1</h3>')
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/^- (.+)$/gm, '<li>$1</li>')
    .replace(/(<li>.*?<\/li>\n?)+/gs, m => '<ul>' + m + '</ul>')
    .split('\n').filter(l => l.trim()).join('\n');
}

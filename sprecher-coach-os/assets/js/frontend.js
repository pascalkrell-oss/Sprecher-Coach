(() => {
  const api = (path, options = {}) => fetch(`${scoData.restUrl}${path}`, {
    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scoData.nonce },
    credentials: 'same-origin',
    ...options,
  }).then(r => r.json());

  const animateNumber = (el, to) => {
    if (!el) return;
    const from = parseInt(el.dataset.value || '0', 10);
    const steps = 20;
    let i = 0;
    const timer = setInterval(() => {
      i++;
      const value = Math.round(from + ((to - from) * (i / steps)));
      el.textContent = el.textContent.replace(/\d+/, value);
      if (i >= steps) {
        el.dataset.value = to;
        clearInterval(timer);
      }
    }, 20);
  };

  const lockBadge = (msg) => `<div class="sco-lock">🔒 ${msg}</div>`;

  const renderDashboard = async () => {
    const root = document.querySelector('[data-sco-module="dashboard"]');
    if (!root) return;
    const data = await api('dashboard');
    root.querySelector('[data-sco-daily-card]').innerHTML = `
      <h3>${data.drill.title}</h3>
      <p>${data.drill.description}</p>
      <p><strong>${data.cta.dashboard_nudge}</strong></p>
      <div class="sco-progress"><div style="width:${Math.min((data.progress.weekly_count / data.progress.weekly_goal) * 100, 100)}%"></div></div>
      <small>Wochenziel: ${data.progress.weekly_count}/${data.progress.weekly_goal}</small>
    `;
    const streak = root.querySelector('[data-sco-streak]');
    const level = root.querySelector('[data-sco-level]');
    streak.textContent = `Streak ${data.progress.streak}`;
    level.textContent = `Level ${data.progress.level}`;
    animateNumber(streak, data.progress.streak);
    animateNumber(level, data.progress.level);
  };

  const setupTimer = (root) => {
    let sec = 0, interval = null;
    const display = root.querySelector('[data-sco-timer]');
    root.querySelector('[data-sco-start]')?.addEventListener('click', () => {
      if (interval) return;
      interval = setInterval(() => {
        sec++;
        const m = String(Math.floor(sec / 60)).padStart(2, '0');
        const s = String(sec % 60).padStart(2, '0');
        display.textContent = `${m}:${s}`;
      }, 1000);
    });
    root.querySelector('[data-sco-pause]')?.addEventListener('click', () => { clearInterval(interval); interval = null; });
    root.querySelector('[data-sco-reset]')?.addEventListener('click', () => { clearInterval(interval); interval = null; sec = 0; display.textContent = '00:00'; });
  };

  const renderDaily = async () => {
    const root = document.querySelector('[data-sco-module="daily"]');
    if (!root) return;
    const wrap = root.querySelector('[data-sco-daily]');
    const data = await api('dashboard');
    const questions = data.drill.self_check_questions || [];
    wrap.innerHTML = `
      <h3>${data.drill.title}</h3>
      <p>${data.drill.description}</p>
      <div class="sco-timer"><strong data-sco-timer>00:00</strong> <button class="sco-btn" data-sco-start>Start</button> <button class="sco-btn" data-sco-pause>Pause</button> <button class="sco-btn" data-sco-reset>Reset</button></div>
      <div class="sco-questions">${questions.map((q, i) => q.type.includes('scale') ? `<label>${q.label}<input type="range" min="1" max="5" value="3" data-q="${i}" data-type="scale_1_5"></label>` : `<label><input type="checkbox" data-q="${i}" data-type="checkbox_multi"> ${q.label}</label>`).join('')}</div>
      <button class="sco-btn sco-btn-primary" data-sco-complete>Abschließen</button>
      <div data-sco-result></div>
    `;
    setupTimer(wrap);

    wrap.querySelector('[data-sco-complete]').addEventListener('click', async () => {
      if (!scoData.isLoggedIn) {
        wrap.querySelector('[data-sco-result]').innerHTML = '<p>Bitte einloggen, um Fortschritt zu speichern.</p>';
        return;
      }
      const answers = Array.from(wrap.querySelectorAll('[data-q]')).map((el) => ({
        type: el.dataset.type,
        value: el.dataset.type.includes('scale') ? parseInt(el.value, 10) : el.checked,
      }));
      const result = await api('complete-drill', { method: 'POST', body: JSON.stringify({ drill_id: data.drill.id, answers }) });
      wrap.querySelector('[data-sco-result]').innerHTML = `<p><strong>Score ${result.score}/100</strong> · +${result.xp} XP</p><p>${result.feedback}</p>`;
    });
  };

  const renderSkilltree = async () => {
    const module = document.querySelector('[data-sco-module="skilltree"]');
    const root = module?.querySelector('[data-sco-skilltree]');
    if (!root) return;
    const rows = await api('skilltree');
    const tooltip = module.dataset.lockedSkill || 'Premium erforderlich';
    root.innerHTML = rows.map((row) => `<div class="sco-card ${row.locked ? 'sco-locked' : ''}" title="${tooltip}"><h3>${row.skill}</h3><p>Level ${row.level} · ${row.xp} XP</p>${row.locked ? lockBadge(tooltip) : ''}</div>`).join('');
  };

  const renderMissions = async () => {
    const module = document.querySelector('[data-sco-module="missions"]');
    const root = module?.querySelector('[data-sco-missions]');
    if (!root) return;
    const response = await api('missions');
    const missions = response.items || [];
    const tooltip = module.dataset.lockedMissions || response.premium_tooltip || 'Premium erforderlich';
    root.innerHTML = missions.map((m) => `<div class="sco-card ${m.locked ? 'sco-locked' : ''}" title="${tooltip}"><h3>${m.title}</h3><p>${m.description}</p><small>${m.duration_days} Tage</small>${m.locked ? lockBadge(tooltip) : ''}</div>`).join('');
  };

  const renderLibrary = async () => {
    const module = document.querySelector('[data-sco-module="library"]');
    if (!module) return;
    const target = module.querySelector('[data-sco-library]');
    const notice = module.querySelector('[data-sco-library-notice]');

    const load = async () => {
      const data = await api('library');
      target.innerHTML = data.items.map((item) => `<div class="sco-card"><h3>${item.title}</h3><p>${item.content}</p></div>`).join('');
      if (!data.premium && data.items.length >= data.limit) {
        const href = data.checkout_url || module.dataset.upgradeUrl || '#';
        notice.innerHTML = `<p>${data.copy.free_limit_reached}</p><a class="sco-btn sco-btn-primary" href="${href}">${data.copy.upgrade_primary}</a>`;
      } else {
        notice.innerHTML = '';
      }
    };
    module.querySelector('[data-sco-random]')?.addEventListener('click', load);
    load();
  };

  renderDashboard();
  renderDaily();
  renderSkilltree();
  renderMissions();
  renderLibrary();
})();

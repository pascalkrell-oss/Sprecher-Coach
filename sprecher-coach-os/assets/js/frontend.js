(() => {
  const root = document.querySelector('.sco-app');
  if (!root) return;

  const appShell = root.querySelector('.sco-shell');
  if (!appShell) return;

  const api = async (path, options = {}) => {
    const response = await fetch(`${scoData.restUrl}${path}`, {
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scoData.nonce },
      credentials: 'same-origin',
      ...options,
    });

    const payload = await response.json();
    if (!response.ok) {
      throw new Error(payload?.message || 'Request failed');
    }

    return payload;
  };

  const state = {
    dashboard: null,
    drill: null,
    answers: {},
    library: [],
    premium: false,
  };

  const drawer = (() => {
    const backdrop = document.createElement('div');
    backdrop.className = 'sco-drawer-backdrop';
    const panel = document.createElement('aside');
    panel.className = 'sco-drawer';
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-modal', 'true');
    document.body.append(backdrop, panel);

    const close = () => {
      panel.classList.remove('is-open');
      backdrop.classList.remove('is-open');
      panel.innerHTML = '';
    };

    backdrop.addEventListener('click', close);
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') close();
    });

    return {
      open: ({ title, body }) => {
        panel.innerHTML = `
          <div class="sco-drawer-header">
            <h3>${title}</h3>
            <button type="button" class="sco-btn" data-close-drawer aria-label="Schließen">✕</button>
          </div>
          <div class="sco-drawer-content">${body}</div>
        `;
        panel.querySelector('[data-close-drawer]')?.addEventListener('click', close);
        panel.classList.add('is-open');
        backdrop.classList.add('is-open');
      },
      close,
    };
  })();

  const setTab = (tab) => {
    root.querySelectorAll('[data-tab]').forEach((button) => {
      button.classList.toggle('is-active', button.dataset.tab === tab);
    });
    root.querySelectorAll('[data-panel]').forEach((panel) => {
      panel.classList.toggle('is-active', panel.dataset.panel === tab);
    });
    window.location.hash = tab;
    window.localStorage.setItem('scoActiveTab', tab);
  };

  const initTabs = () => {
    root.querySelectorAll('[data-tab]').forEach((button) => {
      button.addEventListener('click', () => setTab(button.dataset.tab));
    });

    root.querySelectorAll('[data-sco-switch-tab]').forEach((button) => {
      button.addEventListener('click', (event) => {
        event.preventDefault();
        setTab(button.dataset.scoSwitchTab);
      });
    });

    const fromHash = (window.location.hash || '').replace('#', '');
    const fromStorage = window.localStorage.getItem('scoActiveTab');
    const initial = fromHash || fromStorage || 'today';
    setTab(initial);
  };

  const updateHeader = () => {
    if (!state.dashboard) return;
    const { progress } = state.dashboard;
    root.querySelector('[data-sco-streak]').textContent = `Streak ${progress.streak}`;
    root.querySelector('[data-sco-level]').textContent = `Level ${progress.level}`;
    root.querySelector('[data-sco-weekly]').textContent = `Wochenziel ${progress.weekly_count}/${progress.weekly_goal}`;
    const percent = Math.min((progress.weekly_count / progress.weekly_goal) * 100, 100);
    root.querySelector('[data-sco-weekly-progress]').style.width = `${percent}%`;
    root.querySelector('[data-sco-weekly-copy]').textContent = `Wochenziel: ${progress.weekly_count}/${progress.weekly_goal}`;
  };

  const renderToday = () => {
    const card = root.querySelector('[data-sco-today-card]');
    const next = root.querySelector('[data-sco-next-step]');
    const side = root.querySelector('[data-sco-side-next]');
    const { drill, cta } = state.dashboard;

    card.innerHTML = `
      <div class="sco-card-header"><h2>Heute: ${drill.title}</h2></div>
      <p>${drill.description}</p>
      <div class="sco-actions">
        <button type="button" class="sco-btn sco-btn-primary" data-sco-switch-tab="daily">Jetzt starten</button>
        <button type="button" class="sco-btn" data-sco-switch-tab="skilltree">Skilltree ansehen</button>
      </div>
    `;

    next.innerHTML = `
      <div class="sco-card-header"><h3>Nächste Aktion</h3></div>
      <p><strong>${drill.title}</strong></p>
      <p class="sco-muted">${cta.dashboard_nudge}</p>
      <div class="sco-actions"><button type="button" class="sco-btn" data-sco-switch-tab="daily">Drill öffnen</button></div>
    `;

    side.textContent = `Heute empfohlen: ${drill.title}`;
    initTabs();
  };

  const bindDailyActions = () => {
    let sec = 0;
    let timer = null;
    const display = root.querySelector('[data-sco-timer]');

    root.querySelector('[data-sco-start]')?.addEventListener('click', () => {
      if (timer) return;
      timer = window.setInterval(() => {
        sec += 1;
        const mm = String(Math.floor(sec / 60)).padStart(2, '0');
        const ss = String(sec % 60).padStart(2, '0');
        display.textContent = `${mm}:${ss}`;
      }, 1000);
    });

    root.querySelector('[data-sco-pause]')?.addEventListener('click', () => {
      window.clearInterval(timer);
      timer = null;
    });

    root.querySelector('[data-sco-reset]')?.addEventListener('click', () => {
      window.clearInterval(timer);
      timer = null;
      sec = 0;
      display.textContent = '00:00';
    });

    root.querySelectorAll('[data-toggle-key]').forEach((tile) => {
      tile.addEventListener('click', () => {
        const key = tile.dataset.toggleKey;
        const active = tile.classList.toggle('is-active');
        tile.setAttribute('aria-pressed', active ? 'true' : 'false');
        state.answers[key] = { type: 'checkbox_multi', value: active ? 1 : 0, key };
        updateCompleteState();
      });
    });

    root.querySelectorAll('[data-range-key]').forEach((slider) => {
      slider.addEventListener('input', () => {
        const key = slider.dataset.rangeKey;
        state.answers[key] = { type: 'scale_1_5', value: Number(slider.value), key };
        slider.closest('.sco-self-check-item')?.querySelector('[data-range-value]')?.replaceChildren(document.createTextNode(slider.value));
        updateCompleteState();
      });
    });

    root.querySelector('[data-sco-complete]')?.addEventListener('click', async () => {
      const answers = Object.values(state.answers).filter((entry) => Number(entry.value) > 0);
      if (answers.length < 2) return;

      const result = await api('complete-drill', {
        method: 'POST',
        body: JSON.stringify({ drill_id: state.drill.id, answers }),
      });

      root.querySelector('[data-sco-completion-card]').innerHTML = `
        <div class="sco-card-header"><h3>Drill abgeschlossen</h3></div>
        <p><strong>Score:</strong> ${result.score}/100</p>
        <p><strong>XP:</strong> +${result.xp}</p>
        <p>${result.feedback}</p>
        <div class="sco-actions">
          <button type="button" class="sco-btn" data-sco-switch-tab="today">Zum Dashboard</button>
          <button type="button" class="sco-btn sco-btn-primary" data-sco-switch-tab="skilltree">Skilltree ansehen</button>
        </div>
      `;

      await loadDashboard();
      updateHeader();
      renderToday();
      setTab('today');
    });
  };

  const updateCompleteState = () => {
    const answers = Object.values(state.answers).filter((entry) => Number(entry.value) > 0);
    const button = root.querySelector('[data-sco-complete]');
    if (button) {
      button.disabled = answers.length < 2;
    }
  };

  const renderDaily = () => {
    const wrap = root.querySelector('[data-sco-daily]');
    const questions = state.drill.self_check_questions || [];
    state.answers = {};

    const tileQuestions = questions.filter((q) => q.type === 'checkbox' || q.type === 'checkbox_multi');
    const sliderQuestions = questions.filter((q) => q.type === 'scale' || q.type === 'scale_1_5');

    wrap.innerHTML = `
      <div class="sco-card-header"><h2>${state.drill.title}</h2></div>
      <p>${state.drill.description}</p>
      <div class="sco-timer-row">
        <strong data-sco-timer>00:00</strong>
        <div class="sco-btn-group">
          <button type="button" class="sco-btn" data-sco-start>Start</button>
          <button type="button" class="sco-btn" data-sco-pause>Pause</button>
          <button type="button" class="sco-btn" data-sco-reset>Reset</button>
        </div>
      </div>
      <div class="sco-self-check-grid">
        ${sliderQuestions.map((q, i) => `
          <div class="sco-self-check-item">
            <label>${q.text || q.label || `Slider ${i + 1}`}</label>
            <input type="range" min="1" max="5" value="3" data-range-key="slider_${i}" aria-label="${q.text || q.label || `Slider ${i + 1}`}">
            <small class="sco-muted">Wert: <span data-range-value>3</span></small>
          </div>
        `).join('')}
        ${tileQuestions.length ? `
          <div class="sco-self-check-item">
            <label>Self-Check</label>
            <div class="sco-toggle-tiles">
              ${tileQuestions.map((q, i) => `
                <button type="button" class="sco-toggle-tile" role="button" aria-pressed="false" data-toggle-key="tile_${i}">
                  <span class="sco-check">✓</span>${q.text || q.label || `Check ${i + 1}`}
                </button>
              `).join('')}
            </div>
          </div>
        ` : ''}
      </div>
      <div class="sco-actions">
        <button type="button" class="sco-btn sco-btn-primary" data-sco-complete disabled>Abschließen</button>
      </div>
    `;

    bindDailyActions();
    updateCompleteState();
  };

  const renderSkilltree = async () => {
    const rows = await api('skilltree');
    const wrap = root.querySelector('[data-sco-skilltree]');
    wrap.innerHTML = rows.map((row) => `
      <article class="sco-card ${row.locked ? 'sco-locked' : ''}" data-skill='${JSON.stringify(row).replace(/'/g, '&apos;')}'>
        <div class="sco-card-header"><h3>${row.skill}</h3></div>
        <p>Level ${row.level} · ${row.xp} XP</p>
        <div class="sco-progress"><div style="width:${Math.min((row.level / 8) * 100, 100)}%"></div></div>
        ${row.locked ? '<span class="sco-lock">Premium</span>' : ''}
      </article>
    `).join('');

    wrap.querySelectorAll('[data-skill]').forEach((card) => {
      card.addEventListener('click', () => {
        const item = JSON.parse(card.dataset.skill.replace(/&apos;/g, "'"));
        drawer.open({
          title: item.skill,
          body: item.locked
            ? `<p>Dieser Skill ist Premium.</p><a class="sco-btn sco-btn-primary" href="${root.dataset.upgradeUrl || '#'}">Upgrade</a>`
            : `<p>Level ${item.level}</p><p>${item.xp} XP gesammelt.</p>`,
        });
      });
    });
  };

  const renderMissions = async () => {
    const response = await api('missions');
    const wrap = root.querySelector('[data-sco-missions]');
    wrap.innerHTML = (response.items || []).map((mission) => `
      <article class="sco-card ${mission.locked ? 'sco-locked' : ''}" data-mission='${JSON.stringify(mission).replace(/'/g, '&apos;')}'>
        <div class="sco-card-header"><h3>${mission.title}</h3></div>
        <p>${mission.description}</p>
        <small class="sco-muted">${mission.duration_days} Tage</small>
        ${mission.locked ? '<span class="sco-lock">Premium</span>' : ''}
      </article>
    `).join('');

    wrap.querySelectorAll('[data-mission]').forEach((card) => {
      card.addEventListener('click', () => {
        const mission = JSON.parse(card.dataset.mission.replace(/&apos;/g, "'"));
        if (mission.locked) {
          drawer.open({
            title: mission.title,
            body: `<p>${response.premium_tooltip}</p><a class="sco-btn sco-btn-primary" href="${root.dataset.upgradeUrl || '#'}">Upgrade</a>`,
          });
          return;
        }

        const completed = mission.completed_days || [];
        const steps = mission.steps || [];
        drawer.open({
          title: mission.title,
          body: steps.map((step) => {
            const stepDay = Number(step.step_order);
            const isDone = completed.includes(stepDay);
            return `<div class="sco-card" style="padding:16px;margin-bottom:12px;">
              <p><strong>Tag ${stepDay}:</strong> ${step.title}</p>
              <p class="sco-muted">${step.description}</p>
              <button class="sco-btn ${isDone ? '' : 'sco-btn-primary'}" type="button" data-complete-mission="${mission.id}" data-step-day="${stepDay}" ${isDone ? 'disabled' : ''}>${isDone ? 'Erledigt' : 'Als erledigt markieren'}</button>
            </div>`;
          }).join(''),
        });

        window.setTimeout(() => {
          document.querySelectorAll('[data-complete-mission]').forEach((button) => {
            button.addEventListener('click', async () => {
              await api('missions/step-complete', {
                method: 'POST',
                body: JSON.stringify({ mission_id: Number(button.dataset.completeMission), step_day: Number(button.dataset.stepDay) }),
              });
              await renderMissions();
              drawer.close();
            });
          });
        }, 0);
      });
    });
  };

  const renderLibrary = (items, data) => {
    const query = (root.querySelector('[data-sco-library-search]')?.value || '').toLowerCase().trim();
    const wrap = root.querySelector('[data-sco-library]');
    const notice = root.querySelector('[data-sco-library-notice]');

    const filtered = items.filter((item) => `${item.title} ${item.content}`.toLowerCase().includes(query));
    wrap.innerHTML = filtered.map((item) => `
      <article class="sco-card" data-library='${JSON.stringify(item).replace(/'/g, '&apos;')}'>
        <div class="sco-card-header"><h3>${item.title}</h3></div>
        <p>${(item.content || '').slice(0, 130)}…</p>
        <button type="button" class="sco-btn" data-open-library="${item.id}">Öffnen</button>
      </article>
    `).join('');

    if (data.limit_reached) {
      notice.innerHTML = `<p>${data.copy.free_limit_reached}</p><div class="sco-actions"><a class="sco-btn sco-btn-primary" href="${data.checkout_url || root.dataset.upgradeUrl || '#'}">${data.copy.upgrade_primary}</a></div>`;
    } else {
      notice.innerHTML = `<p class="sco-muted">Heute geöffnet: ${data.opens_count}/${data.daily_limit}</p>`;
    }

    wrap.querySelectorAll('[data-open-library]').forEach((button) => {
      button.addEventListener('click', async () => {
        const itemId = Number(button.dataset.openLibrary);
        try {
          const openState = await api('library/open', {
            method: 'POST',
            body: JSON.stringify({ item_id: itemId }),
          });
          const item = items.find((entry) => Number(entry.id) === itemId);
          if (!item) return;
          drawer.open({
            title: item.title,
            body: `<p>${item.content}</p><div class="sco-actions"><button class="sco-btn sco-btn-primary" type="button" data-copy-text>Text kopieren</button></div><small class="sco-muted">Heute geöffnet: ${openState.opens_count}/${openState.daily_limit}</small>`,
          });
          window.setTimeout(() => {
            document.querySelector('[data-copy-text]')?.addEventListener('click', async () => {
              await navigator.clipboard.writeText(item.content || '');
            });
          }, 0);
        } catch (error) {
          notice.innerHTML = `<p>${error.message}</p><div class="sco-actions"><a class="sco-btn sco-btn-primary" href="${root.dataset.upgradeUrl || '#'}">Upgrade</a></div>`;
        }
      });
    });
  };

  const loadLibrary = async () => {
    const data = await api('library');
    state.library = data.items || [];
    renderLibrary(state.library, data);

    root.querySelector('[data-sco-library-search]')?.addEventListener('input', () => renderLibrary(state.library, data));
  };

  const renderProgress = () => {
    const panel = root.querySelector('[data-sco-progress-panel]');
    if (!panel) return;

    if (!state.dashboard.premium) {
      panel.innerHTML = `
        <div class="sco-card-header"><h3>Fortschritt (Premium)</h3></div>
        <p class="sco-muted">Verlauf, Vergleiche und tiefe Auswertungen sind im Premium-Plan enthalten.</p>
        <div class="sco-actions"><a class="sco-btn sco-btn-primary" href="${root.dataset.upgradeUrl || '#'}">Upgrade</a></div>
      `;
      return;
    }

    panel.innerHTML = `
      <div class="sco-card-header"><h3>Fortschritt</h3></div>
      <p><strong>XP gesamt:</strong> ${state.dashboard.progress.xp_total}</p>
      <p><strong>Streak:</strong> ${state.dashboard.progress.streak}</p>
      <p><strong>Letzter Abschluss:</strong> ${state.dashboard.progress.last_completed_date || '—'}</p>
    `;
  };

  const loadDashboard = async () => {
    state.dashboard = await api('dashboard');
    state.drill = state.dashboard.drill;
    state.premium = state.dashboard.premium;
  };

  const init = async () => {
    initTabs();
    await loadDashboard();
    updateHeader();
    renderToday();
    renderDaily();
    await Promise.all([renderSkilltree(), renderMissions(), loadLibrary()]);
    renderProgress();
  };

  init();
})();

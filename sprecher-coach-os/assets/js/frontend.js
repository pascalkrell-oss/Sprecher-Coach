(() => {
  const root = document.querySelector('.sco-app');
  if (!root || !root.querySelector('.sco-shell')) return;

  const labels = scoData?.labelMaps || { skills: {}, categories: {} };

  const api = async (path, options = {}) => {
    const response = await fetch(`${scoData.restUrl}${path}`, {
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scoData.nonce },
      credentials: 'same-origin',
      ...options,
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload?.message || 'Request failed');
    return payload;
  };

  const state = {
    dashboard: null,
    drill: null,
    answers: {},
    library: [],
    libraryMeta: null,
    premium: false,
    activeLibraryCategory: 'warmup',
  };

  const drawer = (() => {
    const overlay = root.querySelector('.sco-overlay');
    const panel = root.querySelector('.sco-drawer');
    const title = panel?.querySelector('[data-sco-drawer-title]');
    const icon = panel?.querySelector('.sco-drawer__icon');
    const body = panel?.querySelector('[data-sco-drawer-body]');
    const foot = panel?.querySelector('[data-sco-drawer-foot]');
    const closeBtn = panel?.querySelector('.sco-drawer__close');

    const closeDrawer = () => {
      if (!panel || !overlay) return;
      panel.classList.remove('is-open');
      panel.setAttribute('aria-hidden', 'true');
      overlay.hidden = true;
      body.innerHTML = '';
      foot.innerHTML = '';
      foot.classList.remove('has-content');
      icon.innerHTML = '';
      document.body.classList.remove('sco-drawer-open');
    };

    const openDrawer = ({ title: heading, iconClass, html, footerHtml }) => {
      if (!panel || !overlay) return;
      title.textContent = heading || 'Details';
      icon.innerHTML = iconClass ? `<i class="${iconClass}"></i>` : '';
      body.innerHTML = html || '';
      foot.innerHTML = footerHtml || '';
      foot.classList.toggle('has-content', Boolean(footerHtml));
      overlay.hidden = false;
      panel.classList.add('is-open');
      panel.setAttribute('aria-hidden', 'false');
      document.body.classList.add('sco-drawer-open');
      closeBtn?.focus();
    };

    closeBtn?.addEventListener('click', closeDrawer);
    overlay?.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && panel?.classList.contains('is-open')) closeDrawer();
    });

    return { openDrawer, closeDrawer };
  })();

  const skillLabel = (key) => labels.skills?.[key] || key;
  const categoryLabel = (key) => labels.categories?.[key] || key;

  const setTab = (tab) => {
    root.querySelectorAll('[data-tab]').forEach((button) => button.classList.toggle('is-active', button.dataset.tab === tab));
    root.querySelectorAll('[data-panel]').forEach((panel) => panel.classList.toggle('is-active', panel.dataset.panel === tab));
    window.location.hash = tab;
    window.localStorage.setItem('scoActiveTab', tab);
  };

  const bindSwitchTabs = (scope = root) => {
    scope.querySelectorAll('[data-sco-switch-tab]').forEach((button) => {
      button.addEventListener('click', (event) => {
        event.preventDefault();
        setTab(button.dataset.scoSwitchTab);
      });
    });
  };

  const initTabs = () => {
    root.querySelectorAll('[data-tab]').forEach((button) => button.addEventListener('click', () => setTab(button.dataset.tab)));
    bindSwitchTabs(root);
    const initial = (window.location.hash || '').replace('#', '') || window.localStorage.getItem('scoActiveTab') || 'today';
    setTab(initial);
  };

  const updateHeader = () => {
    if (!state.dashboard) return;
    const { progress } = state.dashboard;
    root.querySelector('[data-sco-streak]').textContent = `Streak ${progress.streak}`;
    root.querySelector('[data-sco-level]').textContent = `Level ${progress.level}`;
    root.querySelector('[data-sco-weekly]').textContent = `Wochenziel ${progress.weekly_count}/${progress.weekly_goal}`;
    root.querySelector('[data-sco-weekly-progress]').style.width = `${Math.min((progress.weekly_count / progress.weekly_goal) * 100, 100)}%`;
    root.querySelector('[data-sco-weekly-copy]').textContent = `Wochenziel: ${progress.weekly_count}/${progress.weekly_goal}`;
  };

  const renderToday = () => {
    const { drill, cta } = state.dashboard;
    root.querySelector('[data-sco-today-card]').innerHTML = `
      <div class="sco-card-header"><h2>Heute: ${drill.title}</h2></div>
      <p>${drill.description}</p>
      <div class="sco-actions">
        <button type="button" class="sco-btn sco-btn-primary" data-sco-switch-tab="daily">Jetzt starten</button>
        <button type="button" class="sco-btn" data-sco-switch-tab="skilltree">Skilltree ansehen</button>
      </div>`;

    root.querySelector('[data-sco-next-step]').innerHTML = `
      <div class="sco-card-header"><h3>Nächste Aktion</h3></div>
      <p><strong>${drill.title}</strong></p>
      <p class="sco-muted">${cta.dashboard_nudge}</p>
      <div class="sco-actions"><button type="button" class="sco-btn" data-sco-switch-tab="daily">Drill öffnen</button></div>`;

    root.querySelector('[data-sco-side-next]').textContent = `Heute empfohlen: ${drill.title}`;
    bindSwitchTabs(root);
  };

  const updateCompleteState = () => {
    const answers = Object.values(state.answers).filter((entry) => Number(entry.value) > 0);
    const button = root.querySelector('[data-sco-complete]');
    if (button) button.disabled = answers.length < 2;
  };

  const bindDailyActions = () => {
    let sec = 0;
    let timer = null;
    const display = root.querySelector('[data-sco-timer]');

    root.querySelector('[data-sco-start]')?.addEventListener('click', () => {
      if (timer) return;
      timer = window.setInterval(() => {
        sec += 1;
        display.textContent = `${String(Math.floor(sec / 60)).padStart(2, '0')}:${String(sec % 60).padStart(2, '0')}`;
      }, 1000);
    });
    root.querySelector('[data-sco-pause]')?.addEventListener('click', () => { window.clearInterval(timer); timer = null; });
    root.querySelector('[data-sco-reset]')?.addEventListener('click', () => { window.clearInterval(timer); timer = null; sec = 0; display.textContent = '00:00'; });

    root.querySelectorAll('[data-toggle-key]').forEach((tile) => {
      tile.addEventListener('click', () => {
        const key = tile.dataset.toggleKey;
        const active = tile.classList.toggle('is-active');
        tile.setAttribute('aria-pressed', active ? 'true' : 'false');
        state.answers[key] = { type: 'checkbox_multi', value: active ? 1 : 0, key };
        updateCompleteState();
      });
    });

    root.querySelectorAll('[data-range-key]').forEach((input) => {
      const output = input.parentElement.querySelector('[data-range-value]');
      const onInput = () => {
        output.textContent = input.value;
        state.answers[input.dataset.rangeKey] = { type: 'scale_1_5', value: Number(input.value), key: input.dataset.rangeKey };
        updateCompleteState();
      };
      input.addEventListener('input', onInput);
      onInput();
    });

    root.querySelector('[data-sco-complete]')?.addEventListener('click', async () => {
      const payload = {
        drill_id: Number(state.drill.id),
        answers: Object.values(state.answers),
        completed_seconds: sec,
      };
      const response = await api('drill/complete', { method: 'POST', body: JSON.stringify(payload) });
      const completion = root.querySelector('[data-sco-completion-card]');
      completion.innerHTML = `<div class="sco-card-header"><h3>Stark! +${response.xp_earned} XP</h3></div><p>${response.feedback}</p>`;
      await loadDashboard();
      updateHeader();
      renderToday();
      setTab('today');
    });
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
      <div class="sco-timer-row"><strong data-sco-timer>00:00</strong><div class="sco-btn-group"><button type="button" class="sco-btn" data-sco-start>Start</button><button type="button" class="sco-btn" data-sco-pause>Pause</button><button type="button" class="sco-btn" data-sco-reset>Reset</button></div></div>
      <div class="sco-self-check-grid">
        ${sliderQuestions.map((q, i) => `<div class="sco-self-check-item"><label>${q.text || q.label || `Slider ${i + 1}`}</label><input type="range" min="1" max="5" value="3" data-range-key="slider_${i}"><small class="sco-muted">Wert: <span data-range-value>3</span></small></div>`).join('')}
        ${tileQuestions.length ? `<div class="sco-self-check-item"><label>Self-Check</label><div class="sco-toggle-tiles">${tileQuestions.map((q, i) => `<button type="button" class="sco-toggle-tile" aria-pressed="false" data-toggle-key="tile_${i}"><span class="sco-check">✓</span>${q.text || q.label || `Check ${i + 1}`}</button>`).join('')}</div></div>` : ''}
      </div>
      <div class="sco-actions"><button type="button" class="sco-btn sco-btn-primary" data-sco-complete disabled>Abschließen</button></div>`;

    bindDailyActions();
    updateCompleteState();
  };

  const renderSkilltree = async () => {
    const rows = await api('skilltree');
    const wrap = root.querySelector('[data-sco-skilltree]');
    wrap.innerHTML = rows.map((row) => `
      <article class="sco-card ${row.locked ? 'sco-locked' : ''}" data-skill='${JSON.stringify(row).replace(/'/g, '&apos;')}'>
        <div class="sco-card-header"><h3>${skillLabel(row.skill_key || row.skill)}</h3></div>
        <p>Level ${row.level} · ${row.xp} XP</p>
        <div class="sco-progress"><div style="width:${Math.min((row.level / 8) * 100, 100)}%"></div></div>
        ${row.locked ? '<span class="sco-lock">Premium</span>' : ''}
      </article>`).join('');

    wrap.querySelectorAll('[data-skill]').forEach((card) => {
      card.addEventListener('click', () => {
        const item = JSON.parse(card.dataset.skill.replace(/&apos;/g, "'"));
        drawer.openDrawer({
          title: skillLabel(item.skill_key || item.skill),
          iconClass: 'fa-solid fa-diagram-project',
          html: item.locked ? `<p>Dieser Skill ist Premium.</p>` : `<p>Level ${item.level}</p><p>${item.xp} XP gesammelt.</p>`,
          footerHtml: item.locked ? `<a class="sco-btn sco-btn-primary" href="${root.dataset.upgradeUrl || '#'}">Upgrade</a>` : '',
        });
      });
    });
  };

  const renderMissions = async () => {
    const response = await api('missions');
    const wrap = root.querySelector('[data-sco-missions]');
    wrap.innerHTML = (response.items || []).map((mission) => `
      <article class="sco-card ${mission.locked ? 'sco-locked' : ''}" data-mission='${JSON.stringify(mission).replace(/'/g, '&apos;')}'>
        <div class="sco-card-header"><h3>${mission.title}</h3></div><p>${mission.description}</p><small class="sco-muted">${mission.duration_days} Tage</small>${mission.locked ? '<span class="sco-lock">Premium</span>' : ''}
      </article>`).join('');

    wrap.querySelectorAll('[data-mission]').forEach((card) => {
      card.addEventListener('click', () => {
        const mission = JSON.parse(card.dataset.mission.replace(/&apos;/g, "'"));
        if (mission.locked) {
          drawer.openDrawer({ title: mission.title, iconClass: 'fa-solid fa-lock', html: `<p>${response.premium_tooltip}</p>`, footerHtml: `<a class="sco-btn sco-btn-primary" href="${root.dataset.upgradeUrl || '#'}">Upgrade</a>` });
          return;
        }
        const completed = mission.completed_days || [];
        const steps = mission.steps || [];
        drawer.openDrawer({
          title: mission.title,
          iconClass: 'fa-solid fa-flag-checkered',
          html: steps.map((step) => {
            const day = Number(step.step_order);
            const isDone = completed.includes(day);
            return `<div class="sco-card" style="padding:16px;margin-bottom:12px;"><p><strong>Tag ${day}:</strong> ${step.title}</p><p class="sco-muted">${step.description}</p><button class="sco-btn ${isDone ? '' : 'sco-btn-primary'}" type="button" data-complete-mission="${mission.id}" data-step-day="${day}" ${isDone ? 'disabled' : ''}>${isDone ? 'Erledigt' : 'Als erledigt markieren'}</button></div>`;
          }).join(''),
        });

        window.setTimeout(() => {
          root.querySelectorAll('[data-complete-mission]').forEach((button) => {
            button.addEventListener('click', async () => {
              await api('missions/step-complete', { method: 'POST', body: JSON.stringify({ mission_id: Number(button.dataset.completeMission), step_day: Number(button.dataset.stepDay) }) });
              await renderMissions();
              drawer.closeDrawer();
            });
          });
        }, 0);
      });
    });
  };

  const mapLibraryCategoryToType = (category) => {
    const map = { warmup: 'warmup', tongue_twister: 'tongue_twister', script: 'script', business: 'business' };
    return map[category] || '';
  };

  const renderLibrary = (items, data) => {
    const wrap = root.querySelector('[data-sco-library]');
    const notice = root.querySelector('[data-sco-library-notice]');
    const activeType = mapLibraryCategoryToType(state.activeLibraryCategory);
    let filtered = activeType ? items.filter((item) => item.category_key === activeType || item.type === activeType) : [...items];
    if (state.activeLibraryCategory === 'random') filtered = [...items].sort(() => Math.random() - 0.5).slice(0, 8);

    wrap.innerHTML = filtered.map((item) => `
      <article class="sco-card" data-library='${JSON.stringify(item).replace(/'/g, '&apos;')}'>
        <div class="sco-card-header"><h3>${item.title}</h3></div>
        <p class="sco-muted">${skillLabel(item.skill_key || 'all')} · ${categoryLabel(item.category_key || item.type || '')}</p>
        <p>${(item.content || '').slice(0, 130)}…</p>
        <button type="button" class="sco-btn" data-open-library="${item.id}">Öffnen</button>
      </article>`).join('');

    notice.innerHTML = data.limit_reached
      ? `<p>${data.copy.free_limit_reached}</p><div class="sco-actions"><a class="sco-btn sco-btn-primary" href="${data.checkout_url || root.dataset.upgradeUrl || '#'}">${data.copy.upgrade_primary}</a></div>`
      : `<p class="sco-muted">Heute geöffnet: ${data.opens_count}/${data.daily_limit}</p>`;

    wrap.querySelectorAll('[data-open-library]').forEach((button) => {
      button.addEventListener('click', async () => {
        const itemId = Number(button.dataset.openLibrary);
        try {
          const openState = await api('library/open', { method: 'POST', body: JSON.stringify({ item_id: itemId }) });
          const item = items.find((entry) => Number(entry.id) === itemId);
          if (!item) return;
          drawer.openDrawer({
            title: item.title,
            iconClass: 'fa-solid fa-book-open',
            html: `<p>${item.content}</p><small class="sco-muted">Heute geöffnet: ${openState.opens_count}/${openState.daily_limit}</small>`,
            footerHtml: `<button class="sco-btn sco-btn-primary" type="button" data-copy-text>Text kopieren</button>`,
          });
          root.querySelector('[data-copy-text]')?.addEventListener('click', async () => navigator.clipboard.writeText(item.content || ''));
        } catch (error) {
          notice.innerHTML = `<p>${error.message}</p><div class="sco-actions"><a class="sco-btn sco-btn-primary" href="${root.dataset.upgradeUrl || '#'}">Upgrade</a></div>`;
        }
      });
    });
  };

  const bindLibraryCategories = () => {
    root.querySelectorAll('[data-library-category]').forEach((button) => {
      button.addEventListener('click', () => {
        root.querySelectorAll('[data-library-category]').forEach((node) => node.classList.toggle('is-active', node === button));
        state.activeLibraryCategory = button.dataset.libraryCategory;
        if (state.libraryMeta) renderLibrary(state.library, state.libraryMeta);
      });
    });
  };

  const loadLibrary = async () => {
    const data = await api('library');
    state.library = data.items || [];
    state.libraryMeta = data;
    renderLibrary(state.library, data);
  };

  const renderProgress = () => {
    const panel = root.querySelector('[data-sco-progress-panel]');
    if (!panel) return;
    const progress = state.dashboard.progress;
    const lastDate = progress.last_completed_date
      ? new Date(progress.last_completed_date).toLocaleDateString('de-DE', { day: '2-digit', month: 'short', year: 'numeric' })
      : 'Noch kein Abschluss';
    const remaining = Math.max(progress.weekly_goal - progress.weekly_count, 0);
    panel.innerHTML = `
      <div class="sco-card-header"><h3>Fortschritt</h3></div>
      <div class="sco-progress-kpis">
        <div class="sco-kpi-card"><span class="sco-kpi-label"><i class="fa-solid fa-star"></i> XP gesamt</span><strong class="sco-kpi-value">${progress.xp_total}</strong><span class="sco-kpi-sub">Level ${progress.level}</span></div>
        <div class="sco-kpi-card"><span class="sco-kpi-label"><i class="fa-solid fa-fire"></i> Streak</span><strong class="sco-kpi-value">${progress.streak}</strong><span class="sco-kpi-sub">Tage am Stück</span></div>
        <div class="sco-kpi-card"><span class="sco-kpi-label"><i class="fa-solid fa-calendar-check"></i> Letzter Abschluss</span><strong class="sco-kpi-value" style="font-size:22px;line-height:1.2;">${lastDate}</strong><span class="sco-kpi-sub">zuletzt erledigt</span></div>
      </div>
      <div class="sco-progress-extra">
        <div class="sco-kpi-card"><p class="sco-kpi-label">Wochenziel</p><div class="sco-progress"><div style="width:${Math.min((progress.weekly_count / progress.weekly_goal) * 100, 100)}%"></div></div><p class="sco-kpi-sub">Noch ${remaining} Drills bis Wochenziel</p></div>
        ${state.dashboard.premium ? '<div class="sco-progress-lock"><strong>Verlauf</strong><p class="sco-muted">Bald: Wochenvergleich, Skill-History, KPI-Trends.</p></div>' : `<div class="sco-progress-lock"><strong>Verlauf (Premium)</strong><p class="sco-muted">Vergleiche und Timeline sind im Premium-Plan enthalten.</p><div class="sco-actions"><a class="sco-btn sco-btn-primary" href="${root.dataset.upgradeUrl || '#'}">Upgrade</a></div></div>`}
      </div>`;
  };

  const loadDashboard = async () => {
    state.dashboard = await api('dashboard');
    state.drill = state.dashboard.drill;
    state.premium = state.dashboard.premium;
  };

  const init = async () => {
    initTabs();
    bindLibraryCategories();
    await loadDashboard();
    updateHeader();
    renderToday();
    renderDaily();
    await Promise.all([renderSkilltree(), renderMissions(), loadLibrary()]);
    renderProgress();
  };

  init();
})();

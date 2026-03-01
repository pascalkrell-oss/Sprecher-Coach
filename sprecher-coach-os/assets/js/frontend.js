(() => {
  const labels = scoData?.labelMaps || { skills: {}, categories: {} };
  const MISSION_CONTEXT_KEY = 'sco_mission_context';
  const MISSION_RUN_STATE_KEY = 'sco_mission_run_state';
  const ACTIVE_TAB_KEY = 'sco_active_tab';
  const SPLIT_VIEW_KEY = 'sco_split_view';

  const scoIsOverlayOpen = () => {
    const overlay = document.querySelector('#scoCoachOverlay');
    return Boolean(overlay && !overlay.hidden);
  };

  const initCoachApp = (root) => {
    if (!root || root.dataset.scoAppInitialized === '1' || !root.querySelector('.sco-shell')) return;
    root.dataset.scoAppInitialized = '1';
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
    generatedTool: null,
    teleprompter: { running: false, raf: null, offset: 0, lastTs: 0, fullscreen: false },
    notesDraft: { better_today: '', focus_tomorrow: '' },
  };

  const esc = (value) => {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
  };

  const toast = (message) => {
    const node = document.createElement('div');
    node.className = 'sco-toast';
    node.textContent = message;
    root.appendChild(node);
    window.setTimeout(() => node.classList.add('is-visible'), 5);
    window.setTimeout(() => {
      node.classList.remove('is-visible');
      window.setTimeout(() => node.remove(), 220);
    }, 1600);
  };

  const getMissionRunState = () => {
    try {
      return JSON.parse(window.sessionStorage.getItem(MISSION_RUN_STATE_KEY) || '{}');
    } catch (error) {
      return {};
    }
  };

  const markMissionStepRun = (missionId, day) => {
    const runState = getMissionRunState();
    runState[`${missionId}:${day}`] = true;
    window.sessionStorage.setItem(MISSION_RUN_STATE_KEY, JSON.stringify(runState));
  };

  const wasMissionStepRun = (missionId, day) => Boolean(getMissionRunState()[`${missionId}:${day}`]);

  const getMissionContext = () => {
    try {
      return JSON.parse(window.sessionStorage.getItem(MISSION_CONTEXT_KEY) || 'null');
    } catch (error) {
      return null;
    }
  };

  const setMissionContext = (ctx) => window.sessionStorage.setItem(MISSION_CONTEXT_KEY, JSON.stringify(ctx));

  const drawer = (() => {
    const overlayRoot = root.closest('#scoCoachOverlay');
    const backdrop = overlayRoot?.querySelector('.sco-drawer-overlay');
    const panel = overlayRoot?.querySelector('.sco-drawer');
    const title = panel?.querySelector('[data-sco-drawer-title]');
    const icon = panel?.querySelector('.sco-drawer__icon');
    const body = panel?.querySelector('[data-sco-drawer-body]');
    const foot = panel?.querySelector('[data-sco-drawer-foot]');
    const closeBtn = panel?.querySelector('.sco-drawer__close');

    let closeTimer = null;

    const closeDrawer = () => {
      if (!panel || !backdrop) return;
      panel.classList.remove('is-open');
      panel.setAttribute('aria-hidden', 'true');
      backdrop.hidden = true;
      window.clearTimeout(closeTimer);
      closeTimer = window.setTimeout(() => {
        panel.hidden = true;
        body.innerHTML = '';
        foot.innerHTML = '';
        foot.classList.remove('has-content');
        icon.innerHTML = '';
      }, 320);
      document.body.classList.remove('sco-drawer-open');
    };

    const openDrawer = ({ title: heading, iconClass, html, footerHtml }) => {
      if (!panel || !backdrop) return;
      window.clearTimeout(closeTimer);
      title.textContent = heading || 'Details';
      icon.innerHTML = iconClass ? `<i class="${iconClass}"></i>` : '';
      body.innerHTML = html || '';
      foot.innerHTML = footerHtml || '';
      foot.classList.toggle('has-content', Boolean(footerHtml));
      panel.hidden = false;
      backdrop.hidden = false;
      requestAnimationFrame(() => panel.classList.add('is-open'));
      panel.setAttribute('aria-hidden', 'false');
      document.body.classList.add('sco-drawer-open');
      closeBtn?.focus();
    };

    closeBtn?.addEventListener('click', closeDrawer);
    backdrop?.addEventListener('click', closeDrawer);
    const isOpen = () => Boolean(panel && !panel.hidden && panel.classList.contains('is-open'));

    return { openDrawer, closeDrawer, isOpen };
  })();

  const skillLabel = (key) => labels.skills?.[key] || key;
  const categoryLabel = (key) => labels.categories?.[key] || key;

  const setTab = (tab) => {
    root.querySelectorAll('[data-tab]').forEach((button) => button.classList.toggle('is-active', button.dataset.tab === tab));
    root.querySelectorAll('[data-panel]').forEach((panel) => panel.classList.toggle('is-active', panel.dataset.panel === tab));
    if (tab === 'tools') {
      const savedTool = window.localStorage.getItem('sco_active_tool') || 'demo';
      setActiveToolPanel(savedTool);
    }
    window.location.hash = tab;
    window.localStorage.setItem(ACTIVE_TAB_KEY, tab);
    window.localStorage.setItem('scoActiveTab', tab);
  };

  const setActiveToolPanel = (tool) => {
    const activeTool = tool === 'teleprompter' ? 'teleprompter' : 'demo';
    root.querySelectorAll('[data-tool-tab]').forEach((button) => {
      const active = button.dataset.toolTab === activeTool;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    root.querySelectorAll('[data-tool-panel]').forEach((panel) => panel.classList.toggle('is-active', panel.dataset.toolPanel === activeTool));
    window.localStorage.setItem('sco_active_tool', activeTool);
  };

  const isSplitView = () => window.localStorage.getItem(SPLIT_VIEW_KEY) === '1';

  const applySplitView = (enabled) => {
    root.querySelectorAll('[data-sco-split-layout]').forEach((node) => {
      node.classList.toggle('is-split', enabled);
      node.setAttribute('data-split-active', enabled ? '1' : '0');
    });
    root.querySelectorAll('[data-sco-split-toggle]').forEach((button) => {
      button.classList.toggle('is-active', enabled);
      button.setAttribute('aria-pressed', enabled ? 'true' : 'false');
    });
  };

  const setSplitView = (enabled) => {
    window.localStorage.setItem(SPLIT_VIEW_KEY, enabled ? '1' : '0');
    applySplitView(enabled);
  };

  const gotoMission = () => {
    setTab('missions');
    const firstMission = root.querySelector('[data-mission]');
    firstMission?.click();
  };

  const runQuickAction = (action) => {
    if (action === 'daily') {
      setTab('daily');
      return;
    }
    if (action === 'teleprompter') {
      setTab('tools');
      setActiveToolPanel('teleprompter');
      return;
    }
    if (action === 'demo') {
      setTab('tools');
      setActiveToolPanel('demo');
      return;
    }
    if (action === 'mission') {
      gotoMission();
    }
  };

  const bindQuickActions = () => {};

  const bindSwitchTabs = (scope = document) => {
    scope.querySelectorAll('[data-sco-switch-tab]').forEach((button) => {
      if (button.dataset.scoSwitchBound === '1') return;
      button.dataset.scoSwitchBound = '1';
      button.style.cursor = 'pointer';
      button.addEventListener('click', (event) => {
        event.preventDefault();
        setTab(button.dataset.scoSwitchTab);
      });
    });
  };

  const initTabs = () => {
    const initial = (window.location.hash || '').replace('#', '') || window.localStorage.getItem(ACTIVE_TAB_KEY) || window.localStorage.getItem('scoActiveTab') || 'today';
    setTab(initial);
  };

  const bindCommandPalette = () => {
    const modal = root.querySelector('[data-sco-cmdk]');
    const panel = modal?.querySelector('.sco-cmdk__panel');
    const input = modal?.querySelector('[data-sco-command-input]');
    const list = modal?.querySelector('[data-sco-command-results]');
    const closeBtn = modal?.querySelector('.sco-cmdk__close');
    const backdrop = modal?.querySelector('.sco-cmdk__backdrop');
    if (!modal || !panel || !input || !list) return { open: () => {}, close: () => {}, isOpen: () => false };

    const commands = [
      { label: 'Heute öffnen', action: () => setTab('today') },
      { label: 'Daily Drill starten', action: () => setTab('daily') },
      { label: 'Mission fortsetzen', action: () => gotoMission() },
      { label: 'Tools → Demo Text Generator', action: () => { setTab('tools'); setActiveToolPanel('demo'); } },
      { label: 'Tools → Teleprompter', action: () => { setTab('tools'); setActiveToolPanel('teleprompter'); } },
      { label: 'Bibliothek → Skripte', action: () => { setTab('library'); root.querySelector('[data-library-category="script"]')?.click(); } },
      { label: 'Fortschritt öffnen', action: () => setTab('progress') },
      { label: 'Coach zurücksetzen', action: () => { setTab('progress'); root.querySelector('[data-sco-reset-coach]')?.focus(); } },
    ];

    let activeIndex = 0;
    let filtered = commands;

    const isOpen = () => !modal.hidden;
    const close = () => {
      modal.hidden = true;
      modal.setAttribute('aria-hidden', 'true');
      input.value = '';
      filtered = commands;
    };
    const run = (idx) => {
      const cmd = filtered[idx];
      if (!cmd) return;
      close();
      cmd.action();
    };

    const render = () => {
      const q = input.value.trim().toLowerCase();
      filtered = commands.filter((item) => item.label.toLowerCase().includes(q));
      if (activeIndex >= filtered.length) activeIndex = 0;
      list.innerHTML = filtered.map((item, idx) => `<button type="button" class="sco-command-item ${idx === activeIndex ? 'is-active' : ''}" data-command-idx="${idx}" role="option" aria-selected="${idx === activeIndex ? 'true' : 'false'}">${esc(item.label)}</button>`).join('') || '<p class="sco-muted">Kein Treffer</p>';
    };

    const open = () => {
      if (!scoIsOverlayOpen()) return;
      modal.hidden = false;
      modal.setAttribute('aria-hidden', 'false');
      activeIndex = 0;
      render();
      input.focus();
    };

    modal.addEventListener('click', (event) => {
      const button = event.target.closest('[data-command-idx]');
      if (button) run(Number(button.dataset.commandIdx));
    });
    backdrop?.addEventListener('click', close);
    closeBtn?.addEventListener('click', close);
    input.addEventListener('input', () => { activeIndex = 0; render(); });
    input.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowDown') { event.preventDefault(); activeIndex = Math.min(activeIndex + 1, Math.max(filtered.length - 1, 0)); render(); }
      if (event.key === 'ArrowUp') { event.preventDefault(); activeIndex = Math.max(activeIndex - 1, 0); render(); }
      if (event.key === 'Enter') { event.preventDefault(); run(activeIndex); }
      if (event.key === 'Escape') { event.preventDefault(); close(); }
    });

    return { open, close, isOpen };
  };

  const updateHeader = () => {
    if (!state.dashboard) return;
    const { progress } = state.dashboard;
    root.querySelector('[data-sco-streak]').textContent = `Trainingsserie ${progress.streak}`;
    root.querySelector('[data-sco-level]').textContent = `Level ${progress.level}`;
    root.querySelector('[data-sco-weekly]').textContent = `Wochenziel ${progress.weekly_count}/${progress.weekly_goal}`;
    root.querySelector('[data-sco-weekly-progress]').style.width = `${Math.min((progress.weekly_count / progress.weekly_goal) * 100, 100)}%`;
    root.querySelector('[data-sco-weekly-copy]').textContent = `Wochenziel: ${progress.weekly_count}/${progress.weekly_goal}`;
  };

  const renderToday = () => {
    const { drill, cta } = state.dashboard;
    root.querySelector('[data-sco-today-card]').innerHTML = `
      <div class="sco-card-header"><h2>Heute: ${esc(drill.title)}</h2></div>
      <p>${esc(drill.description)}</p>
      <div class="sco-actions">
        <button type="button" class="sco-btn sco-btn-primary" data-sco-switch-tab="daily">Jetzt starten</button>
        <button type="button" class="sco-btn" data-sco-switch-tab="skilltree">Skilltree ansehen</button>
      </div>`;

    root.querySelector('[data-sco-next-step]').innerHTML = `
      <div class="sco-card-header"><h3>Nächste Aktion</h3></div>
      <p><strong>${esc(drill.title)}</strong></p>
      <p class="sco-muted">${esc(cta.dashboard_nudge)}</p>
      <div class="sco-actions"><button type="button" class="sco-btn" data-sco-switch-tab="daily">Drill öffnen</button></div>`;

    root.querySelector('[data-sco-side-next]').textContent = `Heute empfohlen: ${drill.title}`;

    const planCard = root.querySelector('[data-sco-plan-card]');
    if (planCard) {
      const plan = state.dashboard.adaptive_plan || { focus: [] };
      const slots = plan.focus || [];
      planCard.innerHTML = `<div class="sco-card-header"><h3>Dein Plan</h3></div><ul>${slots.map((item) => `<li>${esc(item)}</li>`).join('')}</ul><p class="sco-muted">Heute · Morgen · Übermorgen</p>`;
    }
  };

  const updateCompleteState = () => {
    const answers = Object.values(state.answers).filter((entry) => Number(entry.value) > 0);
    const button = root.querySelector('[data-sco-complete]');
    if (button) button.disabled = answers.length < 2;
  };

  const renderMissionContextBanner = () => {
    const context = getMissionContext();
    const banner = root.querySelector('[data-sco-mission-context]');
    if (!banner) return;
    if (!context) {
      banner.innerHTML = '';
      banner.hidden = true;
      return;
    }

    banner.hidden = false;
    banner.innerHTML = `
      <strong>Mission aktiv:</strong> ${esc(context.mission_title)} · Tag ${Number(context.step_day)}
      <span class="sco-muted">${esc(context.step_title || '')} (${esc(skillLabel(context.skill_key))} / ${esc(categoryLabel(context.category_key))})</span>
      <button class="sco-btn" type="button" data-sco-clear-mission-context>Mission-Kontext schließen</button>`;

    banner.querySelector('[data-sco-clear-mission-context]')?.addEventListener('click', () => {
      window.sessionStorage.removeItem(MISSION_CONTEXT_KEY);
      renderMissionContextBanner();
    });
  };

  const scoGoToDailyDrill = async ({ skill_key, category_key, recommended_drill_id, mission_id, step_day, mission_title, step_title }) => {
    setMissionContext({ skill_key, category_key, recommended_drill_id, mission_id, step_day, mission_title, step_title });
    markMissionStepRun(mission_id, step_day);
    setTab('daily');
    const query = new URLSearchParams({ skill: skill_key || '', category: category_key || '' });
    if (Number(recommended_drill_id) > 0) query.set('recommended_drill_id', String(recommended_drill_id));
    const drill = await api(`drills/recommend?${query.toString()}`);
    if (drill?.id) {
      state.drill = drill;
      renderDaily();
    }
    renderMissionContextBanner();
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

    root.querySelector('[data-sco-split-toggle]')?.addEventListener('click', () => {
      setSplitView(!isSplitView());
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
      const response = await api('complete-drill', { method: 'POST', body: JSON.stringify(payload) });
      const completion = root.querySelector('[data-sco-completion-card]');
      completion.innerHTML = `<div class="sco-card-header"><h3>Stark! +${response.xp} XP</h3></div><p>${esc(response.feedback)}</p>`;
      const notesPanel = root.querySelector('[data-sco-notes-panel]');
      if (notesPanel) {
        notesPanel.hidden = false;
        notesPanel.innerHTML = `<div class="sco-card-header"><h3>Session Notes</h3></div><label>Heute besser:<textarea data-sco-note-better rows="2" class="sco-input" placeholder="1–2 Sätze"></textarea></label><label>Morgen Fokus:<textarea data-sco-note-focus rows="2" class="sco-input" placeholder="Worauf willst du achten?"></textarea></label><div class="sco-actions"><button type="button" class="sco-btn sco-btn-primary" data-sco-note-save>Notiz speichern</button></div>`;
        notesPanel.querySelector('[data-sco-note-save]')?.addEventListener('click', async () => {
          const better = notesPanel.querySelector('[data-sco-note-better]')?.value || '';
          const focus = notesPanel.querySelector('[data-sco-note-focus]')?.value || '';
          await api('notes/save', { method: 'POST', body: JSON.stringify({ drill_id: Number(state.drill.id), date: new Date().toISOString().slice(0, 10), better, focus }) });
          toast('Notiz gespeichert.');
          await loadDashboard();
          renderProgress();
        });
      }
      await loadDashboard();
      updateHeader();
      renderToday();
    });
  };

  const renderDaily = () => {
    const wrap = root.querySelector('[data-sco-daily]');
    const questions = state.drill.self_check_questions || [];
    state.answers = {};
    const tileQuestions = questions.filter((q) => q.type === 'checkbox' || q.type === 'checkbox_multi');
    const sliderQuestions = questions.filter((q) => q.type === 'scale' || q.type === 'scale_1_5');
    const missionContext = getMissionContext();
    const missionScriptText = missionContext?.step?.script_text ? String(missionContext.step.script_text).trim() : '';
    const resolvedText = String(state.drill.script_text_resolved || '').trim();
    const scriptText = missionScriptText || resolvedText || 'Sprich den Text ruhig und klar. Konzentriere dich auf Atmung, Betonung und Pausen.';
    const scriptSource = missionScriptText ? 'mission' : (state.drill.script_source || 'pool');
    const scriptTitle = missionScriptText ? (missionContext?.step?.title || 'Mission-Text') : (state.drill.script_title || 'Trainingstext');
    const sourceLabelMap = { drill: 'Skript', library: 'Bibliothek', pool: 'Trainingstext', mission: 'Mission' };
    const sourceLabel = sourceLabelMap[scriptSource] || 'Trainingstext (Fallback)';

    wrap.innerHTML = `
      <div class="sco-card-header"><h2>${esc(state.drill.title)}</h2></div>
      <p>${esc(state.drill.description)}</p>
      <div class="sco-actions" style="margin-top:10px;">
        <button type="button" class="sco-btn" data-sco-split-toggle aria-pressed="false"><i class="fa-solid fa-columns" aria-hidden="true"></i>Split View</button>
      </div>
      <section class="sco-split-layout" data-sco-split-layout>
        <div class="sco-split-layout__left">
          <section class="sco-script-card">
            <div class="sco-script-card__header">
              <div class="sco-script-card__title-wrap">
                <h3>Übungstext</h3>
                <span class="sco-pill sco-pill-neutral">${esc(sourceLabel)}</span>
              </div>
              <div class="sco-btn-group">
                <button type="button" class="sco-btn" data-sco-copy-script><i class="fa-solid fa-copy" aria-hidden="true"></i> Copy</button>
                <button type="button" class="sco-btn" data-sco-alt-script>Neuer Text</button>
              </div>
            </div>
            <p class="sco-muted sco-script-card__subtitle">${esc(scriptTitle || 'Trainingstext')}</p>
            <div class="sco-script-card__body" data-sco-script-text>${esc(scriptText)}</div>
          </section>
        </div>
        <div class="sco-split-layout__right">
          <div class="sco-timer-row"><strong data-sco-timer>00:00</strong><div class="sco-btn-group"><button type="button" class="sco-btn" data-sco-start>Start</button><button type="button" class="sco-btn" data-sco-pause>Pause</button><button type="button" class="sco-btn" data-sco-reset>Reset</button></div></div>
          <div class="sco-self-check-grid">
            ${sliderQuestions.map((q, i) => `<div class="sco-self-check-item"><label>${esc(q.text || q.label || `Slider ${i + 1}`)}</label><input type="range" min="1" max="5" value="3" data-range-key="slider_${i}"><small class="sco-muted">Wert: <span data-range-value>3</span></small></div>`).join('')}
            ${tileQuestions.length ? `<div class="sco-self-check-item"><label>Self-Check</label><div class="sco-toggle-tiles">${tileQuestions.map((q, i) => `<button type="button" class="sco-toggle-tile" aria-pressed="false" data-toggle-key="tile_${i}"><span class="sco-check">✓</span>${esc(q.text || q.label || `Check ${i + 1}`)}</button>`).join('')}</div></div>` : ''}
          </div>
          <div class="sco-mission-mini">${missionContext ? `Mission: ${esc(missionContext.mission_title)} · Tag ${Number(missionContext.step_day)}` : 'Mission-Kontext erscheint hier, sobald du aus Missionen startest.'}</div>
        </div>
      </section>
      <div class="sco-actions"><button type="button" class="sco-btn sco-btn-primary" data-sco-complete disabled>Abschließen</button></div>
      <section class="sco-session-notes" data-sco-notes-panel hidden></section>`;

    wrap.querySelector('[data-sco-copy-script]')?.addEventListener('click', async () => {
      await navigator.clipboard.writeText(scriptText);
      toast('Kopiert!');
    });

    wrap.querySelector('[data-sco-alt-script]')?.addEventListener('click', async () => {
      const variant = Number(state.drill.script_variant || 1) + 1;
      const alt = await api(`drills/alt-text?skill=${encodeURIComponent(state.drill.skill_key || 'werbung')}&variant=${variant}`);
      state.drill.script_variant = variant;
      state.drill.script_text_resolved = alt?.text || state.drill.script_text_resolved;
      state.drill.script_source = alt?.source || state.drill.script_source;
      state.drill.script_title = alt?.title || state.drill.script_title;
      renderDaily();
    });

    bindDailyActions();
    applySplitView(isSplitView());
    updateCompleteState();
    renderMissionContextBanner();
  };

  const renderSkilltree = async () => {
    const rows = await api('skilltree');
    const wrap = root.querySelector('[data-sco-skilltree]');
    wrap.innerHTML = rows.map((row) => `
      <article class="sco-card ${row.locked ? 'sco-locked' : ''}" data-skill='${JSON.stringify(row).replace(/'/g, '&apos;')}'>
        <div class="sco-card-header"><h3>${esc(skillLabel(row.skill_key || row.skill))}</h3></div>
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
          html: item.locked ? '<p>Dieser Skill ist Premium.</p>' : `<p>Level ${item.level}</p><p>${item.xp} XP gesammelt.</p>`,
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
        <div class="sco-card-header"><h3>${esc(mission.title)}</h3></div><p>${esc(mission.description)}</p><small class="sco-muted">${mission.duration_days} Tage</small>${mission.locked ? '<span class="sco-lock">Premium</span>' : ''}
      </article>`).join('');

    wrap.querySelectorAll('[data-mission]').forEach((card) => {
      card.addEventListener('click', () => {
        const mission = JSON.parse(card.dataset.mission.replace(/&apos;/g, "'"));
        if (mission.locked) {
          drawer.openDrawer({ title: mission.title, iconClass: 'fa-solid fa-lock', html: `<p>${esc(response.premium_tooltip)}</p>`, footerHtml: `<a class="sco-btn sco-btn-primary" href="${root.dataset.upgradeUrl || '#'}">Upgrade</a>` });
          return;
        }
        const completed = mission.completed_days || [];
        const steps = mission.steps || [];
        drawer.openDrawer({
          title: mission.title,
          iconClass: 'fa-solid fa-flag-checkered',
          html: steps.map((step) => {
            const day = Number(step.day || step.step_order);
            const isDone = completed.includes(day);
            const canComplete = wasMissionStepRun(mission.id, day);
            const tasks = (step.tasks || []).map((task) => `<li>${esc(task)}</li>`).join('');
            return `<section class="sco-card sco-mission-step-card">
              <div class="sco-mission-step-head">
                <h4 class="sco-mission-step-day">Tag ${day}</h4>
                <span class="sco-mission-time-badge">~${Number(step.estimated_minutes || 0)} Min</span>
              </div>
              <p class="sco-mission-step-title"><strong>${esc(step.title)}</strong></p>
              <p class="sco-muted sco-mission-task-label"><strong>Deine Aufgabe</strong></p>
              <ul class="sco-mission-task-list">${tasks || '<li>Keine Aufgaben hinterlegt.</li>'}</ul>
              ${step.script_text ? `<button class="sco-btn" type="button" data-toggle-script="${mission.id}-${day}">Text anzeigen</button><div class="sco-mission-script" data-script-body="${mission.id}-${day}" hidden><p>${esc(step.script_text)}</p><button class="sco-btn" type="button" data-copy-script="${mission.id}-${day}" data-copy-value="${esc(step.script_text)}">Text kopieren</button></div>` : ''}
              <div class="sco-mission-step-actions">
                ${!isDone && !canComplete ? '<small class="sco-muted sco-mission-step-hint">Erst ausführen</small>' : ''}
                <div class="sco-actions sco-actions--stack">
                  <button class="sco-btn" type="button" data-run-step='${JSON.stringify({ mission_id: mission.id, step_day: day, mission_title: mission.title, step_title: step.title, skill_key: step.drill_skill_key || mission.skill_key || 'werbung', category_key: step.drill_category_key || '', recommended_drill_id: Number(step.recommended_drill_id || 0) }).replace(/'/g, '&apos;')}'><i class="fa-solid fa-play"></i> Jetzt ausführen</button>
                  <button class="sco-btn sco-btn-brand" type="button" data-sco-switch-tab="daily">Zum Daily Drill</button>
                  <button class="sco-btn ${isDone ? '' : 'sco-btn-primary'}" type="button" data-complete-mission="${mission.id}" data-step-day="${day}" ${isDone || !canComplete ? 'disabled' : ''}>${isDone ? 'Erledigt' : 'Als erledigt markieren'}</button>
                </div>
              </div>
            </section>`;
          }).join(''),
        });

        window.setTimeout(() => {
          const drawerScope = root.closest('#scoCoachOverlay') || document;

          drawerScope.querySelectorAll('[data-toggle-script]').forEach((button) => {
            button.addEventListener('click', () => {
              const body = drawerScope.querySelector(`[data-script-body="${button.dataset.toggleScript}"]`);
              if (!body) return;
              body.hidden = !body.hidden;
              button.textContent = body.hidden ? 'Text anzeigen' : 'Text ausblenden';
            });
          });

          drawerScope.querySelectorAll('[data-copy-script]').forEach((button) => {
            button.addEventListener('click', async () => navigator.clipboard.writeText(button.dataset.copyValue || ''));
          });

          drawerScope.querySelectorAll('[data-run-step]').forEach((button) => {
            button.addEventListener('click', async () => {
              const payload = JSON.parse(button.dataset.runStep.replace(/&apos;/g, "'"));
              await scoGoToDailyDrill(payload);
              drawer.closeDrawer();
            });
          });

          drawerScope.querySelectorAll('[data-complete-mission]').forEach((button) => {
            button.addEventListener('click', async () => {
              await api('missions/step-complete', { method: 'POST', body: JSON.stringify({ mission_id: Number(button.dataset.completeMission), step_day: Number(button.dataset.stepDay) }) });
              await renderMissions();
              drawer.closeDrawer();
            });
          });

          bindSwitchTabs(drawerScope);
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
        <div class="sco-card-header"><h3>${esc(item.title)}</h3></div>
        <p class="sco-muted">${esc(skillLabel(item.skill_key || 'all'))} · ${esc(categoryLabel(item.category_key || item.type || ''))}</p>
        <p>${esc((item.content || '').slice(0, 130))}…</p>
        <button type="button" class="sco-btn" data-open-library="${item.id}">Öffnen</button>
      </article>`).join('');

    notice.innerHTML = data.limit_reached
      ? `<p>${esc(data.copy.free_limit_reached)}</p><div class="sco-actions"><a class="sco-btn sco-btn-primary" href="${data.checkout_url || root.dataset.upgradeUrl || '#'}">${esc(data.copy.upgrade_primary)}</a></div>`
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
            html: `<p>${esc(item.content)}</p><small class="sco-muted">Heute geöffnet: ${openState.opens_count}/${openState.daily_limit}</small>`,
            footerHtml: '<button class="sco-btn sco-btn-primary" type="button" data-copy-text>Text kopieren</button>',
          });
          root.querySelector('[data-copy-text]')?.addEventListener('click', async () => navigator.clipboard.writeText(item.content || ''));
        } catch (error) {
          notice.innerHTML = `<p>${esc(error.message)}</p><div class="sco-actions"><a class="sco-btn sco-btn-primary" href="${root.dataset.upgradeUrl || '#'}">Upgrade</a></div>`;
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
      : 'Noch kein Training';
    const remaining = Math.max(progress.weekly_goal - progress.weekly_count, 0);
    const seriesInfo = progress.streak > 0 ? `${progress.streak} Tage in Folge trainiert` : 'Noch keine Serie – starte heute';
    const badge = progress.streak >= 14 ? 'Sehr stark' : (progress.streak >= 7 ? 'Stabil' : (progress.streak >= 3 ? 'Konstant' : ''));
    const notes = state.dashboard.notes_recent || [];
    const week = state.dashboard.week_review || { trainings_this_week: 0, streak_max: 0, top_skill: 'werbung', insights: [] };
    const plan = state.dashboard.adaptive_plan || { focus: [] };
    panel.innerHTML = `
      <div class="sco-card-header"><h3>Fortschritt</h3></div>
      <div class="sco-progress-kpis">
        <div class="sco-kpi-card"><span class="sco-kpi-label"><i class="fa-solid fa-star"></i> XP gesamt</span><strong class="sco-kpi-value">${progress.xp_total}</strong><span class="sco-kpi-sub">Level ${progress.level}</span></div>
        <div class="sco-kpi-card"><span class="sco-kpi-label"><i class="fa-solid fa-fire"></i> Trainingsserie</span><strong class="sco-kpi-value">${progress.streak}</strong><span class="sco-kpi-sub">${seriesInfo}${badge ? ` · ${badge}` : ''}</span></div>
        <div class="sco-kpi-card"><span class="sco-kpi-label"><i class="fa-solid fa-calendar-check"></i> Letztes Training</span><strong class="sco-kpi-value" style="font-size:22px;line-height:1.2;">${lastDate}</strong><span class="sco-kpi-sub">zuletzt erledigt</span></div>
      </div>
      <div class="sco-progress-extra">
        <div class="sco-kpi-card"><p class="sco-kpi-label">Wochenziel: ${progress.weekly_goal} Trainings</p><div class="sco-progress"><div style="width:${Math.min((progress.weekly_count / progress.weekly_goal) * 100, 100)}%"></div></div><p class="sco-kpi-sub">Nächstes Ziel: noch ${remaining} Trainings bis Wochenziel</p></div>
        ${state.dashboard.premium ? '<div class="sco-progress-lock"><strong>Verlauf</strong><p class="sco-muted">Bald: Wochenvergleich, Skill-History, KPI-Trends.</p></div>' : `<div class="sco-progress-lock"><strong>Verlauf (Premium)</strong><p class="sco-muted">Vergleiche und Timeline sind im Premium-Plan enthalten.</p><div class="sco-actions"><a class="sco-btn sco-btn-primary" href="${root.dataset.upgradeUrl || '#'}">Upgrade</a></div></div>`}
      </div>
      <section class="sco-kpi-card sco-progress-section"><h4>Wochenrückblick</h4><p>Trainings diese Woche: <strong>${week.trainings_this_week}</strong></p><p>Trainingsserie (max): <strong>${week.streak_max}</strong></p><p>Top Skill: <strong>${esc(skillLabel(week.top_skill))}</strong></p><ul>${(week.insights || []).map((item) => `<li>${esc(item)}</li>`).join('')}</ul>${state.dashboard.premium ? '<p class="sco-muted">Premium: Verlauf und Vergleich aktiviert.</p>' : '<p class="sco-muted">Premium: Charts + Vergleich.</p>'}</section>
      <section class="sco-kpi-card sco-progress-section"><h4>Dein Plan</h4><ul>${(plan.focus || []).map((item) => `<li>${esc(item)}</li>`).join('')}</ul>${state.dashboard.premium ? '<p class="sco-muted">Premium nutzt zusätzliche Personalisierung und mehr Slots.</p>' : ''}</section>
      <section class="sco-kpi-card sco-progress-section"><h4>Letzte 7 Session Notes</h4>${notes.length ? notes.map((item) => `<article class="sco-note-item"><small>${esc(item.date || '')}</small><p><strong>Heute besser:</strong> ${esc(item.better || item.better_today || '-')}</p><p><strong>Morgen Fokus:</strong> ${esc(item.focus || item.focus_tomorrow || '-')}</p></article>`).join('') : '<p class="sco-muted">Noch keine Notizen gespeichert.</p>'}</section>`;
  };

  const bindAdminTestPlan = () => {
    const box = document.querySelector('[data-sco-admin-test-plan]');
    if (!box) return;
    box.querySelectorAll('[data-plan]').forEach((button) => {
      button.addEventListener('click', async () => {
        const plan = button.dataset.plan;
        await api('admin/test-plan', { method: 'POST', body: JSON.stringify({ plan }) });
        box.querySelectorAll('[data-plan]').forEach((node) => node.classList.toggle('is-active', node === button));
        toast('Testmodus aktualisiert. Lade neu …');
        window.setTimeout(() => window.location.reload(), 350);
      });
    });
  };

  const bindCoachReset = () => {
    root.querySelector('[data-sco-reset-coach]')?.addEventListener('click', async () => {
      const confirmed = window.confirm('Coach wirklich zurücksetzen? Dieser Schritt setzt XP, Missionen und Historie zurück.');
      if (!confirmed) return;
      const response = await api('reset', { method: 'POST' });
      if (response?.success) {
        await loadDashboard();
        updateHeader();
        renderToday();
        renderDaily();
        renderProgress();
        await Promise.all([renderMissions(), loadLibrary()]);
        toast('Coach wurde zurückgesetzt.');
      }
    });
  };

  const bindToolTabs = () => {
    setActiveToolPanel(window.localStorage.getItem('sco_active_tool') || 'demo');
  };

  const toolTemplates = {
    werbung: ['Entdecke {topic}: {hook}.', 'Neu gedacht für {audience}.', 'Mach den nächsten Schritt mit {topic}.', 'Mehr Wirkung, weniger Aufwand.', 'Für alle, die klare Entscheidungen lieben.', 'Heute testen, morgen nicht mehr missen.', '{topic} bringt Fokus in deinen Alltag.', 'Jetzt starten und Unterschied hören.'],
    imagefilm: ['{topic} steht für Haltung und Qualität.', 'Ein Team mit Blick für Details.', 'Für {audience} gemacht.', 'Werte, die man hört und spürt.', 'Stark im Anspruch, klar in der Aussage.', 'Nahbar, präzise und verlässlich.', 'Aus Ideen werden Lösungen.', 'So klingt eine Marke mit Substanz.'],
    erklaervideo: ['{topic} einfach erklärt.', 'Schritt für Schritt zum Ergebnis.', 'Für {audience} sofort verständlich.', 'Komplexes wird klar.', 'Vom Problem zur Lösung in Minuten.', 'Strukturiert, ruhig, hilfreich.', 'Lerne im eigenen Tempo.', 'So funktioniert es wirklich.'],
    elearning: ['Willkommen zum Modul {topic}.', 'Heute trainierst du einen klaren Ablauf.', 'Für {audience} praxisnah aufbereitet.', 'Ein Schritt, ein Ziel, ein Ergebnis.', 'Merksatz: Klarheit vor Tempo.', 'Wiederhole den Kernpunkt einmal laut.', 'So bleibt das Wissen abrufbar.', 'Weiter mit der nächsten Lerneinheit.'],
    telefon: ['Guten Tag und willkommen bei {topic}.', 'Aktuell sind alle Leitungen belegt.', 'Für {audience} sind wir gleich da.', 'Bitte halte deine Daten bereit.', 'Wir verbinden dich zum richtigen Team.', 'Danke für deine Geduld.', 'Dein Anliegen ist uns wichtig.', 'Wir melden uns schnellstmöglich zurück.'],
    hoerbuch: ['Es begann mit einem leisen Moment.', '{topic} lag wie ein Versprechen in der Luft.', 'Für {audience} war nichts mehr wie zuvor.', 'Jeder Schritt klang nach Entscheidung.', 'Die Stille erzählte mehr als Worte.', 'Dann änderte ein Satz alles.', 'Ein Atemzug, ein Blick, ein Neuanfang.', 'Und die Geschichte nahm Fahrt auf.'],
    doku: ['{topic} zeigt, wie Wandel entsteht.', 'Daten und Erfahrung greifen ineinander.', 'Für {audience} werden Folgen sichtbar.', 'Was heute klein wirkt, prägt morgen.', 'Beobachtung schafft Verständnis.', 'Zwischen Fakten steht Verantwortung.', 'Jede Zahl erzählt eine Entwicklung.', 'Der Blick nach vorn beginnt hier.'],
  };

  const renderTools = () => {
    const gen = root.querySelector('[data-sco-tool-generator]');
    const tele = root.querySelector('[data-sco-tool-teleprompter]');
    if (gen) {
      gen.innerHTML = `<div class="sco-card-header"><h3>Demo Text Generator</h3></div><form class="sco-tool-form" data-sco-generator-form>
        <label>Genre<select name="genre"><option value="werbung">Werbung</option><option value="imagefilm">Imagefilm</option><option value="erklaervideo">Erklärvideo</option><option value="elearning">E-Learning</option><option value="telefon">Telefonansage</option><option value="hoerbuch">Hörbuch</option><option value="doku">Dokumentarfilm</option></select></label>
        <label>Tonalität<select name="tone"><option>freundlich</option><option>premium</option><option>dynamisch</option><option>ruhig</option><option>seriös</option><option>emotional</option></select></label>
        <label>Länge<select name="length"><option value="10">10s</option><option value="20">20s</option><option value="30" selected>30s</option><option value="45">45s</option><option value="60">60s</option></select></label>
        <label>Zielgruppe<input name="audience" type="text" placeholder="z.B. B2B, Tech"></label>
        <label>Produkt/Topic<input name="topic" type="text" required placeholder="z.B. Kaffee-Abo"></label>
        <div><span class="sco-muted">Stil-Parameter</span><div class="sco-toggle-tiles" data-sco-style-tiles>
          <button type="button" class="sco-toggle-tile" data-style="Kurz&knackig" aria-pressed="false">Kurz&knackig</button>
          <button type="button" class="sco-toggle-tile" data-style="Warm&weich" aria-pressed="false">Warm&weich</button>
          <button type="button" class="sco-toggle-tile" data-style="Understatement" aria-pressed="false">Understatement</button>
          <button type="button" class="sco-toggle-tile" data-style="CTA-stark" aria-pressed="false">CTA-stark</button>
          <button type="button" class="sco-toggle-tile" data-style="Didaktisch" aria-pressed="false">Didaktisch</button>
        </div></div>
        <button class="sco-btn sco-btn-primary sco-gen-btn" type="submit"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Text generieren</button>
      </form><div data-sco-generator-output></div>`;
      gen.querySelectorAll('[data-sco-style-tiles] [data-style]').forEach((tile) => {
        tile.addEventListener('click', () => {
          const active = tile.classList.toggle('is-active');
          tile.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
      });

      gen.querySelector('[data-sco-generator-form]').addEventListener('submit', (event) => {
        event.preventDefault();
        const data = Object.fromEntries(new FormData(event.currentTarget).entries());
        const lines = toolTemplates[data.genre] || toolTemplates.werbung;
        const targetWords = (() => {
          const l = Number(data.length || 30);
          if (l <= 10) return 24;
          if (l <= 20) return 48;
          if (l <= 30) return 80;
          if (l <= 45) return 110;
          return 140;
        })();
        const styles = Array.from(gen.querySelectorAll('[data-sco-style-tiles] [data-style].is-active')).map((node) => node.dataset.style);
        const seed = `${scoData?.userId || 0}|${new Date().toISOString().slice(0, 10)}|${data.genre}|${data.tone}|${data.length}|${data.topic}|${data.audience}|${styles.join(',')}`;
        const offset = Math.abs(seed.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0)) % lines.length;
        const phrasePool = lines.map((line, idx) => lines[(idx + offset) % lines.length]).map((line) => line.replaceAll('{topic}', data.topic).replaceAll('{audience}', data.audience || 'deine Zielgruppe').replaceAll('{hook}', data.tone));
        const words = [];
        let idx = 0;
        while (words.length < targetWords) {
          words.push(...String(phrasePool[idx % phrasePool.length]).split(/\s+/));
          idx += 1;
        }
        const textOut = words.slice(0, targetWords).join(' ');
        const regie = [`Tempo: ${data.tone === 'dynamisch' ? 'zackig und aktiv' : data.tone === 'ruhig' ? 'ruhig und getragen' : 'mittel und klar'}`, `Pausen: ${(data.genre === 'hoerbuch' || data.genre === 'doku') ? 'deutlich setzen' : 'kurz und rhythmisch'}`, `Smile: ${styles.includes('Warm&weich') ? 'hörbar weich' : 'natürlich dosiert'}`, `Keyword Fokus: ${data.topic}`, `Haltung: ${styles.includes('Understatement') ? 'minimalistisch, souverän' : styles.includes('CTA-stark') ? 'klar, zielgerichtet' : 'offen und präsent'}`];
        const music = data.genre === 'werbung' ? 'Dezenter Corporate Pop, 95–110 BPM, klarer Schlussakzent.' : data.genre === 'hoerbuch' ? 'Sanftes Ambient-Pad ohne dominante Drums, 70–85 BPM.' : 'Leichtes Ambient-Bett, 85–100 BPM, zurückhaltend im Vocal-Bereich.';
        state.generatedTool = { text: textOut, regie, genre: data.genre, tone: data.tone, styles };
        gen.querySelector('[data-sco-generator-output]').innerHTML = `<div class="sco-tool-output"><h4>Regie</h4><ul>${regie.map((r)=>`<li>${esc(r)}</li>`).join('')}</ul><h4>Musikvorschlag</h4><p>${esc(music)}</p><h4>Text</h4><p>${esc(textOut)}</p><div class="sco-actions"><button type="button" class="sco-btn" data-copy-gen-text>Text kopieren</button><button type="button" class="sco-btn" data-copy-gen-regie>Regie kopieren</button><button type="button" class="sco-btn ${state.premium ? '' : 'is-disabled'}" ${state.premium ? '' : 'disabled'} data-save-gen-script title="Nur Premium">In Bibliothek speichern</button></div></div>`;
        gen.querySelector('[data-copy-gen-text]')?.addEventListener('click', async ()=>{ await navigator.clipboard.writeText(textOut); toast('Kopiert!');});
        gen.querySelector('[data-copy-gen-regie]')?.addEventListener('click', async ()=>{ await navigator.clipboard.writeText(regie.join('\n')); toast('Kopiert!');});
        gen.querySelector('[data-save-gen-script]')?.addEventListener('click', async ()=>{
          if (!state.premium) return;
          await api('library/add', { method: 'POST', body: JSON.stringify({ type: 'script', skill_key: data.genre, title: `Demo Script ${new Date().toLocaleDateString('de-DE')}`, content: `Regie:\n- ${regie.join('\n- ')}\n\nText:\n${textOut}` }) });
          toast('Script gespeichert.');
          await loadLibrary();
        });
      });
    }

    if (tele) {
      tele.innerHTML = `<div class="sco-card-header"><h3>Teleprompter</h3></div><div class="sco-actions" style="margin-top:0;"><button type="button" class="sco-btn" data-sco-split-toggle aria-pressed="false"><i class="fa-solid fa-columns" aria-hidden="true"></i>Split View</button></div><section class="sco-split-layout" data-sco-split-layout><div class="sco-split-layout__left"><textarea data-sco-teleprompter-text rows="10" placeholder="Text einfügen oder aus Bibliothek wählen"></textarea><div class="sco-teleprompter-view" data-sco-tp-view><div class="sco-teleprompter-content" data-sco-tp-content></div><div class="sco-teleprompter-focus" hidden></div></div></div><div class="sco-split-layout__right"><div class="sco-tool-controls"><label>Speed (WPM)<input type="range" min="80" max="220" value="130" data-sco-tp-speed></label><label>Font size<input type="range" min="18" max="56" value="32" data-sco-tp-font></label><label>Line height<input type="range" min="1.2" max="2" step="0.1" value="1.5" data-sco-tp-line></label><label><input type="checkbox" data-sco-tp-mirror> Mirror</label><label><input type="checkbox" data-sco-tp-focus> Focus mode</label></div><div class="sco-actions"><button class="sco-btn sco-btn-primary" type="button" data-sco-tp-start>Start</button><button class="sco-btn" type="button" data-sco-tp-pause>Pause</button><button class="sco-btn" type="button" data-sco-tp-reset>Reset</button><button class="sco-btn" type="button" data-sco-tp-library>Aus Bibliothek wählen</button><button class="sco-btn" type="button" data-sco-tp-from-generator>Aus Demo Generator übernehmen</button><button class="sco-btn" type="button" data-sco-tp-fullscreen><i class="fa-solid fa-expand" aria-hidden="true"></i> Fokusansicht</button></div><div class="sco-mission-mini">Mission Kontext & Controls für den nächsten Take.</div></div></section>`;
      const textArea = tele.querySelector('[data-sco-teleprompter-text]');
      const content = tele.querySelector('[data-sco-tp-content]');
      const view = tele.querySelector('[data-sco-tp-view]');
      const speed = tele.querySelector('[data-sco-tp-speed]');
      const font = tele.querySelector('[data-sco-tp-font]');
      const line = tele.querySelector('[data-sco-tp-line]');
      const mirror = tele.querySelector('[data-sco-tp-mirror]');
      const focus = tele.querySelector('[data-sco-tp-focus]');
      const focusBand = tele.querySelector('.sco-teleprompter-focus');
      const settingsKey = "sco_tp_settings";
      try { const saved = JSON.parse(localStorage.getItem(settingsKey) || '{}'); [speed.value,font.value,line.value]=[saved.speed||130,saved.font||32,saved.line||1.5]; mirror.checked=!!saved.mirror; focus.checked=!!saved.focus; } catch(e){}
      const sync = () => {
        content.textContent = textArea.value || 'Teleprompter bereit.';
        content.style.fontSize = `${font.value}px`;
        content.style.lineHeight = line.value;
        content.style.transform = mirror.checked ? 'scaleX(-1)' : 'none';
        focusBand.hidden = !focus.checked;
        localStorage.setItem(settingsKey, JSON.stringify({ speed: Number(speed.value), font: Number(font.value), line: Number(line.value), mirror: mirror.checked, focus: focus.checked }));
      };
      [textArea,speed,font,line,mirror,focus].forEach((el)=>el.addEventListener('input', sync));
      sync();
      applySplitView(isSplitView());
      tele.querySelector('[data-sco-split-toggle]')?.addEventListener('click', () => setSplitView(!isSplitView()));
      const step = (ts) => {
        if (!state.teleprompter.running) return;
        if (!state.teleprompter.lastTs) state.teleprompter.lastTs = ts;
        const dt = (ts - state.teleprompter.lastTs) / 1000;
        state.teleprompter.lastTs = ts;
        const pixelsPerSec = (Number(speed.value) / 60) * (Number(font.value) * 0.6);
        state.teleprompter.offset += pixelsPerSec * dt;
        view.scrollTop = state.teleprompter.offset;
        state.teleprompter.raf = requestAnimationFrame(step);
      };
      tele.querySelector('[data-sco-tp-start]').addEventListener('click', () => { if (state.teleprompter.running) return; state.teleprompter.running = true; state.teleprompter.lastTs = 0; state.teleprompter.raf = requestAnimationFrame(step); });
      tele.querySelector('[data-sco-tp-pause]').addEventListener('click', () => { state.teleprompter.running = false; if (state.teleprompter.raf) cancelAnimationFrame(state.teleprompter.raf); });
      tele.querySelector('[data-sco-tp-reset]').addEventListener('click', () => { state.teleprompter.running = false; if (state.teleprompter.raf) cancelAnimationFrame(state.teleprompter.raf); state.teleprompter.offset = 0; view.scrollTop = 0; });
      tele.querySelector('[data-sco-tp-fullscreen]')?.addEventListener('click', () => {
        state.teleprompter.fullscreen = !state.teleprompter.fullscreen;
        tele.classList.toggle('is-teleprompter-fullscreen', state.teleprompter.fullscreen);
      });
      tele.querySelector('[data-sco-tp-from-generator]').addEventListener('click', () => {
        if (!state.generatedTool?.text) {
          toast('Bitte zuerst im Demo Generator einen Text erzeugen.');
          return;
        }
        textArea.value = state.generatedTool.text;
        sync();
      });

      tele.querySelector('[data-sco-tp-library]').addEventListener('click', () => {
        const scripts = state.library.filter((item) => (item.category_key || item.type) === 'script').slice(0, 20);
        drawer.openDrawer({ title: 'Script auswählen', iconClass: 'fa-solid fa-file-lines', html: scripts.map((item) => `<button type=\"button\" class=\"sco-btn\" data-pick-script=\"${item.id}\">${esc(item.title)}</button>`).join('<div style=\"height:8px\"></div>') || '<p>Keine Skripte verfügbar.</p>' });
        window.setTimeout(() => {
          const drawerScope = root.closest('#scoCoachOverlay') || document;
          drawerScope.querySelectorAll('[data-pick-script]').forEach((button) => button.addEventListener('click', () => {
            const pick = scripts.find((item)=>Number(item.id)===Number(button.dataset.pickScript));
            if (pick) {
              textArea.value = pick.content || '';
              sync();
              drawer.closeDrawer();
            }
          }));
        }, 0);
      });
    }
  };

  const loadDashboard = async () => {
    state.dashboard = await api('dashboard');
    state.drill = state.dashboard.drill;
    state.premium = state.dashboard.premium;
  };

  const init = async () => {
    const commandPalette = bindCommandPalette();

    root.addEventListener('click', (event) => {
      const tabBtn = event.target.closest('[data-tab]');
      if (tabBtn && root.contains(tabBtn)) setTab(tabBtn.dataset.tab);

      const switchBtn = event.target.closest('[data-sco-switch-tab]');
      if (switchBtn) { event.preventDefault(); setTab(switchBtn.dataset.scoSwitchTab); }

      const quickBtn = event.target.closest('[data-sco-quick-action]');
      if (quickBtn && root.contains(quickBtn)) runQuickAction(quickBtn.dataset.scoQuickAction);

      const commandOpenBtn = event.target.closest('[data-sco-command-open]');
      if (commandOpenBtn && root.contains(commandOpenBtn)) commandPalette.open();

      const toolBtn = event.target.closest('[data-tool-tab]');
      if (toolBtn && root.contains(toolBtn)) setActiveToolPanel(toolBtn.dataset.toolTab);
    });

    root.__scoLayerApi = {
      closeCommandPaletteIfOpen: () => { if (commandPalette.isOpen()) { commandPalette.close(); return true; } return false; },
      closeDrawerIfOpen: () => { if (drawer.isOpen()) { drawer.closeDrawer(); return true; } return false; },
      closeTeleprompterFullscreenIfOpen: () => {
        const tele = root.querySelector('[data-sco-tool-teleprompter]');
        if (tele?.classList.contains('is-teleprompter-fullscreen')) {
          tele.classList.remove('is-teleprompter-fullscreen');
          state.teleprompter.fullscreen = false;
          return true;
        }
        return false;
      },
    };

    initTabs();
    bindLibraryCategories();
    bindAdminTestPlan();
    bindCoachReset();
    bindToolTabs();
    bindQuickActions();
    bindSwitchTabs(root.closest('#scoCoachOverlay') || root);
    await loadDashboard();
    updateHeader();
    renderToday();
    renderDaily();
    await Promise.all([renderSkilltree(), renderMissions(), loadLibrary()]);
    renderProgress();
    renderTools();
    renderMissionContextBanner();
  };


    init();
  };

  const initCoachAppsInScope = (scope = document) => {
    scope.querySelectorAll('.sco-app').forEach((node) => initCoachApp(node));
  };

  window.SCOInitApp = initCoachAppsInScope;

  const focusableSelector = 'a[href], area[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

  const createModalController = (launcher) => {
    const overlay = launcher.querySelector('#scoCoachOverlay');
    const mount = launcher.querySelector('#scoCoachAppMount');
    const modal = launcher.querySelector('.sco-modal');
    const closeBtn = launcher.querySelector('.sco-modal__close');
    const openBtn = launcher.querySelector('[data-sco-launcher-btn]');
    if (!overlay || !mount || !openBtn) return;

    let lastActiveElement = null;
    let appLoaded = false;

    const getFocusable = () => Array.from(overlay.querySelectorAll(focusableSelector)).filter((el) => !el.hasAttribute('disabled') && el.getAttribute('aria-hidden') !== 'true' && !el.hidden);

    const persistModalState = () => {
      const activeTab = overlay.querySelector('[data-tab].is-active')?.dataset.tab;
      const activeTool = overlay.querySelector('[data-tool-tab].is-active')?.dataset.toolTab;
      if (activeTab) {
        window.localStorage.setItem(ACTIVE_TAB_KEY, activeTab);
        window.localStorage.setItem('scoActiveTab', activeTab);
      }
      if (activeTool) window.localStorage.setItem('sco_active_tool', activeTool);
    };

    const trapFocus = (event) => {
      if (event.key !== 'Tab') return;
      const nodes = getFocusable();
      if (!nodes.length) return;
      const first = nodes[0];
      const last = nodes[nodes.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };

    const onEscape = (event) => {
      if (event.key !== 'Escape') return;
      const appRoot = mount.querySelector('#sco-root');
      const layers = appRoot?.__scoLayerApi;
      if (layers?.closeCommandPaletteIfOpen?.()) { event.preventDefault(); return; }
      if (layers?.closeDrawerIfOpen?.()) { event.preventDefault(); return; }
      if (layers?.closeTeleprompterFullscreenIfOpen?.()) { event.preventDefault(); return; }
      closeCoachModal();
    };

    const ensureAppLoaded = async () => {
      if (appLoaded) return;
      const response = await fetch(`${scoData.restUrl}app-html`, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { 'X-WP-Nonce': scoData.nonce },
      });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload?.message || 'Coach konnte nicht geladen werden.');
      mount.innerHTML = payload?.html || '<p class="sco-muted">Coach konnte nicht geladen werden.</p>';
      appLoaded = true;
      initCoachAppsInScope(mount);
    };

    let closeTimer = null;

    const openCoachModal = async () => {
      window.clearTimeout(closeTimer);
      lastActiveElement = document.activeElement;
      overlay.hidden = false;
      overlay.classList.remove('is-closing');
      requestAnimationFrame(() => overlay.classList.add('is-open'));
      document.body.classList.add('sco-modal-open');
      document.body.style.overflow = 'hidden';
      closeBtn?.focus();
      try {
        await ensureAppLoaded();
      } catch (error) {
        mount.innerHTML = `<p class="sco-muted">${error.message || 'Coach konnte nicht geladen werden.'}</p>`;
      }
      document.addEventListener('keydown', trapFocus);
      document.addEventListener('keydown', onEscape);
      document.dispatchEvent(new CustomEvent('sco:overlay:open'));
    };

    const closeCoachModal = () => {
      persistModalState();
      overlay.classList.remove('is-open');
      overlay.classList.add('is-closing');
      document.body.classList.remove('sco-modal-open');
      document.body.style.overflow = '';
      document.removeEventListener('keydown', trapFocus);
      document.removeEventListener('keydown', onEscape);
      document.dispatchEvent(new CustomEvent('sco:overlay:close'));
      window.clearTimeout(closeTimer);
      const finalizeClose = () => {
        overlay.hidden = true;
        overlay.classList.remove('is-closing');
        if (lastActiveElement && typeof lastActiveElement.focus === 'function') lastActiveElement.focus();
      };
      if (modal) {
        const onEnd = (event) => {
          if (event.target !== modal || event.propertyName !== 'transform') return;
          modal.removeEventListener('transitionend', onEnd);
          finalizeClose();
        };
        modal.addEventListener('transitionend', onEnd);
        closeTimer = window.setTimeout(() => {
          modal.removeEventListener('transitionend', onEnd);
          finalizeClose();
        }, 320);
      } else {
        closeTimer = window.setTimeout(finalizeClose, 320);
      }
    };

    openBtn.addEventListener('click', openCoachModal);
    closeBtn?.addEventListener('click', closeCoachModal);
    overlay.addEventListener('click', (event) => {
      if (event.target === overlay) closeCoachModal();
    });

    if (launcher.dataset.autoOpen === '1') {
      window.setTimeout(() => openCoachModal(), 60);
    }
  };


  if (!window.__SCO_STAGE2_INIT__) {
    window.__SCO_STAGE2_INIT__ = true;
    let overlayHotkeysEnabled = false;
    document.addEventListener('sco:overlay:open', () => { overlayHotkeysEnabled = true; });
    document.addEventListener('sco:overlay:close', () => { overlayHotkeysEnabled = false; });
    document.addEventListener('keydown', (event) => {
      if (!overlayHotkeysEnabled || !scoIsOverlayOpen()) return;
      const pressedK = event.key.toLowerCase() === 'k';
      const target = event.target;
      const isTyping = target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable);
      if ((event.ctrlKey || event.metaKey) && pressedK && !isTyping) {
        const appRoot = document.querySelector('#scoCoachAppMount #sco-root');
        const cmdkOpen = appRoot?.__scoLayerApi?.closeCommandPaletteIfOpen;
        event.preventDefault();
        if (cmdkOpen && !appRoot.__scoLayerApi.closeCommandPaletteIfOpen()) {
          appRoot.querySelector('[data-sco-command-open]')?.click();
        }
      }
    });
  }

  initCoachAppsInScope(document);
  document.querySelectorAll('[data-sco-launcher]').forEach((launcher) => createModalController(launcher));
})();

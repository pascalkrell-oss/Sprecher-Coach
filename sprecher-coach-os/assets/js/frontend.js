(() => {
  const appRoots = document.querySelectorAll('.sco-app');
  if (!appRoots.length) return;

  const api = (path, options = {}) => fetch(`${scoData.restUrl}${path}`, {
    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scoData.nonce },
    credentials: 'same-origin',
    ...options,
  }).then((r) => r.json());

  const setupTabs = (root) => {
    const tabs = root.querySelectorAll('[data-sco-tab]');
    if (!tabs.length) return;
    tabs.forEach((tab) => {
      tab.addEventListener('click', () => {
        tabs.forEach((btn) => btn.classList.remove('is-active'));
        root.querySelectorAll('.sco-tab-panel').forEach((panel) => panel.classList.remove('is-active'));
        tab.classList.add('is-active');
        const panel = root.querySelector(`#${tab.dataset.scoTab}`);
        panel?.classList.add('is-active');
      });
    });
  };

  const setupInPageNav = (root) => {
    root.querySelectorAll('.sco-nav-list a[href^="#"]').forEach((link) => {
      link.addEventListener('click', (event) => {
        const target = root.querySelector(link.getAttribute('href'));
        if (!target) return;
        event.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
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
            <button type="button" class="sco-btn sco-icon-btn" data-close-drawer aria-label="Schließen">✕</button>
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

  const renderDashboard = async (root) => {
    const dailyCard = root.querySelector('[data-sco-daily-card]');
    if (!dailyCard) return;
    const data = await api('dashboard');
    const progressPercent = Math.min((data.progress.weekly_count / data.progress.weekly_goal) * 100, 100);
    dailyCard.innerHTML = `
      <div class="sco-card-header"><h2>Heute: ${data.drill.title}</h2></div>
      <p>${data.drill.description}</p>
      <div class="sco-actions">
        <a class="sco-btn sco-btn-primary" href="#sco-dashboard-daily" data-sco-start-today>Jetzt starten</a>
        <a class="sco-btn" href="#tab-skilltree">Skilltree ansehen</a>
      </div>
      <div class="sco-progress" style="margin-top:16px;"><div style="width:${progressPercent}%"></div></div>
      <p class="sco-muted" style="margin-top:10px;">Wochenziel: ${data.progress.weekly_count}/${data.progress.weekly_goal}</p>
    `;

    root.querySelector('[data-sco-next-action]')?.insertAdjacentHTML('afterbegin', `
      <div class="sco-card-header"><h3>Heute</h3></div>
      <p><strong>Nächste Aktion:</strong> ${data.drill.title}</p>
      <p class="sco-muted">${data.cta.dashboard_nudge}</p>
      <a class="sco-btn sco-btn-primary" href="#sco-dashboard-daily">Jetzt starten</a>
    `);

    const streak = root.querySelector('[data-sco-streak]');
    const level = root.querySelector('[data-sco-level]');
    const weekly = root.querySelector('[data-sco-weekly]');
    if (streak) streak.textContent = `Streak ${data.progress.streak}`;
    if (level) level.textContent = `Level ${data.progress.level}`;
    if (weekly) weekly.textContent = `Wochenziel ${data.progress.weekly_count}/${data.progress.weekly_goal}`;

    root.querySelectorAll('[data-sco-start-today]').forEach((btn) => {
      btn.addEventListener('click', (event) => {
        event.preventDefault();
        const panelBtn = root.querySelector('[data-sco-tab="tab-daily"]');
        panelBtn?.click();
        root.querySelector('#sco-dashboard-daily')?.scrollIntoView({ behavior: 'smooth' });
      });
    });

    const side = root.querySelector('[data-sco-sidecard] .sco-progress > div');
    if (side) side.style.width = `${progressPercent}%`;
  };

  const setupTimer = (root) => {
    let sec = 0;
    let interval = null;
    const display = root.querySelector('[data-sco-timer]');

    root.querySelector('[data-sco-start]')?.addEventListener('click', () => {
      if (interval) return;
      interval = setInterval(() => {
        sec += 1;
        const m = String(Math.floor(sec / 60)).padStart(2, '0');
        const s = String(sec % 60).padStart(2, '0');
        display.textContent = `${m}:${s}`;
      }, 1000);
    });

    root.querySelector('[data-sco-pause]')?.addEventListener('click', () => {
      clearInterval(interval);
      interval = null;
    });

    root.querySelector('[data-sco-reset]')?.addEventListener('click', () => {
      clearInterval(interval);
      interval = null;
      sec = 0;
      display.textContent = '00:00';
    });
  };

  const renderDaily = async (root) => {
    const wrap = root.querySelector('[data-sco-daily]');
    if (!wrap) return;
    const data = await api('dashboard');
    const questions = data.drill.self_check_questions || [];

    wrap.innerHTML = `
      <div class="sco-card-header"><h2>${data.drill.title}</h2></div>
      <p>${data.drill.description}</p>
      <div class="sco-timer-row">
        <strong data-sco-timer>00:00</strong>
        <div class="sco-btn-group">
          <button class="sco-btn" data-sco-start type="button">Start</button>
          <button class="sco-btn" data-sco-pause type="button">Pause</button>
          <button class="sco-btn" data-sco-reset type="button">Reset</button>
        </div>
      </div>
      <div class="sco-questions">
        ${questions.map((q, i) => q.type.includes('scale')
          ? `<label>${q.label}<input type="range" min="1" max="5" value="3" data-q="${i}" data-type="scale_1_5"></label>`
          : `<label><input type="checkbox" data-q="${i}" data-type="checkbox_multi"> ${q.label}</label>`).join('')}
      </div>
      <button class="sco-btn sco-btn-primary" data-sco-complete disabled type="button">Drill abschließen</button>
      <div data-sco-result></div>
    `;

    setupTimer(wrap);

    const inputs = wrap.querySelectorAll('[data-q]');
    const completeBtn = wrap.querySelector('[data-sco-complete]');
    const hasMinAnswers = () => {
      let count = 0;
      inputs.forEach((input) => {
        if (input.dataset.type.includes('scale')) count += 1;
        if (input.type === 'checkbox' && input.checked) count += 1;
      });
      completeBtn.disabled = count < 2;
    };
    inputs.forEach((input) => input.addEventListener('input', hasMinAnswers));
    hasMinAnswers();

    completeBtn?.addEventListener('click', async () => {
      const resultEl = wrap.querySelector('[data-sco-result]');
      if (!scoData.isLoggedIn) {
        resultEl.innerHTML = '<p>Bitte einloggen, um Fortschritt zu speichern.</p>';
        return;
      }
      const answers = Array.from(inputs).map((el) => ({
        type: el.dataset.type,
        value: el.dataset.type.includes('scale') ? parseInt(el.value, 10) : el.checked,
      }));
      const result = await api('complete-drill', {
        method: 'POST',
        body: JSON.stringify({ drill_id: data.drill.id, answers }),
      });
      resultEl.innerHTML = `<p><strong>Score ${result.score}/100</strong> · +${result.xp} XP</p><p>${result.feedback}</p>`;

      const completion = root.querySelector('[data-sco-completion-card]');
      if (completion) {
        completion.innerHTML = `
          <div class="sco-card-header"><h3>Drill abgeschlossen</h3></div>
          <p><strong>Score:</strong> ${result.score}/100</p>
          <p><strong>XP earned:</strong> +${result.xp}</p>
          <div class="sco-actions">
            <a class="sco-btn" href="#">Morgen geht's weiter</a>
            <a class="sco-btn sco-btn-primary" href="#" data-open-skilltree>Skilltree ansehen</a>
          </div>
        `;
      }
      root.querySelector('[data-sco-tab="daily-completion"]')?.click();
    });
  };

  const renderSkilltree = async (root) => {
    const target = root.querySelector('[data-sco-skilltree]');
    if (!target) return;
    const rows = await api('skilltree');
    const tooltip = root.dataset.lockedSkill || 'Premium erforderlich';

    target.innerHTML = rows.map((row, index) => `
      <article class="sco-card ${row.locked ? 'sco-locked' : ''}" data-sco-skill='${JSON.stringify(row).replace(/'/g, '&apos;')}'>
        <h3>${row.skill}</h3>
        <p>Level ${row.level} · ${row.xp} XP</p>
        <div class="sco-progress"><div style="width:${Math.min(((row.level || 1) / 5) * 100, 100)}%"></div></div>
        ${row.locked ? `<div class="sco-lock">🔒 ${tooltip}</div><div class="sco-tooltip">Premium</div>` : ''}
      </article>
    `).join('');

    target.querySelectorAll('[data-sco-skill]').forEach((card) => {
      card.addEventListener('click', () => {
        const row = JSON.parse(card.dataset.scoSkill.replace(/&apos;/g, "'"));
        const lockedBody = `<p>Dieser Skill ist gesperrt.</p><a class="sco-btn sco-btn-primary" href="${root.dataset.upgradeUrl || '#'}">Upgrade</a>`;
        drawer.open({
          title: row.skill,
          body: row.locked
            ? lockedBody
            : `<p>Aktuelles Level: ${row.level}</p><p>XP: ${row.xp}</p><ul><li>Atemkontrolle Drill</li><li>Pacing Drill</li><li>Resonanz Drill</li></ul><button class="sco-btn sco-btn-primary" type="button">Trainiere diesen Skill</button>`,
        });
      });
    });
  };

  const renderMissions = async (root) => {
    const target = root.querySelector('[data-sco-missions]');
    if (!target) return;
    const response = await api('missions');
    const missions = response.items || [];
    const tooltip = root.dataset.lockedMissions || response.premium_tooltip || 'Premium erforderlich';

    target.innerHTML = missions.map((mission) => `
      <article class="sco-card ${mission.locked ? 'sco-locked' : ''}" data-sco-mission='${JSON.stringify(mission).replace(/'/g, '&apos;')}'>
        <h3>${mission.title}</h3>
        <p>${mission.description}</p>
        <small>${mission.duration_days} Tage</small>
        ${mission.locked ? `<div class="sco-lock">🔒 ${tooltip}</div>` : ''}
      </article>
    `).join('');

    target.querySelectorAll('[data-sco-mission]').forEach((card) => {
      card.addEventListener('click', () => {
        const mission = JSON.parse(card.dataset.scoMission.replace(/&apos;/g, "'"));
        drawer.open({
          title: mission.title,
          body: mission.locked
            ? `<p>${tooltip}</p><a class="sco-btn sco-btn-primary" href="${root.dataset.upgradeUrl || '#'}">Upgrade</a>`
            : `<p>${mission.description}</p><ul class="sco-checklist"><li><label><input type="checkbox"> Warmup</label></li><li><label><input type="checkbox"> Drill</label></li><li><label><input type="checkbox"> Review</label></li></ul>`,
        });
      });
    });
  };

  const renderLibrary = async (root) => {
    const target = root.querySelector('[data-sco-library]');
    if (!target) return;
    const notice = root.querySelector('[data-sco-library-notice]');
    const search = root.querySelector('[data-sco-library-search]');
    const filterWrap = root.querySelector('[data-sco-library-filters]');
    let items = [];
    let activeFilter = 'all';

    const normalizeCategory = (item) => {
      const haystack = `${item.title} ${item.content}`.toLowerCase();
      if (haystack.includes('warmup')) return 'warmups';
      if (haystack.includes('zungen')) return 'zungenbrecher';
      if (haystack.includes('business')) return 'business';
      if (haystack.includes('skript')) return 'skripte';
      return 'all';
    };

    const draw = () => {
      const query = (search?.value || '').toLowerCase().trim();
      const filtered = items.filter((item) => {
        const byFilter = activeFilter === 'all' || normalizeCategory(item) === activeFilter;
        const bySearch = !query || `${item.title} ${item.content}`.toLowerCase().includes(query);
        return byFilter && bySearch;
      });

      target.innerHTML = filtered.map((item) => `
        <article class="sco-card" data-sco-library-item='${JSON.stringify(item).replace(/'/g, '&apos;')}'>
          <h3>${item.title}</h3>
          <p>${item.content.slice(0, 120)}…</p>
          <button class="sco-btn" type="button">Öffnen</button>
        </article>
      `).join('');

      target.querySelectorAll('[data-sco-library-item]').forEach((card) => {
        card.addEventListener('click', () => {
          const item = JSON.parse(card.dataset.scoLibraryItem.replace(/&apos;/g, "'"));
          drawer.open({
            title: item.title,
            body: `<p>${item.content}</p><button class="sco-btn sco-btn-primary" data-sco-copy type="button">Text kopieren</button>`,
          });
          setTimeout(() => {
            document.querySelector('[data-sco-copy]')?.addEventListener('click', async () => {
              await navigator.clipboard.writeText(item.content);
              document.querySelector('[data-sco-copy]').textContent = 'Kopiert';
            });
          }, 0);
        });
      });
    };

    filterWrap?.querySelectorAll('[data-filter]').forEach((chip) => {
      chip.addEventListener('click', () => {
        activeFilter = chip.dataset.filter;
        filterWrap.querySelectorAll('[data-filter]').forEach((entry) => entry.classList.remove('is-active'));
        chip.classList.add('is-active');
        draw();
      });
    });

    search?.addEventListener('input', draw);

    const load = async () => {
      const data = await api('library');
      items = data.items || [];
      draw();
      if (!data.premium && data.items.length >= data.limit) {
        notice.innerHTML = `<p>${data.copy.free_limit_reached}</p><a class="sco-btn sco-btn-primary" href="${data.checkout_url || root.dataset.upgradeUrl || '#'}">${data.copy.upgrade_primary}</a>`;
      } else {
        notice.innerHTML = '';
      }
    };

    root.querySelector('[data-sco-random]')?.addEventListener('click', load);
    load();
  };

  appRoots.forEach((root) => {
    setupTabs(root);
    setupInPageNav(root);
    const module = root.dataset.scoModule;
    if (module === 'dashboard') renderDashboard(root);
    if (module === 'daily') renderDaily(root);
    if (module === 'skilltree') renderSkilltree(root);
    if (module === 'missions') renderMissions(root);
    if (module === 'library') renderLibrary(root);
  });
})();

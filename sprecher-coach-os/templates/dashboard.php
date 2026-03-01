<?php if (!defined('ABSPATH')) { exit; } ?>
<div id="sco-root" class="sco-app" data-sco-module="dashboard" data-upgrade-url="<?php echo esc_url(sco_checkout_url()); ?>">
  <header class="sco-topbar sco-card">
    <div>
      <p class="sco-kicker"><?php echo esc_html__('Sprecher Coach', 'sprecher-coach-os'); ?></p>
      <h1><?php echo esc_html__('Dein Coaching Cockpit', 'sprecher-coach-os'); ?></h1>
      <p class="sco-muted"><?php echo esc_html(SCO_Utils::copy('cta', 'dashboard_nudge')); ?></p>
    </div>
    <div class="sco-pill-row">
      <span class="sco-pill" data-sco-streak><?php echo esc_html__('Streak 0', 'sprecher-coach-os'); ?></span>
      <span class="sco-pill sco-pill-neutral" data-sco-level><?php echo esc_html__('Level 1', 'sprecher-coach-os'); ?></span>
      <span class="sco-pill sco-pill-success" data-sco-weekly><?php echo esc_html__('Wochenziel 0/5', 'sprecher-coach-os'); ?></span>
      <span class="sco-pill sco-pill-premium"><?php echo esc_html__('Upgrade', 'sprecher-coach-os'); ?></span>
    </div>
  </header>

  <div class="sco-shell">
    <aside class="sco-nav sco-card">
      <h3><?php echo esc_html__('Navigation', 'sprecher-coach-os'); ?></h3>
      <nav class="sco-nav-list" aria-label="Coach Navigation">
        <a class="is-active" href="#sco-dashboard-overview"><?php echo esc_html__('Heute', 'sprecher-coach-os'); ?></a>
        <a href="#sco-dashboard-daily"><?php echo esc_html__('Daily Drill', 'sprecher-coach-os'); ?></a>
        <a href="#sco-dashboard-skilltree"><?php echo esc_html__('Skilltree', 'sprecher-coach-os'); ?></a>
        <a href="#sco-dashboard-missions"><?php echo esc_html__('Missionen', 'sprecher-coach-os'); ?></a>
        <a href="#sco-dashboard-library"><?php echo esc_html__('Bibliothek', 'sprecher-coach-os'); ?></a>
        <a class="is-locked" href="<?php echo esc_url(sco_checkout_url()); ?>"><?php echo esc_html__('Fortschritt (Premium)', 'sprecher-coach-os'); ?></a>
      </nav>
    </aside>

    <main class="sco-main">
      <section class="sco-card sco-hero" id="sco-dashboard-overview" data-sco-daily-card>
        <h2><?php echo esc_html__('Heute', 'sprecher-coach-os'); ?></h2>
      </section>

      <div class="sco-tabs" role="tablist" aria-label="Dashboard Tabs">
        <button class="sco-tab is-active" type="button" data-sco-tab="tab-daily"><?php echo esc_html__('Daily Drill', 'sprecher-coach-os'); ?></button>
        <button class="sco-tab" type="button" data-sco-tab="tab-skilltree"><?php echo esc_html__('Skilltree', 'sprecher-coach-os'); ?></button>
        <button class="sco-tab" type="button" data-sco-tab="tab-missions"><?php echo esc_html__('Missionen', 'sprecher-coach-os'); ?></button>
        <button class="sco-tab" type="button" data-sco-tab="tab-library"><?php echo esc_html__('Bibliothek', 'sprecher-coach-os'); ?></button>
      </div>

      <section class="sco-tab-panel is-active" id="tab-daily">
        <div class="sco-grid sco-grid-2">
          <article class="sco-card" id="sco-dashboard-daily" data-sco-next-action></article>
          <article class="sco-card" data-sco-plan>
            <div class="sco-card-header"><h3><?php echo esc_html__('Dein Plan', 'sprecher-coach-os'); ?></h3></div>
            <ul class="sco-checklist">
              <li><label><input type="checkbox" checked> <?php echo esc_html__('Warmup (2 Min)', 'sprecher-coach-os'); ?></label></li>
              <li><label><input type="checkbox"> <?php echo esc_html__('Daily Drill abschließen', 'sprecher-coach-os'); ?></label></li>
              <li><label><input type="checkbox"> <?php echo esc_html__('1 Skill reviewen', 'sprecher-coach-os'); ?></label></li>
            </ul>
          </article>
          <article class="sco-card" data-sco-quick-access>
            <div class="sco-card-header"><h3><?php echo esc_html__('Schnellzugriff', 'sprecher-coach-os'); ?></h3></div>
            <div class="sco-actions">
              <a class="sco-btn sco-btn-primary" href="#sco-dashboard-daily"><?php echo esc_html__('Jetzt starten', 'sprecher-coach-os'); ?></a>
              <a class="sco-btn" href="#sco-dashboard-library"><?php echo esc_html__('Warmups', 'sprecher-coach-os'); ?></a>
              <a class="sco-btn" href="#sco-dashboard-skilltree"><?php echo esc_html__('Skills', 'sprecher-coach-os'); ?></a>
            </div>
          </article>
          <article class="sco-card" data-sco-tip>
            <div class="sco-card-header"><h3><?php echo esc_html__('Pro Tipp', 'sprecher-coach-os'); ?></h3></div>
            <p><?php echo esc_html__('Sprich den ersten Satz bewusst langsamer als gewohnt. So setzt du direkt Kontrolle über Tempo und Präsenz.', 'sprecher-coach-os'); ?></p>
          </article>
        </div>
      </section>

      <section class="sco-tab-panel" id="tab-skilltree"><div class="sco-card" id="sco-dashboard-skilltree"><h3><?php echo esc_html__('Skilltree', 'sprecher-coach-os'); ?></h3><p class="sco-muted"><?php echo esc_html__('Öffne den Skilltree-Shortcode für Details.', 'sprecher-coach-os'); ?></p></div></section>
      <section class="sco-tab-panel" id="tab-missions"><div class="sco-card" id="sco-dashboard-missions"><h3><?php echo esc_html__('Missionen', 'sprecher-coach-os'); ?></h3><p class="sco-muted"><?php echo esc_html__('Öffne die Missionen für aktive Challenges.', 'sprecher-coach-os'); ?></p></div></section>
      <section class="sco-tab-panel" id="tab-library"><div class="sco-card" id="sco-dashboard-library"><h3><?php echo esc_html__('Bibliothek', 'sprecher-coach-os'); ?></h3><p class="sco-muted"><?php echo esc_html__('Suche Übungen und Skripte in der Bibliothek.', 'sprecher-coach-os'); ?></p></div></section>
    </main>

    <aside class="sco-side">
      <div class="sco-card sco-sticky" data-sco-sidecard>
        <h3><?php echo esc_html__('Nächster Schritt', 'sprecher-coach-os'); ?></h3>
        <p class="sco-muted"><?php echo esc_html(SCO_Utils::copy('cta', 'streak_save')); ?></p>
        <div class="sco-progress"><div style="width:0%"></div></div>
        <p class="sco-muted"><?php echo esc_html__('Streak sichern', 'sprecher-coach-os'); ?></p>
        <a class="sco-btn sco-btn-primary" href="<?php echo esc_url(sco_checkout_url()); ?>"><?php echo esc_html__('Premium-Vorteil', 'sprecher-coach-os'); ?></a>
      </div>
    </aside>
  </div>
</div>

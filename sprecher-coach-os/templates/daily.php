<?php if (!defined('ABSPATH')) { exit; } ?>
<div id="sco-root" class="sco-app" data-sco-module="daily">
  <header class="sco-topbar sco-card">
    <div>
      <p class="sco-kicker"><?php echo esc_html__('Daily Drill', 'sprecher-coach-os'); ?></p>
      <h1><?php echo esc_html__('Heute trainieren', 'sprecher-coach-os'); ?></h1>
      <p class="sco-muted"><?php echo esc_html(SCO_Utils::copy('cta', 'streak_save')); ?></p>
    </div>
    <div class="sco-pill-row">
      <span class="sco-pill"><?php echo esc_html__('Fokus', 'sprecher-coach-os'); ?></span>
      <span class="sco-pill sco-pill-success"><?php echo esc_html__('+XP Ready', 'sprecher-coach-os'); ?></span>
    </div>
  </header>

  <div class="sco-shell">
    <aside class="sco-nav sco-card">
      <h3><?php echo esc_html__('Module', 'sprecher-coach-os'); ?></h3>
      <nav class="sco-nav-list" aria-label="Daily Navigation">
        <a class="is-active" href="#sco-daily-drill"><?php echo esc_html__('Daily Drill', 'sprecher-coach-os'); ?></a>
        <a href="#sco-daily-completion"><?php echo esc_html__('Abschluss', 'sprecher-coach-os'); ?></a>
      </nav>
    </aside>

    <main class="sco-main">
      <div class="sco-tabs" role="tablist">
        <button class="sco-tab is-active" type="button" data-sco-tab="daily-main"><?php echo esc_html__('Drill', 'sprecher-coach-os'); ?></button>
        <button class="sco-tab" type="button" data-sco-tab="daily-completion"><?php echo esc_html__('Ergebnis', 'sprecher-coach-os'); ?></button>
      </div>
      <section id="daily-main" class="sco-tab-panel is-active">
        <div class="sco-card sco-hero" id="sco-daily-drill" data-sco-daily></div>
      </section>
      <section id="daily-completion" class="sco-tab-panel">
        <div class="sco-card" id="sco-daily-completion" data-sco-completion-card>
          <h3><?php echo esc_html__('Noch nicht abgeschlossen', 'sprecher-coach-os'); ?></h3>
          <p class="sco-muted"><?php echo esc_html__('Beende deinen Drill, um Score und XP zu sehen.', 'sprecher-coach-os'); ?></p>
        </div>
      </section>
    </main>
  </div>
</div>

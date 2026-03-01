<?php if (!defined('ABSPATH')) { exit; } ?>
<div id="sco-root" class="sco-app" data-upgrade-url="<?php echo esc_url(sco_checkout_url()); ?>">
  <?php if (!is_user_logged_in()) : ?>
    <section class="sco-login-required sco-card" aria-live="polite">
      <div class="sco-card-header">
        <h2><?php echo esc_html__('Bitte einloggen', 'sprecher-coach-os'); ?></h2>
      </div>
      <p><?php echo esc_html__('Der Sprecher Coach speichert deinen Fortschritt – dafür brauchst du ein Konto.', 'sprecher-coach-os'); ?></p>
      <div class="sco-actions">
        <a class="sco-btn sco-btn-primary" href="<?php echo esc_url(wp_login_url(home_url(add_query_arg([])))); ?>"><?php echo esc_html__('Einloggen', 'sprecher-coach-os'); ?></a>
        <?php if (get_option('users_can_register')) : ?>
          <a class="sco-btn" href="<?php echo esc_url(home_url('/my-account/')); ?>"><?php echo esc_html__('Konto erstellen', 'sprecher-coach-os'); ?></a>
        <?php endif; ?>
      </div>
    </section>
  <?php else : ?>
    <header class="sco-topbar sco-card">
      <div>
        <p class="sco-kicker"><?php echo esc_html__('Sprecher Coach', 'sprecher-coach-os'); ?></p>
        <h1><?php echo esc_html__('Sprecher Coach OS', 'sprecher-coach-os'); ?></h1>
        <p class="sco-muted"><?php echo esc_html(SCO_Utils::copy('cta', 'dashboard_nudge')); ?></p>
      </div>
      <div class="sco-pill-row" data-sco-status-pills>
        <span class="sco-pill" data-sco-streak><?php echo esc_html__('Streak 0', 'sprecher-coach-os'); ?></span>
        <span class="sco-pill sco-pill-neutral" data-sco-level><?php echo esc_html__('Level 1', 'sprecher-coach-os'); ?></span>
        <span class="sco-pill sco-pill-neutral" data-sco-weekly><?php echo esc_html__('Wochenziel 0/5', 'sprecher-coach-os'); ?></span>
      </div>
    </header>

    <div class="sco-shell">
      <aside class="sco-nav sco-card">
        <h3><?php echo esc_html__('Navigation', 'sprecher-coach-os'); ?></h3>
        <nav class="sco-nav-list" aria-label="Sprecher Coach Navigation">
          <button type="button" class="is-active" data-tab="today"><?php echo esc_html__('Heute', 'sprecher-coach-os'); ?></button>
          <button type="button" data-tab="daily"><?php echo esc_html__('Daily Drill', 'sprecher-coach-os'); ?></button>
          <button type="button" data-tab="skilltree"><?php echo esc_html__('Skilltree', 'sprecher-coach-os'); ?></button>
          <button type="button" data-tab="missions"><?php echo esc_html__('Missionen', 'sprecher-coach-os'); ?></button>
          <button type="button" data-tab="library"><?php echo esc_html__('Bibliothek', 'sprecher-coach-os'); ?></button>
          <button type="button" data-tab="progress"><?php echo esc_html__('Fortschritt', 'sprecher-coach-os'); ?></button>
        </nav>
      </aside>

      <main class="sco-main">
        <section class="sco-tab-panel is-active" data-panel="today">
          <article class="sco-card sco-hero" data-sco-today-card></article>
          <div class="sco-grid sco-grid-2">
            <article class="sco-card" data-sco-next-step></article>
            <article class="sco-card">
              <div class="sco-card-header"><h3><?php echo esc_html__('Dein Wochenziel', 'sprecher-coach-os'); ?></h3></div>
              <div class="sco-progress"><div data-sco-weekly-progress></div></div>
              <p class="sco-muted" data-sco-weekly-copy><?php echo esc_html__('Wochenziel 0/5', 'sprecher-coach-os'); ?></p>
            </article>
          </div>
        </section>

        <section class="sco-tab-panel" data-panel="daily">
          <article class="sco-card" data-sco-daily></article>
          <article class="sco-card" data-sco-completion-card>
            <div class="sco-card-header"><h3><?php echo esc_html__('Noch nicht abgeschlossen', 'sprecher-coach-os'); ?></h3></div>
            <p class="sco-muted"><?php echo esc_html__('Schließe deinen Drill ab, um Ergebnis und XP zu sehen.', 'sprecher-coach-os'); ?></p>
          </article>
        </section>

        <section class="sco-tab-panel" data-panel="skilltree">
          <div class="sco-grid sco-grid-2" data-sco-skilltree></div>
        </section>

        <section class="sco-tab-panel" data-panel="missions">
          <div class="sco-grid sco-grid-2" data-sco-missions></div>
        </section>

        <section class="sco-tab-panel" data-panel="library">
          <article class="sco-card">
            <div class="sco-search-wrap">
              <input type="search" class="sco-input" data-sco-library-search placeholder="<?php echo esc_attr__('Bibliothek durchsuchen …', 'sprecher-coach-os'); ?>">
              <button class="sco-btn" type="button" data-sco-library-refresh><?php echo esc_html__('Neu laden', 'sprecher-coach-os'); ?></button>
            </div>
          </article>
          <article class="sco-card" data-sco-library-notice></article>
          <div class="sco-grid sco-grid-2" data-sco-library></div>
        </section>

        <section class="sco-tab-panel" data-panel="progress">
          <article class="sco-card" data-sco-progress-panel></article>
        </section>
      </main>

      <aside class="sco-side">
        <div class="sco-card sco-sticky">
          <div class="sco-card-header"><h3><?php echo esc_html__('Nächster Schritt', 'sprecher-coach-os'); ?></h3></div>
          <p class="sco-muted" data-sco-side-next><?php echo esc_html__('Lade Dashboard …', 'sprecher-coach-os'); ?></p>
          <a class="sco-btn sco-btn-primary" href="#daily" data-sco-switch-tab="daily"><?php echo esc_html__('Jetzt starten', 'sprecher-coach-os'); ?></a>
          <hr>
          <p class="sco-muted"><?php echo esc_html__('Premium schaltet Verlauf, mehr Skills und unbegrenzte Bibliothek frei.', 'sprecher-coach-os'); ?></p>
          <a class="sco-btn sco-btn-primary" href="<?php echo esc_url(sco_checkout_url()); ?>"><?php echo esc_html(SCO_Utils::copy('cta', 'upgrade_primary')); ?></a>
        </div>
      </aside>
    </div>
  <?php endif; ?>
</div>

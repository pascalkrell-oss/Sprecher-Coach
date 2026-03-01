<?php if (!defined('ABSPATH')) { exit; } ?>
<?php if (is_user_logged_in() && current_user_can('manage_options')) : ?>
  <?php $test_plan = sanitize_key((string) get_user_meta(get_current_user_id(), 'sco_test_plan', true)); ?>
  <section class="sco-admin-testmode sco-card" data-sco-admin-test-plan data-current-plan="<?php echo esc_attr($test_plan ?: 'off'); ?>"> 
    <div>
      <strong><?php echo esc_html__('Test: Zugriff simulieren', 'sprecher-coach-os'); ?></strong>
      <p class="sco-muted"><?php echo esc_html__('Nur Testmodus (nur für dich)', 'sprecher-coach-os'); ?></p>
    </div>
    <div class="sco-segmented" role="group" aria-label="Test Plan">
      <button type="button" class="sco-btn <?php echo $test_plan === 'free' ? 'is-active' : ''; ?>" data-plan="free">Test: FREE</button>
      <button type="button" class="sco-btn <?php echo $test_plan === 'premium' ? 'is-active' : ''; ?>" data-plan="premium">Test: PREMIUM</button>
      <button type="button" class="sco-btn <?php echo $test_plan === '' ? 'is-active' : ''; ?>" data-plan="off">Aus</button>
    </div>
  </section>
<?php endif; ?>
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
        <span class="sco-pill" data-sco-streak><?php echo esc_html__('Trainingsserie 0', 'sprecher-coach-os'); ?></span>
        <span class="sco-pill sco-pill-neutral" data-sco-level><?php echo esc_html__('Level 1', 'sprecher-coach-os'); ?></span>
        <span class="sco-pill sco-pill-neutral" data-sco-weekly><?php echo esc_html__('Wochenziel 0/5', 'sprecher-coach-os'); ?></span>
      </div>
    </header>

    <div class="sco-shell">
      <aside class="sco-nav sco-card">
        <div class="sco-card-header">
          <h3><?php echo esc_html__('Navigation', 'sprecher-coach-os'); ?></h3>
        </div>
        <nav class="sco-nav-list" aria-label="Sprecher Coach Navigation">
          <button type="button" class="is-active" data-tab="today"><i class="fa-solid fa-house" aria-hidden="true"></i><span><?php echo esc_html__('Heute', 'sprecher-coach-os'); ?></span></button>
          <button type="button" data-tab="daily"><i class="fa-solid fa-dumbbell" aria-hidden="true"></i><span><?php echo esc_html__('Daily Drill', 'sprecher-coach-os'); ?></span></button>
          <button type="button" data-tab="skilltree"><i class="fa-solid fa-diagram-project" aria-hidden="true"></i><span><?php echo esc_html__('Skilltree', 'sprecher-coach-os'); ?></span></button>
          <button type="button" data-tab="missions"><i class="fa-solid fa-flag-checkered" aria-hidden="true"></i><span><?php echo esc_html__('Missionen', 'sprecher-coach-os'); ?></span></button>
          <button type="button" data-tab="library"><i class="fa-solid fa-layer-group" aria-hidden="true"></i><span><?php echo esc_html__('Bibliothek', 'sprecher-coach-os'); ?></span></button>
          <button type="button" data-tab="progress"><i class="fa-solid fa-chart-line" aria-hidden="true"></i><span><?php echo esc_html__('Fortschritt', 'sprecher-coach-os'); ?></span></button>
          <button type="button" data-tab="tools"><i class="fa-solid fa-toolbox" aria-hidden="true"></i><span><?php echo esc_html__('Tools', 'sprecher-coach-os'); ?></span></button>
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
          <article class="sco-card sco-mission-context" data-sco-mission-context hidden></article>
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
            <div class="sco-library-categories" data-sco-library-categories>
              <button class="sco-library-category is-active" type="button" data-library-category="warmup">
                <span class="sco-library-category__icon"><i class="fa-solid fa-sun" aria-hidden="true"></i></span>
                <span>
                  <strong>Warmups</strong>
                  <small>Stimme aktivieren in 2–4 Minuten</small>
                </span>
                <em>30+</em>
              </button>
              <button class="sco-library-category" type="button" data-library-category="tongue_twister">
                <span class="sco-library-category__icon"><i class="fa-solid fa-comments" aria-hidden="true"></i></span>
                <span>
                  <strong>Zungenbrecher</strong>
                  <small>Präzision, Tempo, Artikulation</small>
                </span>
                <em>24+</em>
              </button>
              <button class="sco-library-category" type="button" data-library-category="script">
                <span class="sco-library-category__icon"><i class="fa-solid fa-file-lines" aria-hidden="true"></i></span>
                <span>
                  <strong>Skripte</strong>
                  <small>Genre-Textvorlagen für Takes</small>
                </span>
                <em>50+</em>
              </button>
              <button class="sco-library-category" type="button" data-library-category="business">
                <span class="sco-library-category__icon"><i class="fa-solid fa-briefcase" aria-hidden="true"></i></span>
                <span>
                  <strong>Business</strong>
                  <small>Mails, Pitches und Kundenkommunikation</small>
                </span>
                <em>18+</em>
              </button>
              <button class="sco-library-category" type="button" data-library-category="random">
                <span class="sco-library-category__icon"><i class="fa-solid fa-dice" aria-hidden="true"></i></span>
                <span>
                  <strong>Zufällig</strong>
                  <small>Premium Mix aus allen Kategorien</small>
                </span>
                <em>Pro</em>
              </button>
            </div>
          </article>
          <article class="sco-card" data-sco-library-notice></article>
          <div class="sco-grid sco-grid-2" data-sco-library></div>
        </section>

        <section class="sco-tab-panel" data-panel="progress">
          <article class="sco-card" data-sco-progress-panel></article>
          <article class="sco-card">
            <div class="sco-card-header"><h3><?php echo esc_html__('Coach zurücksetzen', 'sprecher-coach-os'); ?></h3></div>
            <p class="sco-muted"><?php echo esc_html__('Setzt deinen Fortschritt (XP, Missionen, Wochenziel, Trainingshistorie) zurück.', 'sprecher-coach-os'); ?></p>
            <div class="sco-actions"><button type="button" class="sco-btn sco-btn-danger" data-sco-reset-coach><?php echo esc_html__('Coach zurücksetzen', 'sprecher-coach-os'); ?></button></div>
          </article>
        </section>

        <section class="sco-tab-panel" data-panel="tools">
          <article class="sco-card">
            <div class="sco-card-header"><h3><?php echo esc_html__('Tools', 'sprecher-coach-os'); ?></h3></div>
            <div class="sco-segmented" role="tablist" aria-label="Tools" data-sco-tools-tabs>
              <button type="button" class="sco-btn is-active" role="tab" aria-selected="true" data-tool-tab="demo"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i><span><?php echo esc_html__('Demo Text Generator', 'sprecher-coach-os'); ?></span></button>
              <button type="button" class="sco-btn" role="tab" aria-selected="false" data-tool-tab="teleprompter"><i class="fa-solid fa-align-center" aria-hidden="true"></i><span><?php echo esc_html__('Teleprompter', 'sprecher-coach-os'); ?></span></button>
            </div>
          </article>
          <section data-tool-panel="demo" class="sco-tool-panel is-active">
            <article class="sco-card" data-sco-tool-generator></article>
          </section>
          <section data-tool-panel="teleprompter" class="sco-tool-panel">
            <article class="sco-card" data-sco-tool-teleprompter></article>
          </section>
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

    <nav class="sco-mobile-tabs" aria-label="Mobile Tab Navigation">
      <button type="button" class="is-active" data-tab="today"><?php echo esc_html__('Heute', 'sprecher-coach-os'); ?></button>
      <button type="button" data-tab="daily"><?php echo esc_html__('Drill', 'sprecher-coach-os'); ?></button>
      <button type="button" data-tab="skilltree"><?php echo esc_html__('Skills', 'sprecher-coach-os'); ?></button>
      <button type="button" data-tab="library"><?php echo esc_html__('Library', 'sprecher-coach-os'); ?></button>
      <button type="button" data-tab="progress"><?php echo esc_html__('Stats', 'sprecher-coach-os'); ?></button>
      <button type="button" data-tab="tools"><?php echo esc_html__('Tools', 'sprecher-coach-os'); ?></button>
    </nav>

    <div class="sco-overlay" hidden></div>
    <aside class="sco-drawer" aria-hidden="true" role="dialog" aria-modal="true">
      <div class="sco-drawer__head">
        <div class="sco-drawer__title">
          <span class="sco-drawer__icon" aria-hidden="true"></span>
          <h3 data-sco-drawer-title><?php echo esc_html__('Details', 'sprecher-coach-os'); ?></h3>
        </div>
        <button class="sco-icon-btn sco-drawer__close" type="button" aria-label="Schließen"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="sco-drawer__body" data-sco-drawer-body></div>
      <div class="sco-drawer__foot" data-sco-drawer-foot></div>
    </aside>
  <?php endif; ?>
</div>

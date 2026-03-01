<?php if (!defined('ABSPATH')) { exit; } ?>
<div id="sco-root" class="sco-app" data-sco-module="library" data-locked-library="<?php echo esc_attr(SCO_Utils::copy('premium_tooltips', 'locked_library')); ?>" data-upgrade-url="<?php echo esc_url(sco_checkout_url()); ?>">
  <header class="sco-topbar sco-card">
    <div>
      <p class="sco-kicker"><?php echo esc_html__('Bibliothek', 'sprecher-coach-os'); ?></p>
      <h1><?php echo esc_html__('Drills, Warmups & Skripte', 'sprecher-coach-os'); ?></h1>
      <p class="sco-muted"><?php echo esc_html__('Filtere Inhalte und öffne Details im Drawer.', 'sprecher-coach-os'); ?></p>
    </div>
  </header>

  <div class="sco-shell">
    <aside class="sco-nav sco-card">
      <h3><?php echo esc_html__('Kategorien', 'sprecher-coach-os'); ?></h3>
      <div class="sco-chip-row" data-sco-library-filters>
        <button class="sco-pill is-active" data-filter="all" type="button"><?php echo esc_html__('Alle', 'sprecher-coach-os'); ?></button>
        <button class="sco-pill" data-filter="warmups" type="button"><?php echo esc_html__('Warmups', 'sprecher-coach-os'); ?></button>
        <button class="sco-pill" data-filter="zungenbrecher" type="button"><?php echo esc_html__('Zungenbrecher', 'sprecher-coach-os'); ?></button>
        <button class="sco-pill" data-filter="skripte" type="button"><?php echo esc_html__('Skripte', 'sprecher-coach-os'); ?></button>
        <button class="sco-pill" data-filter="business" type="button"><?php echo esc_html__('Business', 'sprecher-coach-os'); ?></button>
      </div>
    </aside>

    <main class="sco-main">
      <section class="sco-card sco-hero">
        <div class="sco-search-wrap">
          <input type="search" class="sco-input" data-sco-library-search placeholder="<?php echo esc_attr__('Inhalte durchsuchen …', 'sprecher-coach-os'); ?>">
          <button class="sco-btn" data-sco-random type="button"><?php echo esc_html__('Neu laden', 'sprecher-coach-os'); ?></button>
        </div>
      </section>
      <div class="sco-card" data-sco-library-notice></div>
      <div class="sco-grid sco-grid-2" data-sco-library></div>
    </main>
  </div>
</div>

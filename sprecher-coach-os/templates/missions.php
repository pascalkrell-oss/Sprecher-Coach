<?php if (!defined('ABSPATH')) { exit; } ?>
<div id="sco-root" class="sco-app" data-sco-module="missions" data-locked-missions="<?php echo esc_attr(SCO_Utils::copy('premium_tooltips', 'locked_missions')); ?>" data-upgrade-url="<?php echo esc_url(sco_checkout_url()); ?>">
  <header class="sco-topbar sco-card">
    <div>
      <p class="sco-kicker"><?php echo esc_html__('Missionen', 'sprecher-coach-os'); ?></p>
      <h1><?php echo esc_html__('Strukturierte Challenges', 'sprecher-coach-os'); ?></h1>
    </div>
  </header>

  <div class="sco-shell">
    <aside class="sco-nav sco-card">
      <h3><?php echo esc_html__('Navigation', 'sprecher-coach-os'); ?></h3>
      <nav class="sco-nav-list" aria-label="Missions Navigation">
        <a class="is-active" href="#sco-missions-list"><?php echo esc_html__('Missionen', 'sprecher-coach-os'); ?></a>
      </nav>
    </aside>

    <main class="sco-main">
      <section class="sco-card sco-hero"><h2><?php echo esc_html__('Setze klare Ziele und schließe Steps nacheinander ab.', 'sprecher-coach-os'); ?></h2></section>
      <div class="sco-grid sco-grid-2" id="sco-missions-list" data-sco-missions></div>
    </main>
  </div>
</div>

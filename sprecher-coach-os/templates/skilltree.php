<?php if (!defined('ABSPATH')) { exit; } ?>
<div id="sco-root" class="sco-app" data-sco-module="skilltree" data-locked-skill="<?php echo esc_attr(SCO_Utils::copy('premium_tooltips', 'locked_skill')); ?>" data-upgrade-url="<?php echo esc_url(sco_checkout_url()); ?>">
  <header class="sco-topbar sco-card">
    <div>
      <p class="sco-kicker"><?php echo esc_html__('Skilltree', 'sprecher-coach-os'); ?></p>
      <h1><?php echo esc_html__('Skills gezielt leveln', 'sprecher-coach-os'); ?></h1>
    </div>
  </header>

  <div class="sco-shell">
    <aside class="sco-nav sco-card">
      <h3><?php echo esc_html__('Module', 'sprecher-coach-os'); ?></h3>
      <nav class="sco-nav-list" aria-label="Skilltree Navigation">
        <a class="is-active" href="#sco-skill-list"><?php echo esc_html__('Skills', 'sprecher-coach-os'); ?></a>
      </nav>
    </aside>

    <main class="sco-main">
      <section class="sco-card sco-hero"><h2><?php echo esc_html__('Wähle einen Skill und trainiere fokussiert.', 'sprecher-coach-os'); ?></h2></section>
      <div class="sco-grid sco-grid-3" id="sco-skill-list" data-sco-skilltree></div>
    </main>
  </div>
</div>

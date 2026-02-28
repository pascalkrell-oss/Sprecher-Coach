<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="sco-app" data-sco-module="library" data-locked-library="<?php echo esc_attr(SCO_Utils::copy('premium_tooltips', 'locked_library')); ?>" data-free-limit="<?php echo esc_attr(SCO_Utils::copy('premium_tooltips', 'free_limit_reached')); ?>" data-upgrade-label="<?php echo esc_attr(SCO_Utils::copy('cta', 'upgrade_primary')); ?>" data-upgrade-url="<?php echo esc_url(sco_checkout_url()); ?>">
  <div class="sco-card sco-header"><h2><?php echo esc_html__('Bibliothek', 'sprecher-coach-os'); ?></h2></div>
  <div class="sco-card"><button class="sco-btn" data-sco-random><?php echo esc_html__('Zufällig laden', 'sprecher-coach-os'); ?></button></div>
  <div class="sco-card" data-sco-library-notice></div>
  <div class="sco-grid" data-sco-library></div>
</div>

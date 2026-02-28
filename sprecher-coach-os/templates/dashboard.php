<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="sco-app" data-sco-module="dashboard" data-locked-history="<?php echo esc_attr(SCO_Utils::copy('premium_tooltips', 'locked_history')); ?>" data-upgrade-label="<?php echo esc_attr(SCO_Utils::copy('cta', 'upgrade_primary')); ?>" data-upgrade-url="<?php echo esc_url(sco_checkout_url()); ?>">
  <div class="sco-card sco-header">
    <h2><?php echo esc_html__('Sprecher Coach – Heute', 'sprecher-coach-os'); ?></h2>
    <p><?php echo esc_html(SCO_Utils::copy('cta', 'dashboard_nudge')); ?></p>
    <div class="sco-badges"><span class="sco-badge" data-sco-streak>Streak 0</span><span class="sco-badge" data-sco-level>Level 1</span></div>
  </div>
  <div class="sco-grid">
    <div class="sco-card" data-sco-daily-card></div>
    <div class="sco-card sco-locked" title="<?php echo esc_attr(SCO_Utils::copy('premium_tooltips', 'locked_history')); ?>"><div class="sco-lock">🔒 <?php echo esc_html(SCO_Utils::copy('premium_tooltips', 'locked_history')); ?></div></div>
  </div>
</div>

<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="sco-app" data-sco-module="daily">
  <div class="sco-card sco-header">
    <h2><?php echo esc_html__('Daily Drill', 'sprecher-coach-os'); ?></h2>
    <p><?php echo esc_html(SCO_Utils::copy('cta', 'streak_save')); ?></p>
  </div>
  <div class="sco-card" data-sco-daily></div>
</div>

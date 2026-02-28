<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="sco-app" data-sco-module="missions" data-locked-missions="<?php echo esc_attr(SCO_Utils::copy('premium_tooltips', 'locked_missions')); ?>">
  <div class="sco-card sco-header"><h2><?php echo esc_html__('Missions', 'sprecher-coach-os'); ?></h2></div>
  <div class="sco-grid" data-sco-missions></div>
</div>

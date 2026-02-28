<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="sco-app" data-sco-module="skilltree" data-locked-skill="<?php echo esc_attr(SCO_Utils::copy('premium_tooltips', 'locked_skill')); ?>">
  <div class="sco-card sco-header"><h2><?php echo esc_html__('Skilltree', 'sprecher-coach-os'); ?></h2></div>
  <div class="sco-grid" data-sco-skilltree></div>
</div>

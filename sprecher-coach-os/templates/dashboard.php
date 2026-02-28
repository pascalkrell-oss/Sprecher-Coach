<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="sco-app" data-sco-module="dashboard">
  <div class="sco-card sco-header">
    <h2><?php echo esc_html__('Sprecher Coach – Heute', 'sprecher-coach-os'); ?></h2>
    <p><?php echo esc_html__('Dein täglicher Fokus für starke Sprecher-Routine.', 'sprecher-coach-os'); ?></p>
    <div class="sco-badges"><span class="sco-badge" data-sco-streak>Streak 0</span><span class="sco-badge" data-sco-level>Level 1</span></div>
  </div>
  <div class="sco-grid">
    <div class="sco-card" data-sco-daily-card></div>
    <div class="sco-card sco-locked"><div class="sco-lock">🔒 Premium: Verlauf & Charts</div></div>
  </div>
</div>

<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="sco-modal-overlay" id="scoCoachOverlay" hidden>
  <div class="sco-modal" role="dialog" aria-modal="true" aria-label="Sprecher Coach">
    <div class="sco-modal__head">
      <div class="sco-modal__title">
        <span class="sco-modal__brand"><?php echo esc_html__('Sprecher Coach', 'sprecher-coach-os'); ?></span>
        <span class="sco-modal__hint"><?php echo esc_html__('Web-App Modus', 'sprecher-coach-os'); ?></span>
      </div>
      <button class="sco-icon-btn sco-modal__close" type="button" aria-label="Schließen">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <div class="sco-modal__body" id="scoCoachAppMount">
      <div class="sco-modal__loading"><?php echo esc_html__('Lade Coach…', 'sprecher-coach-os'); ?></div>
    </div>
  </div>
</div>

<?php if (!defined('ABSPATH')) { exit; } ?>
<?php $test_plan = sanitize_key((string) get_user_meta(get_current_user_id(), 'sco_test_plan', true)); ?>
<div class="sco-modal-overlay" id="scoCoachOverlay" hidden>
  <div class="sco-modal" role="dialog" aria-modal="true" aria-label="Sprecher Coach">
    <div class="sco-modal__head">
      <div class="sco-modal__title">
        <span class="sco-modal__brand"><?php echo esc_html__('Sprecher Coach OS', 'sprecher-coach-os'); ?></span>
        <span class="sco-modal__hint"><?php echo esc_html__('Web-App Modus', 'sprecher-coach-os'); ?></span>
      </div>
      <?php if (is_user_logged_in() && current_user_can('manage_options')) : ?>
        <div class="sco-testbar" aria-label="Testmodus" data-sco-admin-test-plan data-current-plan="<?php echo esc_attr($test_plan ?: 'off'); ?>">
          <div class="sco-testbar__title"><?php echo esc_html__('Test: Zugriff simulieren', 'sprecher-coach-os'); ?></div>
          <div class="sco-testbar__hint"><?php echo esc_html__('Nur Testmodus (nur für dich)', 'sprecher-coach-os'); ?></div>
          <div class="sco-testbar__seg" role="group" aria-label="Test Plan">
            <button type="button" class="sco-btn <?php echo $test_plan === 'free' ? 'is-active' : ''; ?>" data-plan="free">Test: FREE</button>
            <button type="button" class="sco-btn <?php echo $test_plan === 'premium' ? 'is-active' : ''; ?>" data-plan="premium">Test: PREMIUM</button>
            <button type="button" class="sco-btn <?php echo $test_plan === '' ? 'is-active' : ''; ?>" data-plan="off">Aus</button>
          </div>
        </div>
      <?php endif; ?>
      <button class="sco-icon-btn sco-modal__close" type="button" aria-label="Schließen">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <div class="sco-modal__body">
      <div class="sco-modal__canvas" id="scoCoachAppMount">
        <div class="sco-modal__loading"><?php echo esc_html__('Lade Coach…', 'sprecher-coach-os'); ?></div>
      </div>
    </div>
  </div>

  <div class="sco-drawer-overlay" hidden></div>
  <aside class="sco-drawer" aria-hidden="true" role="dialog" aria-modal="true" hidden>
    <div class="sco-drawer__head">
      <div class="sco-drawer__title">
        <span class="sco-drawer__icon" aria-hidden="true"></span>
        <h3 data-sco-drawer-title><?php echo esc_html__('Details', 'sprecher-coach-os'); ?></h3>
      </div>
      <button class="sco-icon-btn sco-drawer__close" type="button" aria-label="Schließen"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="sco-drawer__body" data-sco-drawer-body></div>
    <div class="sco-drawer__foot" data-sco-drawer-foot></div>
  </aside>
</div>

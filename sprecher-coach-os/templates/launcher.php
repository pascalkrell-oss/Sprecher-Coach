<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="sco-launcher" data-sco-launcher data-auto-open="<?php echo esc_attr((string) $auto_open); ?>">
  <button type="button" class="sco-btn <?php echo esc_attr($button_class); ?> sco-launcher__btn" data-sco-launcher-btn>
    <i class="fa-solid fa-up-right-and-down-left-from-center" aria-hidden="true"></i>
    <span><?php echo esc_html($label); ?></span>
  </button>
  <?php include SCO_PLUGIN_PATH . 'templates/overlay.php'; ?>
</div>

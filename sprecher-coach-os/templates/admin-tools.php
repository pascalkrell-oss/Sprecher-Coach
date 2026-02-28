<?php if (!defined('ABSPATH')) { exit; } ?>
<?php if (!empty($message)) : ?><div class="updated"><p><?php echo esc_html($message); ?></p></div><?php endif; ?>
<form method="post" class="sco-admin-form">
    <?php wp_nonce_field('sco_seed_import'); ?>
    <input type="hidden" name="sco_action" value="seed_import" />
    <label><input type="checkbox" name="overwrite_seed" value="1" /> Vorher vorhandene Seed-Inhalte überschreiben</label>
    <button class="button button-primary">Seed erneut importieren</button>
</form>

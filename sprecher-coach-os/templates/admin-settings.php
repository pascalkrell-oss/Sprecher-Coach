<?php if (!defined('ABSPATH')) { exit; } ?>
<form method="post" class="sco-admin-form">
    <?php wp_nonce_field('sco_save_settings'); ?>
    <input type="hidden" name="sco_action" value="save_settings" />
    <p><label>Akzentfarbe <input type="color" name="accent_color" value="<?php echo esc_attr($settings['accent_color']); ?>" /></label></p>
    <p><label>Wochenziel <input type="number" name="weekly_goal" value="<?php echo esc_attr($settings['weekly_goal']); ?>" min="1" /></label></p>
    <p><label>Free Library Limit/Tag <input type="number" name="free_library_limit" value="<?php echo esc_attr($settings['free_library_limit']); ?>" min="1" /></label></p>
    <p><label>Checkout URL <input type="url" name="checkout_url" value="<?php echo esc_attr($settings['checkout_url']); ?>" class="regular-text" /></label></p>
    <p><label>Premium User IDs (CSV) <input type="text" name="premium_user_ids" value="<?php echo esc_attr(implode(',', (array) $settings['premium_user_ids'])); ?>" class="regular-text" /></label></p>
    <p>Free Skills:<br>
        <?php foreach (['werbung', 'elearning', 'imagefilm', 'erklaervideo', 'telefon', 'hoerbuch', 'doku'] as $skill) : ?>
            <label><input type="checkbox" name="free_skills_enabled[]" value="<?php echo esc_attr($skill); ?>" <?php checked(in_array($skill, (array) $settings['free_skills_enabled'], true)); ?> /> <?php echo esc_html($skill); ?></label><br>
        <?php endforeach; ?>
    </p>
    <button class="button button-primary">Speichern</button>
</form>

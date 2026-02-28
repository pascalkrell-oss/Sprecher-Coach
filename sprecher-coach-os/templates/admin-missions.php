<?php if (!defined('ABSPATH')) { exit; } ?>
<?php if (!empty($message)) : ?><div class="updated"><p><?php echo esc_html($message); ?></p></div><?php endif; ?>
<?php if (!empty($error)) : ?><div class="error"><p><?php echo esc_html($error); ?></p></div><?php endif; ?>
<?php
$steps_json = '[]';
if (!empty($edit_steps)) {
    $tmp = [];
    foreach ($edit_steps as $step) {
        $tmp[] = [
            'day' => (int) $step['step_order'],
            'title' => (string) $step['title'],
            'checklist' => json_decode((string) $step['checklist'], true),
        ];
    }
    $steps_json = wp_json_encode($tmp, JSON_PRETTY_PRINT);
}
?>
<form method="post" class="sco-admin-form">
    <?php wp_nonce_field('sco_save_mission'); ?>
    <input type="hidden" name="sco_action" value="save_mission" />
    <input type="hidden" name="mission_id" value="<?php echo esc_attr($edit_mission['id'] ?? 0); ?>" />
    <input name="title" placeholder="Mission Titel" required value="<?php echo esc_attr($edit_mission['title'] ?? ''); ?>" />
    <textarea name="short_description" placeholder="Kurzbeschreibung"><?php echo esc_textarea($edit_mission['description'] ?? ''); ?></textarea>
    <input name="skill_key" placeholder="skill_key (Info)" value="" />
    <input type="number" name="duration_days" min="1" value="<?php echo esc_attr($edit_mission['duration_days'] ?? 7); ?>" />
    <label><input type="checkbox" name="is_premium" value="1" <?php checked(!empty($edit_mission['is_premium'])); ?> /> Premium</label>
    <textarea name="steps_json" rows="12" placeholder='[{"day":1,"title":"...","checklist":["..."]}]'><?php echo esc_textarea($steps_json); ?></textarea>
    <button class="button button-primary">Mission speichern</button>
</form>
<table class="widefat"><thead><tr><th>ID</th><th>Titel</th><th>Dauer</th><th>Premium</th><th></th></tr></thead><tbody>
<?php foreach ($missions as $mission) : ?>
<tr><td><?php echo esc_html($mission['id']); ?></td><td><?php echo esc_html($mission['title']); ?></td><td><?php echo esc_html($mission['duration_days']); ?> Tage</td><td><?php echo esc_html($mission['is_premium'] ? 'Ja' : 'Nein'); ?></td><td><a href="<?php echo esc_url(add_query_arg(['page' => 'sprecher-coach-os-missions', 'edit' => (int) $mission['id']], admin_url('admin.php'))); ?>">Bearbeiten</a></td></tr>
<?php endforeach; ?>
</tbody></table>

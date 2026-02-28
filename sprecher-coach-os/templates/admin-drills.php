<?php if (!defined('ABSPATH')) { exit; } ?>
<?php if (!empty($message)) : ?><div class="updated"><p><?php echo esc_html($message); ?></p></div><?php endif; ?>
<?php if (!empty($error)) : ?><div class="error"><p><?php echo esc_html($error); ?></p></div><?php endif; ?>
<form method="post" class="sco-admin-form">
    <?php wp_nonce_field('sco_save_drill'); ?>
    <input type="hidden" name="sco_action" value="save_drill" />
    <input type="hidden" name="drill_id" value="<?php echo esc_attr($edit_drill['id'] ?? 0); ?>" />
    <input name="title" placeholder="Titel" required value="<?php echo esc_attr($edit_drill['title'] ?? ''); ?>" />
    <textarea name="description" placeholder="Beschreibung"><?php echo esc_textarea($edit_drill['description'] ?? ''); ?></textarea>
    <input name="skill_key" placeholder="Skill-Key" required value="<?php echo esc_attr($edit_drill['skill_key'] ?? ''); ?>" />
    <input name="category_key" placeholder="Kategorie" required value="<?php echo esc_attr($edit_drill['category_key'] ?? ''); ?>" />
    <input type="number" name="duration_min" value="<?php echo esc_attr($edit_drill['duration_min'] ?? 7); ?>" min="1" />
    <input type="number" name="difficulty" value="<?php echo esc_attr($edit_drill['difficulty'] ?? 1); ?>" min="1" max="3" />
    <input type="number" name="xp_reward" value="<?php echo esc_attr($edit_drill['xp_reward'] ?? 15); ?>" min="1" />
    <textarea name="self_check_questions" rows="8" placeholder='[{"type":"scale_1_5","label":"..."}]'><?php echo esc_textarea($edit_drill['self_check_questions'] ?? '[]'); ?></textarea>
    <label><input type="checkbox" name="is_premium" value="1" <?php checked(!empty($edit_drill['is_premium'])); ?> /> Premium</label>
    <button class="button button-primary">Drill speichern</button>
</form>
<table class="widefat"><thead><tr><th>ID</th><th>Titel</th><th>Skill</th><th>Kategorie</th><th>Dauer</th><th>Difficulty</th><th>XP</th><th></th></tr></thead><tbody>
<?php foreach ($drills as $drill) : ?>
<tr>
<td><?php echo esc_html($drill['id']); ?></td>
<td><?php echo esc_html($drill['title']); ?></td>
<td><?php echo esc_html($drill['skill_key']); ?></td>
<td><?php echo esc_html($drill['category_key']); ?></td>
<td><?php echo esc_html($drill['duration_min']); ?></td>
<td><?php echo esc_html($drill['difficulty']); ?></td>
<td><?php echo esc_html($drill['xp_reward']); ?></td>
<td><a href="<?php echo esc_url(add_query_arg(['page' => 'sprecher-coach-os-drills', 'edit' => (int) $drill['id']], admin_url('admin.php'))); ?>">Bearbeiten</a></td>
</tr>
<?php endforeach; ?>
</tbody></table>

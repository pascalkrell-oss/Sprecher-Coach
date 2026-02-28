<?php if (!defined('ABSPATH')) { exit; } ?>
<?php if (!empty($message)) : ?><div class="updated"><p><?php echo esc_html($message); ?></p></div><?php endif; ?>
<form method="get" class="sco-admin-form">
    <input type="hidden" name="page" value="sprecher-coach-os-library" />
    <input name="filter_type" placeholder="Filter type" value="<?php echo esc_attr($filter_type); ?>" />
    <input name="filter_skill" placeholder="Filter skill" value="<?php echo esc_attr($filter_skill); ?>" />
    <button class="button">Filtern</button>
</form>
<form method="post" class="sco-admin-form">
    <?php wp_nonce_field('sco_save_library_item'); ?>
    <input type="hidden" name="sco_action" value="save_library_item" />
    <input type="hidden" name="item_id" value="<?php echo esc_attr($edit_item['id'] ?? 0); ?>" />
    <input name="category_key" placeholder="type (warmup/script/...)" required value="<?php echo esc_attr($edit_item['category_key'] ?? ''); ?>" />
    <input name="skill_key" placeholder="skill_key" required value="<?php echo esc_attr($edit_item['skill_key'] ?? 'all'); ?>" />
    <input type="number" name="difficulty" min="1" max="3" value="<?php echo esc_attr($edit_item['difficulty'] ?? 1); ?>" />
    <input type="number" name="duration_min" min="1" value="<?php echo esc_attr($edit_item['duration_min'] ?? 3); ?>" />
    <input name="title" placeholder="Titel" required value="<?php echo esc_attr($edit_item['title'] ?? ''); ?>" />
    <textarea name="content" rows="6" placeholder="Content"><?php echo esc_textarea($edit_item['content'] ?? ''); ?></textarea>
    <label><input type="checkbox" name="is_premium" value="1" <?php checked(!empty($edit_item['is_premium'])); ?> /> Premium</label>
    <button class="button button-primary">Eintrag speichern</button>
</form>
<table class="widefat"><thead><tr><th>ID</th><th>Titel</th><th>type</th><th>skill</th><th>difficulty</th><th>Dauer</th><th></th></tr></thead><tbody>
<?php foreach ($items as $item) : ?>
<tr><td><?php echo esc_html($item['id']); ?></td><td><?php echo esc_html($item['title']); ?></td><td><?php echo esc_html($item['category_key']); ?></td><td><?php echo esc_html($item['skill_key']); ?></td><td><?php echo esc_html($item['difficulty']); ?></td><td><?php echo esc_html($item['duration_min']); ?></td><td><a href="<?php echo esc_url(add_query_arg(['page' => 'sprecher-coach-os-library', 'edit' => (int) $item['id']], admin_url('admin.php'))); ?>">Bearbeiten</a></td></tr>
<?php endforeach; ?>
</tbody></table>

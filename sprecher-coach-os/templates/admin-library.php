<?php if (!defined('ABSPATH')) { exit; } ?>
<table class="widefat"><thead><tr><th>ID</th><th>Titel</th><th>Kategorie</th><th>Skill</th><th>Premium</th></tr></thead><tbody>
<?php foreach ($items as $item) : ?>
<tr><td><?php echo esc_html($item['id']); ?></td><td><?php echo esc_html($item['title']); ?></td><td><?php echo esc_html($item['category_key']); ?></td><td><?php echo esc_html($item['skill_key']); ?></td><td><?php echo esc_html($item['is_premium'] ? 'Ja' : 'Nein'); ?></td></tr>
<?php endforeach; ?>
</tbody></table>

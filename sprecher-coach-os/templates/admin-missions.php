<?php if (!defined('ABSPATH')) { exit; } ?>
<table class="widefat"><thead><tr><th>ID</th><th>Titel</th><th>Dauer</th><th>Bonus</th><th>Premium</th></tr></thead><tbody>
<?php foreach ($missions as $mission) : ?>
<tr><td><?php echo esc_html($mission['id']); ?></td><td><?php echo esc_html($mission['title']); ?></td><td><?php echo esc_html($mission['duration_days']); ?> Tage</td><td><?php echo esc_html($mission['is_bonus'] ? 'Ja' : 'Nein'); ?></td><td><?php echo esc_html($mission['is_premium'] ? 'Ja' : 'Nein'); ?></td></tr>
<?php endforeach; ?>
</tbody></table>

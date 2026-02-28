<?php if (!defined('ABSPATH')) { exit; } ?>
<form method="post" class="sco-admin-form">
    <?php wp_nonce_field('sco_add_drill'); ?>
    <input type="hidden" name="sco_action" value="add_drill" />
    <input name="title" placeholder="Titel" required />
    <input name="skill_key" placeholder="Skill-Key" required />
    <input name="category_key" placeholder="Kategorie" required />
    <textarea name="description" placeholder="Beschreibung"></textarea>
    <input type="number" name="duration_min" value="7" min="1" />
    <input type="number" name="difficulty" value="1" min="1" max="3" />
    <input type="number" name="xp_reward" value="15" min="1" />
    <input name="question_1" placeholder="Self-Check Frage 1" />
    <input name="question_2" placeholder="Self-Check Frage 2" />
    <label><input type="checkbox" name="is_premium" value="1" /> Premium</label>
    <button class="button button-primary">Drill speichern</button>
</form>
<table class="widefat"><thead><tr><th>ID</th><th>Titel</th><th>Skill</th><th>Kategorie</th><th>XP</th></tr></thead><tbody>
<?php foreach ($drills as $drill) : ?>
<tr><td><?php echo esc_html($drill['id']); ?></td><td><?php echo esc_html($drill['title']); ?></td><td><?php echo esc_html($drill['skill_key']); ?></td><td><?php echo esc_html($drill['category_key']); ?></td><td><?php echo esc_html($drill['xp_reward']); ?></td></tr>
<?php endforeach; ?>
</tbody></table>

<?php
/**
 * Floating quick actions menu shared across admin tabs.
 *
 * @var array<int,array<string,mixed>> $quick_actions
 * @var array<string,mixed>            $quick_actions_settings
 */

if (defined('TEJLG_QUICK_ACTIONS_RENDERED')) {
    return;
}

define('TEJLG_QUICK_ACTIONS_RENDERED', true);

if (!isset($quick_actions) || !is_array($quick_actions)) {
    return;
}

$display_mode = isset($quick_actions_settings['display_mode'])
    ? sanitize_key((string) $quick_actions_settings['display_mode'])
    : 'floating';

if ('toolbar' === $display_mode) {
    return;
}

$actions = array_values(array_filter($quick_actions, static function ($action) {
    if (!is_array($action)) {
        return false;
    }

    $label = isset($action['label']) ? (string) $action['label'] : '';

    if ('' === trim($label)) {
        return false;
    }

    if (isset($action['type']) && 'button' === $action['type']) {
        return true;
    }

    $url = isset($action['url']) ? (string) $action['url'] : '';

    return '' !== trim($url);
}));

if (empty($actions)) {
    return;
}

$action_count = count($actions);
$menu_id = 'tejlg-quick-actions-menu';
$panel_label = __('Actions rapides', 'theme-export-jlg');
$current_tab = isset($quick_actions_settings['current_tab'])
    ? (string) $quick_actions_settings['current_tab']
    : '';

$angle_step = ($action_count > 1) ? 180 / ($action_count - 1) : 0;
$angle_start = -90;

$container_styles = sprintf('--tejlg-quick-actions-count:%d;', (int) $action_count);
?>
<div
    class="tejlg-quick-actions"
    data-quick-actions
    data-state="closed"
    data-dismissed="false"
    data-active-tab="<?php echo esc_attr($current_tab); ?>"
    style="<?php echo esc_attr($container_styles); ?>"
>
    <button
        type="button"
        class="tejlg-quick-actions__toggle"
        data-quick-actions-toggle
        aria-expanded="false"
        aria-controls="<?php echo esc_attr($menu_id); ?>"
    >
        <span class="tejlg-quick-actions__toggle-visual" aria-hidden="true"></span>
        <span class="tejlg-quick-actions__toggle-label"><?php echo esc_html($panel_label); ?></span>
    </button>
    <div
        id="<?php echo esc_attr($menu_id); ?>"
        class="tejlg-quick-actions__panel"
        data-quick-actions-menu
        role="region"
        aria-label="<?php echo esc_attr($panel_label); ?>"
        hidden
    >
        <ul class="tejlg-quick-actions__list" role="list">
            <?php foreach ($actions as $index => $action) :
                $angle = $angle_start + ($angle_step * $index);
                $item_style = sprintf('--item-angle: %.2fdeg; --quick-actions-index: %d;', (float) $angle, (int) $index);
                $action_id = isset($action['id']) ? sanitize_html_class((string) $action['id']) : 'quick-action-' . $index;
                $type = isset($action['type']) ? (string) $action['type'] : 'link';
                $type = in_array($type, ['link', 'button'], true) ? $type : 'link';
                $label = trim((string) $action['label']);
                $aria_label = isset($action['aria_label']) ? (string) $action['aria_label'] : '';
                $description = isset($action['description']) ? (string) $action['description'] : '';
                $rel = isset($action['rel']) ? (string) $action['rel'] : '';
                $target = isset($action['target']) ? (string) $action['target'] : '';
                $url = isset($action['url']) ? (string) $action['url'] : '';
                $extra_attributes = '';

                if (!empty($action['attributes']) && is_array($action['attributes'])) {
                    foreach ($action['attributes'] as $attr_name => $attr_value) {
                        $attr_name = sanitize_key((string) $attr_name);

                        if ('' === $attr_name) {
                            continue;
                        }

                        if (is_bool($attr_value)) {
                            if ($attr_value) {
                                $extra_attributes .= ' ' . esc_attr($attr_name);
                            }

                            continue;
                        }

                        if (is_array($attr_value)) {
                            continue;
                        }

                        $extra_attributes .= sprintf(' %s="%s"', esc_attr($attr_name), esc_attr((string) $attr_value));
                    }
                }

                $common_attributes = sprintf(
                    ' class="tejlg-quick-actions__link" data-quick-actions-link %s%s%s%s',
                    '' !== $aria_label ? ' aria-label="' . esc_attr($aria_label) . '"' : '',
                    '' !== $target ? ' target="' . esc_attr($target) . '"' : '',
                    '' !== $rel ? ' rel="' . esc_attr($rel) . '"' : '',
                    $extra_attributes
                );
            ?>
                <li
                    class="tejlg-quick-actions__item"
                    style="<?php echo esc_attr($item_style); ?>"
                    data-quick-actions-item
                    data-quick-actions-item-id="<?php echo esc_attr($action_id); ?>"
                >
                    <?php if ('button' === $type) : ?>
                        <button type="button"<?php echo $common_attributes; ?>>
                            <span class="tejlg-quick-actions__label"><?php echo esc_html($label); ?></span>
                            <?php if ('' !== $description) : ?>
                                <span class="tejlg-quick-actions__description"><?php echo esc_html($description); ?></span>
                            <?php endif; ?>
                        </button>
                    <?php else : ?>
                        <a href="<?php echo esc_url($url); ?>"<?php echo $common_attributes; ?>>
                            <span class="tejlg-quick-actions__label"><?php echo esc_html($label); ?></span>
                            <?php if ('' !== $description) : ?>
                                <span class="tejlg-quick-actions__description"><?php echo esc_html($description); ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="tejlg-quick-actions__dismiss" data-quick-actions-dismiss>
            <span class="tejlg-quick-actions__dismiss-icon" aria-hidden="true">&times;</span>
            <span class="tejlg-quick-actions__dismiss-label"><?php esc_html_e('Masquer ce menu', 'theme-export-jlg'); ?></span>
        </button>
    </div>
    <button type="button" class="tejlg-quick-actions__restore" data-quick-actions-restore hidden>
        <?php esc_html_e('Afficher les actions rapides', 'theme-export-jlg'); ?>
    </button>
</div>

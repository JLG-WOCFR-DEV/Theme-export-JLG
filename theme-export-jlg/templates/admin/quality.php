<?php
/**
 * Quality benchmarking page comparing the plugin with professional suites.
 *
 * @var string                             $quality_page_title
 * @var string                             $quality_intro
 * @var array<int,array<string,mixed>>     $quality_sections
 * @var array<int,string>                  $quality_summary_lines
 */
?>
<div id="tejlg-section-quality" class="tejlg-section-anchor" tabindex="-1"></div>

<section class="tejlg-dashboard tejlg-dashboard--quality" aria-labelledby="tejlg-quality-page-title">
    <header class="tejlg-dashboard__header">
        <h2 id="tejlg-quality-page-title"><?php echo esc_html($quality_page_title); ?></h2>
        <p class="tejlg-dashboard__intro"><?php echo esc_html($quality_intro); ?></p>
        <?php if (!empty($quality_summary_lines)) : ?>
            <?php foreach ($quality_summary_lines as $summary_line) : ?>
                <p class="tejlg-dashboard__meta"><?php echo esc_html($summary_line); ?></p>
            <?php endforeach; ?>
        <?php endif; ?>
    </header>
    <div class="tejlg-dashboard__grid">
        <div
            class="tejlg-dashboard__card tejlg-dashboard__card--benchmark components-card is-elevated"
            role="region"
            aria-labelledby="tejlg-quality-benchmark-title"
        >
            <div class="components-card__body tejlg-quality-benchmark">
                <div class="tejlg-quality-benchmark__header">
                    <h3 id="tejlg-quality-benchmark-title" class="tejlg-quality-benchmark__title">
                        <?php esc_html_e('Comparaison avec les extensions pro', 'theme-export-jlg'); ?>
                    </h3>
                    <p class="tejlg-quality-benchmark__intro">
                        <?php esc_html_e('Suivez les forces actuelles du plugin et les axes inspirés des suites professionnelles (UI/UX, accessibilité, fiabilité, visuel).', 'theme-export-jlg'); ?>
                    </p>
                </div>
                <div class="tejlg-quality-benchmark__grid">
                    <?php foreach ($quality_sections as $section) : ?>
                        <?php
                        $section_id = isset($section['id']) ? 'tejlg-quality-benchmark-' . sanitize_key((string) $section['id']) : '';
                        $score = isset($section['score']) ? (int) $section['score'] : 0;
                        $score_label = isset($section['score_label']) ? (string) $section['score_label'] : '';
                        $badge_class = isset($section['badge_class']) ? (string) $section['badge_class'] : '';
                        $badge_label = isset($section['badge_label']) ? (string) $section['badge_label'] : '';
                        ?>
                        <section class="tejlg-quality-benchmark__item" <?php echo '' !== $section_id ? 'aria-labelledby="' . esc_attr($section_id) . '"' : ''; ?>>
                            <header class="tejlg-quality-benchmark__item-header">
                                <h3 id="<?php echo esc_attr($section_id); ?>" class="tejlg-quality-benchmark__title">
                                    <?php echo esc_html(isset($section['label']) ? (string) $section['label'] : ''); ?>
                                </h3>
                                <?php if ('' !== $badge_label) : ?>
                                    <span class="tejlg-quality-benchmark__badge <?php echo esc_attr($badge_class); ?>">
                                        <?php echo esc_html($badge_label); ?>
                                    </span>
                                <?php endif; ?>
                            </header>
                            <div class="tejlg-quality-benchmark__score" role="img" aria-label="<?php echo esc_attr($score_label); ?>">
                                <div
                                    class="tejlg-quality-benchmark__meter"
                                    style="--tejlg-quality-progress: <?php echo esc_attr($score); ?>%;"
                                ></div>
                                <div class="tejlg-quality-benchmark__score-value">
                                    <span class="tejlg-quality-benchmark__score-number"><?php echo esc_html($score); ?></span>
                                    <span class="tejlg-quality-benchmark__score-unit">/100</span>
                                </div>
                            </div>
                            <?php if (!empty($section['summary'])) : ?>
                                <p class="tejlg-quality-benchmark__summary"><?php echo esc_html((string) $section['summary']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($section['context'])) : ?>
                                <p class="tejlg-quality-benchmark__context"><?php echo esc_html((string) $section['context']); ?></p>
                            <?php endif; ?>
                            <div class="tejlg-quality-benchmark__lists">
                                <?php if (!empty($section['strengths']) && is_array($section['strengths'])) : ?>
                                    <div class="tejlg-quality-benchmark__list">
                                        <h4 class="tejlg-quality-benchmark__list-title"><?php esc_html_e('Atouts actuels', 'theme-export-jlg'); ?></h4>
                                        <ul class="tejlg-quality-benchmark__bullets">
                                            <?php foreach ($section['strengths'] as $strength) : ?>
                                                <li><?php echo esc_html((string) $strength); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($section['roadmap']) && is_array($section['roadmap'])) : ?>
                                    <div class="tejlg-quality-benchmark__list">
                                        <h4 class="tejlg-quality-benchmark__list-title"><?php esc_html_e('Pistes pro', 'theme-export-jlg'); ?></h4>
                                        <ul class="tejlg-quality-benchmark__bullets">
                                            <?php foreach ($section['roadmap'] as $roadmap_item) : ?>
                                                <li><?php echo esc_html((string) $roadmap_item); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/quick-actions.php'; ?>

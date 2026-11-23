<?php
/**
 * ALL NEWS - Content File (controller: tools/news.php). Variables: $news, $siteTitle.
 */
?>
<div class="container my-4" style="max-width: 760px;">
  <h1 class="h3 mb-3"><i class="fa fa-bullhorn me-2 text-muted"></i><?= t('News') ?></h1>
<?php if (!$news): ?>
  <p class="text-muted"><?= t('No news yet.') ?></p>
<?php else: ?>
  <ul class="home-panel-news home-panel-news-page">
    <?php foreach ($news as $n): ?>
    <li><time datetime="<?= htmlspecialchars($n['date']) ?>"><?= htmlspecialchars(date('j M Y', strtotime($n['date']))) ?></time> <span><?= moop_news_html($n['text']) ?></span></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
  <p class="small text-muted mt-3"><?= t_markup('Something you would like to see here? %s.', '<a href="' . htmlspecialchars(moop_url('tools/feedback.php')) . '">' . t('Send us a message') . '</a>') ?></p>
</div>

<?php
require_once __DIR__ . '/inc/theme.php';

$settings = meyar_load_settings();
$data = meyar_build_prices();
$items = array_values(array_filter($data['items'], function ($item) {
    return empty($item['hidden']);
}));
$tickerIds = ['sekee', 'sekeb', 'nim', 'rob', 'gerami', 'geram18', 'silver999', 'usd', 'eur', 'ons'];
$ticker = array_values(array_filter($items, function ($item) use ($tickerIds) {
    return in_array($item['id'], $tickerIds, true);
}));

$galleryDir = __DIR__ . '/assets/img/gallery';
$galleryUrl = 'assets/img/gallery';
$galleryImages = [];
if (is_dir($galleryDir)) {
    foreach (glob($galleryDir . '/*') ?: [] as $imagePath) {
        $imageInfo = @getimagesize($imagePath);
        if (!$imageInfo) continue;
        $galleryImages[] = [
            'url' => $galleryUrl . '/' . rawurlencode(basename($imagePath)),
            'name' => basename($imagePath),
            'mtime' => (int)@filemtime($imagePath),
        ];
    }
    usort($galleryImages, function ($a, $b) {
        return strnatcasecmp($a['name'], $b['name']);
    });
}

$galleryCanonical = meyar_public_url_for('gallery.php');
$gallerySchema = [
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => 'آرشیو تصاویر | سکه و جواهر معیار',
    'description' => 'آرشیو تصاویر سکه و جواهر معیار.',
    'url' => $galleryCanonical,
    'inLanguage' => 'fa-IR',
];
$galleryBreadcrumbSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'صفحه اصلی', 'item' => meyar_public_url_for('')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'آرشیو تصاویر', 'item' => $galleryCanonical],
    ],
];

meyar_theme_head(
    'آرشیو تصاویر | سکه و جواهر معیار',
    'آرشیو تصاویر سکه و جواهر معیار.',
    $galleryCanonical,
    meyar_jsonld($gallerySchema) . meyar_jsonld($galleryBreadcrumbSchema)
);
meyar_theme_topbar($settings, $ticker);
?>
<main class="container gallery-page" id="gallery">
  <section class="gallery-list" aria-labelledby="galleryListTitle">
    <header class="gallery-list-head"><h1 id="galleryListTitle">آرشیو تصاویر</h1></header>
    <?php if ($galleryImages): ?>
      <div class="gallery-grid">
        <?php foreach ($galleryImages as $image): ?>
          <figure class="gallery-item">
            <button class="gallery-item-button" type="button" data-gallery-image="<?= meyar_h($image['url']) ?>" aria-label="نمایش بزرگ تصویر">
              <img src="<?= meyar_h($image['url']) ?>" alt="تصویر گالری معیار" loading="lazy">
              <span class="gallery-item-overlay"><i class="hgi-stroke hgi-zoom-in-area" aria-hidden="true"></i><span>مشاهده تصویر</span></span>
            </button>
          </figure>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="gallery-empty"><i class="hgi-stroke hgi-image-01" aria-hidden="true"></i><h3>هنوز تصویری در گالری نیست</h3><p>با انتخاب فایل از بخش بالا، اولین تصویر را اضافه کنید.</p></div>
    <?php endif; ?>
  </section>
</main>
<div class="gallery-lightbox" id="galleryLightbox" hidden>
  <div class="gallery-lightbox-backdrop" data-gallery-close></div>
  <div class="gallery-lightbox-dialog" role="dialog" aria-modal="true" aria-label="نمایش تصویر گالری">
    <button class="gallery-lightbox-close" type="button" data-gallery-close aria-label="بستن تصویر"><i class="hgi-stroke hgi-cancel-01" aria-hidden="true"></i></button>
    <img id="galleryLightboxImage" src="" alt="تصویر بزرگ گالری معیار">
  </div>
</div>
<script>
  (function () {
    var lightbox = document.getElementById('galleryLightbox');
    var lightboxImage = document.getElementById('galleryLightboxImage');
    var closeLightbox = function () { if (!lightbox) return; lightbox.hidden = true; document.body.classList.remove('gallery-lightbox-open'); };
    document.querySelectorAll('[data-gallery-image]').forEach(function (button) {
      button.addEventListener('click', function () {
        if (!lightbox || !lightboxImage) return;
        lightboxImage.src = button.getAttribute('data-gallery-image');
        lightbox.hidden = false;
        document.body.classList.add('gallery-lightbox-open');
      });
    });
    document.querySelectorAll('[data-gallery-close]').forEach(function (button) { button.addEventListener('click', closeLightbox); });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape') closeLightbox(); });
  }());
</script>
<?php meyar_theme_footer($settings); ?>

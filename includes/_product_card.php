<?php
/** Product Card Partial. Expects $prod variable. */
$effectivePrice = $prod['sale_price'] ?: $prod['price'];
$hasDiscount    = $prod['sale_price'] && $prod['sale_price'] < $prod['price'];
$lowStock       = $prod['stock'] > 0 && $prod['stock'] <= 5;
$outOfStock     = $prod['stock'] <= 0;
?>
<article class="product-card card h-100 border-0 shadow-sm">
    <a href="/product.php?slug=<?= e($prod['slug']) ?>" class="product-image-wrap" aria-label="View <?= e($prod['name']) ?>">
        <?php if ($hasDiscount): ?><span class="badge-sale">Sale</span><?php endif; ?>
        <?php if ($outOfStock): ?>
            <span class="badge-stock out">Out</span>
        <?php elseif ($lowStock): ?>
            <span class="badge-stock low">Only <?= (int)$prod['stock'] ?></span>
        <?php endif; ?>
        <?php if ($prod['image']): ?>
            <img src="/<?= e($prod['image']) ?>" alt="<?= e($prod['name']) ?>" loading="lazy">
        <?php else: ?>
            <div class="product-placeholder" aria-hidden="true"><i class="bi bi-image"></i></div>
        <?php endif; ?>
    </a>
    <div class="card-body product-body">
        <?php if (!empty($prod['category_name'])): ?><div class="product-category"><?= e($prod['category_name']) ?></div><?php endif; ?>
        <h3 class="product-title"><a href="/product.php?slug=<?= e($prod['slug']) ?>"><?= e($prod['name']) ?></a></h3>
        <div class="d-flex align-items-end justify-content-between gap-2 mt-auto">
            <div class="product-price">
                <?= format_price((float)$effectivePrice) ?>
                <?php if ($hasDiscount): ?><span class="original-price"><?= format_price((float)$prod['price']) ?></span><?php endif; ?>
            </div>
            <span class="quick-icon"><i class="bi bi-arrow-right"></i></span>
        </div>
    </div>
</article>

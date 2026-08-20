<?php
declare(strict_types=1);
?>
<article class="card">
    <a class="card-media" href="item.php?id=<?= (int) $it['id'] ?>" aria-label="<?= e($it['title']) ?>">
        <img src="<?= e(first_photo($it)) ?>" alt="<?= e($it['title']) ?>" loading="lazy">
        <?php if ((int) $it['is_giveaway'] === 1): ?>
            <span class="badge badge-give">Отдам даром</span>
        <?php else: ?>
            <span class="badge badge-used">б/у</span>
        <?php endif; ?>
    </a>
    <?php if ($currentUser): ?>
        <form class="card-fav-form" method="post" action="favorites.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="toggle_fav" value="item">
            <input type="hidden" name="target_id" value="<?= (int) $it['id'] ?>">
            <input type="hidden" name="next" value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>">
            <button class="card-fav<?= in_array((int) $it['id'], $favItems ?? [], true) ? ' is-fav' : '' ?>" type="submit" aria-label="<?= in_array((int) $it['id'], $favItems ?? [], true) ? 'Убрать из избранного' : 'В избранное' ?>">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            </button>
        </form>
    <?php endif; ?>
    <div class="card-body">
        <h3 class="card-title"><a href="item.php?id=<?= (int) $it['id'] ?>"><?= e($it['title']) ?></a></h3>
        <div class="card-price">
            <?= (int) $it['price'] > 0 ? money((int) $it['price']) : 'Отдам даром' ?>
        </div>
        <div class="card-meta">
            <?php if ($it['size'] !== null && $it['size'] !== ''): ?>
                <span class="chip">размер <?= e($it['size']) ?></span>
            <?php endif; ?>
            <?php if ($it['condition_label'] !== null && $it['condition_label'] !== '' && mb_strtolower($it['condition_label']) !== 'б/у'): ?>
                <span class="chip"><?= e($it['condition_label']) ?></span>
            <?php endif; ?>
        </div>
        <div class="card-footer">
            <span class="seller"><?= e($it['city']) ?></span>
            <a class="btn btn-primary btn-sm card-cta" href="item.php?id=<?= (int) $it['id'] ?>">Смотреть</a>
        </div>
    </div>
</article>

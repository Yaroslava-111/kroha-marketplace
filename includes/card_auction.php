<?php
declare(strict_types=1);
?>
<article class="card">
    <a class="card-media" href="auction.php?id=<?= (int) $a['id'] ?>" aria-label="<?= e($a['title']) ?>">
        <img src="<?= e(first_photo($a)) ?>" alt="<?= e($a['title']) ?>" loading="lazy">
        <span class="badge badge-auction">Аукцион</span>
    </a>
    <?php if ($currentUser): ?>
        <form class="card-fav-form" method="post" action="favorites.php">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="toggle_fav" value="auction">
            <input type="hidden" name="target_id" value="<?= (int) $a['id'] ?>">
            <input type="hidden" name="next" value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>">
            <button class="card-fav<?= in_array((int) $a['id'], $favAuctions ?? [], true) ? ' is-fav' : '' ?>" type="submit" aria-label="<?= in_array((int) $a['id'], $favAuctions ?? [], true) ? 'Убрать из избранного' : 'В избранное' ?>">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            </button>
        </form>
    <?php endif; ?>
    <div class="card-body">
        <h3 class="card-title"><a href="auction.php?id=<?= (int) $a['id'] ?>"><?= e($a['title']) ?></a></h3>
        <div class="card-price">
            <?= money((int) $a['current_price']) ?>
            <span class="muted small">старт <?= money((int) $a['start_price']) ?></span>
        </div>
        <div class="card-meta">
            <span class="chip timer" data-end="<?= (int) strtotime($a['end_at']) ?>"><?= e(format_countdown(seconds_until($a['end_at']))) ?></span>
            <span class="chip"><?= (int) $a['bid_count'] ?> <?= e(plural((int) $a['bid_count'], ['ставка', 'ставки', 'ставок'])) ?></span>
        </div>
        <div class="card-footer">
            <span class="seller"><?= e($a['seller_city']) ?></span>
            <a class="btn btn-primary btn-sm card-cta" href="auction.php?id=<?= (int) $a['id'] ?>">Смотреть</a>
        </div>
    </div>
</article>

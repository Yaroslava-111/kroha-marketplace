<?php
declare(strict_types=1);

/* Переиспользуемая секция сообщений (чат).
 * Используется вкладкой «Сообщения» в account.php.
 * Ожидает: $pdo (PDO), $meId (int), доступные функции e(), initials(), msg_time(), csrf_token(), money(). */

function messages_base(): string
{
    return 'account.php?tab=messages';
}

function messages_url(bool $inArchive = false, int $convId = 0): string
{
    $base = messages_base();
    $params = [];
    if ($inArchive) {
        $params['view'] = 'archive';
    }
    if ($convId > 0) {
        $params['id'] = $convId;
    }
    if (!$params) {
        return $base;
    }
    return $base . (str_contains($base, '?') ? '&' : '?') . http_build_query($params);
}

function messages_post(PDO $pdo, int $meId): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }
    $postConvId = (int) ($_POST['conv_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? 'send');

    $stmt = $pdo->prepare('SELECT id FROM conversations WHERE id = ? AND (buyer_id = ? OR seller_id = ?)');
    $stmt->execute([$postConvId, $meId, $meId]);
    $ownConv = $stmt->fetch();

    if ($ownConv && csrf_check()) {
        if ($action === 'archive') {
            $pdo->prepare('UPDATE conversations SET archived_at = ? WHERE id = ?')
                ->execute([date('Y-m-d H:i:s'), $postConvId]);
            header('Location: ' . messages_url(false));
            exit;
        }
        if ($action === 'restore') {
            $pdo->prepare('UPDATE conversations SET archived_at = NULL WHERE id = ?')
                ->execute([$postConvId]);
            header('Location: ' . messages_url(true));
            exit;
        }
        $text = trim((string) ($_POST['text'] ?? ''));
        if ($text !== '' && mb_strlen($text) <= 2000) {
            $pdo->prepare('INSERT INTO messages (conversation_id, sender_id, text, created_at) VALUES (?, ?, ?, ?)')
                ->execute([$postConvId, $meId, $text, date('Y-m-d H:i:s')]);
            $pdo->prepare('UPDATE conversations SET updated_at = ?, archived_at = NULL WHERE id = ?')
                ->execute([date('Y-m-d H:i:s'), $postConvId]);
        }
    }
    header('Location: ' . messages_url(false, $postConvId));
    exit;
}

function render_messages_section(PDO $pdo, int $meId): void
{
    $inArchive = (string) ($_GET['view'] ?? '') === 'archive';

    $listSql = 'SELECT c.*,
                o.name AS other_name, o.city AS other_city,
                m.text AS last_text, m.created_at AS last_at, m.sender_id AS last_sender,
                (SELECT COUNT(*) FROM messages mm WHERE mm.conversation_id = c.id AND mm.sender_id != ? AND mm.is_read = 0) AS unread
         FROM conversations c
         JOIN users o ON o.id = CASE WHEN c.buyer_id = ? THEN c.seller_id ELSE c.buyer_id END
         LEFT JOIN messages m ON m.id = (
             SELECT id FROM messages mm WHERE mm.conversation_id = c.id ORDER BY mm.created_at DESC, mm.id DESC LIMIT 1
         )
         WHERE (c.buyer_id = ? OR c.seller_id = ?)
           AND c.archived_at IS ' . ($inArchive ? 'NOT NULL' : 'NULL') . '
         ORDER BY c.updated_at DESC, c.id DESC';
    $stmt = $pdo->prepare($listSql);
    $stmt->execute([$meId, $meId, $meId, $meId]);
    $convs = $stmt->fetchAll();

    $unreadTotal = 0;
    foreach ($convs as $c) {
        $unreadTotal += (int) $c['unread'];
    }

    $archStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM conversations WHERE (buyer_id = ? OR seller_id = ?) AND archived_at IS NOT NULL'
    );
    $archStmt->execute([$meId, $meId]);
    $archivedCount = (int) $archStmt->fetchColumn();

    $activeConv = null;
    $other = null;
    $messages = [];

    $convId = (int) ($_GET['id'] ?? 0);
    if ($convId > 0) {
        $convStmt = $pdo->prepare('SELECT * FROM conversations WHERE id = ?');
        $convStmt->execute([$convId]);
        $candidate = $convStmt->fetch();
        if ($candidate) {
            $isBuyer = (int) $candidate['buyer_id'] === $meId;
            $isSeller = (int) $candidate['seller_id'] === $meId;
            if ($isBuyer || $isSeller) {
                $activeConv = $candidate;

                $otherId = $isBuyer ? (int) $candidate['seller_id'] : (int) $candidate['buyer_id'];
                $otherStmt = $pdo->prepare('SELECT id, name, city, rating FROM users WHERE id = ?');
                $otherStmt->execute([$otherId]);
                $other = $otherStmt->fetch();

                $pdo->prepare('UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_id != ? AND is_read = 0')
                    ->execute([$convId, $meId]);

                $msgStmt = $pdo->prepare(
                    'SELECT m.*, u.name AS sender_name FROM messages m JOIN users u ON u.id = m.sender_id
                     WHERE m.conversation_id = ? ORDER BY m.created_at ASC, m.id ASC'
                );
                $msgStmt->execute([$convId]);
                $messages = $msgStmt->fetchAll();
            }
        }
    }

    $base = messages_base();
    ?>
    <div class="chat-app<?= $activeConv ? ' has-conv' : '' ?>">
        <aside class="chat-side">
            <div class="chat-side-head">
                <span><?= $inArchive ? 'Архив' : 'Диалоги' ?></span>
                <?php if (!$inArchive && $unreadTotal > 0): ?>
                    <span class="msg-badge"><?= $unreadTotal ?></span>
                <?php endif; ?>
                <span class="chat-side-toggle">
                    <?php if ($inArchive): ?>
                        <a class="icon-btn" href="<?= e(messages_url(false)) ?>" title="Вернуться к диалогам" aria-label="Вернуться к диалогам">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
                        </a>
                    <?php else: ?>
                        <a class="icon-btn<?= $archivedCount > 0 ? ' has-count' : '' ?>" href="<?= e(messages_url(true)) ?>" title="Архив" aria-label="Архив">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5" rx="1"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
                            <?php if ($archivedCount > 0): ?><span class="mini-badge"><?= $archivedCount ?></span><?php endif; ?>
                        </a>
                    <?php endif; ?>
                </span>
            </div>
            <div class="msg-list">
                <?php if (!$convs): ?>
                    <?php if ($inArchive): ?>
                        <p class="empty">В архиве пока пусто. Неактивные переписки можно перенести сюда — они больше не будут мешать в диалогах.</p>
                    <?php else: ?>
                        <p class="empty">Диалогов пока нет. Нажмите «Написать продавцу» на объявлении или аукционе — и я здесь появится.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <?php foreach ($convs as $c): ?>
                        <a class="msg-row<?= (int) $c['unread'] > 0 ? ' is-unread' : '' ?><?= $activeConv !== null && (int) $activeConv['id'] === (int) $c['id'] ? ' is-active' : '' ?>" href="<?= e(messages_url($inArchive, (int) $c['id'])) ?>">
                            <span class="avatar<?= (int) $c['unread'] > 0 ? ' avatar-unread' : '' ?>"><?= e(initials((string) $c['other_name'])) ?></span>
                            <span class="msg-row-main">
                                <span class="msg-row-head">
                                    <span class="msg-row-name"><?= e($c['other_name']) ?></span>
                                    <span class="msg-row-time"><?= e(msg_time((string) $c['last_at'])) ?></span>
                                </span>
                                <span class="msg-row-topic"><?= e($c['subject']) ?></span>
                                <span class="msg-row-preview">
                                    <?php if ($c['last_text'] !== null): ?>
                                        <?php if ((int) $c['last_sender'] === $meId): ?><span class="msg-row-me">Вы:</span><?php endif; ?>
                                        <?= e(mb_strimwidth((string) $c['last_text'], 0, 100, '…')) ?>
                                    <?php else: ?>
                                        <span class="muted">Сообщений пока нет</span>
                                    <?php endif; ?>
                                </span>
                            </span>
                            <?php if ((int) $c['unread'] > 0): ?>
                                <span class="msg-badge"><?= (int) $c['unread'] ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>

        <main class="chat-main">
            <?php if ($activeConv !== null && $other): ?>
                <header class="chat-head">
                    <a class="btn btn-secondary btn-back" href="<?= e(messages_url(false)) ?>" title="Ко всем диалогам" aria-label="Ко всем диалогам">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    </a>
                    <a class="chat-user" href="profile.php?id=<?= (int) $other['id'] ?>">
                        <span class="avatar avatar-sm"><?= e(initials($other['name'])) ?></span>
                        <span class="chat-user-info">
                            <span class="chat-user-name"><?= e($other['name']) ?></span>
                            <span class="chat-user-sub">
                                <?= e($other['city']) ?><?php if ($other['rating'] !== null): ?> · рейтинг <?= (string) $other['rating'] ?>/5<?php endif; ?>
                            </span>
                        </span>
                    </a>
                    <?php if ($activeConv['item_url'] !== ''): ?>
                        <a class="chip chat-topic" href="<?= e($activeConv['item_url']) ?>"><?= e($activeConv['subject']) ?> →</a>
                    <?php else: ?>
                        <span class="chip chat-topic"><?= e($activeConv['subject']) ?></span>
                    <?php endif; ?>
                    <?php if ($activeConv['archived_at'] !== null): ?>
                        <form class="chat-arch-form" method="post" action="<?= e($base) ?>">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="conv_id" value="<?= (int) $activeConv['id'] ?>">
                            <input type="hidden" name="action" value="restore">
                            <button class="icon-btn" type="submit" title="Вернуть из архива" aria-label="Вернуть из архива">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
                            </button>
                        </form>
                    <?php else: ?>
                        <form class="chat-arch-form" method="post" action="<?= e($base) ?>">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="conv_id" value="<?= (int) $activeConv['id'] ?>">
                            <input type="hidden" name="action" value="archive">
                            <button class="icon-btn" type="submit" title="В архив" aria-label="В архив">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5" rx="1"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
                            </button>
                        </form>
                    <?php endif; ?>
                </header>

                <div class="chat-thread" id="chatThread" aria-live="polite">
                    <?php if (!$messages): ?>
                        <p class="empty">Сообщений пока нет. Напишите первым — например, спросите о состоянии и возможности встречи.</p>
                    <?php endif; ?>
                    <?php
                    $prevDay = '';
                    foreach ($messages as $m):
                        $day = date('Y-m-d', strtotime($m['created_at']));
                        if ($day !== $prevDay):
                            $prevDay = $day;
                            $dayLabel = date('d.m.Y', strtotime($m['created_at']));
                            if ($day === date('Y-m-d')) {
                                $dayLabel = 'Сегодня';
                            } elseif ($day === date('Y-m-d', strtotime('-1 day'))) {
                                $dayLabel = 'Вчера';
                            }
                    ?>
                        <div class="msg-day"><span><?= e($dayLabel) ?></span></div>
                    <?php endif; ?>
                        <div class="msg-line is-<?= (int) $m['sender_id'] === $meId ? 'mine' : 'other' ?>">
                            <?php if ((int) $m['sender_id'] !== $meId): ?>
                                <span class="avatar avatar-xs"><?= e(initials((string) $m['sender_name'])) ?></span>
                            <?php endif; ?>
                            <div class="msg-bubble">
                                <div class="msg-text"><?= nl2br(e($m['text'])) ?></div>
                                <div class="msg-time"><?= e(date('d.m H:i', strtotime($m['created_at']))) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div id="msgEnd"></div>
                </div>

                <form class="chat-compose" method="post" action="<?= e($base) ?>">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="conv_id" value="<?= (int) $activeConv['id'] ?>">
                    <div class="chat-compose-wrap">
                        <textarea id="msgText" name="text" rows="1" maxlength="2000" placeholder="Сообщение…" required></textarea>
                        <button class="chat-send" type="submit" aria-label="Отправить" title="Отправить">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="chat-empty">
                    <span class="chat-empty-icon">
                        <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php if ($inArchive): ?><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5" rx="1"/><line x1="10" y1="12" x2="14" y2="12"/><?php else: ?><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><?php endif; ?></svg>
                    </span>
                    <h2><?= $inArchive ? 'Архив пуст' : 'Ваши диалоги' ?></h2>
                    <p><?= $inArchive ? 'Здесь будут переписки, которые вы отправили в архив.' : 'Выберите переписку слева или напишите продавцу на странице объявления или аукциона.' ?></p>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
    var msgEnd = document.getElementById('msgEnd');
    var thread = document.getElementById('chatThread');
    if (msgEnd && thread) { thread.scrollTop = thread.scrollHeight; }
    var msgForm = document.querySelector('.chat-compose');
    var msgInput = document.getElementById('msgText');
    if (msgForm && msgInput) {
        var msgSend = msgForm.querySelector('.chat-send');
        msgForm.addEventListener('submit', function () {
            msgInput.readOnly = true;
            if (msgSend) { msgSend.disabled = true; }
        });
        msgInput.addEventListener('input', function () {
            msgInput.style.height = 'auto';
            msgInput.style.height = Math.min(msgInput.scrollHeight, 140) + 'px';
        });
        msgInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                msgForm.requestSubmit();
            }
        });
    }
    </script>
<?php
}
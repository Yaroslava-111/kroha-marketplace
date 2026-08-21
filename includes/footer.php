</main>

<footer class="site-footer">
    <div class="container footer-inner">
        <span class="footer-copy">© <?= date('Y') ?> <?= e(APP_NAME) ?> — детский маркетплейс</span>
        <nav class="footer-nav" aria-label="Полезные ссылки">
            <a href="index.php?type=all">Каталог</a>
            <a href="help.php">Помощь</a>
            <a href="policy.php">Политика конфиденциальности</a>
        </nav>
    </div>
</footer>

<dialog class="post-modal" id="postModal" aria-labelledby="postModalTitle">
    <div class="post-modal-head">
        <h2 id="postModalTitle">Разместить</h2>
        <button class="post-modal-close" type="button" aria-label="Закрыть">×</button>
    </div>
    <div class="post-modal-body" id="postModalBody"></div>
</dialog>

<dialog class="lightbox" id="lightbox" aria-label="Просмотр фотографии">
    <button class="lightbox-close" type="button" aria-label="Закрыть">×</button>
    <button class="lightbox-nav lightbox-prev" type="button" aria-label="Предыдущее фото">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
    </button>
    <img class="lightbox-img" src="" alt="Просмотр фотографии">
    <button class="lightbox-nav lightbox-next" type="button" aria-label="Следующее фото">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
    </button>
</dialog>

<dialog class="post-modal login-modal" id="loginModal" aria-labelledby="loginModalTitle">
    <div class="post-modal-head">
        <h2 id="loginModalTitle">Вход</h2>
        <button class="post-modal-close" type="button" aria-label="Закрыть">×</button>
    </div>
    <div class="post-modal-body">
        <div class="alert alert-error" id="loginError" hidden></div>
        <form class="form-auth" id="loginForm" method="post" action="login.php" novalidate>
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="next" value="index.php">
            <p class="hint modal-note" id="loginModalNote" hidden>Войдите, чтобы продолжить.</p>
            <div class="form-row">
                <label for="login-email">Email</label>
                <input type="email" id="login-email" name="email" required autocomplete="email" placeholder="you@example.ru">
            </div>
            <div class="form-row">
                <label for="login-password">Пароль</label>
                <div class="pass-wrap">
                    <input type="password" id="login-password" name="password" required autocomplete="current-password" placeholder="••••••••" class="pass-input">
                    <button class="pass-toggle" type="button" aria-label="Показать пароль" aria-pressed="false" data-target="login-password">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>
            <div class="form-auth-meta">
                <a class="forgot-link" href="forgot_password.php">Забыли пароль?</a>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Войти</button>
        </form>
        <p class="auth-alt">Нет аккаунта? <button class="auth-switch" type="button" data-auth-switch="register">Зарегистрируйтесь</button></p>
    </div>
</dialog>

<dialog class="post-modal login-modal" id="registerModal" aria-labelledby="registerModalTitle">
    <div class="post-modal-head">
        <h2 id="registerModalTitle">Регистрация</h2>
        <button class="post-modal-close" type="button" aria-label="Закрыть">×</button>
    </div>
    <div class="post-modal-body">
        <div class="alert alert-error" id="registerError" hidden></div>
        <form class="form-auth" id="registerForm" method="post" action="register.php" novalidate>
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="next" value="index.php">
            <div class="form-row">
                <label for="reg-name">Имя</label>
                <input type="text" id="reg-name" name="name" maxlength="100" required autocomplete="name" placeholder="Как вас представлять">
            </div>
            <div class="form-row">
                <label for="reg-email">Email</label>
                <input type="email" id="reg-email" name="email" required autocomplete="email" placeholder="you@example.ru">
            </div>
            <div class="form-row">
                <label for="reg-city">Город</label>
                <input type="text" id="reg-city" name="city" maxlength="100" required autocomplete="address-level2" placeholder="Например: Москва">
            </div>
            <div class="form-row">
                <label for="reg-password">Пароль</label>
                <div class="pass-wrap">
                    <input type="password" id="reg-password" name="password" required minlength="8" autocomplete="new-password" placeholder="Не короче 8 символов" class="pass-input">
                    <button class="pass-toggle" type="button" aria-label="Показать пароль" aria-pressed="false" data-target="reg-password">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>
            <div class="form-row">
                <label for="reg-password2">Повторите пароль</label>
                <div class="pass-wrap">
                    <input type="password" id="reg-password2" name="password2" required minlength="8" autocomplete="new-password" placeholder="Ещё раз" class="pass-input">
                    <button class="pass-toggle" type="button" aria-label="Показать пароль" aria-pressed="false" data-target="reg-password2">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>
            <div class="form-row form-row-check">
                <label class="agree-check">
                    <input type="checkbox" name="agree" value="1">
                    <span>Я согласен(на) на обработку персональных данных — <a href="policy.php" target="_blank" rel="noopener">политика</a></span>
                </label>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Зарегистрироваться</button>
        </form>
        <p class="auth-alt">Уже есть аккаунт? <button class="auth-switch" type="button" data-auth-switch="login">Войдите</button></p>
    </div>
</dialog>

<script>
document.querySelectorAll('.timer[data-end]').forEach(function (el) {
    var ts = parseInt(el.dataset.end, 10);
    var end = new Date(ts * 1000);
    function tick() {
        var sec = Math.max(0, Math.floor((end - new Date()) / 1000));
        if (sec <= 0) { el.textContent = 'завершён'; el.classList.add('done'); return; }
        var d = Math.floor(sec / 86400), h = Math.floor(sec % 86400 / 3600), m = Math.floor(sec % 3600 / 60);
        el.textContent = d > 0 ? d + ' дн ' + h + ' ч' : (h > 0 ? h + ' ч ' + m + ' мин' : m + ' мин');
        if (sec <= 600) el.classList.add('urgent');
        setTimeout(tick, 1000);
    }
    tick();
});
</script>
<script>
(function () {
    if (document.body.getAttribute('data-logged') !== '1') return;

    function setBadge(badge, count) {
        if (!badge) return;
        if (count > 0) {
            var prev = parseInt(badge.dataset.count, 10) || 0;
            badge.textContent = count;
            badge.dataset.count = String(count);
            badge.classList.remove('is-empty');
            if (count > prev) {
                badge.classList.remove('is-new');
                void badge.offsetWidth;
                badge.classList.add('is-new');
            }
        } else {
            badge.classList.add('is-empty');
            badge.dataset.count = '0';
        }
    }

    function poll() {
        fetch('notifications_poll.php', { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.ok) return;
                setBadge(document.querySelector('.nav-badge-notif'), d.notifications);
                setBadge(document.querySelector('.notif-badge'), d.notifications);
                setBadge(document.querySelector('.nav-badge-msg'), d.messages);
                var dot = document.querySelector('.avatar-dot');
                if (dot) dot.classList.toggle('is-empty', (d.notifications || 0) + (d.messages || 0) === 0);
            })
            .catch(function () {});
    }

    setInterval(poll, 15000);
})();
</script>
<script>
(function () {
    var burger = document.getElementById('navBurger');
    var nav = document.getElementById('mainNav');
    if (burger && nav) {
        function setOpen(open) {
            nav.classList.toggle('is-open', open);
            burger.classList.toggle('is-open', open);
            burger.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        burger.addEventListener('click', function () {
            setOpen(!nav.classList.contains('is-open'));
        });
        nav.addEventListener('click', function (e) {
            if (e.target.closest('a') && window.innerWidth < 1200) {
                setOpen(false);
            }
        });
        document.addEventListener('click', function (e) {
            if (!nav.contains(e.target) && !burger.contains(e.target) && window.innerWidth < 1200) {
                setOpen(false);
            }
        });
    }
    document.querySelectorAll('.user-toggle').forEach(function (t) {
        t.addEventListener('click', function () {
            var u = t.closest('.nav-user');
            if (!u) return;
            var open = u.classList.toggle('is-open');
            t.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });
    document.querySelectorAll('.nav-dropdown').forEach(function (d) {
        var link = d.querySelector('.nav-dropdown-link');
        if (!link) return;
        link.addEventListener('click', function (e) {
            if (window.innerWidth < 1200) return;
            var open = d.classList.toggle('is-open');
            link.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        d.addEventListener('mouseleave', function () {
            d.classList.remove('is-open');
            link.setAttribute('aria-expanded', 'false');
        });
    });
    document.addEventListener('click', function (e) {
        if (window.innerWidth >= 1200 && !e.target.closest('.nav-dropdown')) {
            document.querySelectorAll('.nav-dropdown.is-open').forEach(function (d) {
                d.classList.remove('is-open');
                var l = d.querySelector('.nav-dropdown-link');
                if (l) l.setAttribute('aria-expanded', 'false');
            });
        }
    });
    document.addEventListener('click', function (e) {
        if (window.innerWidth >= 1200 && !e.target.closest('.nav-user')) {
            document.querySelectorAll('.nav-user.is-open').forEach(function (u) {
                u.classList.remove('is-open');
                var t = u.querySelector('.user-toggle');
                if (t) t.setAttribute('aria-expanded', 'false');
            });
        }
    });
    document.querySelectorAll('.pass-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-target'));
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
            btn.setAttribute('aria-label', show ? 'Скрыть пароль' : 'Показать пароль');
            btn.classList.toggle('is-on', show);
            input.focus();
        });
    });
    document.querySelectorAll('.pass-toggle-card').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var form = btn.nextElementSibling;
            if (!form || !form.classList.contains('pass-card')) return;
            var open = form.classList.toggle('is-open');
            btn.classList.toggle('is-open', open);
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });
})();
</script>
<script>
(function () {
    var header = document.querySelector('.site-header');
    if (!header) return;
    function onScroll() { header.classList.toggle('is-scrolled', window.scrollY > 8); }
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
})();
</script>
<script>
(function () {
    if (typeof HTMLDialogElement === 'undefined') return;
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-report-trigger]');
        if (!trigger) return;
        e.preventDefault();
        var dialog = document.getElementById('reportModal');
        if (dialog && typeof dialog.showModal === 'function') dialog.showModal();
    });
    document.querySelectorAll('.report-modal').forEach(function (dialog) {
        dialog.addEventListener('click', function (e) {
            if (e.target === dialog) dialog.close();
        });
    });
})();
</script>
<script>
(function () {
    var dialog = document.getElementById('reportModal');
    if (!dialog) return;
    var close = dialog.querySelector('.post-modal-close');
    if (close) close.addEventListener('click', function () { dialog.close(); });
})();
</script>
<script>
(function () {
    if (typeof MutationObserver === 'undefined') return;
    var dialogs = document.querySelectorAll('dialog');
    if (!dialogs.length) return;
    function sync() {
        var anyOpen = document.querySelector('dialog[open]') !== null;
        document.body.classList.toggle('no-scroll', anyOpen);
    }
    var obs = new MutationObserver(sync);
    dialogs.forEach(function (d) { obs.observe(d, { attributes: true, attributeFilter: ['open'] }); });
})();
</script>
<script>
(function () {
    var dialog = document.getElementById('postModal');
    var bodyEl = document.getElementById('postModalBody');
    var titleEl = document.getElementById('postModalTitle');
    if (!dialog || !bodyEl || typeof HTMLDialogElement === 'undefined') return;

    var KINDS = [
        {
            type: 'item',
            name: 'Объявление',
            desc: 'Фикс-цена или «отдам даром»',
            endpoint: 'post_form.php?fragment=1',
            title: 'Разместить объявление',
            submit: 'Опубликовать'
        },
        {
            type: 'auction',
            name: 'Аукцион',
            desc: 'Ставки и торги, «купить сейчас»',
            endpoint: 'auction_form.php?fragment=1',
            title: 'Создать аукцион',
            submit: 'Опубликовать лот'
        }
    ];

    function showChooser() {
        if (titleEl) titleEl.textContent = 'Разместить';
        bodyEl.innerHTML =
            '<div class="post-kind">' +
            KINDS.map(function (k) {
                return '<button type="button" class="post-kind-card" data-kind="' + k.type + '">' +
                    '<span class="post-kind-name">' + k.name + '</span>' +
                    '<span class="post-kind-desc">' + k.desc + '</span>' +
                    '</button>';
            }).join('') +
            '</div>';
        bodyEl.querySelectorAll('[data-kind]').forEach(function (btn) {
            btn.addEventListener('click', function () { loadForm(btn.getAttribute('data-kind')); });
        });
    }

    function loadForm(kind) {
        var conf = KINDS.find(function (k) { return k.type === kind; });
        if (!conf) return;
        if (titleEl) titleEl.textContent = conf.title;
        bodyEl.innerHTML = '<div class="modal-loading" role="status"><span class="spinner" aria-hidden="true"></span>Загружаем форму…</div>';
        fetch(conf.endpoint, { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                bodyEl.innerHTML = html;
                attachForm();
            })
            .catch(function () {
                bodyEl.innerHTML = '<div class="alert alert-error">Не удалось загрузить форму. <button type="button" class="btn btn-secondary" data-retry-load>Попробовать ещё раз</button></div>';
                var retry = bodyEl.querySelector('[data-retry-load]');
                if (retry) retry.addEventListener('click', function () { loadForm(kind); });
            });
    }

    function attachForm() {
        if (typeof window.initPhotoUploader === 'function') window.initPhotoUploader();
        var form = bodyEl.querySelector('form.post-modal-form');
        if (!form) return;
        var endpoint = form.getAttribute('data-endpoint') || 'post_form.php?fragment=1';
        var submitLabel = form.querySelector('button[type=submit]');

        var actions = document.createElement('div');
        actions.className = 'post-actions';
        if (submitLabel) {
            submitLabel.classList.remove('btn-secondary');
            submitLabel.classList.add('btn-primary', 'post-submit');
            actions.appendChild(submitLabel);
        }
        var back = document.createElement('button');
        back.type = 'button';
        back.className = 'btn btn-secondary post-kind-back';
        back.textContent = '← Выбрать другой тип';
        back.addEventListener('click', showChooser);
        actions.appendChild(back);
        form.appendChild(actions);

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = form.querySelector('button[type=submit]');
            if (btn) {
                btn.disabled = true;
                btn.classList.add('is-loading');
                btn.dataset.label = btn.textContent;
                btn.textContent = 'Публикуем…';
            }
            fetch(endpoint, {
                method: 'POST', credentials: 'same-origin', body: new FormData(form)
            }).then(function (r) {
                var ct = r.headers.get('Content-Type') || '';
                if (ct.indexOf('application/json') !== -1) {
                    return r.json().then(function (d) { return { ok: true, data: d }; });
                }
                return r.text().then(function (t) { return { ok: false, html: t }; });
            }).then(function (res) {
                if (res.ok && res.data && res.data.login) {
                    if (typeof window.openLoginPopup === 'function') {
                        window.openLoginPopup('post.php', 'Сессия истекла. Войдите, чтобы продолжить.');
                        dialog.close();
                    } else {
                        window.location.href = 'login.php';
                    }
                    return;
                }
                if (res.ok && res.data && res.data.url) {
                    window.location.href = res.data.url;
                    return;
                }
                if (btn) {
                    btn.disabled = false;
                    btn.classList.remove('is-loading');
                    btn.textContent = btn.dataset.label || 'Опубликовать';
                }
                if (res.html) bodyEl.innerHTML = res.html;
                attachForm();
            }).catch(function () {
                if (btn) {
                    btn.disabled = false;
                    btn.classList.remove('is-loading');
                    btn.textContent = btn.dataset.label || 'Опубликовать';
                }
            });
        });
        var conf = KINDS.find(function (k) { return k.endpoint === endpoint; });
        if (submitLabel && conf) submitLabel.textContent = conf.submit;
    }

    function openModal(kind) {
        dialog.showModal();
        if (kind) {
            loadForm(kind);
            return;
        }
        showChooser();
    }
    window.openPostModal = openModal;

    function guardLogin(url, message) {
        if (document.body.getAttribute('data-logged') === '1') return true;
        if (typeof window.openLoginPopup === 'function') {
            window.openLoginPopup(url, message);
        }
        return false;
    }

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-modal-post], [data-modal-auction], [data-modal-item]');
        if (!trigger) return;
        e.preventDefault();
        if (trigger.hasAttribute('data-modal-auction')) {
            if (guardLogin('auction_form.php', 'Войдите, чтобы создать аукцион.')) openModal('auction');
        } else if (trigger.hasAttribute('data-modal-item')) {
            if (guardLogin('post_form.php', 'Войдите, чтобы разместить объявление.')) openModal('item');
        } else {
            if (guardLogin('post.php', 'Войдите, чтобы разместить.')) openModal();
        }
    });

    var closeBtn = dialog.querySelector('.post-modal-close');
    if (closeBtn) closeBtn.addEventListener('click', function () { dialog.close(); });
    dialog.addEventListener('click', function (e) {
        if (e.target === dialog) dialog.close();
    });
    dialog.addEventListener('close', function () {
        bodyEl.innerHTML = '';
    });
})();
</script>
<script>
(function () {
    var dlg = document.getElementById('lightbox');
    if (!dlg || typeof HTMLDialogElement === 'undefined') return;
    var img = dlg.querySelector('.lightbox-img');
    var list = [];
    var idx = 0;
    function show(i) {
        if (!list.length) return;
        idx = (i + list.length) % list.length;
        img.src = list[idx];
    }
    document.addEventListener('click', function (e) {
        var gal = e.target.closest('.gallery[data-lightbox]');
        if (!gal) return;
        var main = gal.querySelector('.gallery-main');
        var srcs = [];
        gal.querySelectorAll('.gallery-thumb').forEach(function (t) {
            var s = t.getAttribute('data-src') || (t.querySelector('img') ? t.querySelector('img').getAttribute('src') : null);
            if (s) srcs.push(s);
        });
        if (!srcs.length && main) srcs.push(main.getAttribute('src'));
        if (!srcs.length) return;
        var i = 0;
        var thumbBtn = e.target.closest('.gallery-thumb');
        if (thumbBtn) {
            i = Array.prototype.indexOf.call(gal.querySelectorAll('.gallery-thumb'), thumbBtn);
        } else if (e.target === main) {
            i = Math.max(0, srcs.indexOf(main.getAttribute('src')));
        } else {
            return;
        }
        list = srcs;
        show(i);
        dlg.showModal();
    });
    dlg.querySelector('.lightbox-close').addEventListener('click', function () { dlg.close(); });
    dlg.querySelector('.lightbox-prev').addEventListener('click', function () { show(idx - 1); });
    dlg.querySelector('.lightbox-next').addEventListener('click', function () { show(idx + 1); });
    dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });
})();
</script>
<script>
(function () {
    var MAX = 8;
    window.initPhotoUploader = function () {
        var input = document.getElementById('photos');
        var box = document.getElementById('photoPreviews');
        var zone = document.getElementById('photoDropzone');
        var countEl = document.getElementById('photoCount');
        if (!input || !box || input.getAttribute('data-uploader-ready')) return;
        input.setAttribute('data-uploader-ready', '1');

        var files = [];

        function syncInput() {
            try {
                var dt = new DataTransfer();
                files.forEach(function (f) { dt.items.add(f); });
                input.files = dt.files;
            } catch (e) {}
        }

        function setError(text) {
            if (!zone) return;
            var old = zone.querySelector('.photo-dropzone-error');
            if (old) old.remove();
            if (!text) return;
            var err = document.createElement('span');
            err.className = 'photo-dropzone-error';
            err.textContent = text;
            zone.appendChild(err);
        }

        function addFiles(list) {
            var arr = Array.prototype.slice.call(list || []);
            var rejected = false;
            var added = 0;
            arr.forEach(function (f) {
                if (!/^image\//.test(f.type)) { rejected = true; return; }
                if (files.length >= MAX) return;
                var dup = files.some(function (x) {
                    return x.name === f.name && x.size === f.size && x.lastModified === f.lastModified;
                });
                if (dup) return;
                files.push(f);
                added++;
            });
            if (rejected) setError('Некоторые файлы не подходят по формату. Нужны JPG, PNG или WebP.');
            else if (arr.length > 0 && added === 0) setError('Можно добавить не больше ' + MAX + ' фотографий.');
            else setError('');
            syncInput();
            render();
        }

        function render() {
            box.innerHTML = '';
            box.hidden = files.length === 0;
            files.forEach(function (f, i) {
                var el = document.createElement('div');
                el.className = 'photo-preview' + (i === 0 ? ' is-first' : '');
                el.title = f.name + ' · ' + Math.round(f.size / 1024) + ' КБ';
                if (/^image\//.test(f.type)) {
                    var img = document.createElement('img');
                    img.src = URL.createObjectURL(f);
                    img.alt = 'Фотография ' + (i + 1);
                    el.appendChild(img);
                }
                var num = document.createElement('span');
                num.className = 'photo-preview-num';
                num.textContent = String(i + 1);
                el.appendChild(num);
                if (i === 0) {
                    var cover = document.createElement('span');
                    cover.className = 'photo-preview-cover';
                    cover.textContent = 'Главная';
                    el.appendChild(cover);
                }
                var rm = document.createElement('button');
                rm.type = 'button';
                rm.className = 'photo-preview-remove';
                rm.setAttribute('aria-label', 'Удалить фотографию ' + (i + 1));
                rm.textContent = '×';
                rm.addEventListener('click', function () {
                    files.splice(i, 1);
                    syncInput();
                    setError('');
                    render();
                });
                el.appendChild(rm);
                box.appendChild(el);
            });
            if (countEl) countEl.textContent = files.length ? ' · выбрано ' + files.length + ' / ' + MAX : '';
            if (zone) zone.classList.toggle('has-photos', files.length > 0);
        }

        input.addEventListener('change', function () {
            var list = Array.prototype.slice.call(input.files);
            input.value = '';
            addFiles(list);
        });

        if (zone) {
            ['dragover', 'dragenter'].forEach(function (type) {
                zone.addEventListener(type, function (e) { e.preventDefault(); zone.classList.add('is-drag'); });
            });
            ['dragleave', 'dragend', 'drop'].forEach(function (type) {
                zone.addEventListener(type, function (e) { e.preventDefault(); zone.classList.remove('is-drag'); });
            });
            zone.addEventListener('drop', function (e) {
                e.preventDefault();
                if (e.dataTransfer) addFiles(e.dataTransfer.files);
            });
            zone.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); }
            });
        }

        if (input.getAttribute('data-required')) {
            var form = input.form;
            if (form) form.addEventListener('submit', function (e) {
                if (files.length === 0) {
                    e.preventDefault();
                    if (e.stopImmediatePropagation) e.stopImmediatePropagation();
                    setError('Добавьте хотя бы одну фотографию.');
                    if (zone) zone.scrollIntoView({ block: 'center', behavior: 'smooth' });
                }
            });
        }
    };
    window.initPhotoUploader();
})();
</script>
<script>
(function () {
    var input = document.getElementById('header-q');
    if (!input) return;
    var wrap = input.closest('.header-search-wrap');
    var box = document.getElementById('searchSuggest');
    if (!wrap || !box) return;

    var timer = null;
    var seq = 0;
    var rows = [];
    var active = -1;

    function group(title) {
        var g = document.createElement('div');
        g.className = 'search-suggest-group';
        g.textContent = title;
        return g;
    }

    function mark(el, text, q) {
        var lc = text.toLowerCase();
        var i = lc.indexOf(q.toLowerCase());
        if (i === -1) { el.textContent = text; return; }
        el.appendChild(document.createTextNode(text.slice(0, i)));
        var m = document.createElement('mark');
        m.textContent = text.slice(i, i + q.length);
        el.appendChild(m);
        el.appendChild(document.createTextNode(text.slice(i + q.length)));
    }

    function row(conf) {
        var a = document.createElement('a');
        a.className = 'suggest-row';
        a.href = conf.url;
        var badge = document.createElement('span');
        badge.className = 'suggest-badge suggest-badge-' + conf.kind;
        badge.textContent = conf.badgeText;
        a.appendChild(badge);
        var title = document.createElement('span');
        title.className = 's-title';
        mark(title, conf.title, conf.q);
        a.appendChild(title);
        var price = document.createElement('span');
        price.className = 's-price';
        price.textContent = conf.priceLabel;
        a.appendChild(price);
        rows.push(a);
        return a;
    }

    function lotLabel(n) {
        var n10 = n % 10, n100 = n % 100;
        var form = (n10 === 1 && n100 !== 11) ? 'лот' : (n10 >= 2 && n10 <= 4 && (n100 < 10 || n100 >= 20)) ? 'лота' : 'лотов';
        return n + ' ' + form;
    }

    function closeBox() {
        box.hidden = true;
        box.innerHTML = '';
        rows = [];
        active = -1;
    }

    function setActive(idx) {
        if (idx < 0 || idx >= rows.length) return;
        if (active >= 0 && rows[active]) rows[active].classList.remove('is-active');
        active = idx;
        var el = rows[active];
        el.classList.add('is-active');
        var boxTop = box.getBoundingClientRect().top;
        var elTop = el.getBoundingClientRect().top;
        if (elTop < boxTop) {
            box.scrollTop -= boxTop - elTop;
        } else if (el.getBoundingClientRect().bottom > box.getBoundingClientRect().bottom) {
            box.scrollTop += el.getBoundingClientRect().bottom - box.getBoundingClientRect().bottom;
        }
    }

    function render(d) {
        box.innerHTML = '';
        rows = [];
        active = -1;
        var q = (input.value || '').trim();
        if (!d.categories.length && !d.items.length && !d.auctions.length) {
            var e = document.createElement('div');
            e.className = 'search-suggest-empty';
            e.textContent = 'Ничего не найдено по запросу «' + q + '»';
            box.appendChild(e);
        } else {
            if (d.categories.length) {
                box.appendChild(group('Категории'));
                d.categories.forEach(function (c) {
                    box.appendChild(row({
                        kind: 'cat', badgeText: 'категория', title: c.name, q: q,
                        priceLabel: lotLabel(c.count),
                        url: 'index.php?category=' + encodeURIComponent(c.name)
                    }));
                });
            }
            if (d.items.length) {
                box.appendChild(group('Объявления'));
                d.items.forEach(function (it) {
                    box.appendChild(row({
                        kind: 'item', badgeText: 'объявление', title: it.title, q: q,
                        priceLabel: it.price_label, url: it.url
                    }));
                });
            }
            if (d.auctions.length) {
                box.appendChild(group('Аукционы'));
                d.auctions.forEach(function (au) {
                    box.appendChild(row({
                        kind: 'auction', badgeText: 'аукцион', title: au.title, q: q,
                        priceLabel: au.price_label, url: au.url
                    }));
                });
            }
        }
        var all = document.createElement('a');
        all.className = 'suggest-all';
        all.href = d.all_url;
        all.textContent = 'Показать все результаты';
        box.appendChild(all);
        rows.push(all);
        box.hidden = false;
    }

    function fetchSuggest() {
        var q = (input.value || '').trim();
        if (q.length < 2) { closeBox(); return; }
        var my = ++seq;
        fetch('search_suggest.php?q=' + encodeURIComponent(q), { credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (d) { if (my === seq) render(d); })
            .catch(function () { if (my === seq) closeBox(); });
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(fetchSuggest, 220);
    });

    input.addEventListener('keydown', function (e) {
        if (box.hidden) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); setActive(active + 1 < rows.length ? active + 1 : active); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(active - 1 >= 0 ? active - 1 : active); }
        else if (e.key === 'Enter') {
            if (active >= 0 && rows[active]) {
                e.preventDefault();
                window.location.href = rows[active].getAttribute('href');
            }
        } else if (e.key === 'Escape') {
            closeBox();
        }
    });

    input.addEventListener('blur', function () {
        setTimeout(function () {
            if (!wrap.contains(document.activeElement)) closeBox();
        }, 150);
    });

    document.addEventListener('click', function (e) {
        if (!wrap.contains(e.target)) closeBox();
    });
})();
</script>
<script>
(function () {
    var dialog = document.getElementById('loginModal');
    if (!dialog || typeof HTMLDialogElement === 'undefined') return;

    var errorEl = document.getElementById('loginError');
    var form = document.getElementById('loginForm');

    function showError(msg) {
        if (!errorEl || !msg) return;
        errorEl.textContent = msg;
        errorEl.hidden = false;
    }

    function openDialog(next, note) {
        if (errorEl) errorEl.hidden = true;
        var nextEl = form ? form.querySelector('input[name=next]') : null;
        var noteEl = document.getElementById('loginModalNote');
        if (next && nextEl) nextEl.value = next;
        if (noteEl) {
            noteEl.textContent = note || 'Войдите, чтобы продолжить.';
            noteEl.hidden = !note;
            if (!next && nextEl) nextEl.value = 'index.php';
        }
        dialog.showModal();
        var email = document.getElementById('login-email');
        if (email) setTimeout(function () { email.focus(); }, 50);
    }
    window.openLoginPopup = openDialog;

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-login-modal]');
        if (!trigger) return;
        e.preventDefault();
        openDialog();
    });

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-login-next]');
        if (!trigger) return;
        e.preventDefault();
        openDialog(trigger.getAttribute('data-login-next') || 'index.php', 'Войдите, чтобы продолжить.');
    });

    var closeBtn = dialog.querySelector('.post-modal-close');
    if (closeBtn) closeBtn.addEventListener('click', function () { dialog.close(); });
    dialog.addEventListener('click', function (e) {
        if (e.target === dialog) dialog.close();
    });

    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = form.querySelector('button[type=submit]');
        if (btn) {
            btn.disabled = true;
            btn.classList.add('is-loading');
            btn.dataset.label = btn.textContent;
            btn.textContent = 'Входим…';
        }
        fetch('login.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form)
        }).then(function (r) {
            var ct = r.headers.get('Content-Type') || '';
            if (ct.indexOf('application/json') !== -1) {
                return r.json().then(function (d) { return { ok: true, data: d }; });
            }
            return r.text().then(function (t) { return { ok: false, html: t }; });
        }).then(function (res) {
            if (btn) {
                btn.disabled = false;
                btn.classList.remove('is-loading');
                btn.textContent = btn.dataset.label || 'Войти';
            }
            if (res.ok && res.data && res.data.ok && res.data.redirect) {
                if (res.data.redirect === 'post.php' && typeof window.openPostModal === 'function') {
                    dialog.close();
                    window.openPostModal();
                    return;
                }
                window.location.href = res.data.redirect;
                return;
            }
            if (res.ok && res.data) {
                showError(res.data.error || 'Не удалось войти. Попробуйте ещё раз.');
                return;
            }
            showError('Не удалось войти. Попробуйте ещё раз.');
        }).catch(function () {
            if (btn) {
                btn.disabled = false;
                btn.classList.remove('is-loading');
                btn.textContent = btn.dataset.label || 'Войти';
            }
            showError('Сеть недоступна. Попробуйте ещё раз.');
        });
    });
})();
</script>
<script>
(function () {
    if (typeof HTMLDialogElement === 'undefined') return;
    var login = document.getElementById('loginModal');
    var register = document.getElementById('registerModal');
    if (!login || !register) return;

    var errorEl = document.getElementById('registerError');
    var form = document.getElementById('registerForm');

    function showError(msg) {
        if (!errorEl || !msg) return;
        errorEl.textContent = msg;
        errorEl.hidden = false;
    }

    function openRegister() {
        if (errorEl) errorEl.hidden = true;
        if (register.open) return;
        if (login.open) login.close();
        var src = login.querySelector('input[name=next]');
        var dst = register.querySelector('input[name=next]');
        if (src && dst) dst.value = src.value;
        register.showModal();
        var name = document.getElementById('reg-name');
        if (name) setTimeout(function () { name.focus(); }, 50);
    }

    function openLogin() {
        if (login.open) return;
        if (register.open) register.close();
        login.showModal();
        var email = document.getElementById('login-email');
        if (email) setTimeout(function () { email.focus(); }, 50);
    }

    document.querySelectorAll('[data-auth-switch="register"]').forEach(function (el) {
        el.addEventListener('click', function (e) { e.preventDefault(); openRegister(); });
    });
    document.querySelectorAll('[data-auth-switch="login"]').forEach(function (el) {
        el.addEventListener('click', function (e) { e.preventDefault(); openLogin(); });
    });

    var closeBtn = register.querySelector('.post-modal-close');
    if (closeBtn) closeBtn.addEventListener('click', function () { register.close(); });
    register.addEventListener('click', function (e) {
        if (e.target === register) register.close();
    });

    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = form.querySelector('button[type=submit]');
        if (btn) {
            btn.disabled = true;
            btn.classList.add('is-loading');
            btn.dataset.label = btn.textContent;
            btn.textContent = 'Регистрируем…';
        }
        fetch('register.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form)
        }).then(function (r) {
            var ct = r.headers.get('Content-Type') || '';
            if (ct.indexOf('application/json') !== -1) {
                return r.json().then(function (d) { return { ok: true, data: d }; });
            }
            return r.text().then(function (t) { return { ok: false, html: t }; });
        }).then(function (res) {
            if (btn) {
                btn.disabled = false;
                btn.classList.remove('is-loading');
                btn.textContent = btn.dataset.label || 'Зарегистрироваться';
            }
            if (res.ok && res.data && res.data.ok && res.data.redirect) {
                if (res.data.redirect === 'post.php' && typeof window.openPostModal === 'function') {
                    register.close();
                    window.openPostModal();
                    return;
                }
                window.location.href = res.data.redirect;
                return;
            }
            if (res.ok && res.data) {
                showError(res.data.error || 'Не удалось зарегистрироваться. Попробуйте ещё раз.');
                return;
            }
            showError('Не удалось зарегистрироваться. Попробуйте ещё раз.');
        }).catch(function () {
            if (btn) {
                btn.disabled = false;
                btn.classList.remove('is-loading');
                btn.textContent = btn.dataset.label || 'Зарегистрироваться';
            }
            showError('Сеть недоступна. Попробуйте ещё раз.');
        });
    });
})();
</script>
<script>
(function () {
    var KEY = 'kroha_review_dismissed';
    var store = [];
    try { store = JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) {}
    document.querySelectorAll('[data-review-id]').forEach(function (box) {
        var id = box.getAttribute('data-review-id');
        if (id && store.indexOf(id) !== -1) box.hidden = true;
    });
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.review-dismiss');
        if (!btn) return;
        var box = btn.closest('[data-review-id]');
        if (!box) return;
        var id = box.getAttribute('data-review-id');
        if (id && store.indexOf(id) === -1) store.push(id);
        try { localStorage.setItem(KEY, JSON.stringify(store)); } catch (err) {}
        box.hidden = true;
    });
})();
</script>
</body>
</html>

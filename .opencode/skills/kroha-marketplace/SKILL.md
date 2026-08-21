---
name: kroha-marketplace
description: Работа с репозиторием «Кроха» — маркетплейс детских вещей на PHP + SQLite/MySQL (объявления, аукционы, сообщения, отзывы, админка). Используй, когда нужно понять структуру проекта, запустить/остановить локальный сервер, внести изменения в фичи или вёрстку, обновить README.md, прогнать/дописать тесты, проверить php -l и подготовить коммит к публикации на GitHub. Триггеры: «кроха», «маркетплейс», «объявления», «аукцион», «запустить сайт», «обновить README», «проверить ошибки», «подготовить к публикации».
---

# Скилл: разработка и сопровождение проекта «Кроха»

«Кроха» — C2C-маркетплейс детских вещей: продажа по фикс-цене («объявления») и через аукционы. Чистый PHP 8+ без фреймворка, SQLite локально / MySQL на хостинге, ванильный JS и собственная дизайн-система в CSS.

## Быстрый старт (расположение и запуск)

- Корень проекта: **рядом с этим скиллом** — поднимитесь на три уровня вверх от `.opencode/skills/kroha-marketplace/`. Всё остальное в скилле считается относительным от корня проекта.
- Запустить сервис (Windows PowerShell):
  ```powershell
  Start-Process -FilePath "php" -ArgumentList "-S","127.0.0.1:8091","router.php" -WorkingDirectory "<корень проекта>" -WindowStyle Hidden
  ```
- Проверить, что сервер жив: `Invoke-WebRequest -Uri "http://127.0.0.1:8091" -UseBasicParsing`. Ответ 200 — ок.
- Пауза при старте: процессы запускать через `Start-Sleep -Seconds 2` до первой проверки.
- Найти PID по порту 8091, если нужно остановить:
  ```powershell
  Get-NetTCPConnection -LocalPort 8091 -ErrorAction SilentlyContinue | Select-Object -ExpandProperty OwningProcess
  ```

## Установка «с нуля»

1. `php setup.php` — применяет схему, создаёт `database/kroha.db`.
2. `php seed.php` — загружает демо-данные.
3. Запустить сервер (см. выше).
4. `php tests/run_tests.php` — набрать 123/123 OK.

Демо-аккаунты из `seed.php`: `masha@kroha.test` / `sveta@kroha.test` / `anna@kroha.test`, пароль `demo`; админ `admin@kroha.test` / `admin123`. Тестовый пользователь сид-скрипта `test@test.ru` / `Test1234!`.

## Структура (что где лежит)

- `index.php` — каталог: режимы `home` / `catalog` / `items` / `auctions` через `$mode` (получается из `$_GET['type']`). Вкладки «Все/Объявления/Аукционы» показываются при `$mode !== 'home'`; использование `data-modal-post`/`data-modal-item` и `$showPills` — см. текущее состояние файла, не «по памяти».
- `item.php`, `auction.php` — карточки объявления и лота.
- `post.php` + `includes/photo_preview.php` — размещение объявления (модалка `data-modal-item`).
- `auction_new.php`, `auction_form.php` — создание аукциона (модалка `data-modal-auction`).
- `my_items.php`, `my_auctions.php`, `my_bids.php`, `favorites.php`, `history.php`, `edit_item.php` — личные подборки.
- `account.php` — личный кабинет: разделы через `?tab=` (`overview`, `items`, `auctions`, `bids`, `reviews`, `favorites`, `history`, `messages`, `notifications`). Секция вывода кабинета — `includes/account_aside.php` (активная вкладка — `$accountActive`).
- `manage.php` — переходы «продано/снять/удалить» и выбор покупателя.
- `message.php`, `messages.php`, `includes/messages_section.php` — личные сообщения (чат).
- `notifications.php` — редирект на `account.php?tab=notifications`; `notifications_poll.php` — AJAX-опрос непрочитанных.
- `profile.php` — публичный профиль с рейтингом и отзывами.
- `admin.php` — панель администратора (модерация отзывов, жалоб; только `is_admin=1`).
- `login.php`, `register.php`, `logout.php`, `forgot_password.php`, `reset_password.php` — аутентификация; `help.php`, `policy.php` — статичные.
- `includes/db.php` — фабрика `pdo()` (SQLite или MySQL по `DB_DRIVER`).
- `includes/functions.php` — вся бизнес-логика: хелперы вывода (`e`, `money`, `photos_of`, `aged`-строки), сессии (`start_session`, `csrf_token`/`csrf_check`), пользователи (`current_user`, `require_login`, `require_admin`), аукционы (`auction_state`, `finalize_auction`, `finalize_due_auctions`), уведомления (`notify`), избранное (`favorite_state`, `toggle_favorite`), сделки и отзывы (`mark_item_sold`, `add_review`, `confirm_receipt`, `can_review`), жалобы (`report_listing`), история просмотров (`record_view`, `view_history`).
- `includes/header.php`, `includes/footer.php` — шапка/подвал и JS. **Внимание:** эти файлы и `account.php`/`index.php` уже откатывались — править аккуратно, сверяться с фактическим содержимым перед Edit.
- `includes/card_item.php`, `includes/card_auction.php` — карточки в каталоге.
- `assets/css/styles.css` — единственный CSS (дизайн-система: секции, кнопки `.btn btn-primary/btn-secondary/btn-ghost`, табы `.tab`, карточки `.card`).
- `database/schema.sqlite.sql`, `database/schema.mysql.sql` — совместимые схемы; `database/kroha.db` — рабочая БД, **в git не коммитится**.
- `tests/run_tests.php` — автономный раннер 123 проверки на `sqlite::memory:`; свои хелперы: `check()`, `assert_eq()`, `fresh_db()`, `section()`.

## Внесение изменений

- Сначала прочитать текущее состояние файла (особенно `index.php`, `account.php`, шаблоны), потом Edit. Никогда не восстанавливать файлы «из головы».
- PHP — `declare(strict_types=1);`, типизированные сигнатуры, подготовленные запросы (PDO), экранирование вывода через `e()`. Новые функции — в `includes/functions.php`, а не в страницах.
- CSRF: любой POST-обработчик проверяет `csrf_check()` и на форме есть `<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">`.
- Цены хранятся в копейках/рублях как целые (см. `money()`/`price` в схеме) — форматируйте через `money()`, не вручную.
- Категории и их иконки централизованы в `categories()`; возраста/размеры — поля с ключами, не свободный текст (проверяйте по схеме).
- Вёрстка: следовать паттернам `styles.css`. Кнопки `btn-ghost` — без рамки, `.section-head-action` — правое действие в строке с заголовком. Новые ui-паттерны сначала искать в существующих страницах.
- После изменения **любой** страницы: `php -l <файл>` и `php tests/run_tests.php` (123/123).
- Если менялся тэмплейт/выходные атрибуты `data-modal-*` — сверить JS-обработчик в `includes/footer.php`.
- Изменения в БД: прописывать в ОБЕ схемы (`schema.sqlite.sql` и `schema.mysql.sql`), а при необходимости и в тестовый `fresh_db()`.

## Проверка ошибок

- Синтаксис всех PHP: `Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }`.
- Тесты: `php tests/run_tests.php`; норма — все `OK`, счётчики `tests=123 failed=0 skipped=0`.
- Браузерная проверка (если есть Playwright): открыть `http://127.0.0.1:8091`, убедиться в 200, проверить затронутую страницу (логин через демо-аккаунт), смотреть console на ошибки.
- Сервер должен быть поднят перед браузерной проверкой.

## Обновление README.md

- README — единственный источник «что это за проект» и публикуется на GitHub. Держать актуальным: структура, функционал, технологии, установка, демо-аккаунты, счётчик тестов.
- При изменении количества тестов — поменять «123 проверки» и в README, и в `tests/run_tests.php`, и этой цифрой не разбрасываться.
- При изменении структуры (новые страницы/папки) — обновить блок «Структура проекта» и «Технологии».
- README пишется на русском, markdown, без лишних бейджей; упоминания внутренних доков, которых нет в репо (PRD, исследования), в README не добавлять.

## Подготовка к публикации (Git)

- Репозиторий: `github.com/Yaroslava-111/kroha-marketplace`, ветка `master`, author `Yaroslava-111 <318971482+Yaroslava-111@users.noreply.github.com>` (git user.name/email установлены локально только в этом репо — не менять и не трогать глобальный config).
- В коммит **не должны попасть**: `database/kroha.db*`, `uploads/*` (кроме `.gitkeep`), `*.log`, скриншоты (png), временные файлы, PRD/исследования/прочие внутренние доки.
- Перед публикацией: `git status --short`, сверить с файлом `uploads/.gitkeep` и `.gitignore`, убедиться, что нет лишних `*.png`. Если что-то лишнее было застейджено — `git rm --cached` или удалить файл.
- Команды: `git add -A`; `git commit -m "<краткое описание>"`; `git push`. Перед пушем обязательно `php tests/run_tests.php` полный прогон.
- GitHub CLI установлен: `C:\Program Files\GitHub CLI\gh.exe` (использовать `& "путь"` при вызове).

## Правила поведения агента

- Язык общения с пользователем — русский (как и комментарии/доки проекта).
- Ничего не ставить и не устанавливать (npm, браузеры и т.п.) без явного разрешения пользователя.
- Не коммитить и не пушить без явной просьбы.
- Не переписывать файлы целиком по памяти — всегда `Read` перед `Edit`.
- Если задача меняет поведение сайта — проверить через сервер и тесты, не только глазами.
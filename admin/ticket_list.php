<?php
/**
 * Страница управления обращениями — модуль bitrix.ticketmanager
 * Путь в админке: /bitrix/admin/ticket_manager_list.php
 */
define('NO_KEEP_STATISTIC', true);
define('STOP_STATISTICS', true);
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Localization\Loc;

Loc::loadLanguageFile(__FILE__);

// Проверки доступа
if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm(Loc::getMessage('TM_ACCESS_DENIED'));
}
if (!CModule::IncludeModule('support')) {
    ShowError(Loc::getMessage('TM_NO_SUPPORT_MODULE'));
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    exit;
}

$_TM_MODULE_DIR = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/bitrix.ticketmanager';

require_once $_TM_MODULE_DIR . '/include/filter.php';
require_once $_TM_MODULE_DIR . '/include/actions.php';

$PAGE_URL  = '/bitrix/admin/ticket_manager_list.php';
$sLang     = 'lang=' . LANGUAGE_ID;

// ------------------------------------------------------------------ Параметры
$currentPage = max(1, intval($_GET['page'] ?? 1));
$perPage     = max(10, min(200, intval($_GET['per_page'] ?? 50)));
$filterActive = isset($_GET['filter_submitted']);

$filter  = new BitrixTicketManagerFilter();
$actions = new BitrixTicketManagerActions($filter, $PAGE_URL);

// ------------------------------------------------------------------ POST
$actions->handle();

// ------------------------------------------------------------------ Данные
$result = $actions->fetchTickets(true, $currentPage, $perPage);
$rows   = $result['rows'];
$total  = $result['total'];
$pages  = $total > 0 ? (int)ceil($total / $perPage) : 1;

// ------------------------------------------------------------------ Уведомления
$noticeDeleted = intval($_GET['deleted'] ?? 0);
$noticeMsg     = strval($_GET['msg'] ?? '');

// ------------------------------------------------------------------ Заголовок
$APPLICATION->SetTitle(Loc::getMessage('TM_PAGE_TITLE'));

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

// ------------------------------------------------------------------ CSS
?>
<style>
.tm-wrap          { font-family: Arial, sans-serif; font-size: 13px; }
.tm-filter-block  { background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px;
                    padding: 14px 16px; margin-bottom: 16px; }
.tm-filter-block h3 { margin: 0 0 10px; font-size: 14px; font-weight: bold; }
.tm-filter-row    { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; }
.tm-filter-group  { display: flex; flex-direction: column; gap: 3px; }
.tm-filter-group label { font-size: 12px; color: #555; }
.tm-filter-group input[type=text],
.tm-filter-group input[type=date],
.tm-filter-group select { padding: 4px 7px; border: 1px solid #ccc; border-radius: 3px;
                           font-size: 13px; min-width: 140px; }
.tm-filter-cb     { display: flex; align-items: center; gap: 5px; padding-bottom: 2px; }
.tm-filter-cb input { width: 15px; height: 15px; }
.tm-toolbar       { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; flex-wrap: wrap; }
.tm-toolbar select { padding: 4px 7px; border: 1px solid #ccc; border-radius: 3px; font-size: 13px; }
.tm-btn           { display: inline-flex; align-items: center; gap: 5px;
                    padding: 5px 12px; border-radius: 3px; border: 1px solid transparent;
                    font-size: 13px; cursor: pointer; text-decoration: none; }
.tm-btn-primary   { background: #3d7fc1; color: #fff; border-color: #2f6399; }
.tm-btn-primary:hover { background: #2f6399; }
.tm-btn-danger    { background: #c0392b; color: #fff; border-color: #962d22; }
.tm-btn-danger:hover  { background: #962d22; }
.tm-btn-default   { background: #f0f0f0; color: #333; border-color: #bbb; }
.tm-btn-default:hover { background: #e0e0e0; }
.tm-btn-export    { background: #27ae60; color: #fff; border-color: #1e8449; }
.tm-btn-export:hover  { background: #1e8449; }
.tm-table         { width: 100%; border-collapse: collapse; font-size: 13px; }
.tm-table th      { background: #4b7dc8; color: #fff; padding: 7px 10px;
                    text-align: left; font-weight: normal; white-space: nowrap; }
.tm-table td      { padding: 6px 10px; border-bottom: 1px solid #e8e8e8; vertical-align: middle; }
.tm-table tr:nth-child(even) td { background: #f9f9f9; }
.tm-table tr:hover td { background: #eef3fb; }
.tm-table td.tm-spam { color: #c0392b; font-weight: bold; }
.tm-check-col     { width: 28px; text-align: center; }
.tm-id-col        { width: 60px; }
.tm-date-col      { width: 140px; white-space: nowrap; }
.tm-notice        { padding: 9px 14px; border-radius: 4px; margin-bottom: 12px; font-size: 13px; }
.tm-notice-ok     { background: #d4efdf; border: 1px solid #a9dfbf; color: #1e8449; }
.tm-notice-warn   { background: #fef9e7; border: 1px solid #f9ca24; color: #7d6608; }
.tm-notice-err    { background: #fadbd8; border: 1px solid #f1948a; color: #922b21; }
.tm-pager         { display: flex; gap: 4px; margin-top: 12px; flex-wrap: wrap; align-items: center; }
.tm-pager a, .tm-pager span { padding: 4px 9px; border: 1px solid #ccc; border-radius: 3px;
                               font-size: 13px; text-decoration: none; color: #333; }
.tm-pager .tm-cur { background: #3d7fc1; color: #fff; border-color: #2f6399; }
.tm-pager a:hover { background: #e8edf5; }
.tm-summary       { color: #555; font-size: 12px; margin-left: auto; }
.tm-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45);
                    z-index: 9999; align-items: center; justify-content: center; }
.tm-modal-overlay.active { display: flex; }
.tm-modal         { background: #fff; border-radius: 6px; padding: 24px 28px; max-width: 460px;
                    width: 90%; box-shadow: 0 8px 32px rgba(0,0,0,.18); }
.tm-modal h3      { margin: 0 0 12px; font-size: 15px; }
.tm-modal p       { margin: 0 0 18px; font-size: 13px; color: #444; }
.tm-modal-btns    { display: flex; gap: 8px; justify-content: flex-end; }
.tm-empty         { text-align: center; padding: 40px; color: #888; font-size: 14px; }
</style>

<div class="tm-wrap">
<?php

// ------------------------------------------------------------------ Notices
if ($noticeDeleted > 0):?>
<div class="tm-notice tm-notice-ok">
    ✓ Удалено обращений: <strong><?= $noticeDeleted ?></strong>
</div>
<?php endif;

if ($noticeMsg === 'no_ids'):?>
<div class="tm-notice tm-notice-warn">Не выбрано ни одного обращения.</div>
<?php elseif ($noticeMsg === 'not_confirmed'):?>
<div class="tm-notice tm-notice-warn">Удаление не подтверждено.</div>
<?php endif;

if ($filter->getErrors()):?>
<div class="tm-notice tm-notice-err">
    <?= implode('<br>', array_map('htmlspecialcharsbx', $filter->getErrors())) ?>
</div>
<?php endif; ?>

<!-- ============================================================ ФИЛЬТР -->
<form method="get" action="<?= $PAGE_URL ?>">
<input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
<input type="hidden" name="filter_submitted" value="Y">
<div class="tm-filter-block">
    <h3>Фильтр</h3>
    <div class="tm-filter-row">

        <div class="tm-filter-group">
            <label>ID обращения</label>
            <input type="text" name="filter_id" value="<?= htmlspecialcharsbx($filter->get('filter_id')) ?>" style="min-width:80px">
        </div>

        <div class="tm-filter-group">
            <label>Заголовок содержит</label>
            <input type="text" name="filter_title" value="<?= htmlspecialcharsbx($filter->get('filter_title')) ?>">
        </div>

        <div class="tm-filter-group">
            <label>Email автора</label>
            <input type="text" name="filter_email" value="<?= htmlspecialcharsbx($filter->get('filter_email')) ?>" placeholder="user@example.com">
        </div>

        <div class="tm-filter-group">
            <label>Маска email (спам)</label>
            <input type="text" name="filter_email_mask" value="<?= htmlspecialcharsbx($filter->get('filter_email_mask')) ?>" placeholder="*@spamsite.ru">
        </div>

        <div class="tm-filter-group">
            <label>Дата от</label>
            <input type="date" name="filter_date_from" value="<?= htmlspecialcharsbx($filter->get('filter_date_from')) ?>">
        </div>

        <div class="tm-filter-group">
            <label>Дата до</label>
            <input type="date" name="filter_date_to" value="<?= htmlspecialcharsbx($filter->get('filter_date_to')) ?>">
        </div>

        <div class="tm-filter-group">
            <label>Показывать по</label>
            <select name="per_page">
                <?php foreach ([25, 50, 100, 200] as $pp): ?>
                <option value="<?= $pp ?>" <?= $pp === $perPage ? 'selected' : '' ?>><?= $pp ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="tm-filter-group" style="justify-content:flex-end">
            <div class="tm-filter-cb">
                <input type="checkbox" id="cb_no_type" name="filter_no_type" value="Y"
                    <?= $filter->get('filter_no_type') === 'Y' ? 'checked' : '' ?>>
                <label for="cb_no_type" style="font-size:13px;color:#333">Только без типа (спам)</label>
            </div>
        </div>

    </div>
    <div style="display:flex;gap:8px;margin-top:12px">
        <button type="submit" class="tm-btn tm-btn-primary">🔍 Применить</button>
        <a href="<?= $PAGE_URL ?>?<?= $sLang ?>" class="tm-btn tm-btn-default">Сбросить</a>
    </div>
</div>
</form>

<!-- ============================================================ ТАБЛИЦА -->
<form method="post" action="<?= $PAGE_URL ?>?<?= $sLang ?>&<?= http_build_query($_GET) ?>" id="tm-main-form">
<?= bitrix_sessid_post() ?>

<div class="tm-toolbar">
    <select name="action" id="tm-action-select">
        <option value="">— Действие —</option>
        <option value="delete_selected">Удалить выбранные</option>
        <option value="export_csv">Экспорт в CSV</option>
    </select>
    <button type="button" class="tm-btn tm-btn-primary" onclick="tmApplyAction()">Применить</button>

    <?php if ($filterActive && $total > 0): ?>
    <button type="button" class="tm-btn tm-btn-danger" onclick="tmShowDeleteAll()">
        🗑 Удалить все по фильтру (<?= $total ?>)
    </button>
    <?php endif; ?>

    <span class="tm-summary">Найдено: <strong><?= $total ?></strong></span>
</div>

<?php if (empty($rows)): ?>
<div class="tm-empty">Обращений не найдено</div>
<?php else: ?>

<table class="tm-table">
    <thead>
        <tr>
            <th class="tm-check-col">
                <input type="checkbox" id="tm-check-all" title="Выбрать все"
                       onchange="tmToggleAll(this.checked)">
            </th>
            <th class="tm-id-col">ID</th>
            <th>Заголовок</th>
            <th>Email автора</th>
            <th class="tm-date-col">Дата</th>
            <th style="width:60px">Спам</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $t):
        $isSpam  = BitrixTicketManagerFilter::isSpam($t['TITLE']);
        $editUrl = '/bitrix/admin/ticket_edit.php?ID=' . intval($t['ID']) . '&' . $sLang;
    ?>
    <tr>
        <td class="tm-check-col">
            <input type="checkbox" name="ticket_ids[]" value="<?= intval($t['ID']) ?>" class="tm-row-cb">
        </td>
        <td class="tm-id-col">
            <a href="<?= htmlspecialcharsbx($editUrl) ?>" target="_blank"><?= intval($t['ID']) ?></a>
        </td>
        <td><?= htmlspecialcharsbx($t['TITLE']) ?></td>
        <td><?= htmlspecialcharsbx($t['OWNER_SID']) ?></td>
        <td class="tm-date-col"><?= htmlspecialcharsbx($t['TIMESTAMP_X']) ?></td>
        <td <?= $isSpam ? 'class="tm-spam"' : '' ?>><?= $isSpam ? 'Да' : '' ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- Пагинация -->
<?php if ($pages > 1):
    $baseQs = http_build_query(array_merge($_GET, ['per_page' => $perPage]));
?>
<div class="tm-pager">
    <?php if ($currentPage > 1): ?>
        <a href="<?= $PAGE_URL ?>?<?= $baseQs ?>&page=<?= $currentPage - 1 ?>">&#8249;</a>
    <?php endif; ?>

    <?php
    $range = 5;
    $start = max(1, $currentPage - $range);
    $end   = min($pages, $currentPage + $range);
    if ($start > 1) echo '<span>…</span>';
    for ($p = $start; $p <= $end; $p++):
    ?>
        <?php if ($p === $currentPage): ?>
        <span class="tm-cur"><?= $p ?></span>
        <?php else: ?>
        <a href="<?= $PAGE_URL ?>?<?= $baseQs ?>&page=<?= $p ?>"><?= $p ?></a>
        <?php endif; ?>
    <?php endfor;
    if ($end < $pages) echo '<span>…</span>'; ?>

    <?php if ($currentPage < $pages): ?>
        <a href="<?= $PAGE_URL ?>?<?= $baseQs ?>&page=<?= $currentPage + 1 ?>">&#8250;</a>
    <?php endif; ?>
    <span class="tm-summary" style="margin-left:8px">
        Стр. <?= $currentPage ?> из <?= $pages ?>
    </span>
</div>
<?php endif; ?>
<?php endif; ?>

</form>

<!-- ============================================================ МОДАЛЬНОЕ ОКНО удаления по фильтру -->
<div class="tm-modal-overlay" id="tm-del-all-modal">
    <div class="tm-modal">
        <h3>Удалить все обращения по фильтру</h3>
        <p>Будет удалено <strong><?= $total ?></strong> обращений. Действие необратимо. Продолжить?</p>
        <form method="post" action="<?= $PAGE_URL ?>?<?= $sLang ?>&<?= http_build_query($_GET) ?>">
            <?= bitrix_sessid_post() ?>
            <input type="hidden" name="action" value="delete_by_filter">
            <input type="hidden" name="confirm_delete" value="Y">
            <div class="tm-modal-btns">
                <button type="button" class="tm-btn tm-btn-default" onclick="tmHideDeleteAll()">Отмена</button>
                <button type="submit" class="tm-btn tm-btn-danger">Удалить <?= $total ?></button>
            </div>
        </form>
    </div>
</div>

</div><!-- /tm-wrap -->

<script>
function tmToggleAll(checked) {
    document.querySelectorAll('.tm-row-cb').forEach(cb => cb.checked = checked);
}
function tmApplyAction() {
    var action = document.getElementById('tm-action-select').value;
    if (!action) { alert('Выберите действие'); return; }
    var checked = document.querySelectorAll('.tm-row-cb:checked');
    if (action === 'delete_selected') {
        if (checked.length === 0) { alert('Не выбрано ни одного обращения'); return; }
        if (!confirm('Удалить ' + checked.length + ' выбранных обращений?')) return;
    }
    document.getElementById('tm-main-form').submit();
}
function tmShowDeleteAll() {
    document.getElementById('tm-del-all-modal').classList.add('active');
}
function tmHideDeleteAll() {
    document.getElementById('tm-del-all-modal').classList.remove('active');
}
// Закрытие по клику вне модалки
document.getElementById('tm-del-all-modal').addEventListener('click', function(e) {
    if (e.target === this) tmHideDeleteAll();
});
</script>

<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';

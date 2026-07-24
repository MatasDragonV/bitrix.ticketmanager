<?php
/**
 * Страница управления обращениями — модуль bitrix.ticketmanager
 * Использует стандартный Битрикс-grid (CAdminList + CAdminFilter)
 */
define('NO_KEEP_STATISTIC', true);
define('STOP_STATISTICS', true);
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Localization\Loc;

Loc::loadLanguageFile(__FILE__);

if (!$USER->IsAdmin()) $APPLICATION->AuthForm(Loc::getMessage('TM_ACCESS_DENIED'));

if (!CModule::IncludeModule('support')) {
    ShowError(Loc::getMessage('TM_NO_SUPPORT_MODULE'));
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    exit;
}

$_TM_MODULE_DIR = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/bitrix.ticketmanager';
require_once $_TM_MODULE_DIR . '/include/filter.php';
require_once $_TM_MODULE_DIR . '/include/actions.php';

$MODULE_ID = 'bitrix.ticketmanager';
$PAGE_URL  = '/bitrix/admin/ticket_manager_list.php';
$sLang     = 'lang=' . LANGUAGE_ID;

// ------------------------------------------------------------------ Grid ID и сортировка
$GRID_ID   = 'bitrix_ticketmanager_list';
$FILTER_ID = 'bitrix_ticketmanager_filter';

$gridOptions = new CGridOptions($GRID_ID);
$sort        = $gridOptions->GetSorting(['sort' => ['TIMESTAMP_X' => 'desc'], 'vars' => ['by' => 'by', 'order' => 'order']]);
$navParams   = $gridOptions->GetNavParams(['nPageSize' => 50]);

// ------------------------------------------------------------------ Фильтр (стандартный CAdminFilter)
$filterFields = [
    ['id' => 'ID',              'name' => Loc::getMessage('TM_COL_ID'),       'type' => 'number'],
    ['id' => 'TITLE',           'name' => Loc::getMessage('TM_COL_TITLE'),    'type' => 'string'],
    ['id' => 'OWNER_SID',       'name' => Loc::getMessage('TM_COL_EMAIL'),    'type' => 'string'],
    ['id' => 'EMAIL_MASK',      'name' => Loc::getMessage('TM_FILTER_EMAIL_MASK'), 'type' => 'string'],
    ['id' => 'DATE_FROM',       'name' => Loc::getMessage('TM_FILTER_DATE_FROM'), 'type' => 'date'],
    ['id' => 'DATE_TO',         'name' => Loc::getMessage('TM_FILTER_DATE_TO'),   'type' => 'date'],
    ['id' => 'ONLY_NO_TYPE',    'name' => Loc::getMessage('TM_FILTER_NO_TYPE'),   'type' => 'checkbox'],
];

$oFilter = new CAdminFilter($FILTER_ID, array_column($filterFields, 'name'));

// Читаем значения из фильтра
$filterVals = [];
if ($oFilter->isFilterActive()) {
    foreach ($filterFields as $ff) {
        $v = $oFilter->getFilterValue($ff['id']);
        if ($v !== false && $v !== '') $filterVals[$ff['id']] = $v;
    }
}

$filter  = new BitrixTicketManagerFilter($filterVals);
$actions = new BitrixTicketManagerActions($filter, $PAGE_URL);

// ------------------------------------------------------------------ POST-действия
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    $action = strval($_POST['action'] ?? $_POST['action_button'] ?? '');

    if ($action === 'delete_by_filter') {
        if ($_POST['confirm_delete'] === 'Y') {
            // Запускаем через AJAX прогресс — редирект на прогресс-страницу
            LocalRedirect($PAGE_URL . '?delete_by_filter=Y&sessid=' . bitrix_sessid() . '&' . $sLang . '&' . http_build_query($_GET));
            exit;
        }
    }

    if ($action === 'export_csv') {
        $actions->exportCsv();
        exit;
    }

    // Стандартные действия грида (delete_selected)
    if (in_array($action, ['delete', 'delete_selected'])) {
        $ids = array_map('intval', (array)($_POST['ID'] ?? $_POST['ticket_ids'] ?? []));
        $ids = array_filter($ids, fn($id) => $id > 0);
        $deleted = 0;
        foreach ($ids as $id) {
            if (CTicket::Delete($id)) $deleted++;
        }
        LocalRedirect($PAGE_URL . '?deleted=' . $deleted . '&' . $sLang);
        exit;
    }
}

// ------------------------------------------------------------------ Данные для грида
$by    = $sort['sort'] ? array_key_first($sort['sort']) : 'TIMESTAMP_X';
$order = $sort['sort'][$by] ?? 'desc';

$result = $actions->fetchForDisplay($navParams, $by, $order);
$rows   = $result['rows'];
$total  = $result['total'];

// ------------------------------------------------------------------ Заголовки колонок
$headers = [
    ['id' => 'ID',          'content' => Loc::getMessage('TM_COL_ID'),      'sort' => 'ID',          'default' => true],
    ['id' => 'TITLE',       'content' => Loc::getMessage('TM_COL_TITLE'),   'sort' => 'TITLE',       'default' => true],
    ['id' => 'MESSAGE',     'content' => Loc::getMessage('TM_COL_MESSAGE'), 'sort' => false,          'default' => true],
    ['id' => 'OWNER_SID',   'content' => Loc::getMessage('TM_COL_EMAIL'),   'sort' => 'OWNER_SID',   'default' => true],
    ['id' => 'TIMESTAMP_X', 'content' => Loc::getMessage('TM_COL_DATE'),    'sort' => 'TIMESTAMP_X', 'default' => true],
    ['id' => 'IS_SPAM',     'content' => Loc::getMessage('TM_COL_SPAM'),    'sort' => false,          'default' => true],
];

$APPLICATION->SetTitle(Loc::getMessage('TM_PAGE_TITLE'));
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

// ------------------------------------------------------------------ Уведомления
$noticeDeleted = intval($_GET['deleted'] ?? 0);
if ($noticeDeleted > 0) {
    CAdminMessage::ShowMessage(['MESSAGE' => Loc::getMessage('TM_NOTICE_DELETED', ['#N#' => $noticeDeleted]), 'TYPE' => 'OK']);
}

// ------------------------------------------------------------------ Стандартный фильтр
$oFilter->Begin();
foreach ($filterFields as $ff):
    echo '<tr><td class="adm-filter-label">' . htmlspecialcharsbx($ff['name']) . ':</td><td>';
    $val = $oFilter->getFilterValue($ff['id']);
    switch ($ff['type']) {
        case 'checkbox':
            echo '<input type="checkbox" name="' . $ff['id'] . '" id="' . $ff['id'] . '" value="Y"' . ($val === 'Y' ? ' checked' : '') . '>';
            break;
        case 'date':
            echo CAdminCalendar::CalendarDate($ff['id'], $val ?: '');
            break;
        default:
            echo '<input type="text" name="' . $ff['id'] . '" value="' . htmlspecialcharsbx($val ?: '') . '" class="adm-input">';
    }
    echo '</td></tr>';
endforeach;
$oFilter->Buttons();
$oFilter->End();

// ------------------------------------------------------------------ Grid
$lAdmin = new CAdminList($GRID_ID, new CAdminSorting($GRID_ID));
$lAdmin->AddHeaders($headers);

foreach ($rows as $row) {
    $isSpam  = BitrixTicketManagerFilter::isSpam($row['TITLE']);
    $editUrl = '/bitrix/admin/ticket_edit.php?ID=' . intval($row['ID']) . '&' . $sLang;
    $msgText = htmlspecialcharsbx(mb_substr(strip_tags($row['MESSAGE'] ?? ''), 0, 200))
               . (mb_strlen(strip_tags($row['MESSAGE'] ?? '')) > 200 ? '…' : '');

    $oRow = $lAdmin->AddRow($row['ID'], $row);
    $oRow->AddViewField('ID',
        '<a href="' . htmlspecialcharsbx($editUrl) . '">' . intval($row['ID']) . '</a>'
    );
    $oRow->AddViewField('TITLE',    htmlspecialcharsbx($row['TITLE']));
    $oRow->AddViewField('MESSAGE',  '<span style="color:#555;font-size:12px">' . $msgText . '</span>');
    $oRow->AddViewField('OWNER_SID', htmlspecialcharsbx($row['OWNER_SID']));
    $oRow->AddViewField('TIMESTAMP_X', htmlspecialcharsbx($row['TIMESTAMP_X']));
    $oRow->AddViewField('IS_SPAM',
        $isSpam ? '<span style="color:#c0392b;font-weight:bold">' . Loc::getMessage('TM_SPAM_YES') . '</span>' : ''
    );

    $oRow->AddActionButton(
        Loc::getMessage('TM_ACTION_VIEW'),
        "window.open('" . CUtil::JSEscape($editUrl) . "','_blank')"
    );
    $oRow->AddDeleteButton();
}

// Кнопки внизу грида
$lAdmin->AddFooter([
    ['title' => Loc::getMessage('TM_TOTAL'), 'value' => $total],
]);

$lAdmin->AddGroupActionTable([
    'delete' => Loc::getMessage('TM_ACTION_DELETE_SELECTED'),
]);

// Кастомные кнопки над гридом
ob_start();
?>
<div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap">
    <?php if ($filter->isActive()): ?>
    <form method="post" action="<?= $PAGE_URL ?>?<?= $sLang ?>&<?= http_build_query($_GET) ?>" id="tm-del-filter-form">
        <?= bitrix_sessid_post() ?>
        <input type="hidden" name="action" value="delete_by_filter">
        <input type="hidden" name="confirm_delete" id="tm-confirm-val" value="">
        <button type="button" class="adm-btn adm-btn-danger" onclick="tmConfirmDeleteAll(<?= $total ?>)">
            🗑 <?= Loc::getMessage('TM_ACTION_DELETE_BY_FILTER', ['#N#' => $total]) ?>
        </button>
    </form>
    <?php endif; ?>

    <form method="post" action="<?= $PAGE_URL ?>?<?= $sLang ?>">
        <?= bitrix_sessid_post() ?>
        <input type="hidden" name="action" value="export_csv">
        <?php foreach ($filterVals as $k => $v): ?>
        <input type="hidden" name="<?= htmlspecialcharsbx($k) ?>" value="<?= htmlspecialcharsbx($v) ?>">
        <?php endforeach; ?>
        <button type="submit" class="adm-btn">
            📥 <?= Loc::getMessage('TM_ACTION_EXPORT_CSV') ?>
        </button>
    </form>

    <a href="/bitrix/admin/ticket_manager_settings.php?<?= $sLang ?>" class="adm-btn">
        ⚙ <?= Loc::getMessage('TM_SETTINGS_LINK') ?>
    </a>
</div>

<!-- Progress bar (скрыт до начала удаления) -->
<div id="tm-progress-wrap" style="display:none;margin-bottom:12px">
    <div style="font-size:13px;margin-bottom:6px">
        <span id="tm-progress-label"><?= Loc::getMessage('TM_PROGRESS_LABEL') ?></span>
        <strong id="tm-progress-count">0</strong> / <strong id="tm-progress-total">0</strong>
    </div>
    <div style="background:#e0e0e0;border-radius:4px;height:18px;overflow:hidden">
        <div id="tm-progress-bar"
             style="background:#3d7fc1;height:100%;width:0%;transition:width 0.3s;border-radius:4px"></div>
    </div>
    <div id="tm-progress-status" style="font-size:12px;color:#555;margin-top:4px"></div>
</div>

<script>
function tmConfirmDeleteAll(total) {
    if (!confirm('<?= CUtil::JSEscape(Loc::getMessage('TM_CONFIRM_DELETE_ALL')) ?>' + total + '<?= CUtil::JSEscape(Loc::getMessage('TM_CONFIRM_DELETE_ALL2')) ?>')) return;
    tmStartDeleteByFilter(total);
}

function tmStartDeleteByFilter(total) {
    var wrap    = document.getElementById('tm-progress-wrap');
    var bar     = document.getElementById('tm-progress-bar');
    var count   = document.getElementById('tm-progress-count');
    var totalEl = document.getElementById('tm-progress-total');
    var status  = document.getElementById('tm-progress-status');

    wrap.style.display    = 'block';
    totalEl.textContent   = total;
    count.textContent     = '0';
    bar.style.width       = '0%';
    status.textContent    = '';

    // Блокируем кнопки
    document.querySelectorAll('#tm-del-filter-form button').forEach(function(b){ b.disabled = true; });

    var deleted = 0;
    var sessid  = '<?= bitrix_sessid() ?>';
    var ajaxUrl = '/bitrix/admin/ticket_manager_ajax.php';
    var filterData = <?= json_encode($filterVals) ?>;

    function doChunk() {
        var params = new URLSearchParams(filterData);
        params.append('sessid', sessid);
        params.append('action', 'delete_chunk');

        fetch(ajaxUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: params.toString()
        })
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (data.error) {
                status.textContent = '<?= CUtil::JSEscape(Loc::getMessage('TM_PROGRESS_ERROR')) ?>: ' + data.error;
                status.style.color = '#c0392b';
                return;
            }
            deleted += data.deleted;
            count.textContent = deleted;

            var pct = total > 0 ? Math.min(100, Math.round(deleted / total * 100)) : 100;
            bar.style.width = pct + '%';
            status.textContent = pct + '%';

            if (data.has_more && data.deleted > 0) {
                doChunk();
            } else {
                bar.style.background = '#27ae60';
                status.style.color   = '#1e8449';
                status.textContent   = '<?= CUtil::JSEscape(Loc::getMessage('TM_PROGRESS_DONE')) ?>';
                setTimeout(function(){
                    window.location.href = '<?= CUtil::JSEscape($PAGE_URL . '?deleted=') ?>' + deleted + '&<?= $sLang ?>';
                }, 1200);
            }
        })
        .catch(function(e) {
            status.textContent = '<?= CUtil::JSEscape(Loc::getMessage('TM_PROGRESS_ERROR')) ?>: ' + e.message;
            status.style.color = '#c0392b';
        });
    }

    doChunk();
}
</script>
<?php
$customHtml = ob_get_clean();
echo $customHtml;

// Пагинация
$nav = new CAdminResultNavigation($navParams['nPageSize'], $total, $navParams['PAGEN_1'] ?? 1);
$lAdmin->AddNavigation($nav);

$lAdmin->CheckAllRows();
$lAdmin->DisplayList();

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';

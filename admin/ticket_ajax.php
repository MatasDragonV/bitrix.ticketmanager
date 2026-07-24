<?php
/**
 * AJAX-эндпоинт для пошагового удаления обращений с прогрессом.
 * /bitrix/admin/ticket_manager_ajax.php
 */
define('NO_KEEP_STATISTIC', true);
define('STOP_STATISTICS', true);
define('NO_AGENT_CHECK', true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

header('Content-Type: application/json; charset=utf-8');

// Проверки
if (!$USER->IsAdmin()) {
    echo json_encode(['error' => 'Access denied']);
    exit;
}
if (!check_bitrix_sessid()) {
    echo json_encode(['error' => 'Invalid session']);
    exit;
}
if (!CModule::IncludeModule('support')) {
    echo json_encode(['error' => 'Support module not found']);
    exit;
}
if (strval($_POST['action'] ?? '') !== 'delete_chunk') {
    echo json_encode(['error' => 'Unknown action']);
    exit;
}

$_TM_MODULE_DIR = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/bitrix.ticketmanager';
require_once $_TM_MODULE_DIR . '/include/filter.php';
require_once $_TM_MODULE_DIR . '/include/actions.php';

// Получаем параметры фильтра из POST
$filterVals = [];
$knownKeys  = ['ID', 'TITLE', 'OWNER_SID', 'EMAIL_MASK', 'DATE_FROM', 'DATE_TO', 'ONLY_NO_TYPE'];
foreach ($knownKeys as $key) {
    if (isset($_POST[$key]) && $_POST[$key] !== '') {
        $filterVals[$key] = $_POST[$key];
    }
}

$filter    = new BitrixTicketManagerFilter($filterVals);
$actions   = new BitrixTicketManagerActions($filter, '');
$chunkSize = max(1, COption::GetOptionInt('bitrix.ticketmanager', 'chunk_size', 100));

// Удаляем одну порцию
$ids     = $actions->fetchIds($chunkSize);
$deleted = 0;
foreach ($ids as $id) {
    if (CTicket::Delete($id)) $deleted++;
}

// Проверяем есть ли ещё
$hasMore = count($ids) === $chunkSize && $deleted > 0;

echo json_encode([
    'deleted'  => $deleted,
    'has_more' => $hasMore,
]);
exit;

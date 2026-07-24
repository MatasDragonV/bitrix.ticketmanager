<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/**
 * Обработчик действий над обращениями.
 * Вызывается до вывода страницы, выполняет POST-действия и редиректит.
 */
class BitrixTicketManagerActions
{
    private BitrixTicketManagerFilter $filter;
    private string $pageUrl;

    public function __construct(BitrixTicketManagerFilter $filter, string $pageUrl)
    {
        $this->filter  = $filter;
        $this->pageUrl = $pageUrl;
    }

    public function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        if (!check_bitrix_sessid()) return;

        $action = strval($_POST['action'] ?? '');

        switch ($action) {
            case 'delete_selected':
                $this->deleteSelected();
                break;
            case 'delete_by_filter':
                $this->deleteByFilter();
                break;
            case 'export_csv':
                $this->exportCsv();
                break;
        }
    }

    // -------------------------------------------------------------------------

    private function deleteSelected(): void
    {
        $ids = array_map('intval', (array)($_POST['ticket_ids'] ?? []));
        $ids = array_filter($ids, fn($id) => $id > 0);

        if (empty($ids)) {
            $this->redirect(['msg' => 'no_ids']);
        }

        $deleted = 0;
        foreach ($ids as $id) {
            if (CTicket::Delete($id)) {
                $deleted++;
            }
        }

        $this->redirect(['deleted' => $deleted]);
    }

    private function deleteByFilter(): void
    {
        // Требуем явного подтверждения
        if ($_POST['confirm_delete'] !== 'Y') {
            $this->redirect(['msg' => 'not_confirmed']);
        }

        $tickets = $this->fetchTickets(false); // все без пагинации
        $deleted = 0;
        foreach ($tickets as $t) {
            if (CTicket::Delete($t['ID'])) {
                $deleted++;
            }
        }

        $this->redirect(['deleted' => $deleted]);
    }

    private function exportCsv(): void
    {
        $tickets = $this->fetchTickets(false);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="tickets_' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        // BOM для Excel
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['ID', 'Заголовок', 'Email', 'Дата', 'Спам'], ';');

        foreach ($tickets as $t) {
            fputcsv($out, [
                $t['ID'],
                $t['TITLE'],
                $t['OWNER_SID'],
                $t['TIMESTAMP_X'],
                BitrixTicketManagerFilter::isSpam($t['TITLE']) ? 'Да' : 'Нет',
            ], ';');
        }

        fclose($out);
        exit;
    }

    // -------------------------------------------------------------------------

    /**
     * Получает обращения по текущему фильтру.
     * @param bool $paginate Применять пагинацию (для preview) или нет (для удаления/экспорта)
     */
    public function fetchTickets(bool $paginate = true, int $page = 1, int $perPage = 50): array
    {
        $f      = $this->filter->getFilter();
        $onlyNoType  = !empty($f['ONLY_NO_TYPE']);
        $emailMask   = $f['EMAIL_MASK'] ?? '';

        // Убираем наши виртуальные ключи, которые CTicket не знает
        unset($f['ONLY_NO_TYPE'], $f['EMAIL_MASK']);

        $tickets = [];
        $offset  = $paginate ? ($page - 1) * $perPage : false;
        $limit   = $paginate ? $perPage : false;
        $count   = 0;
        $total   = 0;

        $rs = CTicket::GetList(
            's_timestamp',
            'desc',
            $f,
            $isFiltered,
            'N', // check_rights — N, т.к. вызывает только администратор
            'N',
            'N',
            false,
            ['SELECT' => []]
        );

        while ($row = $rs->GetNext()) {
            // Постфильтрация
            if ($onlyNoType && !BitrixTicketManagerFilter::isSpam($row['TITLE'])) {
                continue;
            }
            if ($emailMask !== '' && !BitrixTicketManagerFilter::emailMatchesMask($row['OWNER_SID'], $emailMask)) {
                continue;
            }

            $total++;

            if ($paginate) {
                if ($count < ($offset ?? 0)) { $count++; continue; }
                if (count($tickets) >= $perPage) continue;
            }

            $tickets[] = $row;
            $count++;
        }

        return $paginate ? ['rows' => $tickets, 'total' => $total] : $tickets;
    }

    // -------------------------------------------------------------------------

    private function redirect(array $params = []): void
    {
        $qs = http_build_query(array_merge($_GET, $params));
        LocalRedirect($this->pageUrl . '?' . $qs);
        exit;
    }
}

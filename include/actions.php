<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/**
 * Обработчик действий над обращениями.
 */
class BitrixTicketManagerActions
{
    private BitrixTicketManagerFilter $filter;
    private string $pageUrl;

    /** Сколько записей обрабатывать за один проход при массовом удалении */
    const CHUNK_SIZE = 100;

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
            if (CTicket::Delete($id)) $deleted++;
        }

        $this->redirect(['deleted' => $deleted]);
    }

    private function deleteByFilter(): void
    {
        if ($_POST['confirm_delete'] !== 'Y') {
            $this->redirect(['msg' => 'not_confirmed']);
        }

        $deleted = 0;
        $hasMore = true;

        // Удаляем порциями — после каждого удаления смещение не нужно,
        // т.к. удалённые записи выпадают из выборки сами
        while ($hasMore) {
            $ids     = $this->fetchIds(self::CHUNK_SIZE);
            $hasMore = count($ids) === self::CHUNK_SIZE;

            if (empty($ids)) break;

            foreach ($ids as $id) {
                if (CTicket::Delete($id)) $deleted++;
            }

            // Освобождаем память явно
            unset($ids);
        }

        $this->redirect(['deleted' => $deleted]);
    }

    private function exportCsv(): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="tickets_' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM для Excel
        fputcsv($out, ['ID', 'Заголовок', 'Email', 'Дата', 'Спам'], ';');

        $offset = 0;
        $limit  = 200;

        while (true) {
            $rows = $this->fetchPage($limit, $offset);
            if (empty($rows)) break;

            foreach ($rows as $t) {
                fputcsv($out, [
                    $t['ID'],
                    $t['TITLE'],
                    $t['OWNER_SID'],
                    $t['TIMESTAMP_X'],
                    BitrixTicketManagerFilter::isSpam($t['TITLE']) ? 'Да' : 'Нет',
                ], ';');
            }

            $offset += $limit;
            unset($rows);

            if (ob_get_level()) ob_flush();
            flush();
        }

        fclose($out);
        exit;
    }

    // -------------------------------------------------------------------------

    /**
     * Возвращает одну страницу таблицы для отображения.
     * Также считает общее количество через отдельный лёгкий запрос.
     */
    public function fetchForDisplay(int $page = 1, int $perPage = 50): array
    {
        $offset = ($page - 1) * $perPage;
        $rows   = $this->fetchPage($perPage, $offset);
        $total  = $this->countTotal();

        return ['rows' => $rows, 'total' => $total];
    }

    // -------------------------------------------------------------------------
    // Внутренние методы выборки — никогда не тянут всё в память
    // -------------------------------------------------------------------------

    /**
     * Возвращает страницу записей с учётом фильтра.
     * Постфильтрация (ONLY_NO_TYPE, EMAIL_MASK) применяется на PHP-уровне,
     * но курсор не держится открытым — читаем ровно столько, сколько нужно.
     */
    private function fetchPage(int $limit, int $offset): array
    {
        [$dbFilter, $onlyNoType, $emailMask] = $this->prepareFilter();

        $rows    = [];
        $skipped = 0;
        $found   = 0;

        $rs = CTicket::GetList('s_timestamp', 'desc', $dbFilter, $isFiltered, 'N', 'N', 'N', false, ['SELECT' => []]);

        while ($row = $rs->GetNext()) {
            if (!$this->passPostFilter($row, $onlyNoType, $emailMask)) continue;

            if ($skipped < $offset) { $skipped++; continue; }
            if ($found >= $limit) break;

            $rows[] = $row;
            $found++;
        }

        return $rows;
    }

    /**
     * Считает общее количество записей по фильтру без загрузки данных.
     */
    private function countTotal(): int
    {
        [$dbFilter, $onlyNoType, $emailMask] = $this->prepareFilter();

        // Если нет постфильтрации — используем COUNT запрос
        if (!$onlyNoType && $emailMask === '') {
            $rs = CTicket::GetList('s_id', 'asc', $dbFilter, $isFiltered, 'N', 'N', 'N', false, ['SELECT' => ['ID']]);
            return $rs->SelectedRowsCount();
        }

        // С постфильтрацией — придётся пройти по всем, но без загрузки полного контента
        $count = 0;
        $rs    = CTicket::GetList('s_id', 'asc', $dbFilter, $isFiltered, 'N', 'N', 'N', false, ['SELECT' => ['ID', 'TITLE', 'OWNER_SID']]);

        while ($row = $rs->GetNext()) {
            if ($this->passPostFilter($row, $onlyNoType, $emailMask)) $count++;
        }

        return $count;
    }

    /**
     * Возвращает только ID записей (для удаления порциями).
     */
    private function fetchIds(int $limit): array
    {
        [$dbFilter, $onlyNoType, $emailMask] = $this->prepareFilter();

        $ids   = [];
        $rs    = CTicket::GetList('s_id', 'asc', $dbFilter, $isFiltered, 'N', 'N', 'N', false, ['SELECT' => ['ID', 'TITLE', 'OWNER_SID']]);

        while ($row = $rs->GetNext()) {
            if (!$this->passPostFilter($row, $onlyNoType, $emailMask)) continue;
            $ids[] = intval($row['ID']);
            if (count($ids) >= $limit) break;
        }

        return $ids;
    }

    // -------------------------------------------------------------------------

    private function prepareFilter(): array
    {
        $f          = $this->filter->getFilter();
        $onlyNoType = !empty($f['ONLY_NO_TYPE']);
        $emailMask  = strval($f['EMAIL_MASK'] ?? '');

        unset($f['ONLY_NO_TYPE'], $f['EMAIL_MASK']);

        return [$f, $onlyNoType, $emailMask];
    }

    private function passPostFilter(array $row, bool $onlyNoType, string $emailMask): bool
    {
        if ($onlyNoType && !BitrixTicketManagerFilter::isSpam($row['TITLE'])) return false;
        if ($emailMask !== '' && !BitrixTicketManagerFilter::emailMatchesMask($row['OWNER_SID'], $emailMask)) return false;
        return true;
    }

    // -------------------------------------------------------------------------

    private function redirect(array $params = []): void
    {
        $qs = http_build_query(array_merge($_GET, $params));
        LocalRedirect($this->pageUrl . '?' . $qs);
        exit;
    }
}

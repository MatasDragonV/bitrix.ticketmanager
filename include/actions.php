<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

class BitrixTicketManagerActions
{
    private BitrixTicketManagerFilter $filter;
    private string $pageUrl;

    public function __construct(BitrixTicketManagerFilter $filter, string $pageUrl)
    {
        $this->filter  = $filter;
        $this->pageUrl = $pageUrl;
    }

    private function getChunkSize(): int
    {
        return max(1, COption::GetOptionInt('bitrix.ticketmanager', 'chunk_size', 100));
    }

    // -------------------------------------------------------------------------
    // Публичные методы
    // -------------------------------------------------------------------------

    /**
     * Возвращает страницу для отображения в гриде + общее количество.
     * $navParams — массив из CGridOptions::GetNavParams()
     */
    public function fetchForDisplay(array $navParams, string $by = 'TIMESTAMP_X', string $order = 'desc'): array
    {
        $perPage = max(1, intval($navParams['nPageSize'] ?? 50));
        $page    = max(1, intval($navParams['PAGEN_1'] ?? 1));
        $offset  = ($page - 1) * $perPage;

        $rows  = $this->fetchPage($perPage, $offset, $by, $order);
        $total = $this->countTotal();

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Удаляет одну порцию записей по фильтру. Возвращает ['deleted' => N, 'has_more' => bool].
     * Используется AJAX-эндпоинтом.
     */
    public function deleteChunk(): array
    {
        $chunkSize = $this->getChunkSize();
        $ids       = $this->fetchIds($chunkSize);
        $deleted   = 0;

        foreach ($ids as $id) {
            if (CTicket::Delete($id)) $deleted++;
        }

        return [
            'deleted'  => $deleted,
            'has_more' => count($ids) === $chunkSize && $deleted > 0,
        ];
    }

    /**
     * Удаляет выбранные ID.
     */
    public function deleteSelected(array $ids): int
    {
        $deleted = 0;
        foreach (array_filter(array_map('intval', $ids), fn($id) => $id > 0) as $id) {
            if (CTicket::Delete($id)) $deleted++;
        }
        return $deleted;
    }

    /**
     * Экспорт в CSV потоком.
     */
    public function exportCsv(): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="tickets_' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['ID', 'Заголовок', 'Текст', 'Email', 'Дата', 'Спам'], ';');

        $offset = 0;
        $limit  = 200;
        while (true) {
            $rows = $this->fetchPage($limit, $offset);
            if (empty($rows)) break;
            foreach ($rows as $t) {
                fputcsv($out, [
                    $t['ID'],
                    $t['TITLE'],
                    strip_tags($t['MESSAGE'] ?? ''),
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
    // Публичный метод fetchIds (используется AJAX)
    // -------------------------------------------------------------------------

    public function fetchIds(int $limit): array
    {
        [$dbFilter, $onlyNoType, $emailMask] = $this->prepareFilter();
        $ids = [];
        $rs  = CTicket::GetList('s_id', 'asc', $dbFilter, $isFiltered, 'N', 'N', 'N', false,
                                ['SELECT' => ['ID', 'TITLE', 'OWNER_SID']]);
        while ($row = $rs->GetNext()) {
            if (!$this->passPostFilter($row, $onlyNoType, $emailMask)) continue;
            $ids[] = intval($row['ID']);
            if (count($ids) >= $limit) break;
        }
        return $ids;
    }

    // -------------------------------------------------------------------------
    // Внутренние методы
    // -------------------------------------------------------------------------

    private function fetchPage(int $limit, int $offset, string $by = 'TIMESTAMP_X', string $order = 'desc'): array
    {
        [$dbFilter, $onlyNoType, $emailMask] = $this->prepareFilter();

        // Маппинг колонок грида в поля CTicket
        $sortMap = [
            'ID'          => 's_id',
            'TITLE'       => 's_title',
            'OWNER_SID'   => 's_owner',
            'TIMESTAMP_X' => 's_timestamp',
        ];
        $sortField = $sortMap[$by] ?? 's_timestamp';
        $sortOrder = strtolower($order) === 'asc' ? 'asc' : 'desc';

        $rows    = [];
        $skipped = 0;
        $found   = 0;

        $rs = CTicket::GetList($sortField, $sortOrder, $dbFilter, $isFiltered, 'N', 'N', 'N', false,
                               ['SELECT' => ['MESSAGE']]);

        while ($row = $rs->GetNext()) {
            if (!$this->passPostFilter($row, $onlyNoType, $emailMask)) continue;
            if ($skipped < $offset) { $skipped++; continue; }
            if ($found >= $limit) break;
            $rows[] = $row;
            $found++;
        }

        return $rows;
    }

    private function countTotal(): int
    {
        [$dbFilter, $onlyNoType, $emailMask] = $this->prepareFilter();

        if (!$onlyNoType && $emailMask === '') {
            $rs = CTicket::GetList('s_id', 'asc', $dbFilter, $isFiltered, 'N', 'N', 'N', false,
                                   ['SELECT' => ['ID']]);
            return (int)$rs->SelectedRowsCount();
        }

        $count = 0;
        $rs    = CTicket::GetList('s_id', 'asc', $dbFilter, $isFiltered, 'N', 'N', 'N', false,
                                  ['SELECT' => ['ID', 'TITLE', 'OWNER_SID']]);
        while ($row = $rs->GetNext()) {
            if ($this->passPostFilter($row, $onlyNoType, $emailMask)) $count++;
        }
        return $count;
    }

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
}

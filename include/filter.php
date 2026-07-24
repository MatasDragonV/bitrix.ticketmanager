<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/**
 * Класс фильтра обращений.
 * Читает параметры из GET/POST, валидирует, строит массив фильтра для CTicket::GetList().
 */
class BitrixTicketManagerFilter
{
    private array $raw    = [];
    private array $filter = [];
    private array $errors = [];

    /** Допустимые признаки спама (заголовок заканчивается на "Тип: ") */
    public const SPAM_PATTERN = '/Тип:\s*$/u';

    public function __construct()
    {
        $this->raw = array_merge($_GET, $_POST);
        $this->build();
    }

    // -------------------------------------------------------------------------
    // Публичный интерфейс
    // -------------------------------------------------------------------------

    public function getFilter(): array  { return $this->filter; }
    public function getRaw(): array     { return $this->raw; }
    public function getErrors(): array  { return $this->errors; }

    public function get(string $key, $default = ''): mixed
    {
        return $this->raw[$key] ?? $default;
    }

    // -------------------------------------------------------------------------
    // Построение фильтра
    // -------------------------------------------------------------------------

    private function build(): void
    {
        // --- Только без типа (спам) ---
        if ($this->raw['filter_no_type'] === 'Y') {
            // фильтр применим только при переборе: заголовок матчится постфактум
            $this->filter['ONLY_NO_TYPE'] = true;
        }

        // --- Поиск по email (автор) ---
        $email = trim(strval($this->raw['filter_email'] ?? ''));
        if ($email !== '') {
            // CTicket хранит OWNER_SID как email
            $this->filter['OWNER_SID'] = $email;
        }

        // --- Маска email (для массового удаления спама по домену) ---
        $emailMask = trim(strval($this->raw['filter_email_mask'] ?? ''));
        if ($emailMask !== '') {
            $this->filter['EMAIL_MASK'] = $emailMask; // применяем через LIKE в PHP
        }

        // --- Дата от ---
        $dateFrom = trim(strval($this->raw['filter_date_from'] ?? ''));
        if ($dateFrom !== '') {
            if ($this->isValidDate($dateFrom)) {
                $this->filter['>=TIMESTAMP_X'] = $dateFrom . ' 00:00:00';
            } else {
                $this->errors[] = 'Неверный формат даты «от»';
            }
        }

        // --- Дата до ---
        $dateTo = trim(strval($this->raw['filter_date_to'] ?? ''));
        if ($dateTo !== '') {
            if ($this->isValidDate($dateTo)) {
                $this->filter['<=TIMESTAMP_X'] = $dateTo . ' 23:59:59';
            } else {
                $this->errors[] = 'Неверный формат даты «до»';
            }
        }

        // --- Поиск по заголовку ---
        $title = trim(strval($this->raw['filter_title'] ?? ''));
        if ($title !== '') {
            $this->filter['TITLE'] = $title;
        }

        // --- Поиск по ID ---
        $id = intval($this->raw['filter_id'] ?? 0);
        if ($id > 0) {
            $this->filter['ID'] = $id;
        }
    }

    // -------------------------------------------------------------------------
    // Хелперы
    // -------------------------------------------------------------------------

    private function isValidDate(string $date): bool
    {
        // Принимаем DD.MM.YYYY или YYYY-MM-DD
        if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $date)) {
            [$d, $m, $y] = explode('.', $date);
            return checkdate((int)$m, (int)$d, (int)$y);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            [$y, $m, $d] = explode('-', $date);
            return checkdate((int)$m, (int)$d, (int)$y);
        }
        return false;
    }

    /**
     * Проверяет, является ли обращение спамом по заголовку.
     */
    public static function isSpam(string $title): bool
    {
        return (bool)preg_match(self::SPAM_PATTERN, $title);
    }

    /**
     * Проверяет совпадение email с маской (* в конце).
     * Например: *@bizopphand.info, *@goldtip.shop
     */
    public static function emailMatchesMask(string $email, string $mask): bool
    {
        if ($mask === '') return false;
        $regex = '/^' . str_replace(['.', '*'], ['\.', '.*'], preg_quote($mask, '/')) . '$/iu';
        return (bool)preg_match($regex, $email);
    }
}

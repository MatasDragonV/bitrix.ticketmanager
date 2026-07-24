<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/**
 * Класс фильтра обращений.
 * Принимает массив значений (из CAdminFilter или напрямую из POST для AJAX).
 */
class BitrixTicketManagerFilter
{
    private array $raw    = [];
    private array $filter = [];
    private array $errors = [];
    private bool  $active = false;

    public const SPAM_PATTERN = '/Тип:\s*$/u';

    public function __construct(array $values = [])
    {
        $this->raw = $values;
        if (!empty($values)) {
            $this->active = true;
            $this->build();
        }
    }

    public function getFilter(): array { return $this->filter; }
    public function getRaw(): array    { return $this->raw; }
    public function getErrors(): array { return $this->errors; }
    public function isActive(): bool   { return $this->active; }

    public function get(string $key, $default = ''): mixed
    {
        return $this->raw[$key] ?? $default;
    }

    private function build(): void
    {
        if (!empty($this->raw['ONLY_NO_TYPE'])) {
            $this->filter['ONLY_NO_TYPE'] = true;
        }

        $email = trim(strval($this->raw['OWNER_SID'] ?? ''));
        if ($email !== '') {
            $this->filter['OWNER_SID'] = $email;
        }

        $emailMask = trim(strval($this->raw['EMAIL_MASK'] ?? ''));
        if ($emailMask !== '') {
            $this->filter['EMAIL_MASK'] = $emailMask;
        }

        $dateFrom = trim(strval($this->raw['DATE_FROM'] ?? ''));
        if ($dateFrom !== '') {
            if ($this->isValidDate($dateFrom)) {
                $this->filter['>=TIMESTAMP_X'] = $this->normalizeDate($dateFrom) . ' 00:00:00';
            } else {
                $this->errors[] = 'Неверный формат даты «от»';
            }
        }

        $dateTo = trim(strval($this->raw['DATE_TO'] ?? ''));
        if ($dateTo !== '') {
            if ($this->isValidDate($dateTo)) {
                $this->filter['<=TIMESTAMP_X'] = $this->normalizeDate($dateTo) . ' 23:59:59';
            } else {
                $this->errors[] = 'Неверный формат даты «до»';
            }
        }

        $title = trim(strval($this->raw['TITLE'] ?? ''));
        if ($title !== '') {
            $this->filter['TITLE'] = $title;
        }

        $id = intval($this->raw['ID'] ?? 0);
        if ($id > 0) {
            $this->filter['ID'] = $id;
        }
    }

    private function isValidDate(string $date): bool
    {
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

    private function normalizeDate(string $date): string
    {
        if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $date)) {
            [$d, $m, $y] = explode('.', $date);
            return "$y-$m-$d";
        }
        return $date;
    }

    public static function isSpam(string $title): bool
    {
        return (bool)preg_match(self::SPAM_PATTERN, $title);
    }

    public static function emailMatchesMask(string $email, string $mask): bool
    {
        if ($mask === '') return false;
        $regex = '/^' . str_replace(['.', '*'], ['\.', '.*'], preg_quote($mask, '/')) . '$/iu';
        return (bool)preg_match($regex, $email);
    }
}

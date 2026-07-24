<?php
/**
 * Интеграция в меню Битрикс-администратора.
 * Файл копируется в /bitrix/modules/main/admin_menu/ при установке
 * — ИЛИ — подключается через стандартный механизм меню модулей
 * (файл называется именно admin_menu.php и лежит в корне модуля).
 *
 * Добавляет пункт «Управление обращениями» в раздел «Сервисы».
 */

use Bitrix\Main\Localization\Loc;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
if (!CModule::IncludeModule('bitrix.ticketmanager')) return [];

Loc::loadMessages(__DIR__ . '/../lang/' . LANGUAGE_ID . '/install/index.php');

return [
    [
        'parent_menu' => 'global_menu_services',
        'sort'        => 700,
        'text'        => Loc::getMessage('BITRIX_TM_MENU_ITEM') ?: 'Управление обращениями',
        'title'       => Loc::getMessage('BITRIX_TM_MENU_ITEM_TITLE') ?: 'Фильтр и массовое удаление обращений',
        'url'         => 'ticket_manager_list.php?lang=' . LANGUAGE_ID,
        'icon'        => 'support_menu_icon',
        'page_icon'   => 'support_page_icon',
        'items_id'    => 'menu_bitrix_ticketmanager',
        'items'       => [],
    ],
];

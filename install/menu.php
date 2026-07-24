<?php
/**
 * Регистрация пунктов меню в административной панели Битрикс.
 * Битрикс автоматически подключает этот файл если он находится
 * в /bitrix/modules/{module_id}/install/menu.php
 */
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$aMenuLinks = [
    [
        GetMessage('BITRIX_TM_MENU_ITEM'),
        'ticket_manager_list.php',
        [],
        [],
        'CModule::IncludeModule("bitrix.ticketmanager")',
        GetMessage('BITRIX_TM_MENU_ITEM_TITLE'),
    ],
    [
        GetMessage('BITRIX_TM_MENU_SETTINGS'),
        'ticket_manager_settings.php',
        [],
        [],
        'CModule::IncludeModule("bitrix.ticketmanager")',
        GetMessage('BITRIX_TM_MENU_SETTINGS_TITLE'),
    ],
];

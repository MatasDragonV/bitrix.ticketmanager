<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class bitrix_ticketmanager extends CModule
{
    public $MODULE_ID          = 'bitrix.ticketmanager';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';

        $this->MODULE_VERSION      = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME         = Loc::getMessage('BITRIX_TM_MODULE_NAME');
        $this->MODULE_DESCRIPTION  = Loc::getMessage('BITRIX_TM_MODULE_DESC');
        $this->PARTNER_NAME        = '2MBX';
        $this->PARTNER_URI         = 'https://2mbx.ru';
    }

    public function DoInstall()
    {
        global $APPLICATION;

        if (!CModule::IncludeModule('support')) {
            $APPLICATION->ThrowException(Loc::getMessage('BITRIX_TM_ERR_NO_SUPPORT'));
            return false;
        }

        RegisterModule($this->MODULE_ID);

        // Копируем страницу в /bitrix/admin/
        $src  = __DIR__ . '/../admin/ticket_list.php';
        $dest = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/ticket_manager_list.php';
        if (!file_exists($dest)) {
            copy($src, $dest);
        }

        $APPLICATION->IncludeAdminFile(
            Loc::getMessage('BITRIX_TM_INSTALL_TITLE'),
            __DIR__ . '/step.php'
        );
    }

    public function DoUninstall()
    {
        global $APPLICATION;

        // Удаляем скопированную страницу из /bitrix/admin/
        $dest = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/ticket_manager_list.php';
        if (file_exists($dest)) {
            unlink($dest);
        }

        UnRegisterModule($this->MODULE_ID);
        $APPLICATION->IncludeAdminFile(
            Loc::getMessage('BITRIX_TM_UNINSTALL_TITLE'),
            __DIR__ . '/unstep.php'
        );
    }
}

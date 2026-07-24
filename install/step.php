<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);
?>
<p><?= Loc::getMessage('BITRIX_TM_INSTALL_OK') ?></p>
<p><a href="/bitrix/admin/ticket_manager_list.php?lang=<?= LANGUAGE_ID ?>"><?= Loc::getMessage('BITRIX_TM_GO_MODULE') ?></a></p>

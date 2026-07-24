<?php
/**
 * Страница настроек модуля bitrix.ticketmanager
 * /bitrix/admin/ticket_manager_settings.php
 */
define('NO_KEEP_STATISTIC', true);
define('STOP_STATISTICS', true);
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Localization\Loc;

Loc::loadLanguageFile(__FILE__);

if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm(Loc::getMessage('TM_ACCESS_DENIED'));
}
if (!CModule::IncludeModule('bitrix.ticketmanager')) {
    ShowError('Module bitrix.ticketmanager is not installed');
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    exit;
}

$MODULE_ID = 'bitrix.ticketmanager';
$saved     = false;
$errors    = [];

// ------------------------------------------------------------------ Сохранение
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    $chunkSize = intval($_POST['chunk_size'] ?? 100);
    if ($chunkSize < 1 || $chunkSize > 1000) {
        $errors[] = Loc::getMessage('TM_ERR_CHUNK_SIZE');
    } else {
        COption::SetOptionInt($MODULE_ID, 'chunk_size', $chunkSize);
        $saved = true;
    }
}

// ------------------------------------------------------------------ Чтение текущих значений
$currentChunkSize = COption::GetOptionInt($MODULE_ID, 'chunk_size', 100);

$APPLICATION->SetTitle(Loc::getMessage('TM_SETTINGS_TITLE'));
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>
<style>
.tm-settings          { max-width: 640px; font-family: Arial, sans-serif; font-size: 13px; }
.tm-settings-section  { background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;
                         padding: 20px 24px; margin-bottom: 20px; }
.tm-settings-section h3 { margin: 0 0 16px; font-size: 14px; font-weight: bold; color: #333;
                           border-bottom: 1px solid #e0e0e0; padding-bottom: 8px; }
.tm-field             { display: flex; align-items: flex-start; gap: 16px; margin-bottom: 14px; }
.tm-field label       { width: 240px; flex-shrink: 0; padding-top: 5px; color: #444; line-height: 1.4; }
.tm-field .tm-input   { flex: 1; }
.tm-field input[type=number] { padding: 4px 8px; border: 1px solid #ccc; border-radius: 3px;
                                font-size: 13px; width: 100px; }
.tm-field .tm-hint    { color: #888; font-size: 12px; margin-top: 4px; line-height: 1.4; }
.tm-notice            { padding: 9px 14px; border-radius: 4px; margin-bottom: 14px; font-size: 13px; }
.tm-notice-ok         { background: #d4efdf; border: 1px solid #a9dfbf; color: #1e8449; }
.tm-notice-err        { background: #fadbd8; border: 1px solid #f1948a; color: #922b21; }
.tm-btn               { padding: 6px 18px; border-radius: 3px; border: 1px solid transparent;
                         font-size: 13px; cursor: pointer; }
.tm-btn-primary       { background: #3d7fc1; color: #fff; border-color: #2f6399; }
.tm-btn-primary:hover { background: #2f6399; }
</style>

<div class="tm-settings">

<?php if ($saved): ?>
<div class="tm-notice tm-notice-ok">✓ <?= Loc::getMessage('TM_SETTINGS_SAVED') ?></div>
<?php endif; ?>

<?php foreach ($errors as $e): ?>
<div class="tm-notice tm-notice-err"><?= htmlspecialcharsbx($e) ?></div>
<?php endforeach; ?>

<form method="post" action="">
    <?= bitrix_sessid_post() ?>

    <div class="tm-settings-section">
        <h3><?= Loc::getMessage('TM_SECTION_DELETION') ?></h3>

        <div class="tm-field">
            <label for="chunk_size"><?= Loc::getMessage('TM_FIELD_CHUNK_SIZE') ?></label>
            <div class="tm-input">
                <input type="number" id="chunk_size" name="chunk_size"
                       value="<?= intval($currentChunkSize) ?>" min="1" max="1000">
                <div class="tm-hint"><?= Loc::getMessage('TM_FIELD_CHUNK_SIZE_HINT') ?></div>
            </div>
        </div>
    </div>

    <button type="submit" class="tm-btn tm-btn-primary"><?= Loc::getMessage('TM_SAVE') ?></button>
</form>

</div>

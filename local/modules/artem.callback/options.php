<?php

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Artem\Callback\Config;

/** @var CMain $APPLICATION */
/** @var string $mid Идентификатор модуля, приходит от страницы настроек */

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

Loc::loadMessages(__FILE__);
Loader::includeModule('artem.callback');

$moduleId = Config::MODULE_ID;
$request = Application::getInstance()->getContext()->getRequest();
$rightsToEdit = $APPLICATION->GetGroupRight($moduleId) >= 'W';
$message = null;

$fields = [
    'email_to' => ['type' => 'text', 'size' => 50, 'label' => 'ARTEM_CALLBACK_OPT_EMAIL_TO'],
    'rate_limit' => ['type' => 'number', 'size' => 5, 'label' => 'ARTEM_CALLBACK_OPT_RATE_LIMIT'],
    'rate_period' => ['type' => 'number', 'size' => 5, 'label' => 'ARTEM_CALLBACK_OPT_RATE_PERIOD'],
    'max_comment' => ['type' => 'number', 'size' => 5, 'label' => 'ARTEM_CALLBACK_OPT_MAX_COMMENT'],
    'slots' => ['type' => 'textarea', 'label' => 'ARTEM_CALLBACK_OPT_SLOTS'],
    'consent_required' => ['type' => 'checkbox', 'label' => 'ARTEM_CALLBACK_OPT_CONSENT'],
    'comment_required' => ['type' => 'checkbox', 'label' => 'ARTEM_CALLBACK_OPT_COMMENT_REQUIRED'],
];

if ($rightsToEdit && $request->isPost() && check_bitrix_sessid()) {
    if ($request->getPost('restore') !== null) {
        Option::delete($moduleId);
    } else {
        foreach ($fields as $name => $field) {
            $value = $field['type'] === 'checkbox'
                ? ($request->getPost($name) === 'Y' ? 'Y' : 'N')
                : (string) $request->getPost($name);

            Option::set($moduleId, $name, $value);
        }
    }

    $message = new CAdminMessage([
        'MESSAGE' => Loc::getMessage('ARTEM_CALLBACK_OPTIONS_SAVED'),
        'TYPE' => 'OK',
    ]);
}

$tabControl = new CAdminTabControl('artemCallbackTabs', [
    [
        'DIV' => 'settings',
        'TAB' => Loc::getMessage('ARTEM_CALLBACK_TAB_SETTINGS'),
        'TITLE' => Loc::getMessage('ARTEM_CALLBACK_TAB_SETTINGS_TITLE'),
    ],
]);

if ($message !== null) {
    echo $message->Show();
}

$tabControl->Begin();
?>
<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= htmlspecialcharsbx($mid) ?>&amp;lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post() ?>
    <?php $tabControl->BeginNextTab(); ?>

    <?php foreach ($fields as $name => $field): ?>
        <tr>
            <td width="40%"><?= Loc::getMessage($field['label']) ?>:</td>
            <td width="60%">
                <?php if ($field['type'] === 'textarea'): ?>
                    <textarea name="<?= $name ?>" rows="5" cols="40"><?= htmlspecialcharsbx(Config::get($name)) ?></textarea>
                <?php elseif ($field['type'] === 'checkbox'): ?>
                    <input type="hidden" name="<?= $name ?>" value="N">
                    <input type="checkbox" name="<?= $name ?>" value="Y"<?= Config::isOn($name) ? ' checked' : '' ?>>
                <?php else: ?>
                    <input type="<?= $field['type'] ?>" name="<?= $name ?>" size="<?= $field['size'] ?>"
                           value="<?= htmlspecialcharsbx(Config::get($name)) ?>">
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>

    <?php $tabControl->Buttons(); ?>
    <input type="submit" name="save" class="adm-btn-save" value="<?= Loc::getMessage('ARTEM_CALLBACK_SAVE') ?>"<?= $rightsToEdit ? '' : ' disabled' ?>>
    <input type="submit" name="restore" value="<?= Loc::getMessage('ARTEM_CALLBACK_RESET') ?>"<?= $rightsToEdit ? '' : ' disabled' ?>
           onclick="return confirm('Сбросить настройки модуля?');">
    <?php $tabControl->End(); ?>
</form>

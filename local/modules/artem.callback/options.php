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

$errors = [];

$fields = [
    'email_to' => ['type' => 'text', 'size' => 50, 'label' => 'ARTEM_CALLBACK_OPT_EMAIL_TO'],
    'rate_limit' => ['type' => 'number', 'size' => 5, 'label' => 'ARTEM_CALLBACK_OPT_RATE_LIMIT'],
    'rate_period' => ['type' => 'number', 'size' => 5, 'label' => 'ARTEM_CALLBACK_OPT_RATE_PERIOD'],
    'max_comment' => ['type' => 'number', 'size' => 5, 'label' => 'ARTEM_CALLBACK_OPT_MAX_COMMENT'],
    'slots' => ['type' => 'textarea', 'label' => 'ARTEM_CALLBACK_OPT_SLOTS'],
    'consent_required' => ['type' => 'checkbox', 'label' => 'ARTEM_CALLBACK_OPT_CONSENT'],
    'comment_required' => ['type' => 'checkbox', 'label' => 'ARTEM_CALLBACK_OPT_COMMENT_REQUIRED'],
];

/** @var array<string, string> $values что пришло из формы, уже приведённое */
$values = [];

if ($rightsToEdit && $request->isPost() && check_bitrix_sessid()) {
    if ($request->getPost('restore') !== null) {
        // Сбрасываем только поля формы: служебные значения модуля вроде
        // demo_installed должны пережить сброс, иначе при удалении модуль
        // не узнает свою демо-страницу и оставит её на сайте
        foreach (array_keys($fields) as $name) {
            Option::delete($moduleId, ['name' => $name]);
        }
    } else {
        foreach ($fields as $name => $field) {
            if ($field['type'] === 'checkbox') {
                $values[$name] = $request->getPost($name) === 'Y' ? 'Y' : 'N';

                continue;
            }

            $value = (string) $request->getPost($name);

            // Пустое поле оставляем значением по умолчанию, остальное прижимаем
            // к границам, чтобы 5000 стало 1000, а не молча превратилось в 3
            if (isset(Config::LIMITS[$name])) {
                $value = trim($value) === ''
                    ? Config::DEFAULTS[$name]
                    : (string) Config::clamp($name, (int) $value);
            }

            // Слишком длинный интервал не влезет в колонку, и заявка с ним потеряется
            if ($name === 'slots') {
                foreach (preg_split('/\R/', $value) ?: [] as $slot) {
                    if (mb_strlen(trim($slot)) > Config::MAX_SLOT_LENGTH) {
                        $errors[] = Loc::getMessage('ARTEM_CALLBACK_OPT_SLOT_TOO_LONG', [
                            '#MAX#' => Config::MAX_SLOT_LENGTH,
                            '#SLOT#' => trim($slot),
                        ]);
                    }
                }
            }

            $values[$name] = $value;
        }

        // Всё или ничего: при ошибке не сохраняем и остальные поля, иначе
        // на экране старые значения, а в базе уже половина новых
        if ($errors === []) {
            foreach ($values as $name => $value) {
                Option::set($moduleId, $name, $value);
            }
        }
    }

    $message = $errors === []
        ? new CAdminMessage(['MESSAGE' => Loc::getMessage('ARTEM_CALLBACK_OPTIONS_SAVED'), 'TYPE' => 'OK'])
        : new CAdminMessage(['MESSAGE' => implode('<br>', array_map('htmlspecialcharsbx', $errors)), 'TYPE' => 'ERROR']);
}

// После ошибки в форме остаётся введённое, чтобы его не набирать заново
$shown = static fn (string $name): string => $errors !== [] && isset($values[$name])
    ? $values[$name]
    : Config::get($name);

// Лимит считается по REMOTE_ADDR. Если запрос пришёл через прокси, а веб-сервер
// не подставил настоящий адрес, у всех посетителей один адрес прокси, и один
// лимит на весь сайт. Заметно это только отсюда, поэтому предупреждаем здесь
$forwardedFor = (string) $request->getServer()->get('HTTP_X_FORWARDED_FOR');
$proxyWarning = $forwardedFor !== ''
    && !in_array((string) $request->getRemoteAddress(), array_map('trim', explode(',', $forwardedFor)), true);

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

if ($proxyWarning) {
    echo (new CAdminMessage(['MESSAGE' => Loc::getMessage('ARTEM_CALLBACK_OPT_PROXY_WARNING'), 'TYPE' => 'ERROR']))->Show();
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
                    <textarea name="<?= $name ?>" rows="5" cols="40"><?= htmlspecialcharsbx($shown($name)) ?></textarea>
                <?php elseif ($field['type'] === 'checkbox'): ?>
                    <input type="hidden" name="<?= $name ?>" value="N">
                    <input type="checkbox" name="<?= $name ?>" value="Y"<?= $shown($name) === 'Y' ? ' checked' : '' ?>>
                <?php else: ?>
                    <input type="<?= $field['type'] ?>" name="<?= $name ?>" size="<?= $field['size'] ?>"
                           value="<?= htmlspecialcharsbx($shown($name)) ?>">
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

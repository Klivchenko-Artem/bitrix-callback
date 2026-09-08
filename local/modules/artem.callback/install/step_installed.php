<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;

/** @var CMain $APPLICATION */
?>
<p>Модуль установлен. Дальше:</p>
<ol>
    <li>укажите почту менеджера в настройках модуля;</li>
    <li>поставьте компонент <b>artem:callback.form</b> на нужную страницу;</li>
    <li>заявки смотрите в разделе «Обратный звонок».</li>
</ol>
<form action="<?= $APPLICATION->GetCurPage() ?>">
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
    <input type="submit" name="back" value="<?= Loc::getMessage('ARTEM_CALLBACK_BACK') ?>">
</form>

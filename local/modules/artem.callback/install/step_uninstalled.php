<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;

/** @var CMain $APPLICATION */
?>
<p>Модуль удалён.</p>
<form action="<?= $APPLICATION->GetCurPage() ?>">
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
    <input type="submit" name="back" value="<?= Loc::getMessage('ARTEM_CALLBACK_BACK') ?>">
</form>

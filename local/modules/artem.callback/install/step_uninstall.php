<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Localization\Loc;

/** @var CMain $APPLICATION */
?>
<form action="<?= $APPLICATION->GetCurPage() ?>">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
    <input type="hidden" name="id" value="artem.callback">
    <input type="hidden" name="uninstall" value="Y">
    <input type="hidden" name="step" value="2">

    <p>
        <label>
            <input type="checkbox" name="keep_data" value="Y" checked>
            <?= Loc::getMessage('ARTEM_CALLBACK_STEP_KEEP_DATA') ?>
        </label>
    </p>

    <input type="submit" name="inst" value="<?= Loc::getMessage('ARTEM_CALLBACK_STEP_DELETE') ?>">
</form>

<?php

use Bitrix\Main\UI\Extension;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array $arParams */
/** @var array $arResult */
/** @var CBitrixComponentTemplate $this */

Extension::load('ui.buttons');

$formId = 'artem-callback-' . $this->randString();
$signedParameters = $this->getComponent()->getSignedParameters();
?>
<div class="artem-callback" id="<?= $formId ?>">
    <h3 class="artem-callback__title"><?= htmlspecialcharsbx($arParams['TITLE']) ?></h3>

    <form class="artem-callback__form" novalidate>
        <label class="artem-callback__field">
            <span class="artem-callback__label"><?= GetMessage('ARTEM_CALLBACK_T_NAME') ?></span>
            <input class="artem-callback__input" type="text" name="name" maxlength="100" autocomplete="name" required>
            <span class="artem-callback__error" data-error="name"></span>
        </label>

        <label class="artem-callback__field">
            <span class="artem-callback__label"><?= GetMessage('ARTEM_CALLBACK_T_PHONE') ?></span>
            <input class="artem-callback__input" type="tel" name="phone" autocomplete="tel"
                   placeholder="+7 (___) ___-__-__" required>
            <span class="artem-callback__error" data-error="phone"></span>
        </label>

        <?php if ($arResult['SLOTS'] !== []): ?>
            <label class="artem-callback__field">
                <span class="artem-callback__label"><?= GetMessage('ARTEM_CALLBACK_T_SLOT') ?></span>
                <select class="artem-callback__input" name="slot">
                    <option value=""><?= GetMessage('ARTEM_CALLBACK_T_SLOT_ANY') ?></option>
                    <?php foreach ($arResult['SLOTS'] as $slot): ?>
                        <option value="<?= htmlspecialcharsbx($slot) ?>"><?= htmlspecialcharsbx($slot) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="artem-callback__error" data-error="slot"></span>
            </label>
        <?php endif; ?>

        <?php if ($arParams['SHOW_COMMENT']): ?>
            <label class="artem-callback__field">
                <span class="artem-callback__label">
                    <?= GetMessage('ARTEM_CALLBACK_T_COMMENT') ?><?= $arResult['COMMENT_REQUIRED'] ? '' : GetMessage('ARTEM_CALLBACK_T_COMMENT_OPTIONAL') ?>
                </span>
                <textarea class="artem-callback__input" name="comment" rows="3"
                          maxlength="<?= (int) $arResult['MAX_COMMENT'] ?>"></textarea>
                <span class="artem-callback__error" data-error="comment"></span>
            </label>
        <?php endif; ?>

        <?php if ($arResult['CONSENT_REQUIRED']): ?>
            <label class="artem-callback__consent">
                <input type="checkbox" name="consent" value="1">
                <span>
                    <?= GetMessage('ARTEM_CALLBACK_T_CONSENT') ?>
                    <a href="<?= htmlspecialcharsbx($arParams['CONSENT_URL']) ?>" target="_blank"><?= GetMessage('ARTEM_CALLBACK_T_CONSENT_LINK') ?></a>
                </span>
                <span class="artem-callback__error" data-error="consent"></span>
            </label>
        <?php endif; ?>

        <button class="artem-callback__submit ui-btn ui-btn-primary" type="submit">
            <?= htmlspecialcharsbx($arParams['BUTTON_TEXT']) ?>
        </button>

        <div class="artem-callback__common-error" data-error="common"></div>
    </form>

    <div class="artem-callback__success" hidden>
        <?= htmlspecialcharsbx($arParams['SUCCESS_TEXT']) ?>
    </div>
</div>

<script>
    BX.ready(function () {
        new ArtemCallbackForm({
            container: document.getElementById('<?= $formId ?>'),
            signedParameters: '<?= CUtil::JSEscape($signedParameters) ?>'
        });
    });
</script>

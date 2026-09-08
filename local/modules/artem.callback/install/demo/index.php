<?php

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';

/** @var CMain $APPLICATION */

$APPLICATION->SetTitle('Обратный звонок');

$APPLICATION->IncludeComponent(
    'artem:callback.form',
    '',
    [
        'TITLE' => 'Заказать звонок',
        'BUTTON_TEXT' => 'Жду звонка',
        'SUCCESS_TEXT' => 'Спасибо, перезвоним в ближайшее время.',
        'SHOW_COMMENT' => 'Y',
        'SHOW_SLOTS' => 'Y',
        'SEND_MAIL' => 'Y',
        'CONSENT_URL' => '/policy/',
    ]
);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';

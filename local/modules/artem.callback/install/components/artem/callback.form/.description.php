<?php

use Bitrix\Main\Localization\Loc;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

Loc::loadMessages(__FILE__);

$arComponentDescription = [
    'NAME' => Loc::getMessage('ARTEM_CALLBACK_COMPONENT_NAME'),
    'DESCRIPTION' => Loc::getMessage('ARTEM_CALLBACK_COMPONENT_DESC'),
    'ICON' => '/images/icon.gif',
    'SORT' => 10,
    'PATH' => [
        'ID' => 'artem',
        'NAME' => Loc::getMessage('ARTEM_CALLBACK_COMPONENT_GROUP'),
    ],
];

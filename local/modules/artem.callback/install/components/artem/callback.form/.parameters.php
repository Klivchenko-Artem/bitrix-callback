<?php

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Artem\Callback\Config;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

Loc::loadMessages(__FILE__);
Loader::includeModule('artem.callback');

$arComponentParameters = [
    'GROUPS' => [
        'CALLBACK' => ['NAME' => Loc::getMessage('ARTEM_CALLBACK_P_GROUP')],
    ],
    'PARAMETERS' => [
        'TITLE' => [
            'PARENT' => 'CALLBACK',
            'NAME' => Loc::getMessage('ARTEM_CALLBACK_P_TITLE'),
            'TYPE' => 'STRING',
            'DEFAULT' => Loc::getMessage('ARTEM_CALLBACK_P_DEF_TITLE'),
        ],
        'BUTTON_TEXT' => [
            'PARENT' => 'CALLBACK',
            'NAME' => Loc::getMessage('ARTEM_CALLBACK_P_BUTTON'),
            'TYPE' => 'STRING',
            'DEFAULT' => Loc::getMessage('ARTEM_CALLBACK_P_DEF_BUTTON'),
        ],
        'SUCCESS_TEXT' => [
            'PARENT' => 'CALLBACK',
            'NAME' => Loc::getMessage('ARTEM_CALLBACK_P_SUCCESS'),
            'TYPE' => 'STRING',
            'DEFAULT' => Loc::getMessage('ARTEM_CALLBACK_P_DEF_SUCCESS'),
        ],
        'SHOW_COMMENT' => [
            'PARENT' => 'CALLBACK',
            'NAME' => Loc::getMessage('ARTEM_CALLBACK_P_SHOW_COMMENT'),
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
        'SHOW_SLOTS' => [
            'PARENT' => 'CALLBACK',
            'NAME' => Loc::getMessage('ARTEM_CALLBACK_P_SHOW_SLOTS'),
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
        'CONSENT_URL' => [
            'PARENT' => 'CALLBACK',
            'NAME' => Loc::getMessage('ARTEM_CALLBACK_P_CONSENT_URL'),
            'TYPE' => 'STRING',
            'DEFAULT' => '/policy/',
        ],
        'SEND_MAIL' => [
            'PARENT' => 'CALLBACK',
            'NAME' => Loc::getMessage('ARTEM_CALLBACK_P_SEND_MAIL'),
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
        'CACHE_TIME' => ['DEFAULT' => 0],
    ],
];

<?php

use Bitrix\Main\Loader;
use Artem\Callback\Config;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

Loader::includeModule('artem.callback');

$arComponentParameters = [
    'GROUPS' => [
        'CALLBACK' => ['NAME' => 'Обратный звонок'],
    ],
    'PARAMETERS' => [
        'TITLE' => [
            'PARENT' => 'CALLBACK',
            'NAME' => 'Заголовок формы',
            'TYPE' => 'STRING',
            'DEFAULT' => 'Заказать звонок',
        ],
        'BUTTON_TEXT' => [
            'PARENT' => 'CALLBACK',
            'NAME' => 'Надпись на кнопке',
            'TYPE' => 'STRING',
            'DEFAULT' => 'Жду звонка',
        ],
        'SUCCESS_TEXT' => [
            'PARENT' => 'CALLBACK',
            'NAME' => 'Текст после отправки',
            'TYPE' => 'STRING',
            'DEFAULT' => 'Спасибо, перезвоним в ближайшее время.',
        ],
        'SHOW_COMMENT' => [
            'PARENT' => 'CALLBACK',
            'NAME' => 'Показывать поле комментария',
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
        'SHOW_SLOTS' => [
            'PARENT' => 'CALLBACK',
            'NAME' => 'Показывать выбор удобного времени',
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
        'CONSENT_URL' => [
            'PARENT' => 'CALLBACK',
            'NAME' => 'Ссылка на политику обработки данных',
            'TYPE' => 'STRING',
            'DEFAULT' => '/policy/',
        ],
        'SEND_MAIL' => [
            'PARENT' => 'CALLBACK',
            'NAME' => 'Отправлять письмо менеджеру',
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
        'CACHE_TIME' => ['DEFAULT' => 0],
    ],
];

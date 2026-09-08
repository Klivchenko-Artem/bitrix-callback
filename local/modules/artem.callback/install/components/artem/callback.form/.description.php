<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentDescription = [
    'NAME' => 'Форма обратного звонка',
    'DESCRIPTION' => 'Заявка на звонок: проверка телефона, защита от повторных отправок, письмо менеджеру',
    'ICON' => '/images/icon.gif',
    'SORT' => 10,
    'PATH' => [
        'ID' => 'artem',
        'NAME' => 'Обратный звонок',
    ],
];

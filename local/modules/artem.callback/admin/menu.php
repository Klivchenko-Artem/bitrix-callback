<?php

use Bitrix\Main\Localization\Loc;

/** @var CMain $APPLICATION */

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

if ($APPLICATION->GetGroupRight('artem.callback') === 'D') {
    return false;
}

return [
    'parent_menu' => 'global_menu_services',
    'sort' => 300,
    'text' => Loc::getMessage('ARTEM_CALLBACK_MENU_TEXT') ?: 'Обратный звонок',
    'title' => Loc::getMessage('ARTEM_CALLBACK_MENU_TITLE') ?: 'Заявки на обратный звонок',
    'icon' => 'artem_callback_menu_icon',
    'page_icon' => 'artem_callback_page_icon',
    'items_id' => 'menu_artem_callback',
    'items' => [
        [
            'text' => 'Заявки',
            'url' => 'artem_callback_index.php?lang=' . LANGUAGE_ID,
            'more_url' => ['artem_callback_index.php'],
            'title' => 'Список заявок на обратный звонок',
        ],
    ],
];

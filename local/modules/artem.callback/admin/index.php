<?php

use Artem\Callback\Service\PageUrlSanitizer;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Artem\Callback\Bitrix\RequestRepository;
use Artem\Callback\Model\RequestTable;
use Artem\Callback\Service\PhoneNormalizer;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

/** @var CMain $APPLICATION */
/** @var CUser $USER */

Loc::loadMessages(__FILE__);

$moduleId = 'artem.callback';
$rights = $APPLICATION->GetGroupRight($moduleId);

if ($rights === 'D') {
    $APPLICATION->AuthForm(Loc::getMessage('ARTEM_CALLBACK_ADMIN_ACCESS_DENIED'));
}

Loader::includeModule($moduleId);

$canEdit = $rights >= 'W';
$repository = new RequestRepository();
$phones = new PhoneNormalizer();
$statuses = RequestTable::getStatusList();

$tableId = 'tbl_artem_callback';
$sorting = new CAdminUiSorting($tableId, 'ID', 'desc');
$list = new CAdminUiList($tableId, $sorting);

$filter = [];
$list->AddFilter(
    [
        ['id' => 'PHONE', 'name' => Loc::getMessage('ARTEM_CALLBACK_ADMIN_F_PHONE'), 'filterable' => '%'],
        ['id' => 'NAME', 'name' => Loc::getMessage('ARTEM_CALLBACK_ADMIN_F_NAME'), 'filterable' => '%'],
        ['id' => 'STATUS', 'name' => Loc::getMessage('ARTEM_CALLBACK_ADMIN_F_STATUS'), 'type' => 'list', 'items' => $statuses, 'filterable' => '='],
    ],
    $filter
);

// Групповые действия применяются либо к отмеченным строкам, либо ко всей выборке
if ($canEdit && ($ids = $list->GroupAction())) {
    if ($list->IsGroupActionToAll()) {
        $ids = [];
        $rows = RequestTable::query()->setSelect(['ID'])->setFilter($filter)->exec();

        while ($row = $rows->fetch()) {
            $ids[] = $row['ID'];
        }
    }

    $action = Application::getInstance()->getContext()->getRequest()->getPost('action');

    foreach ($ids as $id) {
        $id = (int) $id;

        if ($id <= 0) {
            continue;
        }

        try {
            match ($action) {
                'delete' => $repository->delete($id),
                'mark_done' => $repository->setStatus($id, RequestTable::STATUS_DONE),
                'mark_spam' => $repository->setStatus($id, RequestTable::STATUS_SPAM),
                'mark_new' => $repository->setStatus($id, RequestTable::STATUS_NEW),
                default => null,
            };
        } catch (Throwable $e) {
            $list->AddGroupError(
                (string) Loc::getMessage('ARTEM_CALLBACK_ADMIN_ROW_ERROR', ['#ID#' => $id, '#ERROR#' => $e->getMessage()]),
                $id
            );
        }
    }
}

$list->AddHeaders([
    ['id' => 'ID', 'content' => 'ID', 'sort' => 'ID', 'default' => true],
    ['id' => 'CREATED_AT', 'content' => Loc::getMessage('ARTEM_CALLBACK_ADMIN_H_CREATED'), 'sort' => 'CREATED_AT', 'default' => true],
    ['id' => 'NAME', 'content' => Loc::getMessage('ARTEM_CALLBACK_ADMIN_F_NAME'), 'sort' => 'NAME', 'default' => true],
    ['id' => 'PHONE', 'content' => Loc::getMessage('ARTEM_CALLBACK_ADMIN_F_PHONE'), 'sort' => 'PHONE', 'default' => true],
    ['id' => 'SLOT', 'content' => Loc::getMessage('ARTEM_CALLBACK_ADMIN_H_SLOT'), 'default' => true],
    ['id' => 'COMMENT', 'content' => Loc::getMessage('ARTEM_CALLBACK_ADMIN_H_COMMENT'), 'default' => true],
    ['id' => 'STATUS', 'content' => Loc::getMessage('ARTEM_CALLBACK_ADMIN_F_STATUS'), 'sort' => 'STATUS', 'default' => true],
    ['id' => 'PAGE_URL', 'content' => Loc::getMessage('ARTEM_CALLBACK_ADMIN_H_PAGE'), 'default' => false],
    ['id' => 'CLIENT_IP', 'content' => Loc::getMessage('ARTEM_CALLBACK_ADMIN_H_IP'), 'default' => false],
]);

// Поле и направление сортировки только из белого списка.
//
// Раньше оно приходило из URL как есть, и artem_callback_index.php?by=XXX
// кладло страницу необработанным ArgumentException вместо списка заявок.
$sortableFields = ['ID', 'CREATED_AT', 'NAME', 'PHONE', 'STATUS', 'SLOT'];
$sortField = in_array($sorting->getField(), $sortableFields, true)
    ? $sorting->getField()
    : 'ID';
$sortOrder = strtoupper((string) $sorting->getOrder()) === 'ASC' ? 'ASC' : 'DESC';

// Страница выбирается в запросе. NavStart у результата ORM сначала вычитывает
// всю выборку в память и только потом режет её на страницы, поэтому берём
// смещение из навигации сами.
//
// Считаем до выборки: номер страницы приходит из адреса, и за концом списка
// (последнюю заявку удалили, ссылку сохранили в закладки) смещение уводило
// запрос в пустоту, а на экране был пустой список без объяснений. NavStart
// такой номер прижимал к последней странице, поэтому делаем это руками
$nav = $list->getPageNavigation('nav-artem-callback');
$nav->setRecordCount(RequestTable::getCount($filter));

if ($nav->getCurrentPage() > $nav->getPageCount()) {
    $nav->setCurrentPage(max(1, $nav->getPageCount()));
}

$query = RequestTable::query()
    ->setSelect(['*'])
    ->setFilter($filter)
    ->setOrder([$sortField => $sortOrder])
    ->setOffset($nav->getOffset())
    ->setLimit($nav->getLimit());

$queryResult = $query->exec();
$list->setNavigation($nav, (string) Loc::getMessage('ARTEM_CALLBACK_ADMIN_TITLE'));

$result = new CAdminUiResult($queryResult, $tableId);

while ($row = $result->GetNext()) {
    $item = $list->AddRow($row['ID'], $row);
    $item->AddViewField('PHONE', htmlspecialcharsbx($phones->format($row['PHONE']) ?? $row['PHONE']));
    $item->AddViewField('STATUS', $statuses[$row['STATUS']] ?? $row['STATUS']);

    // Ссылку собираем из «сырого» значения и проверяем схему.
    //
    // GetNext() уже прогнал поля через htmlspecialcharsEx, поэтому повторное
    // экранирование ломало адреса с параметрами (&amp;amp;), а сама схема
    // не проверялась вовсе: javascript: в href выполнялся в сессии
    // администратора, стоило ему нажать «открыть».
    $rawPageUrl = (string) ($row['~PAGE_URL'] ?? $row['PAGE_URL']);

    if ($rawPageUrl !== '' && PageUrlSanitizer::isSafe($rawPageUrl)) {
        $item->AddViewField(
            'PAGE_URL',
            '<a href="'.htmlspecialcharsbx($rawPageUrl).'" target="_blank" rel="noopener noreferrer">'
                .Loc::getMessage('ARTEM_CALLBACK_ADMIN_PAGE_OPEN')
                .'</a>'
        );
    } else {
        $item->AddViewField('PAGE_URL', '');
    }

    if ($canEdit) {
        $item->AddActions([
            [
                'ICON' => 'edit',
                'DEFAULT' => true,
                'TEXT' => Loc::getMessage('ARTEM_CALLBACK_ADMIN_ACT_DONE'),
                'ACTION' => $list->ActionDoGroup($row['ID'], 'mark_done'),
            ],
            [
                'ICON' => 'delete',
                'TEXT' => Loc::getMessage('ARTEM_CALLBACK_ADMIN_ACT_DELETE'),
                'ACTION' => "if(confirm('" . CUtil::JSEscape((string) Loc::getMessage('ARTEM_CALLBACK_ADMIN_ACT_DELETE_CONFIRM')) . "')) " . $list->ActionDoGroup($row['ID'], 'delete'),
            ],
        ]);
    }
}

$list->AddGroupActionTable([
    'mark_done' => Loc::getMessage('ARTEM_CALLBACK_ADMIN_GA_DONE'),
    'mark_new' => Loc::getMessage('ARTEM_CALLBACK_ADMIN_GA_NEW'),
    'mark_spam' => Loc::getMessage('ARTEM_CALLBACK_ADMIN_GA_SPAM'),
    'delete' => Loc::getMessage('ARTEM_CALLBACK_ADMIN_GA_DELETE'),
]);

$list->CheckListMode();

$APPLICATION->SetTitle((string) Loc::getMessage('ARTEM_CALLBACK_ADMIN_TITLE'));

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$list->DisplayList();

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';

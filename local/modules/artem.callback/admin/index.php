<?php

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Artem\Callback\Bitrix\RequestRepository;
use Artem\Callback\Model\RequestTable;
use Artem\Callback\Service\PhoneNormalizer;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

/** @var CMain $APPLICATION */
/** @var CUser $USER */

$moduleId = 'artem.callback';
$rights = $APPLICATION->GetGroupRight($moduleId);

if ($rights === 'D') {
    $APPLICATION->AuthForm('Доступ закрыт');
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
        ['id' => 'PHONE', 'name' => 'Телефон', 'filterable' => '%'],
        ['id' => 'NAME', 'name' => 'Имя', 'filterable' => '%'],
        ['id' => 'STATUS', 'name' => 'Статус', 'type' => 'list', 'items' => $statuses, 'filterable' => '='],
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
            $list->AddGroupError('Заявка #' . $id . ': ' . $e->getMessage(), $id);
        }
    }
}

$list->AddHeaders([
    ['id' => 'ID', 'content' => 'ID', 'sort' => 'ID', 'default' => true],
    ['id' => 'CREATED_AT', 'content' => 'Когда', 'sort' => 'CREATED_AT', 'default' => true],
    ['id' => 'NAME', 'content' => 'Имя', 'sort' => 'NAME', 'default' => true],
    ['id' => 'PHONE', 'content' => 'Телефон', 'sort' => 'PHONE', 'default' => true],
    ['id' => 'SLOT', 'content' => 'Когда звонить', 'default' => true],
    ['id' => 'COMMENT', 'content' => 'Комментарий', 'default' => true],
    ['id' => 'STATUS', 'content' => 'Статус', 'sort' => 'STATUS', 'default' => true],
    ['id' => 'PAGE_URL', 'content' => 'Страница', 'default' => false],
    ['id' => 'CLIENT_IP', 'content' => 'IP', 'default' => false],
]);

$query = RequestTable::query()
    ->setSelect(['*'])
    ->setFilter($filter)
    ->setOrder([$sorting->getField() => $sorting->getOrder()]);

$result = new CAdminUiResult($query->exec(), $tableId);
$result->NavStart();
$list->SetNavigationParams($result, ['BASE_LINK' => 'artem_callback_index.php']);

while ($row = $result->GetNext()) {
    $item = $list->AddRow($row['ID'], $row);
    $item->AddViewField('PHONE', htmlspecialcharsbx($phones->format($row['PHONE']) ?? $row['PHONE']));
    $item->AddViewField('STATUS', $statuses[$row['STATUS']] ?? $row['STATUS']);

    if ($row['PAGE_URL'] !== '') {
        $item->AddViewField('PAGE_URL', '<a href="' . htmlspecialcharsbx($row['PAGE_URL']) . '" target="_blank">открыть</a>');
    }

    if ($canEdit) {
        $item->AddActions([
            [
                'ICON' => 'edit',
                'DEFAULT' => true,
                'TEXT' => 'Обработана',
                'ACTION' => $list->ActionRedirect('artem_callback_index.php?action=mark_done&id=' . $row['ID'] . '&' . bitrix_sessid_get()),
            ],
            [
                'ICON' => 'delete',
                'TEXT' => 'Удалить',
                'ACTION' => "if(confirm('Удалить заявку?')) " . $list->ActionDoGroup($row['ID'], 'delete'),
            ],
        ]);
    }
}

$list->AddGroupActionTable([
    'mark_done' => 'Отметить обработанными',
    'mark_new' => 'Вернуть в новые',
    'mark_spam' => 'Отметить спамом',
    'delete' => 'Удалить',
]);

$list->CheckListMode();

$APPLICATION->SetTitle('Заявки на обратный звонок');

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$list->DisplayList();

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';

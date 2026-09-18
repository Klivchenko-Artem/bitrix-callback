<?php

use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;
use Artem\Callback\Bitrix\Log;
use Artem\Callback\Model\RequestTable;

Loc::loadMessages(__FILE__);

class artem_callback extends CModule
{
    public $MODULE_ID = 'artem.callback';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $MODULE_GROUP_RIGHTS = 'Y';
    public $PARTNER_NAME;
    public $PARTNER_URI;

    public const MAIL_EVENT_TYPE = 'ARTEM_CALLBACK_NEW';

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';

        $this->MODULE_VERSION = $arModuleVersion['VERSION'] ?? '1.0.0';
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'] ?? '';
        $this->MODULE_NAME = Loc::getMessage('ARTEM_CALLBACK_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('ARTEM_CALLBACK_MODULE_DESC');
        $this->PARTNER_NAME = Loc::getMessage('ARTEM_CALLBACK_PARTNER_NAME');
        $this->PARTNER_URI = Loc::getMessage('ARTEM_CALLBACK_PARTNER_URI');
    }

    /**
     * Ядро считает установку удачной, если вернулось что угодно, кроме false,
     * поэтому при сбое возвращаем именно false, а не пустой return.
     */
    public function DoInstall(): bool
    {
        global $APPLICATION;

        // includeModule отказывает незарегистрированному модулю, поэтому
        // регистрируем сразу, а при сбое снимаем регистрацию вместе с остальным
        ModuleManager::registerModule($this->MODULE_ID);

        try {
            if (!Loader::includeModule($this->MODULE_ID)) {
                throw new \RuntimeException('модуль не подключился');
            }

            $this->InstallDB();
            $this->InstallEvents();
            $this->InstallFiles();
        } catch (\Throwable $e) {
            // Файлы откатываем всегда: InstallFiles мог упасть на середине,
            // успев что-то скопировать. Удаление своих файлов безопасно и
            // тогда, когда до копирования не дошли
            $this->rollback(fn () => $this->UnInstallFiles());
            $this->rollback(fn () => $this->UnInstallEvents());
            ModuleManager::unRegisterModule($this->MODULE_ID);

            // Автозагрузка модуля тут может не работать, поэтому классы журнала
            // подключаем напрямую
            require_once dirname(__DIR__).'/lib/Config.php';
            require_once dirname(__DIR__).'/lib/Bitrix/Log.php';
            Log::error('установка не удалась: '.$e->getMessage());

            $APPLICATION->ThrowException(
                Loc::getMessage('ARTEM_CALLBACK_INSTALL_FAILED', ['#ERROR#' => $e->getMessage()])
            );

            return false;
        }

        $APPLICATION->IncludeAdminFile(
            Loc::getMessage('ARTEM_CALLBACK_INSTALL_TITLE'),
            __DIR__ . '/step_installed.php'
        );

        return true;
    }

    /**
     * Шаг отката не должен срывать остальные и подменять исходную ошибку.
     */
    private function rollback(callable $step): void
    {
        try {
            $step();
        } catch (\Throwable) {
        }
    }

    public function DoUninstall(): void
    {
        global $APPLICATION;

        // Без этого на шаге удаления не автозагрузятся классы модуля,
        // и UnInstallDB упадёт на первом же обращении к ORM.
        Loader::includeModule($this->MODULE_ID);

        $request = Application::getInstance()->getContext()->getRequest();

        if ((int) $request->get('step') < 2) {
            $APPLICATION->IncludeAdminFile(
                Loc::getMessage('ARTEM_CALLBACK_UNINSTALL_TITLE'),
                __DIR__ . '/step_uninstall.php'
            );

            return;
        }

        $this->UnInstallFiles();
        $this->UnInstallEvents();
        $this->UnInstallDB(['keepData' => $request->get('keep_data') === 'Y']);

        ModuleManager::unRegisterModule($this->MODULE_ID);

        $APPLICATION->IncludeAdminFile(
            Loc::getMessage('ARTEM_CALLBACK_UNINSTALL_TITLE'),
            __DIR__ . '/step_uninstalled.php'
        );
    }

    public function InstallDB(): bool
    {
        $connection = Application::getConnection();
        $table = RequestTable::getTableName();

        if (!$connection->isTableExists($table)) {
            RequestTable::getEntity()->createDbTable();
        }

        // Индексы проверяются по одному: если прошлая установка упала после
        // создания таблицы, повторная должна их доделать
        $indexes = [
            'ix_artem_callback_status' => ['STATUS', 'CREATED_AT'],
            'ix_artem_callback_phone' => ['PHONE', 'CREATED_AT'],
            'ix_artem_callback_ip' => ['CLIENT_IP', 'CREATED_AT'],
        ];

        foreach ($indexes as $name => $columns) {
            if (!$connection->isIndexExists($table, $columns)) {
                $connection->createIndex($table, $name, $columns);
            }
        }

        return true;
    }

    /**
     * @param array{keepData?: bool} $params
     */
    public function UnInstallDB(array $params = []): bool
    {
        // Настройки убираем всегда.
        //
        // Галка на шаге удаления обещает сохранить *заявки*, а не конфигурацию.
        // С прежним поведением настройки оставались в b_option, и при повторной
        // установке всплывали старые значения, включая нулевой лимит,
        // от которого форма молчит; человек потом долго ищет, почему
        // свежепоставленный модуль не принимает заявки.
        Option::delete($this->MODULE_ID);

        if (empty($params['keepData'])) {
            $connection = Application::getConnection();

            if ($connection->isTableExists(RequestTable::getTableName())) {
                $connection->dropTable(RequestTable::getTableName());
            }
        }

        return true;
    }

    public function InstallEvents(): bool
    {
        $eventType = new CEventType();
        $eventMessage = new CEventMessage();

        $existing = CEventType::GetList(['TYPE_ID' => self::MAIL_EVENT_TYPE])->Fetch();

        if (!$existing) {
            $eventType->Add([
                'LID' => 'ru',
                'EVENT_NAME' => self::MAIL_EVENT_TYPE,
                'NAME' => Loc::getMessage('ARTEM_CALLBACK_MAIL_EVENT_NAME'),
                'DESCRIPTION' => Loc::getMessage('ARTEM_CALLBACK_MAIL_EVENT_DESC'),
            ]);
        }

        $templates = CEventMessage::GetList('id', 'asc', ['EVENT_NAME' => self::MAIL_EVENT_TYPE]);

        if (!$templates->Fetch()) {
            foreach ($this->getSiteIds() as $siteId) {
                $eventMessage->Add([
                    'ACTIVE' => 'Y',
                    'EVENT_NAME' => self::MAIL_EVENT_TYPE,
                    'LID' => $siteId,
                    'EMAIL_FROM' => '#DEFAULT_EMAIL_FROM#',
                    'EMAIL_TO' => '#EMAIL_TO#',
                    'SUBJECT' => Loc::getMessage('ARTEM_CALLBACK_MAIL_SUBJECT'),
                    'BODY_TYPE' => 'text',
                    'MESSAGE' => $this->getMailBody(),
                ]);
            }
        }

        return true;
    }

    public function UnInstallEvents(): bool
    {
        $templates = CEventMessage::GetList('id', 'asc', ['EVENT_NAME' => self::MAIL_EVENT_TYPE]);

        while ($template = $templates->Fetch()) {
            CEventMessage::Delete((int) $template['ID']);
        }

        // Массив-фильтр, а не строка: скалярный аргумент ядро принимает за ID
        // и делает intval('ARTEM_CALLBACK_NEW') = 0, то есть не удаляет ничего,
        // и тип письма оставался в системе после удаления модуля
        CEventType::Delete(['EVENT_NAME' => self::MAIL_EVENT_TYPE]);

        return true;
    }

    public function InstallFiles(): bool
    {
        // Компонент едет из состава модуля в /local/components, как и положено
        CopyDirFiles(
            __DIR__ . '/components',
            Application::getDocumentRoot() . '/local/components',
            true,
            true
        );

        CopyDirFiles(
            __DIR__ . '/admin',
            Application::getDocumentRoot() . '/bitrix/admin',
            true,
            true
        );

        // Демо-страница, чтобы форму было где посмотреть сразу после установки.
        // Кладём только если на сайте такой страницы ещё нет, и запоминаем это:
        // удалять потом можно только свою
        $demoPage = Application::getDocumentRoot().'/callback/index.php';

        if (!file_exists($demoPage)) {
            CopyDirFiles(__DIR__.'/demo', Application::getDocumentRoot().'/callback', false, true);
            Option::set($this->MODULE_ID, 'demo_installed', 'Y');
        }

        return true;
    }

    public function UnInstallFiles(): bool
    {
        DeleteDirFilesEx('/local/components/artem/callback.form');

        // Демо-страницу удаляем по файлам и только если ставили её сами:
        // в /callback у клиента может лежать своя страница
        if (Option::get($this->MODULE_ID, 'demo_installed') === 'Y') {
            foreach (glob(__DIR__.'/demo/*') ?: [] as $file) {
                DeleteDirFilesEx('/callback/'.basename($file));
            }

            Option::delete($this->MODULE_ID, ['name' => 'demo_installed']);
        }

        foreach (glob(__DIR__ . '/admin/*.php') ?: [] as $file) {
            DeleteDirFilesEx('/bitrix/admin/' . basename($file));
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function getSiteIds(): array
    {
        $ids = [];
        $sites = CSite::GetList();

        while ($site = $sites->Fetch()) {
            $ids[] = $site['LID'];
        }

        return $ids ?: ['s1'];
    }

    private function getMailBody(): string
    {
        return implode("\n", [
            'Заявка на обратный звонок с сайта #SITE_NAME#',
            '',
            'Имя: #NAME#',
            'Телефон: #PHONE#',
            'Удобное время: #SLOT#',
            'Комментарий: #COMMENT#',
            '',
            'Страница: #PAGE_URL#',
        ]);
    }
}

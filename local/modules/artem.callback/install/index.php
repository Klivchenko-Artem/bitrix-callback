<?php

use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;
use Artem\Callback\Bitrix\CacheRateStorage;
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

    public function DoInstall(): void
    {
        global $APPLICATION;

        // Регистрация — последним шагом, а не первым.
        //
        // Loader::includeModule смотрит на файловую систему, а не на b_module,
        // поэтому классы доступны и до регистрации. Зато при прежнем порядке
        // упавшее создание таблицы (нет прав на CREATE, остался хвост
        // от прошлой установки) оставляло модуль зарегистрированным: компонент
        // на сайте «работал», молча терял заявки, а админка падала
        // на отсутствующей таблице.
        Loader::includeModule($this->MODULE_ID);

        try {
            $this->InstallDB();
            $this->InstallEvents();
            $this->InstallFiles();
        } catch (\Throwable $e) {
            // Откатываем то, что успели создать, и говорим, что случилось
            $this->UnInstallFiles();
            $this->UnInstallEvents();

            $APPLICATION->ThrowException(
                Loc::getMessage('ARTEM_CALLBACK_INSTALL_FAILED') ?: 'Установка не удалась: '.$e->getMessage()
            );

            AddMessage2Log('artem.callback: установка не удалась: '.$e->getMessage(), 'artem.callback');

            return;
        }

        ModuleManager::registerModule($this->MODULE_ID);

        $APPLICATION->IncludeAdminFile(
            Loc::getMessage('ARTEM_CALLBACK_INSTALL_TITLE'),
            __DIR__ . '/step_installed.php'
        );
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

        if (!$connection->isTableExists(RequestTable::getTableName())) {
            RequestTable::getEntity()->createDbTable();
            $connection->createIndex(RequestTable::getTableName(), 'ix_artem_callback_status', ['STATUS', 'CREATED_AT']);

            // Индекс под проверку дублей и подсчёт заявок с адреса: оба запроса
            // идут на каждую отправку формы, а без индекса это full scan
            // по таблице, которая не чистится никогда
            $connection->createIndex(RequestTable::getTableName(), 'ix_artem_callback_phone', ['PHONE', 'CREATED_AT']);
            $connection->createIndex(RequestTable::getTableName(), 'ix_artem_callback_ip', ['CLIENT_IP', 'CREATED_AT']);
        }

        return true;
    }

    /**
     * @param array{keepData?: bool} $params
     */
    public function UnInstallDB(array $params = []): bool
    {
        // Настройки и счётчики убираем всегда.
        //
        // Галка на шаге удаления обещает сохранить *заявки*, а не конфигурацию.
        // С прежним поведением настройки оставались в b_option, и при повторной
        // установке всплывали старые значения — включая нулевой лимит,
        // от которого форма молчит; человек потом долго ищет, почему
        // свежепоставленный модуль не принимает заявки.
        Option::delete($this->MODULE_ID);
        CacheRateStorage::clearAll();

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

        // Демонстрационная страница, чтобы форму было где посмотреть сразу после установки
        CopyDirFiles(
            __DIR__ . '/demo',
            Application::getDocumentRoot() . '/callback',
            false,
            true
        );

        return true;
    }

    public function UnInstallFiles(): bool
    {
        DeleteDirFilesEx('/local/components/artem/callback.form');

        // Демо-страницу удаляем по файлам, а не разделом целиком: путь
        // предсказуемый, и клиент вполне мог дописать в /callback свои
        // страницы или картинки — сносить их без спроса нельзя
        foreach (glob(__DIR__.'/demo/*') ?: [] as $file) {
            DeleteDirFilesEx('/callback/'.basename($file));
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

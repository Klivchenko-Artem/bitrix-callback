<?php

use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Error;
use Bitrix\Main\ErrorCollection;
use Bitrix\Main\Errorable;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use Artem\Callback\Bitrix\DbRateStorage;
use Artem\Callback\Bitrix\Notifier;
use Artem\Callback\Bitrix\RequestRepository;
use Artem\Callback\CallbackRequest;
use Artem\Callback\Config;
use Artem\Callback\Service\PhoneNormalizer;
use Artem\Callback\Service\RateLimiter;
use Artem\Callback\Service\RequestValidator;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/**
 * Форма обратного звонка.
 *
 * Отправка идёт через ajax-действие компонента: адрес обработчика не надо
 * прописывать в шаблоне, а Битрикс сам проверяет подпись параметров и сессию.
 */
class CallbackFormComponent extends CBitrixComponent implements Controllerable, Errorable
{
    /**
     * Потолок длины адреса страницы.
     *
     * Ровно столько разрешает колонка PAGE_URL: без обрезки заявка с длинной
     * рекламной ссылкой не проходила валидацию ORM и терялась.
     */
    private const MAX_PAGE_URL_LENGTH = 500;

    private ErrorCollection $errors;

    public function __construct($component = null)
    {
        parent::__construct($component);

        $this->errors = new ErrorCollection();
    }

    public function onPrepareComponentParams($arParams): array
    {
        return [
            'TITLE' => trim((string) ($arParams['TITLE'] ?? 'Заказать звонок')),
            'BUTTON_TEXT' => trim((string) ($arParams['BUTTON_TEXT'] ?? 'Жду звонка')),
            'SUCCESS_TEXT' => trim((string) ($arParams['SUCCESS_TEXT'] ?? 'Спасибо, перезвоним в ближайшее время.')),
            'SHOW_COMMENT' => self::toBool($arParams['SHOW_COMMENT'] ?? null),
            'SHOW_SLOTS' => self::toBool($arParams['SHOW_SLOTS'] ?? null),
            'CONSENT_URL' => trim((string) ($arParams['CONSENT_URL'] ?? '/policy/')),
            'SEND_MAIL' => self::toBool($arParams['SEND_MAIL'] ?? null),
            'CACHE_TIME' => 0,
        ];
    }

    /**
     * Из настроек компонента приходит 'Y'/'N', а из подписанных параметров —
     * уже готовый bool: подготовка параметров вызывается и на ajax-действии.
     */
    private static function toBool(mixed $value, bool $default = true): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        return in_array($value, ['Y', 'y', '1', 1, true], true);
    }

    /**
     * Параметры, которые шаблон отдаёт обратно в ajax подписанными:
     * иначе с фронта можно было бы подменить настройки компонента.
     */
    public function listKeysSignedParameters(): array
    {
        return ['SEND_MAIL', 'SHOW_COMMENT', 'SHOW_SLOTS'];
    }

    /**
     * По умолчанию ядро вешает на действие проверку авторизации, а форму
     * заполняет гость. Оставляем только POST и защиту от CSRF.
     */
    public function configureActions(): array
    {
        return [
            'submit' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                    new ActionFilter\Csrf(),
                ],
                'postfilters' => [],
            ],
        ];
    }

    public function executeComponent(): void
    {
        if (!$this->includeModule()) {
            $this->showErrors();

            return;
        }

        $this->arResult['SLOTS'] = $this->arParams['SHOW_SLOTS'] ? Config::getSlots() : [];
        $this->arResult['CONSENT_REQUIRED'] = Config::isOn('consent_required');
        $this->arResult['COMMENT_REQUIRED'] = Config::isOn('comment_required');
        $this->arResult['MAX_COMMENT'] = Config::getInt('max_comment');

        $this->includeComponentTemplate();
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array{id: int}|null
     */
    public function submitAction(array $fields): ?array
    {
        if (!$this->includeModule()) {
            return null;
        }

        $phones = new PhoneNormalizer();

        // Комментарий требуем только если поле вообще показано.
        //
        // Настройка модуля и параметр компонента жили независимо: при
        // comment_required=Y и SHOW_COMMENT=N форма требовала то, чего
        // на странице нет, и отправить её было нельзя вообще ничем —
        // сообщение об ошибке падало в общий блок под формой без поля.
        $commentShown = (bool) ($this->arParams['SHOW_COMMENT'] ?? true);

        $validator = new RequestValidator(
            $phones,
            Config::getSlots(),
            $commentShown && Config::isOn('comment_required'),
            Config::getInt('max_comment'),
            Config::isOn('consent_required'),
        );

        $errors = $validator->validate($fields);

        if ($errors !== []) {
            foreach ($errors as $field => $message) {
                $this->errors->setError(new Error($message, $field));
            }

            return null;
        }

        $clientIp = $this->getClientIp();
        $periodSeconds = Config::getInt('rate_period') * 60;

        // Считаем по таблице заявок, а не по кешу ядра.
        //
        // Кеш можно выключить в настройках главного модуля — и защита
        // от спама исчезала молча: initCache всегда возвращал false,
        // счётчик всегда был нулём, и лимит не срабатывал никогда.
        $storage = new DbRateStorage($periodSeconds);
        $storage->remember('callback:rate:'.md5($clientIp), $clientIp);

        $limiter = new RateLimiter(
            $storage,
            Config::getInt('rate_limit'),
            $periodSeconds,
        );

        if (!$limiter->hit($clientIp)) {
            $this->errors->setError(new Error('Слишком много заявок подряд. Мы уже видим вашу — перезвоним.', 'rate'));

            return null;
        }

        $request = new CallbackRequest(
            name: trim((string) $fields['name']),
            phone: (string) $phones->normalize((string) $fields['phone']),
            comment: trim((string) ($fields['comment'] ?? '')),
            slot: trim((string) ($fields['slot'] ?? '')),
            // Адрес страницы приходит из window.location.href, а в колонке
            // стоит потолок в 500 символов: приход с рекламы, где в ссылке
            // utm, gclid и fbclid, легко перебирал его — валидатор отбивал
            // запись, и заявка терялась ровно на самом дорогом трафике.
            // Заодно отсекаем всё, что не http и не https: этот адрес
            // подставляется ссылкой в админке
            pageUrl: self::sanitizePageUrl((string) ($fields['pageUrl'] ?? '')),
            clientIp: $clientIp,
        );

        $repository = new RequestRepository();
        $isDuplicate = $repository->hasRecent($request->phone);

        try {
            $id = $repository->save($request, $this->currentSiteId());
        } catch (Throwable $e) {
            // В журнал обязательно: молчаливый catch превращал любую поломку
            // в «сайт полгода не принимает заявки, и никто не знает»
            AddMessage2Log('artem.callback: заявка не сохранилась: '.$e->getMessage(), 'artem.callback');

            $this->errors->setError(new Error('Не смогли сохранить заявку, попробуйте ещё раз.', 'save'));

            return null;
        }

        // Дубль сохраняем, но менеджера им не дёргаем
        if ($this->arParams['SEND_MAIL'] && !$isDuplicate) {
            $sent = (new Notifier($phones))->notify($request, $this->currentSiteId());

            // Письмо не ушло — посетителю отвечаем «перезвоним», а менеджер
            // о заявке не знает. Такое молчание должно быть видно в журнале
            if (!$sent) {
                AddMessage2Log(
                    'artem.callback: письмо по заявке '.$id.' не отправлено (проверьте настройки модуля)',
                    'artem.callback'
                );
            }
        }

        return ['id' => $id];
    }

    public function getErrors(): array
    {
        return $this->errors->toArray();
    }

    public function getErrorByCode($code): ?Error
    {
        return $this->errors->getErrorByCode($code);
    }

    private function includeModule(): bool
    {
        try {
            if (Loader::includeModule('artem.callback')) {
                return true;
            }
        } catch (LoaderException) {
            // сообщение ниже одно на любой случай
        }

        $this->errors->setError(new Error('Не установлен модуль artem.callback', 'module'));

        return false;
    }

    private function showErrors(): void
    {
        foreach ($this->errors as $error) {
            ShowError($error->getMessage());
        }
    }

    /**
     * Адрес посетителя с оглядкой на доверенные прокси.
     *
     * Голый REMOTE_ADDR за Cloudflare или балансировщиком — это один и тот же
     * адрес для всех, то есть лимит из трёх заявок в час на весь сайт.
     * Bitrix\Main\Service\GeoIp\Manager умеет отдавать настоящий адрес,
     * опираясь на настройку доверенных прокси в ядре.
     */
    private function getClientIp(): string
    {
        if (class_exists(\Bitrix\Main\Service\GeoIp\Manager::class)) {
            $real = \Bitrix\Main\Service\GeoIp\Manager::getRealIp();

            if (is_string($real) && $real !== '') {
                return $real;
            }
        }

        $request = \Bitrix\Main\Context::getCurrent()->getRequest();

        return (string) ($request->getRemoteAddress() ?: 'unknown');
    }

    /**
     * Приводит адрес страницы к тому, что можно и записать, и показать ссылкой.
     */
    private static function sanitizePageUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        // javascript: и data: в href админки — это выполнение кода в сессии
        // администратора, когда он нажмёт «открыть»
        if (!in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

        return mb_substr($url, 0, self::MAX_PAGE_URL_LENGTH);
    }

    private function currentSiteId(): string
    {
        return (string) (\Bitrix\Main\Context::getCurrent()->getSite() ?: 's1');
    }
}

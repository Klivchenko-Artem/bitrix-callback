<?php

declare(strict_types=1);

namespace Artem\Callback\Model;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\EnumField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\Type\DateTime;

/**
 * Таблица заявок. Своя сущность, а не инфоблок: заявка не контент,
 * ей не нужны разделы, свойства и права на элемент, зато нужен быстрый
 * список с фильтром по статусу.
 *
 * @method static \Bitrix\Main\ORM\Query\Query query()
 */
final class RequestTable extends DataManager
{
    public const STATUS_NEW = 'N';
    public const STATUS_DONE = 'D';
    public const STATUS_SPAM = 'S';

    public static function getTableName(): string
    {
        return 'artem_callback_request';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
            (new DatetimeField('CREATED_AT'))
                ->configureRequired()
                ->configureDefaultValue(static fn (): DateTime => new DateTime()),
            (new StringField('NAME'))
                ->configureRequired()
                ->addValidator(new LengthValidator(1, 100)),
            (new StringField('PHONE'))
                ->configureRequired()
                ->addValidator(new LengthValidator(1, 20)),
            (new StringField('COMMENT'))
                ->addValidator(new LengthValidator(0, 2000)),
            (new StringField('SLOT'))
                ->addValidator(new LengthValidator(0, 50)),
            (new StringField('PAGE_URL'))
                ->addValidator(new LengthValidator(0, 500)),
            (new StringField('CLIENT_IP'))
                ->addValidator(new LengthValidator(0, 45)),
            (new EnumField('STATUS'))
                ->configureValues([self::STATUS_NEW, self::STATUS_DONE, self::STATUS_SPAM])
                ->configureDefaultValue(self::STATUS_NEW),
            (new StringField('SITE_ID'))
                ->configureNullable()
                ->addValidator(new LengthValidator(0, 2)),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getStatusList(): array
    {
        Loc::loadMessages(__FILE__);

        return [
            self::STATUS_NEW => (string) Loc::getMessage('ARTEM_CALLBACK_STATUS_NEW'),
            self::STATUS_DONE => (string) Loc::getMessage('ARTEM_CALLBACK_STATUS_DONE'),
            self::STATUS_SPAM => (string) Loc::getMessage('ARTEM_CALLBACK_STATUS_SPAM'),
        ];
    }
}

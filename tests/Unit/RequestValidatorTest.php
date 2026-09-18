<?php

declare(strict_types=1);

namespace Artem\Callback\Tests\Unit;

use Artem\Callback\Service\PhoneNormalizer;
use Artem\Callback\Service\RequestValidator;
use PHPUnit\Framework\TestCase;

final class RequestValidatorTest extends TestCase
{
    private const VALID = [
        'name' => 'Артём',
        'phone' => '+7 900 123-45-67',
        'comment' => 'Хочу узнать про сроки',
        'consent' => '1',
    ];

    public function testPassesCorrectRequest(): void
    {
        self::assertSame([], $this->validator()->validate(self::VALID));
    }

    public function testRequiresNameAndPhone(): void
    {
        $errors = $this->validator()->validate(['consent' => '1']);

        self::assertArrayHasKey('name', $errors);
        self::assertArrayHasKey('phone', $errors);
    }

    public function testRejectsBrokenPhone(): void
    {
        $errors = $this->validator()->validate(['phone' => '123'] + self::VALID);

        self::assertArrayHasKey('phone', $errors);
    }

    public function testRequiresConsentWhenConfigured(): void
    {
        $data = self::VALID;
        unset($data['consent']);

        self::assertArrayHasKey('consent', $this->validator()->validate($data));
        self::assertSame([], $this->validator(consentRequired: false)->validate($data));
    }

    public function testChecksSlotAgainstWhitelist(): void
    {
        $validator = $this->validator(slots: ['10:00-12:00', '12:00-14:00']);

        self::assertSame([], $validator->validate(self::VALID + ['slot' => '10:00-12:00']));
        self::assertArrayHasKey('slot', $validator->validate(self::VALID + ['slot' => 'ночью']));
    }

    /**
     * Пустой список раньше пропускал любую строку: интервалы длиннее колонки
     * отфильтрованы из настроек, форма без выбора, а подделанный slot
     * уходил в базу и ронял запись заявки.
     */
    public function testRejectsAnySlotWhenListIsEmpty(): void
    {
        $validator = $this->validator(slots: []);

        self::assertSame([], $validator->validate(self::VALID));
        self::assertArrayHasKey('slot', $validator->validate(self::VALID + ['slot' => str_repeat('9', 60)]));
    }

    public function testLimitsCommentLength(): void
    {
        $errors = $this->validator()->validate(['comment' => str_repeat('а', 1001)] + self::VALID);

        self::assertArrayHasKey('comment', $errors);
    }

    /**
     * Длина считается в символах, а не в байтах.
     *
     * Прежний тест брал 1001 кириллическую букву, то есть 2002 байта: замени
     * mb_strlen на strlen, и он всё равно проходил, а живой человек получал
     * отказ на 600 символах при maxlength=1000 в разметке.
     */
    public function testCommentAtTheLimitIsAccepted(): void
    {
        $errors = $this->validator()->validate(['comment' => str_repeat('я', 1000)] + self::VALID);

        self::assertSame([], $errors);
    }

    /** Имя тоже ограничено, и тоже в символах. */
    public function testLimitsNameLength(): void
    {
        $long = $this->validator()->validate(['name' => str_repeat('я', 101)] + self::VALID);

        self::assertArrayHasKey('name', $long);

        $atLimit = $this->validator()->validate(['name' => str_repeat('я', 100)] + self::VALID);

        self::assertSame([], $atLimit);
    }

    public function testCommentCanBeRequired(): void
    {
        $data = self::VALID;
        $data['comment'] = '';

        self::assertArrayHasKey('comment', $this->validator(commentRequired: true)->validate($data));
    }

    /**
     * @param list<string> $slots
     */
    private function validator(
        array $slots = [],
        bool $commentRequired = false,
        bool $consentRequired = true,
    ): RequestValidator {
        return new RequestValidator(
            new PhoneNormalizer(),
            $slots,
            $commentRequired,
            1000,
            $consentRequired,
        );
    }
}

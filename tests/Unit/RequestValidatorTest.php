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

    public function testLimitsCommentLength(): void
    {
        $errors = $this->validator()->validate(['comment' => str_repeat('а', 1001)] + self::VALID);

        self::assertArrayHasKey('comment', $errors);
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

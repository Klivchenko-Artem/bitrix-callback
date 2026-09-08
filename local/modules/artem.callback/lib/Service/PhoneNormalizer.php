<?php

declare(strict_types=1);

namespace Artem\Callback\Service;

/**
 * Приводит российский номер к единому виду +7XXXXXXXXXX.
 *
 * Нужен, чтобы в инфоблоке не лежали «8 (900) 123-45-67» и «+79001234567»
 * как два разных телефона: по нормализованному номеру ищутся повторные заявки.
 */
final class PhoneNormalizer
{
    private const DIGITS_IN_NUMBER = 10;

    /**
     * Возвращает номер в формате +7XXXXXXXXXX или null, если это не телефон.
     */
    public function normalize(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        $national = match (true) {
            strlen($digits) === self::DIGITS_IN_NUMBER => $digits,
            strlen($digits) === self::DIGITS_IN_NUMBER + 1 && ($digits[0] === '7' || $digits[0] === '8') => substr($digits, 1),
            default => null,
        };

        if ($national === null) {
            return null;
        }

        // Российские мобильные и городские начинаются с 3–9, коды 0–2 не выдаются
        if (!preg_match('/^[3-9]/', $national)) {
            return null;
        }

        return '+7' . $national;
    }

    public function isValid(string $phone): bool
    {
        return $this->normalize($phone) !== null;
    }

    /**
     * Человекочитаемый вид для писем и админки: +7 (900) 123-45-67.
     */
    public function format(string $phone): ?string
    {
        $normalized = $this->normalize($phone);

        if ($normalized === null) {
            return null;
        }

        $national = substr($normalized, 2);

        return sprintf(
            '+7 (%s) %s-%s-%s',
            substr($national, 0, 3),
            substr($national, 3, 3),
            substr($national, 6, 2),
            substr($national, 8, 2),
        );
    }
}

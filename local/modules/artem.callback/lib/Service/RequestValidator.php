<?php

declare(strict_types=1);

namespace Artem\Callback\Service;

/**
 * Проверяет то, что пришло из формы, до всякого Битрикса.
 *
 * Правила приходят из настроек компонента, поэтому валидатор ничего
 * не знает ни про инфоблоки, ни про $_POST, поэтому его можно гонять тестами.
 */
final class RequestValidator
{
    public const MAX_NAME_LENGTH = 100;

    /**
     * @param list<string> $allowedSlots Разрешённые интервалы звонка
     */
    public function __construct(
        private readonly PhoneNormalizer $phones,
        private readonly array $allowedSlots = [],
        private readonly bool $commentRequired = false,
        private readonly int $maxCommentLength = 1000,
        private readonly bool $consentRequired = true,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, string> Ошибки по полям, пустой массив, если всё хорошо
     */
    public function validate(array $data): array
    {
        $errors = [];

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Как к вам обращаться?';
        } elseif (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            $errors['name'] = 'Имя длиннее ' . self::MAX_NAME_LENGTH . ' символов';
        }

        $phone = trim((string) ($data['phone'] ?? ''));
        if ($phone === '') {
            $errors['phone'] = 'Без телефона перезвонить не получится';
        } elseif (!$this->phones->isValid($phone)) {
            $errors['phone'] = 'Проверьте номер: нужен российский, например +7 900 123-45-67';
        }

        $comment = trim((string) ($data['comment'] ?? ''));
        if ($this->commentRequired && $comment === '') {
            $errors['comment'] = 'Напишите пару слов о вопросе';
        } elseif (mb_strlen($comment) > $this->maxCommentLength) {
            $errors['comment'] = 'Комментарий длиннее ' . $this->maxCommentLength . ' символов';
        }

        // Пустой список не значит «любой»: интервалы, которые не влезли в колонку,
        // отфильтрованы из настроек, и произвольная строка потом не сохранится
        $slot = trim((string) ($data['slot'] ?? ''));
        if ($slot !== '' && !in_array($slot, $this->allowedSlots, true)) {
            $errors['slot'] = 'Выберите время из списка';
        }

        if ($this->consentRequired && empty($data['consent'])) {
            $errors['consent'] = 'Без согласия на обработку данных заявку принять нельзя';
        }

        return $errors;
    }
}

#!/bin/sh
set -e

DIST_URL="${BITRIX_DIST_URL:-https://www.1c-bitrix.ru/download/start_encode.tar.gz}"
ROOT=/var/www/html
ARCHIVE="$ROOT/.bitrix-dist.tar.gz"

# Ядро в репозитории не лежит: при первом старте тянем пробный дистрибутив.
# Отдача 1С-Битрикса рвёт соединение на середине, поэтому качаем циклом с
# докачкой. Внутренний --retry тут не годится: при повторе curl не пересчитывает
# смещение и начинает писать файл с нуля, а вот новый запуск с -C - продолжает
# с того места, где лежит уже скачанное.
if [ ! -d "$ROOT/bitrix" ]; then
    attempt=0
    until tar -tzf "$ARCHIVE" >/dev/null 2>&1; do
        attempt=$((attempt + 1))
        if [ "$attempt" -gt 40 ]; then
            echo "[bitrix] не смог докачать дистрибутив за 40 подходов, сдаюсь" >&2
            exit 1
        fi

        have=0
        [ -f "$ARCHIVE" ] && have=$(wc -c < "$ARCHIVE")
        echo "[bitrix] качаю дистрибутив (~300 МБ), подход $attempt, уже есть $have байт..."

        curl -fL -C - --connect-timeout 20 --speed-time 30 --speed-limit 10240 \
            -s -S -o "$ARCHIVE" "$DIST_URL" || true

        sleep 2
    done

    echo "[bitrix] распаковываю..."
    tar -xzf "$ARCHIVE" -C "$ROOT"
    rm -f "$ARCHIVE"
    echo "[bitrix] готово, мастер установки на http://localhost:${HTTP_PORT:-8081}/"
fi

chown -R www-data:www-data "$ROOT" 2>/dev/null || true

exec "$@"

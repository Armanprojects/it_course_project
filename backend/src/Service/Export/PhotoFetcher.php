<?php

declare(strict_types=1);

namespace App\Service\Export;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Загружает фото кандидата и отдаёт его как data-URI для вставки в PDF.
 *
 * Картинки лежат в облаке, а не у нас, поэтому Dompdf их сам не тянет:
 * remote-загрузка в нём выключена, иначе недоступный хост держал бы запрос
 * открытым до таймаута PHP. Здесь загрузка своя — с коротким таймаутом,
 * потолком размера и тихим отказом: резюме без фото печатается нормально,
 * а ошибка уходит в лог, а не на страницу.
 */
final readonly class PhotoFetcher
{
    private const TIMEOUT_SECONDS = 4;
    private const MAX_BYTES       = 4 * 1024 * 1024;

    /** Dompdf без ext-gd читает только эти форматы. */
    private const ALLOWED = ['image/jpeg', 'image/png', 'image/gif'];

    public function __construct(
        private HttpClientInterface $http,
        private LoggerInterface $logger,
    ) {
    }

    public function toDataUri(?string $url): ?string
    {
        if (null === $url || '' === trim($url)) {
            return null;
        }

        // Только http(s): data: и file: в значении атрибута открыли бы чтение
        // локальных файлов сервера через подставленный URL.
        $scheme = parse_url($url, \PHP_URL_SCHEME);

        if (!\in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        try {
            $response = $this->http->request('GET', $this->optimised($url), [
                'timeout'     => self::TIMEOUT_SECONDS,
                'max_duration' => self::TIMEOUT_SECONDS,
            ]);

            if (200 !== $response->getStatusCode()) {
                return null;
            }

            $bytes = $response->getContent();
        } catch (ExceptionInterface $e) {
            // Недоступное фото — не повод терять документ.
            $this->logger->warning('Не удалось загрузить фото для PDF.', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ('' === $bytes || \strlen($bytes) > self::MAX_BYTES) {
            return null;
        }

        // Тип берём из самих байтов, а не из заголовка ответа: заголовок может
        // соврать, а Dompdf на неизвестном формате бросает исключение.
        $info = @getimagesizefromstring($bytes);

        if (false === $info || !\in_array($info['mime'] ?? '', self::ALLOWED, true)) {
            return null;
        }

        return 'data:' . $info['mime'] . ';base64,' . base64_encode($bytes);
    }

    /**
     * Cloudinary умеет отдать уже обрезанный и сжатый вариант, если попросить
     * его в пути. Исходники там — это мегабайты в 1920px, а в документе фото
     * занимает три сантиметра. Для чужих хостов URL остаётся как есть.
     */
    private function optimised(string $url): string
    {
        if (!str_contains($url, 'res.cloudinary.com') || !str_contains($url, '/image/upload/')) {
            return $url;
        }

        // Уже с трансформацией — не трогаем, чтобы не склеить две подряд.
        [$head, $tail] = explode('/image/upload/', $url, 2);

        if (str_contains(explode('/', $tail)[0], ',')) {
            return $url;
        }

        return $head . '/image/upload/c_fill,g_face,w_300,h_360,q_auto,f_jpg/' . $tail;
    }
}

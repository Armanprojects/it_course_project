<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Command\ShowLastMailCommand;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Message;

/**
 * Записывает отправленные письма на диск, чтобы в dev можно было открыть
 * ссылку подтверждения без настройки SMTP.
 *
 * Symfony Mailer не умеет транспорт вида filesystem://, а null-транспорт
 * молча выбрасывает письмо, поэтому содержимое сохраняется здесь.
 * Подписчик регистрируется только в dev (см. services.yaml).
 *
 * Рядом с .eml кладётся .txt: тело письма закодировано в quoted-printable,
 * где «=» записан как «=3D», а длинные строки разорваны знаком «=» в конце.
 * Скопировать ссылку из .eml глазами нельзя — токен приходится склеивать
 * вручную, поэтому готовые ссылки выносятся в отдельный читаемый файл.
 */
final readonly class MailArchiveSubscriber implements EventSubscriberInterface
{
    public function __construct(private string $archiveDir)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // MessageEvent, а не SentMessageEvent: null-транспорт письмо
        // никуда не отправляет, и события об отправке не будет.
        // MessageEvent срабатывает до передачи транспорту — при любом DSN.
        return [MessageEvent::class => ['onMessage', -1000]];
    }

    public function onMessage(MessageEvent $event): void
    {
        $message = $event->getMessage();

        if (!$message instanceof Message) {
            return;
        }

        if (!is_dir($this->archiveDir) && !mkdir($this->archiveDir, 0o775, true) && !is_dir($this->archiveDir)) {
            return;
        }

        $raw       = $message->toString();
        $recipient = $this->firstRecipient($message);
        $base      = sprintf(
            '%s/%s_%s',
            $this->archiveDir,
            (new \DateTimeImmutable())->format('YmdHis'),
            preg_replace('/[^a-z0-9._-]+/i', '-', $recipient),
        );

        file_put_contents($base . '.eml', $raw);
        file_put_contents($base . '.txt', $this->summarize($raw, $recipient));
    }

    private function firstRecipient(Message $message): string
    {
        $addresses = $message->getHeaders()->get('To')?->getAddresses() ?? [];

        return $addresses[0]?->getAddress() ?? 'unknown';
    }

    /**
     * Человекочитаемая выжимка: кому, тема и ссылки одной строкой каждая.
     * Разбор письма переиспользуется из команды, чтобы правила декодирования
     * жили в одном месте.
     */
    private function summarize(string $raw, string $recipient): string
    {
        $links = ShowLastMailCommand::extractLinks($raw);

        $lines = [
            'Кому:  ' . $recipient,
            'Тема:  ' . ShowLastMailCommand::decodeSubject($raw),
            'Время: ' . (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            '',
            [] === $links ? 'Ссылок в письме нет.' : 'Ссылки:',
        ];

        foreach ($links as $link) {
            $lines[] = '  ' . $link;
        }

        return implode(\PHP_EOL, $lines) . \PHP_EOL;
    }
}

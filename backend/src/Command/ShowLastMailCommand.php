<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Показывает последнее письмо из var/mail в читаемом виде.
 *
 * В dev письма не уходят наружу, а тело закодировано в quoted-printable —
 * без декодирования ссылку подтверждения из файла не скопировать.
 */
#[AsCommand(
    name: 'app:mail:last',
    description: 'Показать последнее письмо и ссылки из него (только dev)',
)]
final class ShowLastMailCommand extends Command
{
    public function __construct(private readonly string $archiveDir)
    {
        parent::__construct();
    }

    /**
     * Тема письма целиком.
     *
     * Кириллица кодируется как =?utf-8?Q?...?=, и длинный заголовок при этом
     * разбивается на несколько физических строк (продолжение начинается с
     * пробела или табуляции). Читать только первую строку — значит обрезать
     * тему на середине.
     */
    public static function decodeSubject(string $raw): string
    {
        if (!preg_match("/^Subject:((?:.*\r?\n[ \t])*.*)$/m", $raw, $m)) {
            return '';
        }

        $folded = preg_replace("/\r?\n[ \t]+/", ' ', trim($m[1])) ?? '';

        return iconv_mime_decode($folded, \ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') ?: $folded;
    }

    /**
     * @return list<string>
     */
    public static function extractLinks(string $raw): array
    {
        // quoted-printable переносит длинные строки, дописывая "=" в конце,
        // и кодирует сам знак равенства как "=3D" — без обратного
        // преобразования ссылка окажется разорванной на части.
        $decoded = quoted_printable_decode($raw);

        preg_match_all('#https?://\S+#', $decoded, $matches);

        return array_values(array_unique(array_map(
            static fn (string $link): string => rtrim($link, '"\'>),.'),
            $matches[0],
        )));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $files = glob($this->archiveDir . '/*.eml') ?: [];

        if ([] === $files) {
            $io->warning('Писем нет. Каталог: ' . $this->archiveDir);

            return Command::SUCCESS;
        }

        // Имена начинаются с временной метки, поэтому сортировка по имени
        // совпадает с сортировкой по времени отправки.
        sort($files);
        $file = end($files);

        $raw = (string) file_get_contents($file);

        $io->section(basename($file));

        $subject = self::decodeSubject($raw);

        if ('' !== $subject) {
            $io->writeln('Тема: ' . $subject);
        }

        if (preg_match('/^To: (.+)$/m', $raw, $m)) {
            $io->writeln('Кому: ' . trim($m[1]));
        }

        $links = self::extractLinks($raw);

        if ([] === $links) {
            $io->warning('Ссылок в письме не найдено.');

            return Command::SUCCESS;
        }

        $io->newLine();
        $io->writeln('<info>Ссылки:</info>');

        foreach ($links as $link) {
            $io->writeln('  ' . $link);
        }

        return Command::SUCCESS;
    }
}

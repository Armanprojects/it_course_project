<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Отправляет одно тестовое письмо текущим транспортом.
 *
 * Нужна, чтобы проверить MAILER_DSN, не регистрируя пользователя: письма
 * подтверждения уходят внутри HTTP-запроса, и ошибка транспорта там видна
 * только в логах канала mail. Здесь исключение печатается прямо в консоль.
 *
 * Локально Brevo проверяется так (порт 443 открыт и на домашней сети,
 * поэтому результат совпадёт с продом на Render):
 *
 *   MAILER_DSN="brevo+api://xkeysib-KEY@default" \
 *       php bin/console app:mail:test you@example.com
 *
 * Отправитель (MAILER_FROM_ADDRESS) обязан быть подтверждён в Brevo
 * (Senders), иначе API ответит отказом даже с верным ключом. Получатель
 * при этом может быть любым — в отличие от Resend без своего домена.
 */
#[AsCommand(
    name: 'app:mail:test',
    description: 'Отправить тестовое письмо и показать ошибку транспорта, если она есть',
)]
final class SendTestMailCommand extends Command
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $mailerDsn,
        private readonly string $senderAddress,
        private readonly string $senderName,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('to', InputArgument::REQUIRED, 'Адрес получателя');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $to = (string) $input->getArgument('to');

        // Схема DSN без логина и ключа: показывает, каким транспортом
        // уйдёт письмо, и не светит секрет в выводе команды.
        $scheme = parse_url($this->mailerDsn, \PHP_URL_SCHEME) ?: '(не разобран)';
        $io->writeln(sprintf('Транспорт: <info>%s</info>', $scheme));
        $io->writeln(sprintf('От: <info>%s</info> → Кому: <info>%s</info>', $this->senderAddress, $to));

        if ('null' === $scheme) {
            // Отдельный случай: команда завершится успехом, но письма не будет.
            $io->warning('MAILER_DSN=null://null — письмо будет отброшено. Смотрите var/mail (app:mail:last).');
        }

        $email = (new Email())
            ->from(new Address($this->senderAddress, $this->senderName))
            ->to($to)
            ->subject('CVMatch: проверка почтового транспорта')
            ->text(sprintf('Тестовое письмо, отправленное транспортом %s.', $scheme));

        $startedAt = microtime(true);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            // Ответ провайдера (например, отказ Brevo про неподтверждённого
            // отправителя) лежит в исходном сообщении — без него ошибка
            // выглядит безымянной.
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Отправлено за %d мс.', (int) round((microtime(true) - $startedAt) * 1000)));

        return Command::SUCCESS;
    }
}

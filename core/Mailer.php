<?php

declare(strict_types=1);

namespace Core;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

final class Mailer
{
    /**
     * Envia e-mail usando Microsoft Graph (preferência) ou SMTP (fallback).
     *
     * @throws \RuntimeException
     */
    public static function send(string $toEmail, ?string $toName, string $subject, string $html): void
    {
        $driver = strtolower((string)($_ENV['MAIL_DRIVER'] ?? 'graph'));

        try {
            if ($driver === 'graph') {
                // === Usa Microsoft Graph ===
                $mailer = new GraphMailer();
                $mailer->send($toEmail, $toName, $subject, $html);
                return;
            }

            // === Fallback para SMTP ===
            $m = new PHPMailer(true);
            $m->isSMTP();
            $m->Host = $_ENV['SMTP_HOST'] ?? 'localhost';
            $m->Port = (int)($_ENV['SMTP_PORT'] ?? 25);

            $user = $_ENV['SMTP_USER'] ?? '';
            $pass = $_ENV['SMTP_PASS'] ?? '';
            if ($user !== '' || $pass !== '') {
                $m->SMTPAuth = true;
                $m->Username = $user;
                $m->Password = $pass;
            }

            $secure = $_ENV['SMTP_SECURE'] ?? '';
            if ($secure) {
                $m->SMTPSecure = $secure;
            }

            $m->setFrom($_ENV['MAIL_FROM'] ?? 'no-reply@nimbusops.local', $_ENV['MAIL_FROM_NAME'] ?? 'NimbusOps');
            $m->addAddress($toEmail, $toName ?? '');
            $m->isHTML(true);
            $m->Subject = $subject;
            $m->Body    = $html;
            $m->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html));

            $m->send();
        } catch (Exception $e) {
            throw new \RuntimeException('Mailer error: ' . $e->getMessage());
        }
    }
}

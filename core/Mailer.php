<?php

declare(strict_types=1);

namespace Core;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

final class Mailer
{
    /** @throws \RuntimeException */
    public static function send(string $toEmail, string $toName, string $subject, string $html): void
    {
        $m = new PHPMailer(true);
        try {
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
            $m->addAddress($toEmail, $toName);
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

<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mail
{
    public $smtp_server;
    public $smtp_username;
    public $smtp_password;
    public $smtp_port;
    public $from;
    public $from_name;


    public $to;
    public $subject;

    private $mail;

    public function __construct()
    {
        $this->smtp_server = env('MAIL_HOST');
        $this->smtp_username = env('MAIL_USERNAME');
        $this->smtp_password = env('MAIL_PASSWORD');
        $this->smtp_port = env('MAIL_PORT', 587);
        $this->from = env('MAIL_FROM_ADDRESS');
        $this->from_name = env('MAIL_FROM_NAME');

        // Configuração no php.ini ou via ini_set()
        ini_set('SMTP', $this->smtp_server);
        ini_set('smtp_port', $this->smtp_port);
        ini_set('sendmail_from', $this->from);

        $this->mail = new PHPMailer(true);
    }

    public function send($to, $subject, $body, $isHtml = false)
    {
        try {
            $encryption = strtolower((string) env('MAIL_ENCRYPTION', ''));
            $auth = filter_var(env('MAIL_AUTH', true), FILTER_VALIDATE_BOOLEAN);

            //Server settings
            $this->mail->isSMTP();
            $this->mail->CharSet    = "UTF-8";
            $this->mail->Encoding   = "base64";
            $this->mail->Host       = $this->smtp_server;
            $this->mail->SMTPAuth   = $auth;
            $this->mail->Username   = $this->smtp_username;
            $this->mail->Password   = $this->smtp_password;
            if ($encryption === 'tls') {
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else if ($encryption === 'ssl') {
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $this->mail->SMTPSecure = '';
                $this->mail->SMTPAutoTLS = false;
            }
            $this->mail->Port       = $this->smtp_port;

            //Recipients
            $this->mail->setFrom($this->from, $this->from_name);
            $this->mail->addAddress($to);

            //Content
            $this->mail->isHTML($isHtml);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $isHtml ? $body : nl2br($body);
            if (!$isHtml) {
                $this->mail->AltBody = strip_tags($body);
            }

            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Message could not be sent. Mailer Error: {$this->mail->ErrorInfo}");
            throw new \RuntimeException($this->mail->ErrorInfo ?: $e->getMessage(), 0, $e);
        }
    }
}

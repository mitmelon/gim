<?php
namespace Manomite\Engine;

use \Manomite\Protect\PostFilter;
use \Symfony\Component\Mailer\Transport;
use \Symfony\Component\Mailer\Mailer;
use \Symfony\Component\Mime\Email as Em;
use \Symfony\Component\Mime\Address;
use \Symfony\Component\Mailer\Transport\SendmailTransport;

class Email
{
    protected $subject;
    protected $to;
    protected $text;
    protected $replyTo;
    protected $file;
    protected $email;
    protected $from;
    protected $body;
    protected $count = 0;
    protected $message;

    public function __construct(
        string $subject,
        array $to,
        string $text,
        string $from = null,
        string $replyTo = null,
        string $file = null
    ) {
        $this->subject = (new PostFilter)->strip($subject);
        $this->body = $text;
        $this->from = $from;
        $this->replyTo = $replyTo;
        $this->file = $file;

        if (is_array($to)) {
            if (count($to) > 0) {
                $this->email = $to;
            }
        }
    }

    public function SMTP()
    {
        try {
            if (empty($this->email) || $this->email === false) {
                return;
            }
            // Create the Transport
            $transport = Transport::fromDsn('smtp://' . urlencode(SMTP_USERNAME) . ':' . urlencode(SMTP_PASSWORD) . '@' . urlencode(SMTP_HOST) . ':' . urlencode(SMTP_PORT) . '?verify_peer=0');
            $mailer = new Mailer($transport);

            $client = (new Em());
            $client->from(new Address($this->from ?: SENDER_EMAIL, APP_NAME));
            $client->subject($this->subject);
            $client->html($this->body);
            if ($this->file !== null) {
                $client->attachFromPath($this->file);
            }
            $client->replyTo($this->replyTo ?: SENDER_EMAIL);
            $client->priority(Em::PRIORITY_HIGH);
            foreach ($this->email as $recipient) {
                $client->to($recipient);
                $mailer->send($client);
                $this->count++;
            }
            if ($this->count > 0) {
                return true;
            }
        } catch (\Exception $e) {
            throw new \Exception($e);
        }
    }

    public function MAIL()
    {
        if (empty($this->email) || $this->email === false) {
            return;
        }
        // Create the Transport
        $transport = new SendmailTransport();
        $mailer = new Mailer($transport);

        $client = (new Em());
        $client->from(new Address($this->from ?: SENDER_EMAIL, APP_NAME));
        $client->subject($this->subject);
        $client->html($this->message);
        if ($this->file !== null) {
            $client->attachFromPath($this->file);
        }
        $client->replyTo($this->replyTo ?: SENDER_EMAIL);
        $client->priority(Em::PRIORITY_HIGH);
        $mailer->header('Source')->value(APP_NAME);
        foreach ($this->email as $recipient) {
            $client->to($recipient);
            $mailer->send($client);
            if ($mailer->send($client)) {
                $this->count++;
            }
        }
        if ($this->count > 0) {
            return true;
        }
    }
}

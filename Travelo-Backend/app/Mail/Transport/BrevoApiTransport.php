<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\RawMessage;

/**
 * Sends mail through Brevo's transactional HTTP API (v3) with an API key.
 *
 * Brevo issues two kinds of credentials:
 *   - API key   (xkeysib-...)  -> for https://api.brevo.com/v3  (used here)
 *   - SMTP key  (xsmtpsib-...) -> for smtp-relay.brevo.com
 * Using the HTTP API means we can reuse the API key and get explicit,
 * readable errors (invalid address, unverified sender, etc.).
 */
class BrevoApiTransport implements TransportInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $endpoint = 'https://api.brevo.com/v3/smtp/email'
    ) {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $email = $message instanceof Email ? $message : MessageConverter::toEmail($message);

        $payload = [
            'sender' => $this->toAddress($email->getFrom()[0] ?? null, config('mail.from.name', 'Travelo')),
            'to' => array_map(fn (Address $address) => $this->toAddress($address), $email->getTo()),
            'subject' => (string) $email->getSubject(),
        ];

        if ($html = $this->bodyToString($email->getHtmlBody())) {
            $payload['htmlContent'] = $html;
        }

        if ($text = $this->bodyToString($email->getTextBody())) {
            $payload['textContent'] = $text;
        }

        if ($cc = $email->getCc()) {
            $payload['cc'] = array_map(fn (Address $address) => $this->toAddress($address), $cc);
        }

        if ($bcc = $email->getBcc()) {
            $payload['bcc'] = array_map(fn (Address $address) => $this->toAddress($address), $bcc);
        }

        if ($replyTo = $email->getReplyTo()) {
            $payload['replyTo'] = $this->toAddress($replyTo[0]);
        }

        $response = Http::withHeaders([
            'api-key' => $this->apiKey,
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->timeout(25)->post($this->endpoint, $payload);

        if ($response->failed()) {
            $detail = $response->json('message') ?? $response->body();

            throw new TransportException(sprintf(
                'Brevo API menolak email (HTTP %d): %s',
                $response->status(),
                is_string($detail) ? $detail : json_encode($detail)
            ));
        }

        Log::info('Brevo accepted email', [
            'messageId' => $response->json('messageId'),
        ]);

        return new SentMessage($message, $envelope ?? Envelope::create($message));
    }

    public function __toString(): string
    {
        return 'brevo-api';
    }

    /**
     * Brevo rejects an empty `name`, so it is only included when present.
     *
     * @return array{email: string, name?: string}
     */
    private function toAddress(?Address $address, ?string $defaultName = null): array
    {
        $result = ['email' => $address?->getAddress() ?? ''];
        $name = $address?->getName() ?: $defaultName;

        if (is_string($name) && trim($name) !== '') {
            $result['name'] = $name;
        }

        return $result;
    }

    private function bodyToString(mixed $body): ?string
    {
        if (is_resource($body)) {
            $body = stream_get_contents($body);
        }

        if (! is_string($body) || trim($body) === '') {
            return null;
        }

        return $body;
    }
}

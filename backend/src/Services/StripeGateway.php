<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\HttpException;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Everything that talks to Stripe lives in this one class. It returns plain arrays,
 * so the rest of the code does not depend on Stripe's SDK types.
 *
 * The keys are optional at start-up: the store must work even where payments are
 * not configured yet. Using a payment feature without its key is a clear 503.
 */
final class StripeGateway
{
    private ?StripeClient $client = null;

    public function __construct(
        private readonly ?string $secretKey,
        private readonly ?string $webhookSecret,
    ) {
    }

    /**
     * Opens a Stripe-hosted payment page for an order. We never see card numbers:
     * the buyer types them on Stripe's page, and Stripe tells us the result.
     *
     * @param list<array{name: string, imageUrl: string, unitPriceCents: int, quantity: int}> $lines
     * @return array{id: string, url: string}
     */
    public function createCheckoutSession(int $orderId, string $email, array $lines, string $successUrl, string $cancelUrl, int $expiresAt): array
    {
        try {
            $session = $this->client()->checkout->sessions->create([
                'mode' => 'payment',
                'payment_method_types' => ['card'], // cards are paid instantly, so "completed" always means "paid"
                'customer_email' => $email,
                'client_reference_id' => (string) $orderId,
                'metadata' => ['order_id' => (string) $orderId], // comes back in the webhook
                'line_items' => array_map(fn (array $line) => [
                    'quantity' => $line['quantity'],
                    'price_data' => [
                        'currency' => 'eur',
                        'unit_amount' => $line['unitPriceCents'], // Stripe also counts in cents
                        'product_data' => ['name' => $line['name'], 'images' => [$line['imageUrl']]],
                    ],
                ], $lines),
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'expires_at' => $expiresAt,
            ]);
        } catch (ApiErrorException $e) {
            throw $this->unavailable($e);
        }

        return ['id' => $session->id, 'url' => $session->url];
    }

    /** @return array{status: ?string, paid: bool} status is "open", "complete" or "expired" */
    public function retrieveSession(string $sessionId): array
    {
        try {
            $session = $this->client()->checkout->sessions->retrieve($sessionId);
        } catch (ApiErrorException $e) {
            throw $this->unavailable($e);
        }

        return ['status' => $session->status, 'paid' => $session->payment_status === 'paid'];
    }

    /**
     * Checks that a webhook really comes from Stripe: Stripe signs each request with
     * our webhook secret (HMAC-SHA256 over the timestamp and the exact body). The SDK
     * recomputes the signature and rejects anything older than 5 minutes (replays).
     *
     * @return array{type: string, orderId: ?int, paid: bool}
     */
    public function parseWebhook(string $payload, string $signature): array
    {
        if ($this->webhookSecret === null) {
            throw new HttpException(503, 'Webhooks are not configured (STRIPE_WEBHOOK_SECRET)');
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $this->webhookSecret);
        } catch (SignatureVerificationException | UnexpectedValueException) {
            throw new HttpException(400, 'Invalid webhook signature');
        }

        $object = $event->data->object;
        $orderId = $object->metadata->order_id ?? null;

        return [
            'type' => $event->type,
            'orderId' => $orderId === null ? null : (int) $orderId,
            'paid' => ($object->payment_status ?? null) === 'paid',
        ];
    }

    private function client(): StripeClient
    {
        if ($this->secretKey === null) {
            throw new HttpException(503, 'Payments are not configured (STRIPE_SECRET_KEY)');
        }

        return $this->client ??= new StripeClient($this->secretKey);
    }

    private function unavailable(ApiErrorException $e): HttpException
    {
        error_log('Stripe error: ' . $e->getMessage());

        return new HttpException(502, 'The payment provider did not respond. Please try again.');
    }
}

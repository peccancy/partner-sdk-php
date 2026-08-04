# peccancy/partner-sdk (PHP)

[![CI](https://github.com/peccancy/partner-sdk-php/actions/workflows/ci.yml/badge.svg)](https://github.com/peccancy/partner-sdk-php/actions/workflows/ci.yml)

Official PHP SDK for the **Peccancy** disputes/betting platform.

Connect your game or app once and let your users bet on outcomes: create disputes, control
their lifecycle, declare winners, take payments, and verify signed result webhooks. Every
request is authenticated for you with HMAC-SHA256 — you never build a signature by hand.

- Requires PHP 8.0+, `ext-curl`, `ext-json` (no other dependencies)
- Same surface as our Node / Python / Go SDKs

## Install

```bash
composer require peccancy/partner-sdk
```

## Get credentials

1. Register as a partner at **https://disputes.online/profile?tab=partners** and open your partner.
2. Copy your **`partnerId`** (UUID) and **`secret`**.
3. Set a **`callback_url`** on your partner if you want result/payment webhooks.

## Quickstart

```php
use Peccancy\Partner\PartnerClient;

$client = new PartnerClient(
    'https://disputes.online/partner', // the partner API base
    getenv('PECCANCY_PARTNER_ID'),
    getenv('PECCANCY_PARTNER_SECRET')
);

$dispute = $client->createDispute([
    'description' => 'Who wins Round 5?',
    'variants'    => [['description' => 'Alice'], ['description' => 'Bob']],
    'stopDate'    => new DateTime('+5 minutes'),
    'finishDate'  => new DateTime('+30 minutes'),
]);

echo $dispute['id'];
```

## Disputes

```php
$dispute = $client->createDispute([
    'description' => 'Who wins?',
    'variants'    => [['description' => 'Team A'], ['description' => 'Team B']],
    'stopDate'    => '2026-01-01T12:00:00Z',
    'finishDate'  => '2026-01-01T13:00:00Z',
    'minBet'      => 1,        // optional
    'maxBet'      => 100,      // optional
    'lang'        => 'en',     // optional (default "en")
    'isClosed'    => false,    // optional — if true, only you resolve the winner
]);

$client->stopBets($dispute['id']);           // close betting
$client->startGame($dispute['id']);          // mark in-progress (optional)
$client->setWinner($dispute['id'], 'Team A'); // declare winner by variant description
```

## Payments

```php
// Charge a known user by email / phone / id:
$tx = $client->initPayment(9.99, ['type' => 'email', 'value' => 'user@example.com'], 'Coins pack');

// Or a hosted payment link (invoice):
$invoice = $client->createInvoice(19.99, 'Tournament entry');
echo $invoice['invoice_url'];
```

## Webhooks (results & payments)

The platform POSTs a signed JSON callback to your `callback_url`. **Always verify it**:

```php
use Peccancy\Partner\PartnerClient;

$body = json_decode(file_get_contents('php://input'), true);

if (!PartnerClient::verifyCallback($body, getenv('PECCANCY_PARTNER_SECRET'))) {
    http_response_code(401);
    exit('bad signature');
}

// ... credit the user / mark the order paid ...
http_response_code(200);
```

Callback body: `{ "transaction_id", "status", "amount", "timestamp", "signature" }`.
`verifyCallback` checks the HMAC signature **and** timestamp freshness (±120s).

## Authentication (under the hood)

The SDK adds `X-Partner-ID`, `X-Partner-Timestamp`, `X-Partner-Signature` headers (payment
endpoints put signature/timestamp in the body). The signature is
`hash_hmac('sha256', $payload, $secret)` (hex), where `$payload` is a colon-joined string
per operation:

| Operation | Signed payload |
|-----------|----------------|
| createDispute | `partnerId:description:timestamp` |
| stopBets / startGame | `partnerId:disputeId:timestamp` |
| setWinner | `partnerId:disputeId:winnerTeamName:timestamp` |
| initPayment | `partnerId:amount(2dp):userValue:timestamp` |
| createInvoice | `partnerId:amount(2dp):description:timestamp` |
| callback (inbound) | `transactionId:status:amount(2dp):timestamp` |

Keep your server clock in sync (NTP) — the platform rejects timestamps more than 120s off.

## Errors

Non-2xx responses throw `Peccancy\Partner\PartnerApiError` with `->statusCode` and `->body`.

## Examples

- [`examples/connect_your_game.php`](./examples/connect_your_game.php)
- [`examples/webhook_receiver.php`](./examples/webhook_receiver.php)

## Links

- **Register / get credentials:** https://disputes.online/profile?tab=partners
- **Other SDKs:** [Node](https://github.com/peccancy/partner-sdk-node) · [PHP](https://github.com/peccancy/partner-sdk-php) · [Python](https://github.com/peccancy/partner-sdk-python) · [Go](https://github.com/peccancy/partner-sdk-go)

## License

MIT

<?php

declare(strict_types=1);

namespace Peccancy\Partner;

/**
 * PartnerClient is the entry point of the SDK. Construct it once with your credentials and
 * reuse it. Every call is authenticated for you via HMAC-SHA256 + a fresh timestamp.
 *
 *   $client = new PartnerClient('https://disputes.online/partner', $partnerId, $secret);
 *   $dispute = $client->createDispute([...]);
 */
class PartnerClient
{
    private string $baseUrl;
    private string $partnerId;
    private string $secret;
    private int $timeoutMs;

    public function __construct(string $baseUrl, string $partnerId, string $secret, int $timeoutMs = 15000)
    {
        if ($baseUrl === '') {
            throw new \InvalidArgumentException('baseUrl is required');
        }
        if ($partnerId === '') {
            throw new \InvalidArgumentException('partnerId is required');
        }
        if ($secret === '') {
            throw new \InvalidArgumentException('secret is required');
        }
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->partnerId = $partnerId;
        $this->secret = $secret;
        $this->timeoutMs = $timeoutMs;
    }

    // ---- Disputes ----

    /**
     * Create a dispute.
     *
     * @param array $input keys: description (string), variants (array of ['description'=>string]),
     *   finishDate (DateTimeInterface|string), stopDate (DateTimeInterface|string),
     *   minBet? (int), maxBet? (int), lang? (string), isClosed? (bool)
     * @return array the created dispute
     */
    public function createDispute(array $input): array
    {
        $ts = time();
        $desc = (string) ($input['description'] ?? '');
        $payload = "{$this->partnerId}:{$desc}:{$ts}";

        $body = [
            'description' => $desc,
            'variants' => $input['variants'] ?? [],
            'finish_date' => self::toIso($input['finishDate'] ?? ($input['finish_date'] ?? null)),
            'stop_date' => self::toIso($input['stopDate'] ?? ($input['stop_date'] ?? null)),
        ];
        foreach (['minBet' => 'min_bet', 'maxBet' => 'max_bet', 'lang' => 'lang', 'isClosed' => 'is_closed'] as $k => $field) {
            if (array_key_exists($k, $input)) {
                $body[$field] = $input[$k];
            }
        }

        $data = $this->headerSigned('POST', '/api/v1/partner/disputes', $payload, $ts, $body);
        return $data['data'] ?? [];
    }

    /** Close betting on a dispute. */
    public function stopBets(string $disputeId): void
    {
        $ts = time();
        $payload = "{$this->partnerId}:{$disputeId}:{$ts}";
        $this->headerSigned('PUT', "/api/v1/partner/disputes/{$disputeId}/stopbet", $payload, $ts, null);
    }

    /** Mark a dispute in-progress. */
    public function startGame(string $disputeId): void
    {
        $ts = time();
        $payload = "{$this->partnerId}:{$disputeId}:{$ts}";
        $this->headerSigned('PUT', "/api/v1/partner/disputes/{$disputeId}/startgame", $payload, $ts, null);
    }

    /** Declare the winning outcome by its variant description (team name). */
    public function setWinner(string $disputeId, string $winnerTeamName): void
    {
        $ts = time();
        $payload = "{$this->partnerId}:{$disputeId}:{$winnerTeamName}:{$ts}";
        $this->headerSigned('POST', "/api/v1/partner/disputes/{$disputeId}/winner", $payload, $ts, [
            'winner_team_name' => $winnerTeamName,
        ]);
    }

    // ---- Payments ----

    /**
     * Charge a known user.
     *
     * @param array $userIdentifier ['type' => 'email'|'phone'|'id', 'value' => string]
     * @return array ['transaction_id'=>..., 'status'=>..., 'amount'=>...]
     */
    public function initPayment(float $amount, array $userIdentifier, string $description): array
    {
        $ts = time();
        $val = (string) ($userIdentifier['value'] ?? '');
        $payload = "{$this->partnerId}:" . self::money($amount) . ":{$val}:{$ts}";

        return $this->bodySigned('/partner/api/v1/init', [
            'id' => $this->partnerId,
            'amount' => $amount,
            'user_identifier' => ['type' => $userIdentifier['type'] ?? '', 'value' => $val],
            'description' => $description,
            'signature' => Signature::sign($payload, $this->secret),
            'timestamp' => $ts,
        ]);
    }

    /**
     * Create a hosted payment link (invoice).
     *
     * @return array ['invoice_id'=>..., 'invoice_url'=>..., 'status'=>..., 'amount'=>...]
     */
    public function createInvoice(float $amount, string $description): array
    {
        $ts = time();
        $payload = "{$this->partnerId}:" . self::money($amount) . ":{$description}:{$ts}";

        return $this->bodySigned('/partner/api/v1/invoice', [
            'amount' => $amount,
            'description' => $description,
            'signature' => Signature::sign($payload, $this->secret),
            'timestamp' => $ts,
        ]);
    }

    // ---- Webhooks ----

    /**
     * Verify a callback the platform POSTed to your callback_url. Returns true when the
     * signature is valid and the timestamp is fresh (default ±120s). Always verify first.
     *
     * @param array $cb decoded JSON body with keys transaction_id, status, amount, timestamp, signature
     */
    public static function verifyCallback(array $cb, string $secret, int $maxSkewSec = 120): bool
    {
        if (!isset($cb['signature'])) {
            return false;
        }
        $amount = (float) ($cb['amount'] ?? 0);
        $payload = ($cb['transaction_id'] ?? '') . ':' . ($cb['status'] ?? '') . ':' . self::money($amount) . ':' . ($cb['timestamp'] ?? '');
        if (!Signature::equals(Signature::sign($payload, $secret), (string) $cb['signature'])) {
            return false;
        }
        $age = abs(time() - (int) ($cb['timestamp'] ?? 0));
        return $age <= $maxSkewSec;
    }

    // ---- internals ----

    private function headerSigned(string $method, string $path, string $payload, int $ts, ?array $body): array
    {
        return $this->request($method, $path, [
            'X-Partner-ID: ' . $this->partnerId,
            'X-Partner-Timestamp: ' . $ts,
            'X-Partner-Signature: ' . Signature::sign($payload, $this->secret),
        ], $body);
    }

    private function bodySigned(string $path, array $body): array
    {
        return $this->request('POST', $path, ['X-Partner-ID: ' . $this->partnerId], $body);
    }

    private function request(string $method, string $path, array $headers, ?array $body): array
    {
        $ch = curl_init($this->baseUrl . $path);
        $headers[] = 'Content-Type: application/json';
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT_MS => $this->timeoutMs,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $resp = curl_exec($ch);
        if ($resp === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new PartnerApiError("request failed: {$err}", 0, '');
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($status < 200 || $status >= 300) {
            throw new PartnerApiError("Peccancy API {$method} {$path} -> {$status}", $status, (string) $resp);
        }
        if ($resp === '' || $resp === null) {
            return [];
        }
        $decoded = json_decode((string) $resp, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param mixed $d */
    private static function toIso($d): ?string
    {
        if ($d === null) {
            return null;
        }
        if ($d instanceof \DateTimeInterface) {
            return $d->format(\DateTimeInterface::ATOM);
        }
        return (string) $d;
    }

    private static function money(float $n): string
    {
        return number_format($n, 2, '.', '');
    }
}

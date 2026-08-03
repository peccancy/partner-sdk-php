<?php

/**
 * Webhook receiver example: verify signed callbacks from the platform.
 *
 * Point your partner's callback_url at https://your-host/peccancy/callback (this file).
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Peccancy\Partner\PartnerClient;

$secret = getenv('PECCANCY_PARTNER_SECRET');
if ($secret === false || $secret === '') {
    http_response_code(500);
    exit('missing PECCANCY_PARTNER_SECRET');
}

$body = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($body)) {
    http_response_code(400);
    exit('bad json');
}

if (!PartnerClient::verifyCallback($body, $secret)) {
    http_response_code(401);
    exit('bad signature');
}

// ✅ Verified & fresh — safe to act on.
error_log("callback: {$body['transaction_id']} {$body['status']} {$body['amount']}");
// ... credit the user / mark the order paid ...

http_response_code(200);
echo 'ok';

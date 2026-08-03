<?php

/**
 * Connect-your-game example: the full dispute lifecycle for one match.
 *
 * Run: PECCANCY_PARTNER_ID=... PECCANCY_PARTNER_SECRET=... php examples/connect_your_game.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php'; // or require the src/*.php files directly

use Peccancy\Partner\PartnerClient;
use Peccancy\Partner\PartnerApiError;

$client = new PartnerClient(
    getenv('PECCANCY_BASE_URL') ?: 'https://disputes.online/partner',
    require_env('PECCANCY_PARTNER_ID'),
    require_env('PECCANCY_PARTNER_SECRET')
);

try {
    // 1) A new match starts -> open a dispute.
    $dispute = $client->createDispute([
        'description' => 'Who wins the Alias round?',
        'variants'    => [['description' => 'Red Team'], ['description' => 'Blue Team']],
        'stopDate'    => new DateTime('+2 minutes'),
        'finishDate'  => new DateTime('+20 minutes'),
        'minBet'      => 1,
    ]);
    echo "created dispute: {$dispute['id']}\n";

    // 2) The round begins -> close betting and mark in-progress.
    $client->stopBets($dispute['id']);
    $client->startGame($dispute['id']);
    echo "betting closed, game in progress\n";

    // 3) The round ends -> declare the winner by its name.
    $client->setWinner($dispute['id'], 'Red Team');
    echo "winner set: Red Team\n";
} catch (PartnerApiError $e) {
    fwrite(STDERR, "API error {$e->statusCode}: {$e->body}\n");
    exit(1);
}

function require_env(string $name): string
{
    $v = getenv($name);
    if ($v === false || $v === '') {
        fwrite(STDERR, "missing env {$name}\n");
        exit(1);
    }
    return $v;
}

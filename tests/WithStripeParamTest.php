<?php

/**
 * Standalone test for the with_stripe table parameter (routes to the
 * GoatCheese behavior instead of being absorbed as a bogus column / tripping
 * the #36 drop-count gate).
 *
 * No phpunit dependency exists in this repo, so this is a plain PHP script that
 * uses the repo's own composer autoloader, exercises the public
 * HjsonToPropelXml::process() entry point, and asserts on the emitted XML.
 *
 * Run:  php tests/WithStripeParamTest.php
 * Exits 0 on success, non-zero (with a message) on the first failed assertion.
 */

require __DIR__ . '/../vendor/autoload.php';

use HjsonToPropelXml\HjsonToPropelXml;
use Psr\Log\AbstractLogger;

/** Minimal PSR-3 logger that just collects messages (keeps the test self-contained). */
final class CollectingLogger extends AbstractLogger
{
    /** @var array<int,string> */
    public array $messages = [];

    public function log($level, $message, array $context = []): void
    {
        $this->messages[] = '[' . $level . '] ' . (string) $message;
    }
}

function fail(string $msg): void
{
    fwrite(STDERR, "FAIL: $msg\n");
    exit(1);
}

function assertContains(string $needle, string $haystack, string $what): void
{
    if (strpos($haystack, $needle) === false) {
        fail("$what — expected to find:\n  $needle\nin:\n$haystack");
    }
    echo "ok: $what\n";
}

function assertNotContains(string $needle, string $haystack, string $what): void
{
    if (strpos($haystack, $needle) !== false) {
        fail("$what — did NOT expect to find:\n  $needle\nin:\n$haystack");
    }
    echo "ok: $what\n";
}

$hjson = <<<'HJSON'
{
    billingtest:
    {
        is_builder: true,

        "invoice('Invoice')":
        {
            with_stripe: { amount_column: "total", currency: "cad", client_column: "id_client" },

            id_invoice: ["primary"],
            "total('Total')": ["decimal(10,2)"],
            id_client: ["integer()"]
        },

        "client('Client')":
        {
            id_client: ["primary"],
            "name('Name')": ["string(100)"]
        }
    }
}
HJSON;

$logger = new CollectingLogger();
$converter = new HjsonToPropelXml($logger);
$converter->process($hjson);
$xml = $converter->getXml();

if (!is_string($xml) || $xml === '') {
    fail("process() produced no XML. Logger said:\n" . implode("\n", $logger->messages));
}

echo "=== Emitted XML ===\n$xml\n===================\n";

// 1. with_stripe must be a GoatCheese behavior parameter, NOT a <column>.
assertContains('name="with_stripe"', $xml, 'with_stripe passed through as parameter');
assertNotContains('<column name="with_stripe"', $xml, 'with_stripe is NOT emitted as a column');

// Sanity: it sits inside a <parameter ...> tag.
if (!preg_match('/<parameter[^>]*name="with_stripe"/', $xml)) {
    fail('with_stripe should be emitted as a <parameter> element');
}
echo "ok: with_stripe is a <parameter> element\n";

// 2. #36 drop-count gate must not be tripped by a whitelisted key.
$dropCount = $converter->getDropCount();
if ($dropCount !== 0) {
    fail('with_stripe tripped the #36 drop-count gate (' . $dropCount . "):\n" . implode("\n", $converter->getDropMessages()));
}
echo "ok: no dropped-parameter gate trip (getDropCount() === 0)\n";

echo "\nALL ASSERTIONS PASSED\n";
exit(0);

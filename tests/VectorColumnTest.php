<?php

/**
 * Standalone test for MariaDB VECTOR column support.
 *
 * No phpunit dependency exists in this repo, so this is a plain PHP script that
 * uses the repo's own composer autoloader, exercises the public
 * HjsonToPropelXml::process() entry point, and asserts on the emitted XML.
 *
 * Run:  php tests/VectorColumnTest.php
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
    chatbot:
    {
        is_builder: true,

        "corpus_chunk('Corpus chunk')":
        {
            set_parent_menu: "Knowledge",

            with_vector: { column: "embedding", dimensions: 3072, metric: "cosine" },

            id_corpus_chunk: ["primary"],
            "content('Content')": ["text", "required"],
            "embedding('Embedding')": ["vector(3072)", "required"]
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

// 1. The embedding column must be a BLOB with a dimensioned VECTOR sqlType.
assertContains('name="embedding"', $xml, 'embedding column emitted');
assertContains('type="BLOB"', $xml, 'embedding column type is BLOB');
assertContains('sqlType="VECTOR(3072)"', $xml, 'embedding column sqlType is VECTOR(3072)');

// Required keyword still applies on top of the vector defaults.
assertContains('required="true"', $xml, 'required keyword respected on vector column');

// The ('Embedding') description still flows through.
assertContains('description="Embedding"', $xml, 'vector column description preserved');

// 2. with_vector must be a GoatCheese behavior parameter, NOT a <column>.
assertContains('name="with_vector"', $xml, 'with_vector passed through as parameter');
assertNotContains('<column name="with_vector"', $xml, 'with_vector is NOT emitted as a column');

// Sanity: it sits inside a <parameter ...> tag.
if (!preg_match('/<parameter[^>]*name="with_vector"/', $xml)) {
    fail('with_vector should be emitted as a <parameter> element');
}
echo "ok: with_vector is a <parameter> element\n";

echo "\nALL ASSERTIONS PASSED\n";
exit(0);

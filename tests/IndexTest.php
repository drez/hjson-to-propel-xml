<?php

/**
 * Standalone test for non-unique index emission.
 *
 * - columns flagged "index" still share ONE <index> block (historic behaviour,
 *   e.g. id_tenant + created), emitted first so its generated name is stable
 * - the table-level key  index: [["col_a", "col_b"]]  emits one extra
 *   multi-column <index> block per inner array, independent of the flags
 * - a malformed table-level value is reported and skipped
 *
 * No phpunit dependency exists in this repo, so this is a plain PHP script
 * (same convention as UniqueTest.php).
 *
 * Run:  php tests/IndexTest.php
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

function check(bool $ok, string $what, string $detail = ''): void
{
    if (!$ok) {
        fail($what . ($detail !== '' ? " — $detail" : ''));
    }
    echo "ok: $what\n";
}

/** @return array<int,array<int,string>> list of index blocks, each a list of column names */
function indexBlocks(SimpleXMLElement $sx, string $table): array
{
    $blocks = [];
    foreach ($sx->table as $t) {
        if ((string) $t['name'] !== $table) {
            continue;
        }
        foreach ($t->index as $i) {
            $cols = [];
            foreach ($i->{'index-column'} as $c) {
                $cols[] = (string) $c['name'];
            }
            $blocks[] = $cols;
        }
    }
    return $blocks;
}

$hjson = <<<'HJSON'
{
    indextest:
    {
        is_builder: true,

        "message('Message')":
        {
            index: [["id_tenant", "ingested_at"], ["id_tenant", "date_creation"]],
            id_message: ["primary"],
            "id_tenant('Company')": ["integer()"],
            "change_seq('Change seq')": ["bigint()", "index"],
            "ingested_at('Ingested')": ["timestamp()"]
        },

        "undo('Undo')":
        {
            id_undo: ["primary"],
            "id_tenant('Company')": ["integer()", "index"],
            "created('Created')": ["bigint()", "index"]
        },

        "plain('Plain')":
        {
            id_plain: ["primary"],
            "label('Label')": ["string(50)"]
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
$sx = simplexml_load_string($xml);
if ($sx === false) {
    fail("emitted XML does not parse:\n$xml");
}

// --- flags + table-level composites: flag block first, then one per group ---
$message = indexBlocks($sx, 'message');
check(count($message) === 3, 'message has three <index> blocks', print_r($message, true));
check($message[0] === ['change_seq'], 'first message index is the flagged column alone', print_r($message, true));
check($message[1] === ['id_tenant', 'ingested_at'], 'second message index is (id_tenant, ingested_at)', print_r($message, true));
check($message[2] === ['id_tenant', 'date_creation'], 'third message index is (id_tenant, date_creation)', print_r($message, true));

// --- historic behaviour kept: two flagged columns share one index ---
$undo = indexBlocks($sx, 'undo');
check($undo === [['id_tenant', 'created']], 'undo keeps one shared (id_tenant, created) index', print_r($undo, true));

// --- no index at all when nothing asks for one ---
check(indexBlocks($sx, 'plain') === [], 'plain has no <index> block');

// --- date_creation is not declared (a behavior adds it): a warning, not an error ---
$warned = array_filter($logger->messages, static fn (string $m): bool => str_contains($m, "'date_creation'") && str_starts_with($m, '[warning]'));
check(count($warned) === 1, 'undeclared composite member is a warning', implode("\n", $logger->messages));
$errors = array_filter($logger->messages, static fn (string $m): bool => str_starts_with($m, '[error]'));
check($errors === [], 'no converter errors for the valid schema', implode("\n", $errors));

// --- malformed table-level value: reported, skipped ---
$bad = <<<'HJSON'
{
    indextest:
    {
        is_builder: true,
        "broken('Broken')":
        {
            index: [["id_tenant"], [], ["a", 5]],
            id_broken: ["primary"],
            "id_tenant('Company')": ["integer()"]
        }
    }
}
HJSON;
$logger2 = new CollectingLogger();
$converter2 = new HjsonToPropelXml($logger2);
$converter2->process($bad);
$sx2 = simplexml_load_string($converter2->getXml());
check(indexBlocks($sx2, 'broken') === [['id_tenant']], 'only the valid group of a malformed index: is kept', print_r(indexBlocks($sx2, 'broken'), true));
$errors2 = array_filter($logger2->messages, static fn (string $m): bool => str_starts_with($m, '[error]') && str_contains($m, 'index:'));
check(count($errors2) === 2, 'each malformed group is reported', implode("\n", $logger2->messages));

echo "all IndexTest checks passed\n";

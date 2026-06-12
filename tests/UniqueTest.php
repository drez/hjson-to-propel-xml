<?php

/**
 * Standalone test for unique-index emission.
 *
 * - each column flagged "unique" gets its OWN single-column <unique> block
 *   (they used to be merged into one composite <unique> per table)
 * - the table-level key  unique: [["col_a", "col_b"]]  emits one
 *   multi-column <unique> block per inner array
 *
 * No phpunit dependency exists in this repo, so this is a plain PHP script
 * (same convention as VectorColumnTest.php).
 *
 * Run:  php tests/UniqueTest.php
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

$hjson = <<<'HJSON'
{
    uniquetest:
    {
        is_builder: true,

        "account('Account')":
        {
            id_account: ["primary"],
            "username('Username')": ["string(32)", "required", "unique"],
            "google_sub('Google sub')": ["string(64)", "not-required", "unique"],
            "label('Label')": ["string(50)"]
        },

        "budget_records('Budget')":
        {
            unique: [["id_budget", "year", "month"]],
            id_budget_records: ["primary"],
            id_budget: ["integer()"],
            "year('Year')": ["integer()"],
            "month('Month')": ["integer()"]
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

/** @return array<int,array<int,string>> list of unique blocks, each a list of column names */
function uniqueBlocks(SimpleXMLElement $sx, string $table): array
{
    $blocks = [];
    foreach ($sx->table as $t) {
        if ((string) $t['name'] !== $table) {
            continue;
        }
        foreach ($t->unique as $u) {
            $cols = [];
            foreach ($u->{'unique-column'} as $c) {
                $cols[] = (string) $c['name'];
            }
            $blocks[] = $cols;
        }
    }
    return $blocks;
}

// --- per-column flags: two SEPARATE single-column blocks, no composite ---
$account = uniqueBlocks($sx, 'account');
check(count($account) === 2, 'account has two <unique> blocks', print_r($account, true));
check($account[0] === ['username'], 'first account unique is [username] alone', print_r($account, true));
check($account[1] === ['google_sub'], 'second account unique is [google_sub] alone', print_r($account, true));

// --- table-level composite: exactly ONE block with the three columns ---
$budget = uniqueBlocks($sx, 'budget_records');
check(count($budget) === 1, 'budget_records has one <unique> block', print_r($budget, true));
check($budget[0] === ['id_budget', 'year', 'month'], 'budget_records unique is the 3-column composite', print_r($budget, true));

// --- no converter errors for the valid schema ---
$errors = array_filter($logger->messages, fn ($m) => str_starts_with($m, '[error]'));
check($errors === [], 'no converter errors for valid schema', implode("\n", $errors));

// --- composite naming a non-existent column warns but still emits ---
$badHjson = <<<'HJSON'
{
    uniquetest2:
    {
        "thing('Thing')":
        {
            unique: [["nope", "year"]],
            id_thing: ["primary"],
            "year('Year')": ["integer()"]
        }
    }
}
HJSON;

$logger2 = new CollectingLogger();
$converter2 = new HjsonToPropelXml($logger2);
$converter2->process($badHjson);
$xml2 = $converter2->getXml();
$sx2 = simplexml_load_string($xml2);
check($sx2 !== false, 'bad-column schema still emits parseable XML');
check(uniqueBlocks($sx2, 'thing') === [['nope', 'year']], 'composite emitted as-given (Propel validates)', $xml2);
$warned = array_filter($logger2->messages, fn ($m) => strpos($m, "'nope'") !== false);
check($warned !== [], 'unknown composite column produced a warning', implode("\n", $logger2->messages));

// --- malformed table-level unique value errors and is skipped ---
$malformed = <<<'HJSON'
{
    uniquetest3:
    {
        "thing('Thing')":
        {
            unique: ["id_thing", "year"],
            id_thing: ["primary"],
            "year('Year')": ["integer()"]
        }
    }
}
HJSON;

$logger3 = new CollectingLogger();
$converter3 = new HjsonToPropelXml($logger3);
$converter3->process($malformed);
$sx3 = simplexml_load_string($converter3->getXml());
check($sx3 !== false, 'malformed-unique schema still emits parseable XML');
check(uniqueBlocks($sx3, 'thing') === [], 'malformed (flat) unique groups are skipped');
$err3 = array_filter($logger3->messages, fn ($m) => strpos($m, 'non-empty array of column-name strings') !== false);
check($err3 !== [], 'malformed unique value produced an error message', implode("\n", $logger3->messages));

echo "ALL OK\n";
exit(0);

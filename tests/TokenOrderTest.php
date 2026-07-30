<?php

/**
 * Column token order must NOT matter.
 *
 * Regression: an explicit "not-required" that PRECEDED "foreign(product)" was
 * silently clobbered — setOtherAttributes() applies tokens in order and the
 * foreign(...) token merges the foreign preset (required=true) over anything
 * set before it. Explicit tokens must win over type presets regardless of
 * where they appear in the list.
 *
 * Also pins the preset behaviour that must NOT change: with no explicit
 * token, the foreign preset still overrides integer()'s defaults
 * (size 10 → 11, required=true) exactly as before.
 *
 * No phpunit dependency exists in this repo, so this is a plain PHP script
 * (same convention as UniqueTest.php / VectorColumnTest.php).
 *
 * Run:  php tests/TokenOrderTest.php
 * Exits 0 on success, non-zero (with a message) on the first failed assertion.
 */

require __DIR__ . '/../vendor/autoload.php';

use HjsonToPropelXml\HjsonToPropelXml;
use Psr\Log\AbstractLogger;

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
    tokenordertest:
    {
        "product('Product')":
        {
            id_product: ["primary"],
            "name('Name')": ["string(50)"]
        },

        "chat('Chat')":
        {
            id_chat: ["primary"],
            // THE regression: not-required BEFORE foreign(...) must survive
            "id_product('Product')": ["integer()", "not-required", "foreign(product)", "onDelete:cascade", "foreign:id_product", "default:null"],
            // control: not-required AFTER foreign(...) (always worked)
            "id_product_after('Product after')": ["integer()", "foreign(product)", "foreign:id_product", "not-required"],
            // preset must still fully apply when nothing explicit conflicts
            "id_product_plain('Product plain')": ["integer()", "foreign(product)", "foreign:id_product"],
            // explicit required before foreign stays required
            "id_product_req('Product req')": ["integer()", "required", "foreign(product)", "foreign:id_product"],
            // foreign as the ONLY type token still gets the full preset
            "id_product_only('Product only')": ["foreign(product)", "foreign:id_product"]
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

/** @return array<string,string> attribute map of a column */
function columnAttrs(SimpleXMLElement $sx, string $table, string $column): array
{
    foreach ($sx->table as $t) {
        if ((string) $t['name'] !== $table) {
            continue;
        }
        foreach ($t->column as $c) {
            if ((string) $c['name'] === $column) {
                $attrs = [];
                foreach ($c->attributes() as $k => $v) {
                    $attrs[$k] = (string) $v;
                }
                return $attrs;
            }
        }
    }
    return [];
}

// --- THE regression: explicit not-required BEFORE foreign(...) must win ---
$a = columnAttrs($sx, 'chat', 'id_product');
check($a !== [], 'chat.id_product exists');
check(($a['required'] ?? '') === 'false',
    'not-required BEFORE foreign(product) survives the preset',
    'required=' . ($a['required'] ?? '(unset)'));
check(($a['defaultValue'] ?? '') === 'null',
    'default:null kept alongside the foreign preset', print_r($a, true));

// --- control: not-required AFTER foreign(...) keeps working ---
$b = columnAttrs($sx, 'chat', 'id_product_after');
check(($b['required'] ?? '') === 'false',
    'not-required AFTER foreign(product) still wins',
    'required=' . ($b['required'] ?? '(unset)'));

// --- token order produced IDENTICAL columns (modulo name/description) ---
unset($a['name'], $a['description'], $b['name'], $b['description'], $a['defaultValue']);
ksort($a);
ksort($b);
check($a === $b, 'before/after token order emit identical attributes',
    "before-order: " . print_r($a, true) . "after-order: " . print_r($b, true));

// --- preset unchanged when nothing explicit conflicts ---
$c = columnAttrs($sx, 'chat', 'id_product_plain');
check(($c['required'] ?? '') === 'true', 'foreign preset still sets required=true by default');
check(($c['size'] ?? '') === '11', 'foreign preset still overrides integer() size 10 → 11',
    'size=' . ($c['size'] ?? '(unset)'));
check(($c['type'] ?? '') === 'INTEGER', 'foreign preset type INTEGER intact');

// --- explicit required before foreign stays required ---
$d = columnAttrs($sx, 'chat', 'id_product_req');
check(($d['required'] ?? '') === 'true', 'explicit required BEFORE foreign stays true');

// --- foreign as the only type token: full preset applies ---
$e = columnAttrs($sx, 'chat', 'id_product_only');
check(($e['type'] ?? '') === 'INTEGER' && ($e['size'] ?? '') === '11' && ($e['required'] ?? '') === 'true',
    'bare foreign(product) gets the full preset', print_r($e, true));

// --- FKs emitted for every variant (order must not break FK creation) ---
$fkCols = [];
foreach ($sx->table as $t) {
    if ((string) $t['name'] !== 'chat') {
        continue;
    }
    foreach ($t->{'foreign-key'} as $fk) {
        foreach ($fk->reference as $r) {
            $fkCols[] = (string) $r['local'];
        }
    }
}
sort($fkCols);
check($fkCols === ['id_product', 'id_product_after', 'id_product_only', 'id_product_plain', 'id_product_req'],
    'all five foreign keys emitted', print_r($fkCols, true));

echo "ALL OK\n";
exit(0);

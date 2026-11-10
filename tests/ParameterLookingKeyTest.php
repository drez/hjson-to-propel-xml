<?php
require __DIR__ . '/../vendor/autoload.php';

use HjsonToPropelXml\Database;
use HjsonToPropelXml\HjsonToPropelXml;
use Psr\Log\NullLogger;

// Audit 2026-11-10: an unwhitelisted / misspelled parameter with a scalar or
// list value looked exactly like a column and became a bogus typeless COLUMN.
// A key with a behavior prefix and no column type is refused as a drop (which
// fails `gc build`); real columns carrying the same prefix are untouched.
$fail = function (string $why) { fwrite(STDERR, "FAIL: $why\n"); exit(1); };

foreach ([
    ['set_pasword_columns', ['pin'], true],
    ['with_foo', true, true],
    ['add_thing', 'yes', true],
    ['child_table_nope', ['note'], true],
    ['is_active', ['boolean()'], false],
    ["is_paid('Paid')", ['enum(Yes, No)', 'default:No'], false],
    ['show_usage', 'boolean', false],
    ['search_text', ['string(80)'], false],
    ['name', ['pin'], false],          // no behavior prefix: never judged
    ['status', true, false],
] as [$key, $value, $expected]) {
    if (Database::looksLikeParameter($key, $value) !== $expected) {
        $fail("looksLikeParameter('$key') should be " . var_export($expected, true));
    }
}
echo "ok: looksLikeParameter classifies parameters vs prefixed columns\n";

$c = new HjsonToPropelXml(new NullLogger());
$c->process('{ shop: { product: { id: ["primary"], is_active: ["boolean()"], name: ["string(50)"],
    set_pasword_columns: ["pin"], set_password_columns: ["pin"], pin: ["string(8)"] } } }');
if ($c->getDropCount() !== 1) { $fail('expected exactly 1 drop, got ' . $c->getDropCount() . ': ' . implode(' | ', $c->getDropMessages())); }
$msg = implode("\n", $c->getDropMessages());
if (strpos($msg, "'set_pasword_columns'") === false || strpos($msg, "table 'product'") === false) {
    $fail("drop message must name the key and the table:\n$msg");
}
echo "ok: a misspelled parameter is a drop; the correct one and prefixed columns are not\n";

echo "\nALL ASSERTIONS PASSED\n";
exit(0);

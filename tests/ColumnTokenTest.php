<?php
require __DIR__ . '/../vendor/autoload.php';

use HjsonToPropelXml\HjsonToPropelXml;
use Psr\Log\NullLogger;

// Audit 2026-11-10: column tokens that were silently mis-mapped.
$fail = function (string $why) { fwrite(STDERR, "FAIL: $why\n"); exit(1); };
$col = function (string $xml, string $name): string {
    return preg_match('/<column name="' . preg_quote($name, '/') . '"[^>]*>/', $xml, $m) ? $m[0] : '';
};

$c = new HjsonToPropelXml(new NullLogger());
$c->process('{ shop: { product: {
    id: ["primary"],
    active: ["bool"],
    answer: ["enum"],
    slot: ["required", "enum(09:00, 10:00)"],
    payload: ["text", "sqlType:JSON"],
    price: ["decimal(10,2)", "scale:4"],
    label: ["string(20)", "indexed"]
} } }');
$xml = (string) $c->getXml();

if (strpos($col($xml, 'active'), 'type="BOOLEAN"') === false) { $fail('bool must map to BOOLEAN: ' . $col($xml, 'active')); }
if (strpos($col($xml, 'answer'), 'valueSet="Yes, No"') === false) { $fail('a bare enum keeps the preset valueSet: ' . $col($xml, 'answer')); }
if (strpos($col($xml, 'slot'), 'type="ENUM"') === false || strpos($col($xml, 'slot'), '09:00') === false) {
    $fail('enum(09:00, 10:00) is a type, colons and all: ' . $col($xml, 'slot'));
}
if (strpos($col($xml, 'payload'), 'sqlType="JSON"') === false) { $fail('sqlType:JSON must reach the column: ' . $col($xml, 'payload')); }
if (strpos($col($xml, 'price'), 'scale="4"') === false) { $fail('scale:4 must override the type argument: ' . $col($xml, 'price')); }
if (strpos($col($xml, 'label'), 'type="VARCHAR"') === false) { $fail('an unknown token must not break the column: ' . $col($xml, 'label')); }
echo "ok: bool, bare enum, enum with colons, attribute:value pairs, unknown token\n";

echo "\nALL ASSERTIONS PASSED\n";
exit(0);

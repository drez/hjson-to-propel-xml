<?php
require __DIR__ . '/../vendor/autoload.php';

use HjsonToPropelXml\HjsonToPropelXml;
use Psr\Log\NullLogger;

// `child_select` was retired 2026-09-26. It must be refused as a drop (which
// fails `gc build`) with a message saying what replaces it, and it must never
// reach the emitted XML. Sibling parameters on the same table are unaffected.
$c = new HjsonToPropelXml(new NullLogger());
$c->process('{ shop: { address: { id: ["primary"], id_city: ["integer()"],
    child_select: { "id_city": false },
    set_selectbox_filters: { "id_city": [["id_region", "%obj%.id_region"]] } } } }');

$fail = function (string $why) { fwrite(STDERR, "FAIL: $why\n"); exit(1); };

if ($c->getDropCount() !== 1) { $fail('expected exactly 1 drop, got ' . $c->getDropCount()); }
$msg = implode("\n", $c->getDropMessages());
if (strpos($msg, "'child_select'") === false || strpos($msg, 'retired') === false || strpos($msg, 'set_selectbox_filters') === false) {
    $fail("drop message must name the key, say retired, and point at set_selectbox_filters:\n$msg");
}
$xml = (string) $c->getXml();
if (strpos($xml, 'child_select') !== false) { $fail('child_select leaked into the XML'); }
if (strpos($xml, 'set_selectbox_filters') === false) { $fail('set_selectbox_filters was lost'); }
echo "ok: child_select refused with a retirement message\n";

echo "\nALL ASSERTIONS PASSED\n";
exit(0);

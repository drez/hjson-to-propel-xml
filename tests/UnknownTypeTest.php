<?php
require __DIR__ . '/../vendor/autoload.php';

use HjsonToPropelXml\HjsonToPropelXml;
use Psr\Log\AbstractLogger;

final class CollectingLogger extends AbstractLogger {
    public array $messages = [];
    public function log($level, $message, array $context = []): void {
        $this->messages[] = '[' . $level . '] ' . (string) $message;
    }
}

// `quantity: ["whole_number"]` uses a type token that is NOT a recognized
// column type and NOT a whitelisted parameter/behavior — the classic
// misrouted-key / typo'd-type case. The converter must surface a findable
// warning that names the offending COLUMN, and must NOT emit a spurious
// "Undefined array key" PHP warning (the isset-guard regression in
// Column::setAttributeFromType).
$hjson = <<<'HJSON'
{
    shop:
    {
        product:
        {
            id: ["primary"],
            name: ["string(100)", "required"],
            quantity: ["whole_number"]
        }
    }
}
HJSON;

$logger = new CollectingLogger();
$converter = new HjsonToPropelXml($logger);

// Collect PHP notices/warnings. We specifically assert that the unknown type
// does NOT trigger an "Undefined array key" on Column::$defaultsTypes (the
// isset-guard regression). Other pre-existing notices in the converter are
// out of scope for this test.
$phpErrors = [];
set_error_handler(static function (int $errno, string $msg) use (&$phpErrors): bool {
    $phpErrors[] = $msg;
    return true;
});
$converter->process($hjson);
restore_error_handler();

// Specific to this fix: the type token must not be read off $defaultsTypes
// without isset. (An unrelated, deliberate '$inner' sentinel key elsewhere in
// the converter also emits a benign undefined-key notice — not our concern.)
$typeKeyWarnings = array_filter(
    $phpErrors,
    static fn (string $m): bool =>
        stripos($m, 'Undefined array key') !== false && strpos($m, 'whole_number') !== false
);
if (!empty($typeKeyWarnings)) {
    fwrite(STDERR, "FAIL: unknown type produced an Undefined array key warning (isset guard regression):\n"
        . implode("\n", $typeKeyWarnings) . "\n");
    exit(1);
}
echo "ok: unknown type does not trigger an Undefined array key warning on the type token\n";

$problems = array_filter(
    $logger->messages,
    static fn (string $msg): bool => str_starts_with($msg, '[warning]') || str_starts_with($msg, '[error]')
);

$matched = array_filter(
    $problems,
    static fn (string $msg): bool =>
        strpos($msg, 'unknown column type') !== false
        && strpos($msg, 'whole_number') !== false
        && strpos($msg, 'quantity') !== false
);

if (empty($matched)) {
    fwrite(STDERR, "FAIL: expected a contextual 'unknown column type' warning naming the column.\nGot:\n"
        . implode("\n", $problems) . "\n");
    exit(1);
}
echo "ok: unknown type warns with column context\n";

// A clean, recognized type must NOT warn — guards against the warning
// firing for valid no-arg types (foreign/date) the way a plain `else` would.
$clean = new CollectingLogger();
(new HjsonToPropelXml($clean))->process('{ shop: { client: { id: ["primary"], joined: ["date"], parent_id: ["foreign(client)"] } } }');
$cleanProblems = array_filter(
    $clean->messages,
    static fn (string $msg): bool =>
        (str_starts_with($msg, '[warning]') || str_starts_with($msg, '[error]'))
        && strpos($msg, 'unknown column type') !== false
);
if (!empty($cleanProblems)) {
    fwrite(STDERR, "FAIL: valid types wrongly warned:\n" . implode("\n", $cleanProblems) . "\n");
    exit(1);
}
echo "ok: valid types (date/foreign) do not warn\n";

echo "\nALL ASSERTIONS PASSED\n";
exit(0);

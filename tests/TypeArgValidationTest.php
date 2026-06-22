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

function warnings(CollectingLogger $l): array {
    return array_values(array_filter(
        $l->messages,
        static fn (string $m): bool => str_starts_with($m, '[warning]') || str_starts_with($m, '[error]')
    ));
}

function convert(string $hjson): CollectingLogger {
    $l = new CollectingLogger();
    (new HjsonToPropelXml($l))->process($hjson);
    return $l;
}

function assertWarns(string $hjson, string $needle, string $msg): void {
    $w = warnings(convert($hjson));
    foreach ($w as $line) {
        if (strpos($line, $needle) !== false) { echo "ok: $msg\n"; return; }
    }
    fwrite(STDERR, "FAIL: $msg — expected a warning containing '$needle'. Got:\n" . implode("\n", $w) . "\n");
    exit(1);
}

function assertNoTypeArgWarning(string $hjson, string $msg): void {
    foreach (warnings(convert($hjson)) as $line) {
        // our type-arg warnings all carry "must be a"; a valid schema must emit none
        if (strpos($line, 'must be a') !== false) {
            fwrite(STDERR, "FAIL: $msg — unexpected type-arg warning: $line\n");
            exit(1);
        }
    }
    echo "ok: $msg\n";
}

$wrap = static fn (string $col): string => '{ shop: { product: { id: ["primary"], c: ' . $col . ' } } }';

// Invalid: non-numeric size, negative size, non-numeric decimal args.
// NB: 'int' is not a size-bearing alias in columnType (only 'integer' is), so
// int(N) ignores its arg by design — use integer(N) to exercise size.
assertWarns($wrap('["string(abc)"]'),   "string(abc)",  'string(abc) warns: size must be a positive integer');
assertWarns($wrap('["integer(-5)"]'),   "size",         'integer(-5) warns on size');
assertWarns($wrap('["decimal(x,2)"]'),  "size",         'decimal(x,2) warns on size arg');
assertWarns($wrap('["decimal(10,y)"]'), "scale",        'decimal(10,y) warns on scale arg');

// Valid: must NOT warn.
assertNoTypeArgWarning($wrap('["string(255)"]'),  'string(255) is valid');
assertNoTypeArgWarning($wrap('["int(11)"]'),      'int(11) is valid');
assertNoTypeArgWarning($wrap('["decimal(10,2)"]'),'decimal(10,2) is valid');
assertNoTypeArgWarning($wrap('["decimal(10,0)"]'),'decimal(10,0) is valid (scale 0 allowed)');
assertNoTypeArgWarning($wrap('["date"]'),         'no-arg type date is valid');
assertNoTypeArgWarning($wrap('["text"]'),         'no-arg type text is valid');
// Empty parens occur in real fleet schemas (integer(), bigint(), longvarchar()):
// the size defaults before validation runs, so these must NOT warn.
assertNoTypeArgWarning($wrap('["integer()"]'),    'integer() (empty parens) defaults size, no warning');
assertNoTypeArgWarning($wrap('["bigint()"]'),     'bigint() (empty parens) defaults size, no warning');
assertNoTypeArgWarning($wrap('["longvarchar()"]'),'longvarchar() (empty parens) defaults size, no warning');

echo "\nALL ASSERTIONS PASSED\n";
exit(0);

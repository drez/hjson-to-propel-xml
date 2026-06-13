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

$hjson = <<<'HJSON'
{
    shop:
    {
        product:
        {
            id: ["primary"],
            name: ["string(100)", "required"],
            brand_color: ["color"],
            accent_color: ["color", "not-required", "default:#667eea"]
        }
    }
}
HJSON;

$logger = new CollectingLogger();
$converter = new HjsonToPropelXml($logger);
$converter->process($hjson);
$xml = $converter->getXml();

function assertContains(string $needle, string $haystack, string $msg): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: $msg\n--- XML ---\n$haystack\n");
        exit(1);
    }
    echo "ok: $msg\n";
}

assertContains('name="brand_color"', $xml, 'brand_color column exists');
assertContains('type="VARCHAR"', $xml, 'color maps to VARCHAR');
assertContains('size="7"', $xml, 'color size is 7');
assertContains('name="color_picker_columns"', $xml, 'color_picker_columns parameter emitted');

// Verify the color_picker_columns PARAMETER VALUE specifically (the column
// names also appear as <column name="..."> elements, so a substring check on
// the whole XML is vacuous). Extract the parameter's value attribute, decode
// XML entities, json_decode, and assert both color columns are present.
if (!preg_match('/name="color_picker_columns"\s+value=\'([^\']*)\'/', $xml, $m)) {
    fwrite(STDERR, "FAIL: could not locate color_picker_columns parameter value\n--- XML ---\n$xml\n");
    exit(1);
}
$paramValue = html_entity_decode($m[1], ENT_XML1 | ENT_QUOTES);
$decoded = json_decode($paramValue, true);
if (!is_array($decoded) || !in_array('brand_color', $decoded, true) || !in_array('accent_color', $decoded, true)) {
    fwrite(STDERR, "FAIL: color_picker_columns value missing a color column. Got: $paramValue\n");
    exit(1);
}
echo "ok: color_picker_columns value lists both color columns\n";

assertContains('defaultValue="#667eea"', $xml, 'default hex preserved');

// Guard against converter warnings/errors. This clean schema produces only
// benign [info] log lines (convert start/ended); a regression in the
// 'default:#667eea' colon-split path (or similar) would surface as a
// warning/error level message, which we want the test to catch.
$problems = array_filter(
    $logger->messages,
    static fn (string $msg): bool => !str_starts_with($msg, '[info]') && !str_starts_with($msg, '[debug]')
);
if (!empty($problems)) {
    fwrite(STDERR, "FAIL: unexpected converter warnings/errors:\n" . implode("\n", $problems) . "\n");
    exit(1);
}

echo "\nALL ASSERTIONS PASSED\n";
exit(0);

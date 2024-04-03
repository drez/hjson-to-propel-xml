<?php

namespace HjsonToPropelXml;

use HJSON\HJSONParser;
use \HJSON\HJSONException;
use Throwable;


/**
 * Main class
 */
class HjsonToPropelXml
{

    private $Database;

    public $logger;
    public $Xml;

    private static $level = 0;

    public function __construct($logger)
    {
        $this->logger = $logger;
    }

    public function getXml()
    {
        return $this->Xml;
    }

    public function process(string $hjson)
    {
        $hjson = mb_ereg_replace('/\r/', "", $hjson); // make sure we have unix style text regardless of the input
        $parser = new HJSONParser();

        $obj = $parser->parse($hjson, ['assoc' => true]);
        if(isset($obj['%PROJECT_NAME%'])){
            return 1;
        }
        self::$level = 0;
        return $this->convert($obj);
    }

    /**
     * Go through the nested array and convert to object than xml
     *
     * @param array $obj
     * @return void
     */
    public function convert(array $obj)
    {
        // foreach level
        try {
            foreach ($obj as $key => $el) {
                if (self::$level == 0) {  // root level
                    $this->logger->info("convert start - found database '$key'");
                    $this->Database = new Database(['name' => $key], $this->logger);
                    self::$level++;
                    if (is_array($el) && count($obj) == 1) {
                        if (!$this->convert($el)) {
                            $this->Xml = $this->Database->getXml();
                            $this->logger->info("convert ended - found " . $this->Database->getTableCount() . " tables");
                        }

                        return false;
                    } else {
                        $this->logger->error("HJSON parser - Empty database or parsing error - make sure all tables are in the database bracket");
                        return true;
                    }
                } else {
                    $done = false;
                    $done = $this->Database->add($key, $el, self::$level);

                    if (is_array($el) && !$done) {
                        self::$level++;
                        $this->convert($el);
                        self::$level--;
                    }
                }
            }
        } catch (\Exception $exception) {
            $this->logger->error($exception);
        }
    }
}

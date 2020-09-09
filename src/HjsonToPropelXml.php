<?php

namespace HjsonToPropelXml;

/**
 * Main class
 */
class HjsonToPropelXml
{

    private $Database;

    public function getXml()
    {
        return $this->Xml;
    }

    /**
     * Go through the nested array and convert to object than xml
     *
     * @param array $obj
     * @return void
     */
    public function convert(array $obj): void
    {
        static $level = 0;
        // foreach level
        foreach ($obj as $key => $el) {
            if ($level == 0) {  // root level
                $this->Database = new Database(['name' => $key]);
                $level++;
                if (is_array($el) && count($obj) == 1) {
                    $this->convert($el);
                    $this->Xml = $this->Database->getXml();
                } else {
                    throw new \Exception("Empty database or parsing error - make sure all tables are in the database");
                }
            } else {
                $this->Database->add($key, $el, $level);

                if (is_array($el)) {
                    $level++;
                    $this->convert($el);
                    $level--;
                }
            }
        }
    }
}

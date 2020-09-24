<?php

namespace HjsonToPropelXml;

class Unique
{

    private $attributes = [];
    private $columns = [];

    public function __construct($logger)
    {
        $this->logger = $logger;
    }

    public function addColumn(string $columnName): void
    {
        $this->columns[] = $columnName;
    }

    private function hasUnique()
    {
        return (count($this->columns)) ? true : false;
    }

    private function setAttributes($key, $value)
    {
        $this->attributes[$key] = json_encode($value);
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getXml(): string
    {

        if ($this->hasUnique()) {
            $Xml = new Xml();
            $Xml->addElement('unique', [], false);
            foreach ($this->columns as $column) {
                $Xml->addElement('unique-column', ["name" => $column]);
            }
            $Xml->addElementClose('unique');
            return $Xml->getXml();
        } else {
            return '';
        }
    }
}

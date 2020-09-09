<?php

namespace HjsonToPropelXml;

class Index
{

    private $attributes = [];
    private $columns = [];

    public function __construct()
    {
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
            $Xml->addElement('index', [], false);
            foreach ($this->columns as $column) {
                $Xml->addElement('index-column', ["name" => $column]);
            }
            $Xml->addElementClose('index');
            return $Xml->getXml();
        } else {
            return '';
        }
    }
}

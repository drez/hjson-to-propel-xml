<?php

namespace HjsonToPropelXml;

class Table
{
    private $name;

    private $defaults = [
        "name" => '',
        "description" => ''
    ];

    private $parameters = [
        ["phpName" => "string"],
        ["package" => "string"],
        ["namespace" => "string"],
        ["skipSql" => "boolean"],
        ["abstract" => "boolean"],
        ["phpNamingMethod" => "string"],
        ["readOnly" => "boolean"],
        ["treeMode" => "string"],
        ["reloadOnInsert" => "boolean"],
        ["reloadOnUpdate" => "boolean"],
        ["allowPkInsert" => "boolean"],
    ];

    private $attributes = [];

    private $Behaviors = [];
    private $Unique;
    private $Index;

    public function __construct($key)
    {
        $this->setKey($key);
        $this->Unique = new Unique();
        $this->Index = new Index();
    }

    private function setKey($key)
    {
        preg_match("/([\w\_\d]+)\(([\w\W]*)\)/", $key, $matches);
        if ($matches) {
            $this->attributes['name'] = $matches[1];
            $this->attributes['description'] = str_replace(['"', "'"], '', $matches[2]);
        } else {
            $this->attributes['name'] = $key;
        }
        $this->name = $key;
    }

    public function setAttibute(string $key, string $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttributes()
    {
        foreach ($this->Behaviors as $Behavior) {
            $this->attributes['$inner'] .= $Behavior->getXml();
        }

        foreach ($this->Columns as $Column) {
            $this->attributes['$inner'] .= $Column->getXml();
        }

        $this->attributes['$inner'] .= $this->Unique->getXml();

        return $this->attributes;
    }

    public function addBehavior($key)
    {
        $this->Behaviors[$key] = new Behavior($key);
    }

    public function hasBehavior($key)
    {
        if (isset($this->Behaviors[$key])) {
            return true;
        }
        return false;
    }

    public function getBehavior($key)
    {
        return $this->Behaviors[$key];
    }

    public function getName()
    {
        return $this->name;
    }

    public function addColumn(Column $Column)
    {
        $this->Columns[$Column->getName()] = $Column;
        if ($Column->isUnique()) {
            $this->Unique->addColumn($Column->getName());
        }
        if ($Column->isIndex()) {
            $this->Indexe->addColumn($Column->getName());
        }
    }

    public function is_cross_ref(string $value)
    {
        $this->attributes['isCrossRef'] = "true";
    }

    public function validator(array $value)
    {
        $this->addValidator($value);
    }

    private function addValidator(array $value)
    {
    }

    public function getXml()
    {
        $Xml = new Xml();
        $xml = $Xml->addElement('table', $this->getAttributes());

        return $xml;
    }
}

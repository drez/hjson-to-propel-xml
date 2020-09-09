<?php

namespace HjsonToPropelXml;

class Behavior
{

    private $name = "";
    private $Parameters = [];
    private $attributes = [];

    public function __construct(string $key)
    {
        $this->setName($key);
    }

    private function setName($key)
    {
        $this->attributes['name'] = $key;
        $this->name = $key;
    }

    public function addParameter($key, $value)
    {
        $this->Parameters[$key] = new Parameter($key, $value);
    }

    public function getAttributes()
    {

        foreach ($this->Parameters as $Parameters) {
            $this->attributes['$inner'] .= $Parameters->getXml();
        }
        return $this->attributes;
    }
    public function getXml()
    {
        $Xml = new Xml();
        return $Xml->addElement('behavior', $this->getAttributes())->getXml();
    }

    public function getName()
    {
        return $this->name;
    }
}

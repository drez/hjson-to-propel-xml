<?php

namespace HjsonToPropelXml;

class Behavior
{

    private $name = "";
    private $Parameters = [];
    private $attributes = [];

    public function __construct(string $key, $value)
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
        // Sentinel content-accumulator key; init before .= to avoid an
        // "Undefined array key" notice when the behavior has no parameters.
        $this->attributes['$inner'] ??= '';
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

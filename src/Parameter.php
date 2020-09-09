<?php

namespace HjsonToPropelXml;

class Parameter
{

    private $attributes = [];

    public function __construct($key, $value)
    {
        $this->setAttributes($key, $value);
    }

    private function setAttributes($key, $value)
    {
        $this->attributes[$key] = json_encode($value);
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getXml()
    {
        $Xml = new Xml();
        return $Xml->addElement('parameter', $this->getAttributes())->getXml();
    }
}

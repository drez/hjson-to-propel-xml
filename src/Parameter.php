<?php

namespace HjsonToPropelXml;

class Parameter
{

    private $attributes = [];
    private $isArray;

    public function __construct($key, $value)
    {
        $this->setAttributes($key, $value);
    }

    private function setAttributes($key, $value)
    {
        if (is_array($value)) {
            $this->attributes['name'] = $key;
            $this->attributes['value'] = json_encode($value);
            $this->isArray = true;
        } else {
            $this->attributes['name'] = $key;
            $this->attributes['value'] = $value;
            $this->isArray = false;
        }
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getXml()
    {
        $Xml = new Xml();
        return $Xml->addElement('parameter', $this->getAttributes(), true, $this->isArray)->getXml();
    }
}

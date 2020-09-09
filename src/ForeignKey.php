<?php

namespace HjsonToPropelXml;

class ForeignKey
{
    private $defaults = [
        'key' => ["foreignTable" => "", "onDelete" => "restrict", "onUpdate" => "restrict"],
        'reference' => ["local" => "", "foreign" => ""]
    ];


    private $attributes = [];

    public function __construct($key, $value)
    {
        $this->setAttributes($key, $value);
    }

    public function setAttribute($ref, $key, $value)
    {
        $this->attributes[$ref][$key] = $value;
    }

    private function setAttributes($key, $value)
    {
        $this->attributes = $this->defaults;
        $this->attributes['key']['foreignTable'] = $value;
        $this->attributes['reference']['local'] = $key;
        $this->attributes['reference']['foreign'] = $key;
    }

    public function getAttributes($part): array
    {
        return $this->attributes[$part];
    }

    public function getXml()
    {
        $Xml = new Xml();
        $Xml->addElement('foreign-key', $this->getAttributes('key'), false);
        $Xml->addElement('reference', $this->getAttributes('reference'), false);
        $Xml->addElementClose('foreign-key');
        return $Xml->getXml();
    }
}

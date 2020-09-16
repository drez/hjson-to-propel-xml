<?php

namespace HjsonToPropelXml;

/**
 * Propel 1.7 style validator
 */
class Validator
{
    private $defaults = [
        'column' => ["column" => ""],
        'rule' => []
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

    private function setAttributes($key, $values)
    {
        $this->attributes = $this->defaults;
        $this->attributes['column']['column'] = $key;
        $i = 0;
        foreach ($values as $key => $value) {
            if (!empty($value)) {
                if (is_array($value)) {
                    $this->attributes['rule'][$i]['name'] = $key;
                    $this->attributes['rule'][$i]['message'] = $value[0];
                    $this->attributes['rule'][$i]['value'] = $value[1];
                } else {
                    $this->attributes['rule'][$i]['name'] = $key;
                    $this->attributes['rule'][$i]['message'] = $value;
                }

                $i++;
            }
        }
    }

    public function getAttributes($part): array
    {
        return $this->attributes[$part];
    }

    public function getXml()
    {
        $Xml = new Xml();
        $Xml->addElement('validator', $this->getAttributes('column'), false);
        $rules = $this->getAttributes('rule');
        foreach ($rules as $rule) {
            $Xml->addElement('reference', $rule, true);
        }
        $Xml->addElementClose('validator');
        return $Xml->getXml();
    }
}

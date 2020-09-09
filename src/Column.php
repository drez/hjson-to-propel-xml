<?php

namespace HjsonToPropelXml;

class Column
{
    private $name;

    private $defaults = [
        "name" => '',
        "description" => ''
    ];

    private $ForeignKeys = [];

    private $defaultsTypes = [
        "primary" => ["type" => "INTEGER", "size" => 11, "required" => "true", "setNull" => "false", "primaryKey" => "true", "autoIncrement" => "true"],
        "foreign" => ["type" => "INTEGER", "size" => 11, "required" => "true", "setNull" => "true",],
        "string" => ["type" => "VARCHAR", "size" => 50, "required" => "false", "setNull" => "true"],
        "enum" => ["type" => "ENUM", "valueSet" => "Yes, No", "required" => "false", "setNull" => "false"],
        "date" => ["type" => "DATE", "required" => "false", "setNull" => "true"],
        "decimal" => ["type" => "DATE", "size" => "", "scale" => "2", "required" => "false", "setNull" => "true"],
    ];

    private $attributes = [];

    private $parameters = [
        ["type" => "string"],
        ["size" => "int"],
        ["required" => "boolean"],
        ["setNull" => "boolean"],
        ["primaryKey" => "boolean"],
        ["autoIncrement" => "boolean"],
        ["phpType" => "string"],
        ["sqlType" => "string"],
        ["scale" => "int"],
        ["defaultValue" => "string"],
        ["defaultExpr" => "string"],
        ["valueSet" => "string"],
        ["primaryKey" => "boolean"],
    ];

    private $keywords = [
        "not-required" => ["required", "false"],
        "required" => ["required", "true"],
        "null" => ["setNull", "true"],
        "not-null" => ["setNull", "false"],
        "default" => ["defaultValue"],
        "primary" => ["primaryKey", "true"],
        "auto-increment" => ["autoIncrement", "true"],
        "unique" => [],
        "index" => []
    ];

    private $foreignKeywords = [
        "onDelete" => "key",
        "onUpdate" => "key",
        "local" => "reference",
        "foreign" => "reference"
    ];

    private $isUnique = false;
    private $isIndex = false;

    public function __construct($key, $value)
    {
        $this->setKey($key);
        $this->setAttributes($value);
    }

    private function parseValue(string $key)
    {
        preg_match("/([\w\_\d]+)\(([\w\W]*)\)/", $key, $matches);
        return $matches;
    }

    private function setKey($key): void
    {
        $matches = $this->parseValue($key);
        if ($matches) {
            $this->attributes['name'] = $matches[1];
            $this->attributes['description'] = str_replace(['"', "'"], '', $matches[2]);
            $this->name = $matches[1];
        } else {
            $this->attributes['name'] = $key;
            $this->name = $key;
        }

        //echo $key . \PHP_EOL;
    }

    private function setAttributes($values): void
    {
        if (is_array($values)) {
            $this->setType($values[0]);
            //remove type from array and parse the other parameters
            unset($values[0]);
            $this->setOtherAttributes($values);
        } else {
            $this->setType($values);
        }
    }

    private function setOtherAttributes(array $values)
    {
        foreach ($values as $value) {
            if (isset($this->keywords[$value])) {
                if ($value == 'unique') {
                    $this->setUnique();
                } elseif ($value == 'index') {
                    $this->setIndex();
                } else {
                    $this->attributes[$this->keywords[$value][0]] = $this->keywords[$value][1];
                }
            } elseif (strstr($value, ":")) {
                $part = explode(':', $value);
                if (isset($this->keywords[$part[0]])) {
                    $this->attributes[$this->keywords[$part[0]][0]] = $part[1];
                } elseif (isset($this->foreignKeywords[$part[0]])) {
                    if (is_object($this->ForeignKeys)) {
                        $this->ForeignKeys->setAttribute($this->foreignKeywords[$part[0]], $part[0], $part[1]);
                    }
                } else {
                    throw new \Exception("Unknown parameter " . $value);
                }
            } else {
                throw new \Exception("Unknown parameter " . $value);
            }
        }
    }

    private function setUnique(): void
    {
        $this->isUnique = true;
    }

    private function setIndex(): void
    {
        $this->isIndex = true;
    }
    public function isUnique(): bool
    {
        return $this->isUnique;
    }

    public function isIndex(): bool
    {
        return $this->isIndex;
    }

    private function setType($values): void
    {
        //echo $values . \PHP_EOL;
        $matches = $this->parseValue($values);

        if ($matches) {
            $matches[1] = strtolower($matches[1]);

            $this->setAttributeFromType($matches[1], $matches[2]);
            if ($matches[1] == 'foreign') {
                $this->addForeignKey($matches[2]);
            }
        } else {
            $this->setAttributeFromType($values);
        }

        //print_r($this->attributes);
    }

    private function setAttributeFromType($type, $value = ""): void
    {
        $type = \strtolower($type);

        if (isset($this->defaultsTypes[$type])) {
            $this->attributes = array_merge($this->attributes, $this->defaultsTypes[$type]);
        }

        // Set the right attribute from the argument in type('argument')
        switch ($type) {
            case 'string':
                $this->attributes['size'] = $value;
                break;
            case 'enum':
                $this->attributes['valueSet'] = $value;
                break;
            default:
                if (empty($this->attributes['type'])) {
                    throw new \Exception("Unknown type " . $type);
                }
        }
    }

    public function setAttibute(string $key, string $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttributes()
    {
        //print_r($this->attributes);
        return $this->attributes;
    }

    public function getXml()
    {
        $Xml = new Xml();
        $xml = $Xml->addElement('column', $this->getAttributes())->getXml();

        if (is_object($this->ForeignKeys)) {
            $xml .= $this->ForeignKeys->getXml();
        }
        return $xml;
    }

    public function getName()
    {
        return $this->name;
    }

    private function addForeignKey(string $value)
    {
        $this->ForeignKeys = new ForeignKey($this->getName(), $value);
    }
}

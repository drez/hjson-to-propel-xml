<?php

namespace HjsonToPropelXml;

/**
 * class representation of a Propel column
 */
class Column
{
    private $name;

    /**
     * defaults attributes
     *
     * @var array
     */
    private $defaults = [
        "name" => '',
        "description" => ''
    ];

    /**
     * collection of related foreign key objects
     *
     * @var array
     */
    private $ForeignKeys = [];

    /**
     * default value for column shortcurt
     *
     * @var array
     */
    private $defaultsTypes = [
        "primary" => ["type" => "INTEGER", "size" => 11, "required" => "true", "primaryKey" => "true", "autoIncrement" => "true"],
        "foreign" => ["type" => "INTEGER", "size" => 11, "required" => "true",],
        "string" => ["type" => "VARCHAR", "size" => 50, "required" => "false"],
        "enum" => ["type" => "ENUM", "valueSet" => "Yes, No", "required" => "false"],
        "date" => ["type" => "DATE", "required" => "false"],
        "decimal" => ["type" => "DATE", "size" => "6", "scale" => "2", "required" => "false"],
        "text" => ["type" => "longvarchar", "scale" => "2", "required" => "false"],
    ];

    /**
     * attributes to be converted to xml
     *
     * @var array
     */
    private $attributes = [];

    /**
     * supported Propel attributes, for validation
     *
     * @var array
     */
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

    /**
     * Propel column attributes shortcuts
     *
     * @var array
     */
    private $keywords = [
        "not-required" => ["required", "false"],
        "required" => ["required", "true"],
        "null" => ["setNull", "true"],
        "not-null" => ["setNull", "false"],
        "default" => ["defaultValue"],
        "primary" => ["primaryKey", "true"],
        "auto-increment" => ["autoIncrement", "true"],
        "unique" => [],
        "index" => [],
        "foreign(" => []
    ];

    /**
     * Propel foreign-key attributes shortcuts map
     *
     * @var array
     */
    private $columnType = [
        "varchar" => "size",
        "integer" => "size",
        "longvarchar" => "size",
        "timestamp" => "",
        "text" => "size"
    ];

    /**
     * Propel foreign-key attributes shortcuts map
     *
     * @var array
     */
    private $foreignKeywords = [
        "onDelete" => "key",
        "onUpdate" => "key",
        "local" => "reference",
        "foreign" => "reference"
    ];

    /**
     * is column unique
     *
     * @var boolean
     */
    private $isUnique = false;

    /**
     * is column an index
     *
     * @var boolean
     */
    private $isIndex = false;

    public function __construct($key, $value, $logger)
    {
        $this->logger = $logger;
        $this->key = $key;
        if (\is_string($key)) {
            $this->setKey($key);
            $this->setAttributes($value);
        } else {
            $this->logger->warning("Key is malformed in " . $this->key);
        }
    }

    /**
     * parse the function style column shortcut ie. String(32)
     *
     * @param string $key
     * @return void
     */
    private function parseValue(string $key)
    {
        preg_match("/([\w\_\d]+)\(([\w\W]*)\)/", $key, $matches);
        return $matches;
    }

    /**
     * set attributes for the column key, columnName(description)
     * or simple table name with no parentheses()
     *
     * @param string $key
     * @return void
     */
    private function setKey($key): void
    {
        if (!\is_string($key)) {
            $this->logger->warning("Key is malformed in " . $this->key);
        } else {
            $matches = $this->parseValue($key);
            if ($matches) {
                $this->attributes['name'] = $matches[1];
                $this->attributes['description'] = str_replace(['"', "'"], '', $matches[2]);
                $this->name = $matches[1];
            } else {
                $this->attributes['name'] = $key;
                $this->name = $key;
            }
        }


        //echo $key . \PHP_EOL;
    }

    /**
     * set the attributes ... lol
     *
     * @param mix $values
     * @return void
     */
    private function setAttributes($values): void
    {
        if (\is_null($values) || \is_null($values[0]) || is_array($values[0])) {
            $this->logger->warning("A column is misconfigured in " . $this->key);
        } else {
            if (is_array($values)) {
                $this->setType($values[0]);
                //remove type from array and parse the other parameters
                unset($values[0]);
                $this->setOtherAttributes($values);
            } else {
                $this->setType($values);
            }
        }
    }

    /**
     * set the attribute from keywords
     * parse the colon separated keywords
     *
     * @param array $values
     * @return void
     */
    private function setOtherAttributes(array $values)
    {
        $keywords = \array_keys($this->keywords);
        //print_r($keywords);
        foreach ($values as $value) {
            if (!\is_null($value)) {
                // check for keywords
                $index = (str_replace($keywords, '', $value) != $value);
                if ($index) {
                    if ($value == 'unique') {
                        $this->setUnique();
                    } elseif ($value == 'index') {
                        $this->setIndex();
                    } elseif (stristr($value, 'foreign')) {
                        $this->setType($value);
                    } else {
                        $this->attributes[$this->keywords[$value][0]] = $this->keywords[$value][1];
                    }
                    // check for key value
                } elseif (strstr($value, ":")) {
                    $part = explode(':', $value);
                    if (isset($this->keywords[$part[0]])) {
                        $this->attributes[$this->keywords[$part[0]][0]] = $part[1];
                    } elseif (isset($this->foreignKeywords[$part[0]])) {
                        if (is_object($this->ForeignKeys)) {
                            $this->ForeignKeys->setAttribute($this->foreignKeywords[$part[0]], $part[0], $part[1]);
                        } else {
                            // postpone the foreign parameters after creation
                            $setForeign[] = [$part[0], $part[1]];
                        }
                    } elseif (in_array($value[0], $this->parameters)) {
                        $this->attributes[$value[0]] = $value[1];
                    } else {
                        $this->logger->warning("Unknown key:pair parameter: " . $value . " in " . $this->key);
                    }
                } else {
                    $this->logger->warning("Unknown parameter: " . $value . " in " . $this->key);
                }
            }
        }

        if (is_array($setForeign)) {
            if (is_object($this->ForeignKeys)) {
                foreach ($setForeign as $params) {
                    $this->ForeignKeys->setAttribute($this->foreignKeywords[$params[0]], $params[0], $params[1]);
                }
            } else {
                $this->logger->warning("Foreign key parameters without foreign key: " . $value . " in " . $this->key);
            }
        }

        if (is_array($setForeign)) {
            if (is_object($this->ForeignKeys)) {
                foreach ($setForeign as $params) {
                    $this->ForeignKeys->setAttribute($this->foreignKeywords[$params[0]], $params[0], $params[1]);
                }
            } else {
                $this->logger->warning("Foreign key parameters without foreign key: " . $value);
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

    /**
     * parse the function style column shortcut ie. String(32)
     *
     * @param mix $values
     * @return void
     */
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

    /**
     * set the attribute to set depending on the type keyword
     *
     * @param [type] $type
     * @param string $value
     * @return void
     */
    private function setAttributeFromType($type, $value = ""): void
    {
        $type = \strtolower($type);

        if (isset($this->defaultsTypes[$type])) {
            $this->attributes = array_merge($this->attributes, $this->defaultsTypes[$type]);
        }

        // Set the right attribute from the argument in type('argument')
        switch ($type) {
            case 'string':
            case 'text':
            case 'integer':
                $this->attributes['size'] = $value;
                break;
            case 'enum':
                $this->attributes['valueSet'] = $value;
                break;
            default:
                if (empty($this->columnType[$this->attributes['type']])) {
                    $this->attributes[$this->columnType[$this->attributes['type']]] = $value;
                } else {
                    $this->logger->warning("Unknown type " . $type);
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

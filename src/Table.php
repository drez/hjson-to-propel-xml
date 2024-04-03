<?php

namespace HjsonToPropelXml;

/**
 * class representation of a Propel table
 */
class Table
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
     * supported Propel table attributes, for validation
     *
     * @var array
     */
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

    /**
     * attributes to be converted to xml
     *
     * @var array
     */
    private $attributes = [];

    /**
     * collection of related Columns objects
     *
     * @var array
     */
    private $Columns = [];

    /**
     * collection of related Behavior objects
     *
     * @var array
     */

    private $Behaviors = [];

    /**
     * collection of related Validator objects
     *
     * @var array
     */
    private $Validators = [];

    /**
     * a Unique object to set column in
     *
     * @var Unique
     */
    private $Unique;

    /**
     * a Index object to set column in
     *
     * @var int
     */
    private $Index;
    private $logger;

    public function __construct($key, $logger)
    {
        $this->logger = $logger;
        $this->setKey($key);
        $this->Unique = new Unique($this->logger);
        $this->Index = new Index($this->logger);
    }

    /**
     * parse the function style column shortcut ie. tablename(Description)
     *
     * @param string $key
     * @return void
     */
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

    /**
     * get table attributes 
     * and related object xml in the attribute '$inner'
     *
     * @return array
     */
    public function getAttributes()
    {
        foreach ($this->Behaviors as $Behavior) {
            $this->attributes['$inner'] .= $Behavior->getXml();
        }

        if(!$this->hasBehavior('GoatCheese')){
            $this->addBehavior('GoatCheese');
            $this->attributes['$inner'] .= $this->Behaviors['GoatCheese']->getXml();
        }

        foreach ($this->Columns as $Column) {
            $this->attributes['$inner'] .= $Column->getXml();
        }

        $this->attributes['$inner'] .= $this->Unique->getXml();

        foreach ($this->Validators as $Validator) {
            $this->attributes['$inner'] .= $Validator->getXml();
        }

        return $this->attributes;
    }

    public function addBehavior($key, $value = null)
    {
        $this->Behaviors[$key] = new Behavior($key, $value);
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

    public function is_cross_ref(bool $values)
    {
        $this->attributes['isCrossRef'] = "true";
    }

    public function validator(array $values)
    {
        foreach ($values as $key => $value) {
            $this->addValidator($key, $value);
        }
    }

    private function addValidator(string $key, array $value)
    {
        $this->Validators[] = new Validator($key, $value);
    }

    public function getXml()
    {
        $Xml = new Xml();
        $xml = $Xml->addElement('table', $this->getAttributes());

        return $xml;
    }
}

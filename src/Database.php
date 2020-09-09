<?php

namespace HjsonToPropelXml;

/**
 * class representation of a Propel database
 * 
 */
class Database
{

    /**
     * database tag default
     *
     * @var array
     */
    private $defaults = [
        "name" => '',
        "defaultIdMethod" => 'native',
        "namespace" => 'App'
    ];

    /**
     * behavior shortcuts, for behavior with no parameters
     *
     * @var array
     */
    private $behaviors = [
        "add_validator", "table_stamp_behavior"
    ];

    /**
     * parameters of APIgoat behavior
     *
     * @var array
     */
    private $parameters = [
        "set_debug_level", "is_builder", "add_hooks", "with_api",
        "checkbox_all_child", "set_parent_menu"
    ];

    /**
     * table inner shortcuts
     *
     * @var array
     */
    private $tableKeywords = [
        "is_cross_ref",
        "validator"
    ];

    /**
     * attributes to be converted to xml
     *
     * @var array
     */
    private $attributes = [];

    /**
     * collection of Behavior class of the table
     *
     * @var array
     */
    private $Behaviors = [];

    /**
     * Currently open object
     * Database, Table
     *
     * @var Object
     */
    private $currentObj;

    public function __construct(array $attributes)
    {
        $this->setAttibutes($attributes);
        $this->currentObj = &$this;
    }

    public function setAttibutes(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }
    }

    public function getAttributes()
    {
        foreach ($this->defaults as $key => $value) {
            if ($this->attibutes[$key]) {
                $attributes[$key] = $this->attibutes[$key];
            } else {
                $attributes[$key] = $value;
            }
        }
        return $attributes;
    }

    /**
     * add object or attributes to the database
     *
     * @param string $key
     * @param [type] $value
     * @param integer $level
     * @return void
     */
    public function add(string $key, $value, int $level = 0)
    {
        if (in_array($key, $this->behaviors)) {
            // simple behavior
            $this->currentObj->addBehavior($key);
        } elseif (in_array($key, $this->parameters)) {
            // behavior with parameters
            if (isset($this->currentObj)) {
                if (!$this->currentObj->hasBehavior('GoatCheese')) {
                    $this->currentObj->addBehavior('GoatCheese');
                }
                $this->currentObj->getBehavior('GoatCheese')->addParameter($key, $value);
            } else {
                throw new \Exception("No current obj");
            }
        } else {

            if ($level == 2) {
                // column
                if (isset($this->currentObj)) {
                    if (!in_array($key, $this->tableKeywords)) {
                        if (isset($this->currentObj)) {
                            $this->currentObj->addColumn(new Column($key, $value));
                        }
                    } else {
                        $this->currentObj->$key($value);
                    }
                } else {
                    throw new \Exception("No current obj 2");
                }
            } elseif ($level < 2) {
                // table
                if (!isset($this->Tables[$key])) {
                    $this->Tables[$key] = new Table($key);

                    $this->currentObj = &$this->Tables[$key];
                }
            }
        }
    }

    private function addBehavior($key)
    {
        $this->Behaviors[$key] = new Behavior($key);
    }

    private function hasBehavior($key)
    {
        if (isset($this->Behaviors[$key])) {
            return true;
        }
        return false;
    }

    private function getBehavior($key)
    {
        return $this->Behaviors[$key];
    }

    /**
     * convert object to xml
     *
     * @return string
     */
    public function getXml(): string
    {

        $Xml = new Xml();
        $Xml->addElement('database', $this->getAttributes());

        foreach ($this->Behaviors as $Behavior) {
            $Xml->addElement('behavior', $Behavior->getAttributes());
        }
        foreach ($this->Tables as $Table) {
            $Xml->addElement('table', $Table->getAttributes());
        }

        $Xml->addElementClose('database');


        return $tables . "Table Count: " . count($this->Tables)
            . \PHP_EOL
            . $Xml->getXml();
    }
}

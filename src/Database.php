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
        "add_validator", "add_tablestamp", "add_archivable"
    ];

    /**
     * parameters of APIgoat behavior
     *
     * @var array
     */
    private $parameters = [
        "set_debug_level", "set_parent_menu", "set_order_list_columns", "set_list_hide_columns",
        "set_menu_priority", "set_parent_table", "set_form_title", "set_child_colunms",
        "set_list_hide_columns_except", "set_input_options", "set_order_child_list_columns",
        "set_top_nav",
        "is_builder", "is_file_upload_table", "is_wysiwyg_colunms", "set_selectbox_filters", "is_root_columns",
        "with_api", "with_child_tables",
        "add_hooks", "add_search_columns", "add_tab_columns", "add_child_search_columns",
        "checkbox_all_child", "auth_session_val", "readonly_columns", "total_columns_child", "calculated_prefix", "multiple_fenetre", "bulk_update"
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

    private $tableCount;

    public function __construct(array $attributes, $logger)
    {
        $this->logger = $logger;
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
            if ($this->attributes[$key]) {
                $attributes[$key] = $this->attributes[$key];
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
            return true;
        } elseif (in_array($key, $this->parameters)) {
            // behavior with parameters
            if (isset($this->currentObj)) {
                if (!$this->currentObj->hasBehavior('GoatCheese')) {
                    $this->currentObj->addBehavior('GoatCheese');
                }
                $this->currentObj->getBehavior('GoatCheese')->addParameter($key, $value);
            } else {
                $this->logger->error("No current obj");
            }
            return true;
        } else {

            if ($level == 2) {
                // column
                if (isset($this->currentObj)) {
                    if (!in_array($key, $this->tableKeywords)) {
                        if (isset($this->currentObj)) {
                            $this->currentObj->addColumn(new Column($key, $value, $this->logger));
                        }
                    } else {
                        $this->currentObj->$key($value);
                    }
                } else {
                    $this->logger->error("No current obj 2");
                }
            } elseif ($level < 2) {
                // table
                if (!isset($this->Tables[$key])) {
                    $this->Tables[$key] = new Table($key, $this->logger);
                    $this->tableCount++;
                    $this->currentObj = &$this->Tables[$key];
                }
            }
            return false;
        }
    }

    private function addBehavior($key)
    {
        $this->Behaviors[$key] = new Behavior($key, $this->logger);
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
        $Xml->addElement('database', $this->getAttributes(), false);

        foreach ($this->Behaviors as $Behavior) {
            $Xml->addElement('behavior', $Behavior->getAttributes());
        }
        foreach ($this->Tables as $Table) {
            $Xml->addElement('table', $Table->getAttributes());
        }

        $Xml->addElementClose('database');

        return $Xml->getXml();
    }

    public function getTableCount()
    {
        return $this->tableCount;
    }
}

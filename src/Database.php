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
     * behavior definitions
     *
     * @var array
     */
    private $behaviors_config = [
        "add_validator" => "bool",
        "add_tablestamp" => "bool",
        "add_archivable" => "bool",
        "add_i18n" => [
            "name" => "i18n",
            "type" => "array",
            "parameter" => "i18n_columns"
        ]
    ];

    /**
     * behavior shortcuts index
     *
     * @var array
     */
    private $behaviors;

    /**
     * parameters of APIgoat behavior
     *
     * @var array
     */
    private $parameters = [
        "set_debug_level",
        "set_parent_menu",
        "set_order_list_columns",
        "set_list_hide_columns",
        "set_menu_priority",
        "set_menu_subtitle",
        "set_parent_table",
        "set_form_title",
        "set_child_colunms",
        "set_list_hide_columns_except",
        "set_input_options",
        "set_order_child_list_columns",
        "set_top_nav",
        "set_selectbox_filters",

        "is_builder",
        "is_file_upload_table",
        "is_wysiwyg_colunms",
        "is_root_columns",
        "is_file_upload",
        "with_api",
        "with_child_tables",
        "with_country",
        "with_refresh_tokens",
        "with_multi_tenant",
        "with_register",

        "add_hooks",
        "add_field_groups",
        "add_search_columns",
        "add_tab_columns",
        "add_child_search_columns",
        "add_menu",
        "checkbox_all_child",
        "auth_session_val",
        "readonly_columns",
        "total_columns_child",
        "calculated_prefix",
        "multiple_fenetre",
        "bulk_update",
        "clone_entry",
        "child_select",
        "filter_select",
        "unit_caption",
        "total_columns",
        "common_filter",
        "order_select",

        "required_child",
        "max_child",
        "owner_visible",
        "admin_columns",
        "auth_passwd_column",
        "i18n_langs",
        "copy_link",

        "set_config",
        "set_readonly_columns",
        "add_child_bulk",
        "add_mass_action",
        "add_total",
        "child_table_read_only",
        "set_label_link",
        "add_crossref_filter",

        // keep in sync with vendor/apigoat/goatcheese/Parameters/ and
        // behavior-parameters.json — an unknown key here silently becomes
        // a table (db level) or column (table level)
        "set_pills",
        "set_main_label",
        "set_field_groups",
        "set_identity_actions",
        "set_menu_icon",
        "set_menu",
        "set_autocomplete",
        "set_quick_add",
        "set_refresh_on_child_change",
        "set_summary_cards",
        "add_title_link",
        "is_drive_backed",
        "with_vector",
        "format_phone_columns",
        "format_date_columns",
        "search_tabs",
        "search_tabs_child",
        "is_group_table",
        "is_rights_column",
        "parent_table",
        "comment_columns",
        "set_comment_columns",
    ];

    /**
     * table inner shortcuts
     *
     * @var array
     */
    private $tableKeywords = [
        "is_cross_ref",
        "validator",
        // composite unique indexes: unique: [["col_a", "col_b"], ...]
        "unique"
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
    private $logger;
    private $Tables;

    /**
     * #36: count of dropped schema elements — table-level keys with an object
     * value that are not a whitelisted parameter/behavior, so they never reach
     * the emitter. The build reads this to gate (a silent drop = a missing
     * feature shipping), turning what was a warn-and-continue into a hard error.
     */
    private $dropCount = 0;
    private $dropMessages = [];

    public function __construct(array $attributes, $logger)
    {
        $this->behaviors = array_keys($this->behaviors_config);
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
            if (isset($this->attributes[$key])) {
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
            // defined behaviors
            $behavior_name = (isset($this->behaviors_config[$key]['name'])) ? $this->behaviors_config[$key]['name'] : $key;
            $this->currentObj->addBehavior($behavior_name, $value);
            if (isset($this->behaviors_config[$key]) && isset($this->behaviors_config[$key]['parameter'])) {
                if (isset($this->behaviors_config[$key]['type'])) {
                    if ($this->behaviors_config[$key]['type'] == 'array') {
                        foreach ($value as $val) {
                            $this->currentObj->getBehavior($behavior_name)->addParameter($this->behaviors_config[$key]['parameter'], $val);
                        }
                    }
                } else {
                    $this->currentObj->getBehavior($behavior_name)->addParameter($this->behaviors_config[$key]['parameter'], $value);
                }
            } elseif (is_array($value) && array_keys($value) !== range(0, count($value) - 1)) {
                # object form passes parameters straight through, e.g.
                # add_tablestamp:{ exclude:"all" } → <parameter name="exclude" value="all"/>
                foreach ($value as $pk => $pv) {
                    $this->currentObj->getBehavior($behavior_name)->addParameter($pk, $pv);
                }
            }
            return true;
        } elseif (in_array($key, $this->parameters)) {
            // GoatCheese behavior
            if (isset($this->currentObj)) {
                if (!$this->currentObj->hasBehavior('GoatCheese')) {
                    $this->currentObj->addBehavior('GoatCheese');
                }
                $this->currentObj->getBehavior('GoatCheese')->addParameter($key, $value);

                // is_drive_backed: tell Propel not to emit DDL for this table —
                // it has no MySQL persistence. OM/Config still generate so the
                // Form/Service emitters and class autoloading keep working.
                if ($key === 'is_drive_backed' && $this->currentObj instanceof Table) {
                    $this->currentObj->setAttibute('skipSql', 'true');
                }
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
                            // Real columns have a scalar type ("string(32)")
                            // or a sequential list of type/modifier strings
                            // (["varchar(80)","required"]). A parameter-style
                            // key that is NOT whitelisted ($this->parameters)
                            // carries an associative/object value
                            // (set_autocomplete:{...}, add_menu:{...}); such a
                            // value can never be a column, so it would be
                            // silently absorbed as a bogus column and never
                            // reach the emitter. Warn (with the table name) so
                            // a typo or missing whitelist entry is findable
                            // from the build log.
                            if (is_array($value) && $this->isAssoc($value)) {
                                $tableName = method_exists($this->currentObj, 'getAttributes')
                                    ? ($this->currentObj->getAttributes()['name'] ?? '?') : '?';
                                $this->logger->error("HJSON converter: table-level key '" . $key
                                    . "' in table '" . $tableName . "' has an object value but is not a whitelisted"
                                    . " parameter or behavior — it will be dropped. Check the spelling or add it to Database::\$parameters.");
                                $this->dropCount++;
                                $this->dropMessages[] = "table '" . $tableName . "': '" . $key
                                    . "' (object value, not a whitelisted parameter/behavior)";
                            }
                            $this->currentObj->addColumn(new Column($key, $value, $this->logger));
                        } else {
                            $this->logger->error("No current obj 3");
                        }
                    } else {
                        if (method_exists($this->currentObj, $key)) {
                            $this->currentObj->$key($value);
                        }
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

    private function addBehavior($key, $value = null)
    {
        $this->Behaviors[$key] = new Behavior($key, $value, $this->logger);
    }

    /** True when $arr has at least one non-sequential (string) key. */
    private function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
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

    /** #36: number of dropped (misrouted) table-level parameters in this database. */
    public function getDropCount(): int
    {
        return $this->dropCount;
    }

    /** #36: human-readable descriptions of each dropped table-level parameter. */
    public function getDropMessages(): array
    {
        return $this->dropMessages;
    }
}

<?php

namespace HjsonToPropelXml;

class Index
{

    private $attributes = [];
    private $columns = [];
    private $composites = [];
    private $logger;

    public function __construct($logger)
    {
        $this->logger = $logger;
    }

    public function addColumn(string $columnName): void
    {
        $this->columns[] = $columnName;
    }

    /**
     * add a composite (non-unique) index spanning several columns
     * (from the table-level `index: [["col_a", "col_b"]]` key)
     *
     * @param array $columnNames non-empty list of column names
     * @return void
     */
    public function addComposite(array $columnNames): void
    {
        $this->composites[] = array_values($columnNames);
    }

    /**
     * warn about composite members that are not declared columns of the table
     * (a behavior may add them, e.g. date_creation; the index is still emitted
     * as-given and Propel validates for real)
     *
     * @param array $knownColumns
     * @param string $tableName
     * @return void
     */
    public function checkColumns(array $knownColumns, string $tableName): void
    {
        foreach ($this->composites as $composite) {
            foreach ($composite as $column) {
                if (!in_array($column, $knownColumns, true)) {
                    $this->logger->warning("index: column '" . $column . "' in table '" . $tableName . "' composite index is not a declared column (fine if a behavior adds it)");
                }
            }
        }
    }

    private function hasUnique()
    {
        return (count($this->columns) || count($this->composites)) ? true : false;
    }

    private function setAttributes($key, $value)
    {
        $this->attributes[$key] = json_encode($value);
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getXml(): string
    {

        if ($this->hasUnique()) {
            $Xml = new Xml();
            // columns flagged "index" share ONE <index> (historic behaviour, kept: existing
            // schemas rely on it, e.g. id_tenant + created) — always emitted first so its
            // generated name (<table>_I_1) does not move when composites are added
            if (count($this->columns)) {
                $Xml->addElement('index', [], false);
                foreach ($this->columns as $column) {
                    $Xml->addElement('index-column', ["name" => $column]);
                }
                $Xml->addElementClose('index');
            }
            // one multi-column <index> per table-level composite group
            foreach ($this->composites as $composite) {
                $Xml->addElement('index', [], false);
                foreach ($composite as $column) {
                    $Xml->addElement('index-column', ["name" => $column]);
                }
                $Xml->addElementClose('index');
            }
            return $Xml->getXml();
        } else {
            return '';
        }
    }
}

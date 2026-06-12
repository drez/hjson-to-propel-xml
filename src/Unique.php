<?php

namespace HjsonToPropelXml;

class Unique
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
     * add a composite unique index spanning several columns
     * (from the table-level `unique: [["col_a", "col_b"]]` key)
     *
     * @param array $columnNames non-empty list of column names
     * @return void
     */
    public function addComposite(array $columnNames): void
    {
        $this->composites[] = array_values($columnNames);
    }

    /**
     * warn about composite members that are not known columns of the table;
     * the index is still emitted as-given (Propel validates for real)
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
                    $this->logger->warning("unique: column '" . $column . "' in table '" . $tableName . "' composite unique index is not a defined column");
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
            // one single-column <unique> per column flagged "unique"
            foreach ($this->columns as $column) {
                $Xml->addElement('unique', [], false);
                $Xml->addElement('unique-column', ["name" => $column]);
                $Xml->addElementClose('unique');
            }
            // one multi-column <unique> per table-level composite group
            foreach ($this->composites as $composite) {
                $Xml->addElement('unique', [], false);
                foreach ($composite as $column) {
                    $Xml->addElement('unique-column', ["name" => $column]);
                }
                $Xml->addElementClose('unique');
            }
            return $Xml->getXml();
        } else {
            return '';
        }
    }
}

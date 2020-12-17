<?php

namespace HjsonToPropelXml;

class Xml
{

    private $xml = "";

    public function addElement(string $name, array $attributes, $close = true, $simple_quotes = false): Object
    {
        $attribs = '';
        $innerXml = '';
        foreach ($attributes as $key => $value) {
            if (!empty($key)) {
                if ($key == '$inner') {
                    $innerXml = $value;
                } elseif ($simple_quotes && $key != 'name') {
                    $attribs .= "$key='" . $value . "' ";
                } else {
                    $attribs .= "$key=\"$value\" ";
                }
            }
        }

        if ($close && empty($innerXml)) {
            $closeit = "/";
        }

        $this->xml .= $this->pad($name) . "<$name $attribs{$closeit}>" . \PHP_EOL;

        if ($innerXml) {
            $this->xml .= $innerXml;
            $this->xml .= $this->addElementClose($name);
        }

        return $this;
    }

    public function addElementClose(string $name): void
    {
        $this->xml .= $this->pad($name) . "</$name>" . \PHP_EOL;
    }

    public function getXml(): string
    {
        return $this->xml;
    }

    private function pad($name): string
    {
        $pad = "";
        $keywords_padding = [
            "table" => 1,
            "parameter" => 3,
            "behavior" => 2,
            "column" => 2,
            "foreign-key" => 2,
            "validator" => 2,
            "unique" => 2,
            "index" => 2,
            "rule" => 3,
            "unique-column" => 3,
            "rule" => 3,
            "rule" => 3,
        ];

        $count = ($keywords_padding[$name]) ? $keywords_padding[$name] : 0;

        for ($i = 0; $i < $count; $i++) {
            $pad .= "\t";
        }

        return $pad;
    }
}

<?php

namespace HjsonToPropelXml;

class Xml
{

    private $xml = "";

    public function addElement(string $name, array $attributes, $close = true): Object
    {
        $attribs = '';
        $innerXml = '';
        foreach ($attributes as $key => $value) {
            if (!empty($key)) {
                if ($key == '$inner') {
                    $innerXml = $value;
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
        $this->xml .= $this->pad($name) . "<$name />" . \PHP_EOL;
    }

    public function getXml(): string
    {
        return $this->xml;
    }

    private function pad($name): string
    {
        $pad = "";
        switch ($name) {
            case "behavior":
                $count = 2;
                break;
            case "parameter":
                $count = 3;
                break;
            case "table":
                $count = 1;
                break;
            case "column":
                $count = 2;
                break;
            case "foreign-key":
                $count = 2;
                break;
            case "reference":
                $count = 3;
                break;
            case "unique":
                $count = 2;
                break;
            case "unique-column":
                $count = 3;
                break;
            case "index":
                $count = 2;
                break;
            case "index-column":
                $count = 3;
                break;
            default:
                return "";
        }

        for ($i = 0; $i < $count; $i++) {
            $pad .= "\t";
        }

        return $pad;
    }
}

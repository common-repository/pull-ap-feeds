<?php

// copied and pasted from http://www.php.net/manual/en/function.simplexml-load-string.php#72697

class simple_xml_extended extends SimpleXMLElement
{
    public    function    Attribute($name)
    {
        foreach($this->Attributes() as $key=>$val)
        {
            if($key == $name)
                return (string)$val;
        }
    }

}

function simple_xml_object_attribute($simple_xml_object, $name) {
        foreach($simple_xml_object->Attributes() as $key=>$val)
        {
            if($key == $name)
                return (string)$val;
        }

}

?>

<?php
/**
 * Created by PhpStorm.
 * User: Kavan
 * Date: 14-09-2017
 * Time: 07:19 PM
 */

namespace Kavanpancholi\Processlist\Process;


class  WmiInstance implements \ArrayAccess, \Countable, \Iterator
{
    private $PropertyNames;
    protected $WmiRow;

    public function __construct($wmi_row)
    {
        $this->WmiRow = $wmi_row;

        foreach ($wmi_row->Properties_ as $property) {
            $property_name = $property->Name;

            // Property is an array : we have to extract each item from the underlying variant
            if ($property->IsArray) {
                $property_value = [];

                // ... but be careful to null or empty arrays
                if ($property->Value !== null && variant_get_type($property->Value) != VT_NULL) {
                    foreach ($property->Value as $item)
                        $property_value [] = $this->__normalize($item);
                }
            } // Property should be a "standard" value, that can be mapped to one of the PHP base scalar types.
            else
                $property_value = $this->__normalize($property->Value);

            // Assign the property to this instance (either a scalar type or an array)
            $this->$property_name = $property_value;

            // Add it to the list of dynamically defined properties
            $this->PropertyNames [] = $property_name;
        }

        $this->PropertyNames = array_flip($this->PropertyNames);
    }


    // __normalize -
    //	Try to guess the type of a property value.
    private function __normalize($item)
    {
        if (is_numeric($item)) {
            if ($item == ( integer )$item)
                $result = ( integer )$item;
            else
                $result = ( double )$item;
        } else if ($item === null)
            $result = null;
        else if (!strcasecmp($item, 'true'))
            $result = true;
        else if (!strcasecmp($item, 'false'))
            $result = false;
        else
            $result = $item;

        return ($result);
    }


    /*--------------------------------------------------------------------------------------------------------------

        ToArray -
            Converts this object to an associative array of property name/value pairs.

     *-------------------------------------------------------------------------------------------------------------*/
    public function ToArray()
    {
        $result = [];

        foreach ($this->PropertyNames as $name)
            $result [$name] = $this->$name;

        return ($result);
    }


    /*--------------------------------------------------------------------------------------------------------------

            Countable interface implementation.

     *-------------------------------------------------------------------------------------------------------------*/
    public function count()
    {
        return (count($this->PropertyNames));
    }


    /*--------------------------------------------------------------------------------------------------------------

            ArrayAccess interface implementation.

     *-------------------------------------------------------------------------------------------------------------*/
    private function __offset_get($offset)
    {
        if (is_integer($offset) && $offset > 0 && $offset < count($this->PropertyNames))
            return ($this->PropertyNames [$offset]);
        else if (is_string($offset) && isset  ($this->PropertyNames [$offset]))
            return ($this->$offset);
        else
            return (false);
    }


    public function offsetExists($offset)
    {
        return ($this->__offset_get($offset) !== false);
    }


    public function offsetGet($offset)
    {
        $value = $this->__offset_get($offset);

        if ($value !== false)
            return ($value);
        else
            throw (new \OutOfRangeException ("Invalid offset $offset."));
    }


    public function offsetSet($offset, $value)
    {
        throw (new \Exception ("Unsupported operation."));
    }


    public function offsetUnset($offset)
    {
        throw (new \Exception ("Unsupported operation."));
    }


    /*--------------------------------------------------------------------------------------------------------------

            Iterator interface implementation.

     *-------------------------------------------------------------------------------------------------------------*/
    private $__iterator_index = null;


    public function rewind()
    {
        $this->__iterator_index = 0;
    }

    public function valid()
    {
        return ($this->__iterator_index >= 0 && $this->__iterator_index < count($this->PropertyNames));
    }

    public function next()
    {
        $this->__iterator_index++;
    }

    public function key()
    {
        return ($this->PropertyNames [$this->__iterator_index]);
    }

    public function current()
    {
        $property = $this->PropertyNames [$this->__iterator_index];

        return ($this->$property);
    }
}
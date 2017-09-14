<?php

namespace Kavanpancholi\Processlist\Process;

class Wmi
{
    // Underlying WMI object
    protected $WmiObject;

    public function __construct($wmi_object_or_namespace = null)
    {
        if (strncasecmp(php_uname('s'), 'windows', 7))
            throw (new \Exception ("This class can only run on Windows platforms."));

        if ($wmi_object_or_namespace === null)
            $this->WmiObject = new \COM ('winmgmts:{impersonationLevel=Impersonate}!//./root/CIMV2');
        else if (is_string($wmi_object_or_namespace))
            $this->WmiObject = new \COM ($wmi_object_or_namespace);
        else
            $this->WmiObject = $wmi_object_or_namespace;
    }


    /*--------------------------------------------------------------------------------------------------------------

        LocalInstance -
        Creates a WMI object instance on the local computer.

     *-------------------------------------------------------------------------------------------------------------*/
    public static function LocalInstance($namespace = 'winmgmts:{impersonationLevel=Impersonate}!//./root/CIMV2')
    {
        $wmi_object = new self (new \COM ($namespace));

        return ($wmi_object);
    }


    /*--------------------------------------------------------------------------------------------------------------

        RemoteInstance -
        Creates a WMI object instance on the specified remote computer.

     *-------------------------------------------------------------------------------------------------------------*/
    public static function RemoteInstance($computer, $user, $password, $namespace = 'root\CIMV2', $locale = null, $domain = null)
    {
        $locator    = new \COM ("WbemScripting.SWbemLocator");
        $wmi_object = $locator->ConnectServer($computer, $namespace, $user, $password, $locale, $domain);
        $wmi_object = new  self ($wmi_object);

        return ($wmi_object);
    }


    /*--------------------------------------------------------------------------------------------------------------

        QueryInstances -
        A shortcut for :

            $wmi -> Query ( "SELECT * FROM $table" ) ;

     *-------------------------------------------------------------------------------------------------------------*/
    public function QueryInstances($table, $base_class = 'WmiInstance', $namespace = false)
    {
        return ($this->Query("SELECT * FROM $table", $base_class, $namespace));
    }


    /*--------------------------------------------------------------------------------------------------------------

        NAME
            Query - Queries WMI interface

        PROTOTYPE
            $array	=  $wmi -> Query ( $query, $base_class = 'WmiInstance', $namespace = 'Wmi' ) ;

        DESCRIPTION
            Performs a query on the Windows WMI interface and returns the results as an array of objects belonging
        to class $base_class.

        PARAMETERS
            $query (string) -
                    Query for the WMI interface, eg :

                SELECT * FROM Win32_Process

        $base_class (string) -
            The Query() method creates a new class for the WMI table being queried. The new class will have
            the table name prepended with 'Wmi' ; for example, querying Win32_Process will return objects
            of class WmiWin32_Process, inheriting from $base_class which is, by default, the WmiInstance
            class.
            If you want to encapsulate the generated class, simply declare a new class inheriting from
            WmiInstance and specify its name to the Query() call.

        $namespace (string) -
            Indicates the namespace where new classes are to be created. An empty value means the current
            namespace.

        RETURN VALUE
            Returns an array of Wmixxx objects, where "xxx" is the name of the WMI table being queried.
        An empty array is returned if the query returned no results.

     *-------------------------------------------------------------------------------------------------------------*/
    public function Query($query, $base_class = 'WmiInstance', $namespace = false)
    {
        if (!preg_match('/FROM \s+ (?P<table> \w+)/imsx', $query, $match))
            throw (new \Exception ("The supplied query does not contain a FROM clause."));

        $wmi_class       = $match ['table'];
        $full_class_path = $this->__get_class_path($wmi_class, $namespace);
        $class_exists    = class_exists($full_class_path, false);
        $rs              = $this->WmiObject->ExecQuery($query);
        $result          = [];

        foreach ($rs as $row) {
            if (!$class_exists) {
                $this->__create_class($row, $wmi_class, $base_class, $namespace);

                if (!is_subclass_of($full_class_path, 'WmiInstance'))
                    throw (new \RuntimeException ("Class \"$full_class_path\" should inherit from WmiInstance"));

                $class_exists = true;
            }

            $result    [] = $this->__get_instance($row, $full_class_path);
        }

        return ($result);
    }


    /*--------------------------------------------------------------------------------------------------------------

        NAME
           FromVariant - converts a Variant to PHP data.

     *-------------------------------------------------------------------------------------------------------------*/
    public static function FromVariant($variant)
    {
        if (!is_a($variant, "variant"))
            return ($variant);

        $variant_type = variant_get_type($variant);        // Get variant type
        $is_array     = ($variant_type & VT_ARRAY);        // Check if array
        $is_ref       = ($variant_type & VT_BYREF);        // Check if reference (not used)
        $variant_type &= ~(VT_ARRAY | VT_BYREF);            // Keep only basic type flags
        $items        = [];                    // Return value

        // If variant is an array, get all array elements into a PHP array
        if ($is_array) {
            foreach ($variant as $variant_item)
                $items [] = $variant_item;
        } else
            $items [] = $variant;

        $item_count = count($items);

        // Loop through array items (item count will be 1 if supplied variant is not an array)
        for ($i = 0; $i < $item_count; $i++) {
            $item = $items [$i];

            // Handle scalar types
            switch ($variant_type) {
                case    VT_NULL :
                    $items [$i] = null;
                    break;

                case    VT_EMPTY :
                    $items [$i] = false;
                    break;

                case    VT_UI1 :
                case    VT_UI2 :
                case    VT_UI4 :
                case    VT_UINT :
                case    VT_I1  :
                case    VT_I2  :
                case    VT_I4  :
                case    VT_INT  :
                    $items [$i] = ( integer )$item;
                    break;

                case    VT_R4 :
                    $items [$i] = ( float )$item;
                    break;

                case    VT_R8 :
                    $items [$i] = ( double )$item;
                    break;

                case    VT_BOOL :
                    $items [$i] = ( boolean )$item;
                    break;

                case    VT_BSTR :
                    $items [$i] = ( string )$item;
                    break;

                case    VT_VARIANT :
                    if ($is_array)
                        break;
                    else
                        /* Intentionally fall through the default: case */
                        ;

                default :
                    warning("Unexpected variant type $variant_type.");
                    $items [$i] = false;
            }
        }

        return (($is_array) ? $items : $items [0]);
    }

    /*--------------------------------------------------------------------------------------------------------------

        Support functions.

     *-------------------------------------------------------------------------------------------------------------*/

    // __create_class -
    //	Creates a class on-the-fly mapped to a query result.
    private function __create_class($row, $class, $base, $namespace)
    {
        $namespace = ($namespace) ? "namespace $namespace ;" : '';
        $classtext = <<<END
$namespace

class  $class extends $base
   {
	public function  __construct ( \$row )
	   {
		parent::__construct ( \$row ) ;
	    }

END;
        $methods   = [];


        foreach ($row->Methods_ as $row_method) {
            $method =
                [
                    'name'       => $row_method->Name,
                    'parameters' => [],
                    'has-result' => false,
                ];

            if ($row_method->InParameters) {
                foreach ($row_method->InParameters->Properties_ as $parameter)
                    $method ['parameters'] [] = ['name' => $parameter->Name, 'out' => false];
            }

            if ($row_method->OutParameters) {
                foreach ($row_method->OutParameters->Properties_ as $parameter) {
                    if (!strcasecmp($parameter->Name, 'ReturnValue'))
                        $method ['has-result'] = true;
                    else
                        $method ['parameters'] [] = ['name' => $parameter->Name, 'out' => true];
                }
            }

            $methods [] = $method;
        }

        // Build method text
        foreach ($methods as $method) {
            // Function header
            $classtext .= "\n\n\tpublic function  {$method [ 'name' ]} ( ";

            // Function arguments
            $list = [];

            foreach ($method ['parameters'] as $parameter) {
                if ($parameter ['out'])
                    $item = '&$' . $parameter ['name'];
                else
                    $item = '$' . $parameter ['name'];

                $list [] = $item;
            }

            $classtext .= implode(', ', $list) . " )\n\t   {\n";

            // Create a variant for each OUT parameter
            foreach ($method ['parameters'] as $parameter) {
                if ($parameter ['out'])
                    $classtext .= "\t\t\$vt_{$parameter [ 'name' ]}	=  new \VARIANT ( ) ;\n";
            }

            // Call the underlying COM function
            $classtext .= "\n\t\t\$__result__	=  \$this -> WmiRow -> {$method [ 'name' ]} ( ";
            $list      = [];

            foreach ($method ['parameters'] as $parameter) {
                if ($parameter ['out'])
                    $item = '$vt_' . $parameter ['name'];
                else
                    $item = '$' . $parameter ['name'];

                $list [] = $item;
            }

            $classtext .= implode(', ', $list) . " ) ;\n\n";

            // Convert OUT parameters from variant to PHP data
            foreach ($method ['parameters'] as $parameter) {
                if ($parameter ['out'])
                    $classtext .= "\t\t\${$parameter [ 'name' ]}	= Wmi::FromVariant ( \$vt_{$parameter [ 'name' ]} ) ;\n";
            }

            $classtext .= "\n";

            // If method returns a value then convert it
            if ($method ['has-result'])
                $classtext .= "\t\treturn ( Wmi::FromVariant ( \$__result__ ) ) ;\n";

            $classtext .= "\t    }\n";
        }

        // Create the class
        $classtext = $classtext . "\n    }";
        eval ($classtext);
    }


    // __get_class_path -
    //	Returns the full path of the specified class.
    private function __get_class_path($class, $namespace)
    {
        if ($namespace)
            return ("$namespace\\$class");
        else
            return ("$class");
    }


    // __get_instance -
    //	Instanciate a query row using our brand new class.
    private function __get_instance($wmi_row, $class)
    {
        return (new $class ($wmi_row));
    }
}
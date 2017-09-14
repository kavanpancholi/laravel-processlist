<?php

namespace Kavanpancholi\Processlist\Process;
/**
 * Created by PhpStorm.
 * User: Kavan
 * Date: 14-09-2017
 * Time: 07:18 PM
 */

class  Process            // extends  Object
{
    // Process-related data
    public $User;
    public $ProcessId;
    public $ParentProcessId;
    public $StartTime;
    public $CpuTime;
    public $Tty;
    // Command-line related properties
    public $Command = '';        // Command name, without its path
    public $CommandLine;                // Full command line
    public $Title;                // Caption on Windows, process name on Unix
    public $Argv;                    // An argv array, with argv[0] being the command path


    public function __construct($command, $process_name = false, $is_windows = false)
    {
        $this->Argv = $this->ToArgv($command, false, $is_windows);

        if (count($this->Argv))
            $this->Command = pathinfo($this->Argv [0], PATHINFO_FILENAME);

        $this->CommandLine = $command;
        $this->Title       = ($process_name) ? $process_name : $this->Command;
    }


    /*--------------------------------------------------------------------------------------------------------------

        NAME
            ToArgv - Converts a command-line string to an argv array.

        PROTOTYPE
            $argv	=  Convert::ToArgv ( $str, $argv0 = false ) ;

        DESCRIPTION
            Converts the specified string, which represents a command line, to an argv array.
        Quotes can be used to protect individual arguments from being split and are removed from the argument.

        PARAMETERS
            $str (string) -
                    Command-line string to be parsed.

        $argv0 (string) -
            Normally, the first element of a $argv array is the program name. $argv0 allows to specify a
            program name if the supplied command-line contains only arguments.

        RETURN VALUE
            Returns an array containing the arguments.

     *-------------------------------------------------------------------------------------------------------------*/
    protected function ToArgv($str, $argv0 = false, $is_windows = false)
    {
        $argv = [];

        if ($argv0)
            $argv [] = $argv0;

        $length   = strlen($str);
        $in_quote = false;
        $param    = '';

        // Loop through input string characters
        for ($i = 0; $i < $length; $i++) {
            $ch = $str [$i];

            switch ($ch) {
                // Backslash : escape sequence - only interpret a few special characters
                case    '\\' :
                    if (!$is_windows && $i + 1 < $length) {
                        $ch2 = $str [++$i];

                        switch ($ch2) {
                            case    'n'    :
                                $param .= "\n";
                                break;
                            case    't'    :
                                $param .= "\t";
                                break;
                            case    'r'    :
                                $param .= "\r";
                                break;
                            case    'v'    :
                                $param .= "\v";
                                break;
                            default        :
                                $param .= $ch2;
                        }
                    } else
                        $param .= '\\';

                    break;

                // Space - this terminates the current parameter, if we are not in a quoted string
                case    ' ' :
                case    "\t" :
                case    "\n" :
                case    "\r" :
                    if ($in_quote)
                        $param .= $ch;
                    else if ($param) {
                        $argv [] = $param;
                        $param   = '';
                    }

                    break;

                // A quote - Either the start or the end of a quoted value
                case    '"' :
                case    "'" :
                    if ($in_quote)        // We started a quoted string
                    {
                        if ($in_quote == $ch)    // This quoted string started with the same character as the current one
                            $in_quote = false;
                        else                // This quoted string started with a different character
                            $param .= $ch;
                    } else                // We are not in a quoted string, so say that one quoted string has started
                        $in_quote = $ch;

                    break;

                // Other : just append the current character to the current parameter
                default :
                    $param .= $ch;
            }
        }

        // Check for last parameter
        if ($param)
            $argv [] = $param;

        // All done, return
        return ($argv);
    }
}
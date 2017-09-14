<?php

namespace Kavanpancholi\Processlist\Process;

class UserProcessList implements \ArrayAccess, \Countable, \IteratorAggregate
{
    // Process list (array of Process objects)
    protected $Processes;

    // True if we are running on a Windows platform
    protected $IsWindows;


    /*--------------------------------------------------------------------------------------------------------------

        Constructor -
        Instanciate a ProcessList object and optionally get the running process list if the $load parameter is
        true.

     *-------------------------------------------------------------------------------------------------------------*/
    public function __construct($load = true)
    {
        if (!strncasecmp(php_uname('s'), 'windows', 7))
            $this->IsWindows = true;
        else
            $this->IsWindows = false;

        if ($load)
            $this->Refresh();
    }


    /*--------------------------------------------------------------------------------------------------------------

        GetProcess -
        Gets a process entry by its process id.

     *-------------------------------------------------------------------------------------------------------------*/
    public function GetProcess($id)
    {
        foreach ($this->Processes as $process) {
            if ($process->ProcessId == $id)
                return ($process);
        }

        return (false);
    }


    /*--------------------------------------------------------------------------------------------------------------

        GetProcessByName -
        Gets a process entry by its name.
        Returns an array since it can match several running processes.

     *-------------------------------------------------------------------------------------------------------------*/
    public function GetProcessByName($name)
    {
        $result = [];

        foreach ($this->Processes as $process) {
            if ($process->Command == $name) {
                $result [] = $process;
            }
        }

        return ($result);
    }

    public function GetProcessByCommand($command)
    {
        $result = [];

        foreach ($this->Processes as $process) {
            if (strpos($process->CommandLine, $command) !== false) {
                $result [] = $process;
            }
        }

        return ($result);
    }


    /*--------------------------------------------------------------------------------------------------------------

        GetChildren -
        Gets process children.

     *-------------------------------------------------------------------------------------------------------------*/
    public function GetChildren($id)
    {
        $result = [];


        foreach ($this->Processes as $process) {
            if ($process->ParentProcessId == $id)
                $result [] = $process;
        }

        return ($result);
    }


    /*--------------------------------------------------------------------------------------------------------------

        Refresh -
        Refreshes the process list.

     *-------------------------------------------------------------------------------------------------------------*/
    public function Refresh()
    {
        $this->Processes = [];

        if ($this->IsWindows) {
            $this->WindowsPs();
        } else {
            $this->UnixPs();
        }
    }


    /*--------------------------------------------------------------------------------------------------------------

        Interfaces implementations.

     *-------------------------------------------------------------------------------------------------------------*/

    // Countable interface
    public function Count()
    {
        return (count($this->Processes));
    }

    // IteratorAggregate interface
    public function getIterator()
    {
        return (new \ArrayIterator ($this->Processes));
    }

    // ArrayAccess interface
    public function offsetExists($offset)
    {
        return ($offset >= 0 && $offset < count($this->Processes));
    }

    public function offsetGet($offset)
    {
        return ($this->Processes [$offset]);
    }

    public function offsetSet($offset, $member)
    {
        throw (new \Exception ("Unsupported operation."));
    }

    public function offsetUnset($offset)
    {
        throw (new \Exception ("Unsupported operation."));
    }


    /*--------------------------------------------------------------------------------------------------------------

        Protected functions.

     *-------------------------------------------------------------------------------------------------------------*/

    // WindowsPs -
    //	Retrieves the process list on Windows platforms.
    protected function WindowsPs()
    {
        $wmi       = new Wmi ();
        $processes = $wmi->QueryInstances('Win32_Process');

        foreach ($processes as $winprocess) {
            $winprocess->GetOwner($user, $domain);
            $user = "$domain/$user";
            $pid  = $winprocess->ProcessId;
            $ppid = $winprocess->ParentProcessId;
            $tty  = '?';

            $ctime      = $winprocess->CreationDate;
            $start_time = substr($ctime, 0, 4) . '-' .
                substr($ctime, 4, 2) . '-' .
                substr($ctime, 6, 2) . ' ' .
                substr($ctime, 8, 2) . ':' .
                substr($ctime, 10, 2) . ':' .
                substr($ctime, 12, 2);
            $seconds    = $winprocess->KernelModeTime + $winprocess->UserModeTime;
            $seconds    = ( integer )($seconds / (10 * 1000 * 1000));
            $hours      = ( integer )($seconds / 3600);
            $seconds    -= $hours * 3600;
            $minutes    = ( integer )($seconds / 60);
            $seconds    -= $minutes * 60;

            $process                  = new Process ($winprocess->CommandLine, $winprocess->Caption, $this->IsWindows);
            $process->User            = $user;
            $process->ProcessId       = $pid;
            $process->ParentProcessId = $ppid;
            $process->StartTime       = $start_time;
            $process->CpuTime         = sprintf('%02d', $hours) . ':' .
                sprintf('%02d', $minutes) . ':' .
                sprintf('%02d', $seconds);
            $process->Tty             = $tty;

            $this->Processes [] = $process;
        }
    }

    // UnixPs -
    //	Retrieves the process list on Unix platforms.
    protected function UnixPs()
    {
        $this->Processes = [];
        exec("ps -aefwwww", $output, $status);
        $count = count($output);

        for ($i = 1; $i < $count; $i++) {
            $line       = trim($output [$i]);
            $columns    = preg_split("/ +/", $line);
            $sliced     = array_slice($columns, 7, count($columns));
            $columns[7] = implode(" ", $sliced);
            $process    = new  Process ($columns [7], null, $this->IsWindows);

            if (preg_match('/\d+:\d+/', $columns [4]))
                $start_time = date('Y-m-d H:i:s', strtotime($columns [4]));
            else
                $start_time = date('Y-m-d', strtotime($columns [4])) . ' ??:??:??';

            $process->User            = $columns [0];
            $process->ProcessId       = $columns [1];
            $process->ParentProcessId = $columns [2];
            $process->StartTime       = $start_time;
            $process->CpuTime         = $columns [5];
            $process->Tty             = $columns [6];

            $this->Processes [] = $process;
        }
    }
}
<?php

namespace Kavanpancholi\Processlist;

use Kavanpancholi\Processlist\Process\UserProcessList;

/**
 * Created by PhpStorm.
 * User: Kavan
 * Date: 14-09-2017
 * Time: 07:47 PM
 */
class ProcessList
{
    protected $processList;

    public function __construct(UserProcessList $processList)
    {
        $this->processList = $processList;
    }

    public function getProcess($id)
    {
        return $this->processList->GetProcess($id);
    }

    public function getArtisanProcesses()
    {
        return $this->processList->getProcessByName('php');
    }

    public function checkRunningCommand($command)
    {
        $this->processList->Refresh();
        $runningCommands = $this->processList->GetProcessByCommand($command);
        if (count($runningCommands) > 1) {
            return true;
        }
        return false;
    }
}
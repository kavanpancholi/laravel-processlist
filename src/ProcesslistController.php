<?php

namespace Kavanpancholi\Processlist;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Kavanpancholi\Processlist\ProcessList;

class ProcesslistController extends Controller
{
    protected $processList;

    public function __construct(ProcessList $processList)
    {
        $this->processList = $processList;
    }

    public function getArtisanProcesses(){
        return $this->processList->getArtisanProcesses();
    }
}

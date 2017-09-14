# ProcessList
A platform-independent way to retrieve the list of processes running on your systems. It works both on the Windows and Unix platforms.

Add following line to your `config/app.php` under _providers_ list

    Kavanpancholi\Processlist\ProcesslistServiceProvider::class,
  
Run
        
    composer dump-autoload

Steps to check if artisan command already running or not

Command File: e.g. Inspire.php in Console/Command

Use
    
    use Kavanpancholi\Processlist\ProcessList;
    
Check for process in handler

    public function handle(ProcessList $processList)
    {
        $isRunning = $processList->checkRunningCommand('command:name');
        if (!$isRunning) {
            // Do something
        }
        echo "This process is already running".PHP_EOL;
    }


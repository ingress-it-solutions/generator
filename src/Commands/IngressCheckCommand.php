<?php

namespace IngressITSolutions\Generator\Commands;

use Illuminate\Console\Command;
use IngressITSolutions\Generator\LicMan;
use Carbon\Carbon;
use Exception;

class IngressCheckCommand extends Command
{
    protected $signature = 'ingress:check';

    protected $description = 'Check the health of Project';

    public function handle(): int
    {
        $licMan = new LicMan();
        $data_check_result = $licMan->checkPersistence();
        if ($data_check_result === false) {
            $this->info('There is some issue.');
        } else {
            $this->info('Everything looks good.');
        }

        return 0;
    }
}

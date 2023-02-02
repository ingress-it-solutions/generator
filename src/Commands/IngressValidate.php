<?php

namespace IngressITSolutions\Generator\Commands;

use Illuminate\Console\Command;
use IngressITSolutions\Generator\LicMan;
use Illuminate\Support\Facades\File;
use IngressITSolutions\Generator\Generator;
use Carbon\Carbon;
use Exception;

class IngressValidate extends Command
{
    protected $signature = 'ingress:validate';

    protected $description = 'Validate the health';

    public function handle(): int
    {
        $licMan = new LicMan();
        $rootURL = env('APP_URL');
        $data = $licMan->verifyLicense($rootURL, true);
        if ($data) {
            if (
                file_exists(storage_path('app/public.key')) &&
                file_exists(storage_path('app/license.lic'))
            ) {
                $public = File::get(storage_path('app/public.key'));
                $license = File::get(storage_path('app/license.lic'));
                try {
                    $data = Generator::parse($license, $public);
                    if (
                        Carbon::now() >
                        Carbon::createFromFormat(
                            'Y-m-d',
                            $data['lastCheckedDate']
                        )->addDays(3)
                    ) {
                        $this->info(
                            "It's been " .
                                Carbon::now()->diffInDays(
                                    Carbon::createFromFormat(
                                        'Y-m-d',
                                        $data['lastCheckedDate']
                                    )
                                ) .
                                " days. Please ensure your instance is able to connect Ingress IT Solution's licensing server else your application will be blocked."
                        );
                    } else {
                        $this->info('Everything is up-to-date.');
                    }
                } catch (Exception $e) {
                    $this->error('Looks like something went wrong!');
                    $this->error($e->getMessage());
                }
            } else {
                $this->info('Everything looks good.');
            }
        }

        return 0;
    }
}

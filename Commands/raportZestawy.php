<?php

namespace App\Console\Commands;

use App\Models\db_tableau\Zestaw;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class raportZestawy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:raportZestawy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Wysyła @ z info o kończacych się zestawach.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $tablica = array();
        $zestawy = Zestaw::get_zestawy();


        $i = 0;
        foreach ($zestawy as $zestaw)
        {
            $skladniki = DB::connection('xxx')
                ->table('zestawy_skladniki')
                ->where('zestaw_id', '=', $zestaw->id)
                ->get();
            $ilosc_zestawow = Zestaw::ile_zestawow($skladniki);
            $suma_wszystkich = array_sum($ilosc_zestawow);

            //zapisuję do tablicy wszytko co nie spełnia warunku aby móc to potem wysłać mailem

            if ($suma_wszystkich <= 5)
            {
                $tablica[$i]['id'] = $zestaw->id;
                $tablica[$i]['sku'] = $zestaw->zest_sku;
                $tablica[$i]['nazwa'] = $zestaw->zest_nazwa;
                $tablica[$i]['ean'] = $zestaw->zest_EAN ?? '';
                $tablica[$i]['stan'] = $suma_wszystkich;
                $i++;
            }
        }

        $emails = ['xxx@wp.pl'];

        Mail::to($emails)->send(new \App\Mail\raportZestawy($tablica));

        return 0;
    }
}

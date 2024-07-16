<?php

namespace App\Http\Controllers;

use App\Mail\UtworzonoZestaw;
use App\Mail\WyslanoDoSellpandera;
use App\Models\ApiSellpander;
use App\Models\db_tableau\Zestaw;
use App\Models\db_tableau\ZestawSkladnik;
use App\Models\Zestawy\Zestawy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SellpanderController extends Controller
{
    public function index()
    {
        ApiSellpander::getAccessToken_step1();
    }

    public function wyslij(Request $request)
    {

        $zestaw = Zestaw::find($request->zestaw_id);

        //Sprawdzam czy źródło to SMA_Samsung - 1422
        $zrodla = DB::connection('tableau')->table('zestawy_zrodla')
            ->where('zestaw_id', '=', $zestaw->id)->pluck('sw_Id')->toArray();
        $czy_Samsung = collect($zrodla)->search(1422); //SMA_Samsung

        $skladniki = array();
        $i=0;
        foreach ($request->skladniki as $row)
        {
            $skladnik = ZestawSkladnik::find($row['skladnik_id']);

            if (
                isset($skladnik->towar_nazwa) &&
                isset($skladnik->towar_sku) &&
                isset($skladnik->ilosc_sztuk) &&
                isset($skladnik->cena_brutto)
            ) {
                $skladniki[$i] = $skladnik;
                $i++;
            } else {
                dd('Pole ze skladnikami nie zostało poprawnie zapisane.');
            }
        }

        if (isset($zestaw->zest_nazwa_allegro) && isset($zestaw->zest_sku))
        {
            if ($czy_Samsung !== false) {
                //Dodaję do Sellpandera Samsungowego
                $set_samsung_id = ApiSellpander::add_set($zestaw, 'Sellpander_Samsung');
            }

            $new_set_id = ApiSellpander::add_set($zestaw, 'Sellpander');
        } else {
            dd('Uzupełnij dane zestawu [nazwę lub SKU]');
        }


        if ($czy_Samsung !== false) {
            //Dodaję do Sellpandera Samsungowego
            foreach ($skladniki as $skladnik) {
                $sell_sams = ApiSellpander::add_products_to_set($set_samsung_id, $skladnik, 'Sellpander_Samsung');
            }
        }

        foreach ($skladniki as $skladnik)
        {
            $prod = ApiSellpander::add_products_to_set($new_set_id, $skladnik, 'Sellpander');
        }

        $linkDoOtwarcia = 'https://panel.sellpander.pl/Zestawy/Products?set_id='.$new_set_id;
        Zestawy::oznacz_wyslano_do_sellpander($zestaw->id);
        if ($zestaw->do_allegro == 1) {
            Mail::to([
                'xxx.wp.pl'
            ])->send(new WyslanoDoSellpandera($zestaw->id, $new_set_id));
        }
        return redirect()->back()->with('linkDoOtwarcia', $linkDoOtwarcia);
    }
}

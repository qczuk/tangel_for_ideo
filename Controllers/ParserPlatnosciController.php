<?php

namespace App\Http\Controllers\Ksiegowosc;

use App\Http\Controllers\Controller;
use App\Models\Ksiegowosc\ParserPlatnosci;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParserPlatnosciController extends Controller
{
    public function index ()
    {
        return view('ksiegowosc.parserPlatnosci');
    }

    public function store(Request $request)
    {
        if(isset($request->file))
        {
            $dane = array();
            $filename = $request->file->getClientOriginalName();
            $file = fopen($request->file, "r+");

            $i=0;
            $j=0;
            while (($row = fgetcsv($file, 1000, "\t")) !== FALSE) {
                if ($i>3)
                {
                    $dane[$j]['plik'] = $row;
                    $dane[$j]['navireo'] = ParserPlatnosci::znajdz_powiazanie($row[14]);
                    $j++;
                }
            $i++;
            }
        }

        $operatorzy = DB::connection('sqlsrv')
            ->select('SELECT  [fp_Id]
                                  ,[fp_Nazwa]
                                  ,[fp_Termin]
                                  ,[fp_Typ]
                                  ,[fp_RachBankId]
                                  ,[fp_CentId]
                                  ,kh_Symbol
                                  ,[fp_InstKredytId]
                                  ,[fp_Glowna]
                                  ,[fp_Aktywna]
                                  ,[fp_TerminalPlatniczy]
                                  ,[fp_FormaPlatnosciWysylajJako]
                                  ,[fp_OpisPlatnosciInna]
                              FROM sl_FormaPlatnosci
                              join   kh__Kontrahent ON kh_id = fp_CentId
                              where fp_Aktywna = 1
                              and fp_Typ = 1');

        return view('ksiegowosc.parserPlatnosci', [
            'dane' => $dane,
            'operatorzy' => $operatorzy,
        ]);
    }

    public function nav_api(Request $request)
    {
        //dd($request);
        $operator = $request->operator;
        $ids = array();
        $ids = $request->id;
        $client = new Client();
        try {
            $response = $client->post('http://1.1.1.1.1/api/parserPlatnosci', [
                'form_params' => [
                    'operator' => $operator,
                    'dok_Ids' => $ids,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getBody()->getContents(); // Zawartość odpowiedzi

        } catch (\Exception $e) {
            dd('Error: ', $e);
        }
        $bledy = json_decode($content, true);

        if($bledy === null && json_last_error() !== JSON_ERROR_NONE){
            // Obsługa błędu przetwarzania JSON
            return view('ksiegowosc.parserPlatnosci')->with(['ok' => 'ok']);
        }

        return view('ksiegowosc.parserPlatnosci')->with(['bledy' => $bledy]);
    }
}

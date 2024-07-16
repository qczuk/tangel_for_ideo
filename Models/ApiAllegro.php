<?php

namespace App\Models;

use App\Models\db_raportowa\AllegroToPayment;
use App\Models\db_raportowa\BillingAllegroPozycje;
use App\Models\db_tableau\billing_allegro_surowy_api;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Illuminate\Database\Eloquent\Model;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery\Exception;



class ApiAllegro extends Model
{
    static public function getCurl($headers, $url, $content = null)
    {
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_RETURNTRANSFER => true
        ));
        if ($content !== null) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        }
        return $ch;
    }

    // --------------------------------------------------------------------------------------------------------
    //                  POBIERANIE OFERT
    // --------------------------------------------------------------------------------------------------------

    public function getAccessToken_step1($zrodlo)
    {
        /*
         * Krok_1 -> Generuje Link który trzeba zaakceptować na www
         *
         */

        //Pobieram kody autoryzacyjne podesłane przez marJana
        $marjan = self::pobierz_token($zrodlo);
        $authorization = base64_encode($marjan['client_id'] . ':' . $marjan['client_secret']);
        $headers = array("Authorization: Basic {$authorization}", "Content-Type: application/x-www-form-urlencoded");
        $content = http_build_query(array("grant_type" => "device"));
        $url = "https://allegro.pl/auth/oauth/device?client_id=" . urlencode($marjan['client_id']);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $tokenResult = curl_exec($ch);
        $resultCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $result = json_decode($tokenResult);
        curl_close($ch);

        if ($tokenResult === false || $resultCode !== 200) {
            exit("Something went wrong to get AccesToken");
        }

        return $result;
    }

    public function getAccessToken_step2($zrodlo, $device_kod)
    {
        /*
         *
         * Krok_2 ->  Pobiera i zapisuje do bazy nowy kod dostępu
         */

        //Pobieram kody autoryzacyjne podesłane przez marJana
        $marjan = self::pobierz_token($zrodlo);
        $authorization = base64_encode($marjan['client_id'] . ':' . $marjan['client_secret']);
        $headers = array("Authorization: Basic {$authorization}", "Content-Type: application/x-www-form-urlencoded");
        $content = http_build_query(array("grant_type" => "device"));
        $url = "https://allegro.pl/auth/oauth/token?grant_type=urn:ietf:params:oauth:grant-type:device_code&device_code=".$device_kod;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $tokenResult = curl_exec($ch);
        $resultCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $result = json_decode($tokenResult);
        curl_close($ch);

        if ($tokenResult === false || $resultCode !== 200) {
            exit("Something went wrong to get AccesToken");
        }

        DB::connection('xxx')->table('tokeny_allegro')
            ->insert([
                'konto' => $zrodlo,
                'access_token' => $result->access_token,
                'refresh_token' => $result->refresh_token,
                'created_at' => now(),
                'updated_at' => now()
            ]);

        return $result;
    }

    public static function generate_new_acces_token_from_refresh($zrodlo)
    {
        $ref_token = self::get_last_refresh_token($zrodlo);
        $marjan = self::pobierz_token($zrodlo);
        $authorization = base64_encode($marjan['client_id'] . ':' . $marjan['client_secret']);
        $headers = array("Authorization: Basic {$authorization}", "Content-Type: application/x-www-form-urlencoded");
        $content = http_build_query(array("grant_type" => "device"));
        $url = "https://allegro.pl/auth/oauth/token?grant_type=refresh_token&refresh_token=".$ref_token;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $tokenResult = curl_exec($ch);
        $resultCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $result = json_decode($tokenResult);

        curl_close($ch);

        if ($tokenResult === false || $resultCode !== 200) {
            Log::channel('exceptions')->error($e->getMessage(), ['exception' => $e]);
            exit("Something went wrong to get AccesToken");
        }

        DB::connection('xxx')->table('tokeny_allegro')
            ->insert([
                'konto' => $zrodlo,
                'access_token' => $result->access_token,
                'refresh_token' => $result->refresh_token,
                'created_at' => now(),
                'updated_at' => now()
            ]);

        return $result->access_token;
    }

    static function getBillingInfo($accessToken, $zrodlo)
    {
        $wczoraj = date('Y-m-d', strtotime('-1 day'));
        $data_od = $wczoraj.' 00:00:01';
        $data_do = $wczoraj.' 23:59:59';

//        $data_od = '2024-01-08 00:00:00';
//        $data_do = '2024-01-09 23:59:59';

        $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $data_od);
        $dateTime->setTimezone(new \DateTimeZone('UTC'));
        $zuluTime_od = $dateTime->format('Y-m-d\TH:i:s.v\Z');

        $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $data_do);
        $dateTime->setTimezone(new \DateTimeZone('UTC'));
        $zuluTime_do = $dateTime->format('Y-m-d\TH:i:s.v\Z');


        $offset = 0; // Początkowe przesunięcie wyników
        $data = array();
        do {
            $client = new Client();
            try {

                $response = $client->request('GET', 'https://api.allegro.pl/billing/billing-entries?occurredAt.gte='.$zuluTime_od.'&occurredAt.lte='.$zuluTime_do.'&offset='.$offset, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Accept' => 'application/vnd.allegro.public.v1+json',
                    ],
                ]);
                //dd('---------------------------------------------------');
                $statusCode = $response->getStatusCode();
                $data = json_decode($response->getBody(), true);
                //dd($data['billingEntries']);
                self::saveBillingToDatabase($data['billingEntries'], $zrodlo);
                $offset += 100;
            } catch (RequestException $e) {

                if ($e->hasResponse()) {
                    $response = $e->getResponse();
                    dd($e);
                }
            }

        } while (count($data['billingEntries']) > 0);
    }

    static public function get_last_refresh_token($zrodlo)
    {
        $sql = DB::connection('tableau')
            ->table('tokeny_allegro')
            ->where('konto', '=', $zrodlo)
            ->orderBy('id', 'DESC')
            ->first();

        return $sql->refresh_token;
    }
    static public function get_last_access_token($zrodlo)
    {
        try {
            $token = DB::connection('tableau')
                ->table('tokeny_allegro')
                ->where('konto', '=', $zrodlo)
                ->orderBy('id', 'DESC')
                ->first();

            if ($token) {
                return $token->access_token;
            } else {
                dd('Nie znaleziono acces tokenu dla konta: '.$zrodlo);
                return '';
            }
        } catch (\Exception $e) {
            // Obsługa błędów - tutaj możesz zrobić coś z błędem, np. zalogować go
            // lub zwrócić wartość domyślną
            dd('Wystąpił błąd podczas pobierania ostatniego tokena dostępu: ' . $e->getMessage());
            return ''; // Zwracamy pusty ciąg w przypadku błędu
        }
    }

    public static function check_acces($zrodlo)
    {
        $client = new Client();
        $accessToken = self::get_last_access_token($zrodlo);
        try {
            //Sprawdzam czy podany access token jest aktualny
            $response = $client->request('GET', 'https://api.allegro.pl/me', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/vnd.allegro.public.v1+json',
                ],
            ]);
            $statusCode = $response->getStatusCode();
            if ($statusCode == 200)
            {
                return $accessToken;
            }
        } catch (RequestException $e) {

            Log::channel('logowanie')->info('Wygenerowanie nowego acces tokenu', ['zrodlo' => $zrodlo]);

            try {
                $new_tok = self::generate_new_acces_token_from_refresh($zrodlo);
                return $new_tok;
            } catch (\Exception $e) {
                Log::channel('logowanie')->warning('Zkładanie nowego źródła nie powiodło się', ['zrodlo' => $zrodlo, 'exception' => $e->getMessage()]);
            }
        }
        return null;
    }

    static public function saveBillingToDatabase($billingData, $zrodlo)
    {
        $i = 0;
        foreach ($billingData as $billing) {
            $existingBilling = billing_allegro_surowy_api::where('billing_id', $billing['id'])->first();

            if ($existingBilling) {
                //dd('Wpis już instniej');
                print "\n ".$i." wpis istnieje: ".$existingBilling->id;

            } else {
                // Faktura nie istnieje w bazie danych, utwórz nowy rekord
                print "\n".$i.' -> '.$billing['id']."\t Data: - ".$billing['occurredAt']."\t Typ operacji: ".$billing['type']['name'];

                $data = Carbon::parse($billing['occurredAt'])->setTimezone(new DateTimeZone('Europe/Warsaw'));


                $data_miesiac = ($data->format("m"));
                $data_rok = ($data->year);


                billing_allegro_surowy_api::create([
                    'Data' => $data,
                    'Nazwa_oferty' => $billing['offer']['name'] ?? '',
                    'Identyfikator_oferty' => $billing['offer']['id'] ?? null,
                    'Typ_operacji' => $billing['type']['name'],
                    'Uznania' => !empty($billing['value']['amount']) && (float)$billing['value']['amount'] > 0 ? (float)$billing['value']['amount'] : 0,
                    'Obciazenia' => !empty($billing['value']['amount']) && (float)$billing['value']['amount'] < 0 ? (float)$billing['value']['amount'] : 0,
                    'Saldo' => $billing['balance']['amount'],
                    'Szczegoly_operacji' => '',
                    'Nazwa_pliku' => '',
                    'miesiac_raportu' => $data_miesiac,
                    'rok_raportu' => $data_rok,
                    'zrodlo' => $zrodlo,
                    'Rok_Miesiac' =>  $data_rok.'-'.$data_miesiac,
                    'billing_id' => $billing['id'],
                    'data_string' => $billing['occurredAt'],
                    'order_id' => $billing['order']['id'] ?? '',
                    'Data_zulu' => Carbon::parse($billing['occurredAt'])
                ]);
            }
            $i++;
        }
    }

    static public function get_orders_to_payments($zrodlo)
    {
        //Pobiera wszystkie ordery do których nie została przypisana płatność
        $orders = DB::connection('raporty')
            ->select('SELECT DISTINCT (order_id )
                              FROM tangel_BillingAllegroPozycje as Biling
                              where not exists (SELECT Id ,Order_id,Payment_id  FROM tangel_AllegroToPayment  as Powiazanie  where  Powiazanie.Order_id = Biling.order_id)
                            and Biling.zrodlo = \''.$zrodlo.'\'
                            and Biling.Typ_operacji != \'Allegro Protect\'
                            and DATALENGTH (Biling.order_id) > 0 ');

        return $orders;
    }

    static public function save_payments($dane)
    {

        AllegroToPayment::create([
            'Order_id' => $dane['id'],
            'Payment_id' => $dane['payment']['id'] ?? '',
            'Type' => $dane['payment']['type'] ?? '',
            'Provider' => $dane['payment']['provider'] ?? '',
            'FinishedAt' => $dane['payment']['finishedAt'] ?? '',
            'PaidAmount' => is_null($dane['payment']['paidAmount']) ? null : $dane['payment']['paidAmount']['amount'],
            'status' => $dane['status']
        ]);
    }

    static public function get_ostatni_wpis_billing($zrodlo)
    {
        $sql = DB::connection('raporty')
            ->select("select top 1 *
                            from tangel_BillingAllegroPozycje tbap
                            WHERE zrodlo = '$zrodlo'
                            order by [Data] DESC ");

        return $sql[0]->Data;
    }

    static public function pobierz_token($zrodlo)
    {
        switch ($zrodlo){
            //Tokeny usunięte

        }
        return $tok ?? dd('Niezdwfiniowane źródło', $zrodlo);
    }

    static public function szczegoly_zamowienia($accessToken, $zrodlo, $id_zamowienia)
    {

        $client = new Client();
        try {

            $response = $client->request('GET', 'https://api.allegro.pl//order/checkout-forms/'.$id_zamowienia, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/vnd.allegro.public.v1+json',
                ],
            ]);
            //dd('---------------------------------------------------');
            $statusCode = $response->getStatusCode();
            $data = json_decode($response->getBody(), true);


        } catch (RequestException $e) {

            if ($e->hasResponse()) {
                $response = $e->getResponse();
                dd($e, $response);
            }
        }
        return $data;
    }

    static public function list_przewozowy($accessToken, $zrodlo, $id_zamowienia)
    {
        $data = array();
        $client = new Client();
        try {

            $response = $client->request('GET', 'https://api.allegro.pl//order/checkout-forms/'.$id_zamowienia.'/shipments', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/vnd.allegro.public.v1+json',
                ],
            ]);
            //dd('---------------------------------------------------');
            $statusCode = $response->getStatusCode();
            $data = json_decode($response->getBody(), true);

        } catch (RequestException $e) {

            if ($e->hasResponse()) {
                $response = $e->getResponse();
                dd('ApiAllegro:ListPrzewozowy - coś poszło nie tak', $e, $id_zamowienia, $response);
            }
        }
        return $data;
    }

    static public function tracking($accessToken, $carrierId, $waybill)
    {
        $offset = 0; // Początkowe przesunięcie wyników
        $data = array();
        do {
            $client = new Client();
            try {
                $response = $client->request('GET', 'https://api.allegro.pl//order/carriers/'.$carrierId.'/tracking?waybill='.$waybill, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Accept' => 'application/vnd.allegro.public.v1+json',
                    ],
                ]);
                //dd('---------------------------------------------------');
                $statusCode = $response->getStatusCode();
                $data = json_decode($response->getBody(), true);
                return $data;

                $offset += 100;
            } catch (RequestException $e) {
                if ($e->hasResponse()) {
                    $response = $e->getResponse();
                    dd('ApiAllegro', $e);
                }
            }

        } while (count($data['billingEntries']) > 0);

        return $data ?? null;
    }

    /**
     * Pobiera status śledzenia przesyłki z serwisu Allegro.
     * @param object $waybills Dane listu przewozowego
     * @param object $zlozenie_zamowienia Znacznik czasu opłacenia zamówienia.
     * @param object $pojawienie_formica Znacznik czasu pojawienia się dokumentu w Formice.
     * @param object $deklaracja_wysylki Znacznik czasu na wysyłkę.
     * @param string $nr_FS Numer faktury sprzedaży
     * @return array Tablica zawierająca informacje o statusie śledzenia przesyłki.
     */    static public function get_status_tracking($waybills, $zlozenie_zamowienia, $pojawienie_formica, $deklaracja_wysylki, string $nr_FS)
    {
        $czas_zlozenie_zamowienia = Carbon::parse($zlozenie_zamowienia);
        $czas_formica = Carbon::parse($pojawienie_formica);
        $deklaracja = Carbon::parse($deklaracja_wysylki);
        $dane = array();
        if ($waybills['waybills'][0]['trackingDetails']) {
            $dane['kurier'] = $waybills['carrierId'];
            foreach ($waybills['waybills'] as $row)
            {
                $dane['numer_lp'] = $row['waybill'];
                //Pobieram tylko listy przypisane do wybranego oddziału
                $numer_fs_z_listu = self::pobierz_fs_z_listu($row['waybill']);

                if ($numer_fs_z_listu == $nr_FS) {
                    foreach ($row['trackingDetails']['statuses'] as $status) {
                        if ($status['code'] === 'PENDING') {
                            $dane['czas_przygotowania']['occurredAt'] = Carbon::parse($status['occurredAt'])->setTimezone(new DateTimeZone('Europe/Warsaw'));
                            $dane['czas_przygotowania']['code'] = $status['code'];
                            $dane['czas_przygotowania']['description'] = $status['description'];
                        }
                        if ($status['code'] === 'IN_TRANSIT') {
                            $dane['czas_odebrania']['occurredAt'] = Carbon::parse($status['occurredAt'])->setTimezone(new DateTimeZone('Europe/Warsaw'));
                            $dane['czas_odebrania']['code'] = $status['code'];
                            $dane['czas_odebrania']['description'] = $status['description'];
                            $dane['czas_odebrania']['roznica_zlozenie'] = $czas_zlozenie_zamowienia->diffInHours($dane['czas_odebrania']['occurredAt']);
                            $dane['czas_odebrania']['roznica_formica'] = $czas_formica->diffInHours($dane['czas_odebrania']['occurredAt']);

                            if ($dane['czas_odebrania']['occurredAt'] > $deklaracja) {
                                $dane['czas_odebrania']['opoznienie'] = 'TAK';
                            } else {
                                $dane['czas_odebrania']['opoznienie'] = '';
                            }
                            break;
                        }
                    }
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }

        return $dane;
    }

    static public function pobierz_fs_z_listu($numer_lp)
    {
        $rows = DB::connection('raporty')
            ->select("SELECT *
                            from xxx 
                            WHERE OSK_Numer_paczki  LIKE '%$numer_lp%'");

        return $rows[0]->Numer_Dokumentu ?? null;
    }

}

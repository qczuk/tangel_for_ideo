<?php

namespace App\Console\Commands;

use App\Models\db_tableau\BL_orders;
use App\Models\db_tableau\BL_orders_positions;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Input\InputArgument;

class import_baselinker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:import_baselinker';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importuje dane z BL do bazy danych';

    protected function configure()
    {
        $this->addArgument('baselinker', InputArgument::REQUIRED, 'Podaj Żródło ', null);
    }
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
        $baselinker = $this->argument('baselinker');

        switch ($baselinker) {
            case 'xxx':
                $token = 'X-BLToken: xxx';
                break;
            case 'yyy':
                $token = 'X-BLToken: yyy';
                break;
            case 'zzz':
                $token = 'X-BLToken: zzz';
                break;
        }

        $data_ostatniego_wpisu = self::utc_ostatniego_wpisu($baselinker);
        //Zwiększam o 1 sekundę
        $data_ostatniego_wpisu++;

        //$data_ostatniego_wpisu = self::konwertuj_date_na_utc('2024-06-01 00:00:01');

        $methodParams = '{
            "get_unconfirmed_orders": false,
            "include_custom_extra_fields": true,
            "date_confirmed_from": '.$data_ostatniego_wpisu.'
        }';

        $apiParams = [
            "method" => "getOrders",
            "parameters" => $methodParams
        ];

        $curl = curl_init("https://api.baselinker.com/connector.php");
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [$token]);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($apiParams));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);


        $respone = curl_exec($curl);
        $tablica_zamowien = json_decode($respone)->orders;

        foreach ($tablica_zamowien as $row)
        {
          //  dd($row);
            $order = new BL_orders();
            $order->order_id = $row->order_id;
            $order->external_order_id = $row->external_order_id;
            $order->order_source = $row->order_source;
            $order->order_source_id = $row->order_source_id;
            $order->order_source_info = $row->order_source_info;
            $order->order_status_id = $row->order_status_id;
            $order->date_confirmed = self::konwertuj_date_na_timestamp($row->date_confirmed);
            $order->date_add = self::konwertuj_date_na_timestamp($row->date_add);
            $order->date_in_status = self::konwertuj_date_na_timestamp($row->date_in_status);
            $order->user_login = $row->user_login;
            $order->phone = $row->phone;
            $order->email = $row->email;
            $order->user_comments = $row->user_comments;
            $order->admin_comments = $row->admin_comments;
            $order->currency = $row->currency;
            $order->payment_method = $row->payment_method;
            $order->payment_method_cod = $row->payment_method_cod;
            $order->payment_done = $row->payment_done;
            $order->delivery_method = $row->delivery_method;
            $order->delivery_price = $row->delivery_price;
            $order->delivery_package_module = $row->delivery_package_module;
            $order->delivery_package_nr = $row->delivery_package_nr;
            $order->delivery_fullname = $row->delivery_fullname;
            $order->delivery_company = $row->delivery_company;
            $order->delivery_address = $row->delivery_address;
            $order->delivery_city = $row->delivery_city;
            $order->delivery_state = $row->delivery_state;
            $order->delivery_postcode = $row->delivery_postcode;
            $order->delivery_country_code = $row->delivery_country_code;
            $order->delivery_point_id = $row->delivery_point_id;
            $order->delivery_point_name = $row->delivery_point_name;
            $order->delivery_point_address = $row->delivery_point_address;
            $order->delivery_point_postcode = $row->delivery_point_postcode;
            $order->delivery_point_city = $row->delivery_point_city;
            $order->invoice_fullname = $row->invoice_fullname;
            $order->invoice_company = $row->invoice_company;
            $order->invoice_nip = $row->invoice_nip;
            $order->invoice_address = $row->invoice_address;
            $order->invoice_city = $row->invoice_city;
            $order->invoice_state = $row->invoice_state;
            $order->invoice_postcode = $row->invoice_postcode;
            $order->invoice_country_code = $row->invoice_country_code;
            $order->want_invoice = $row->want_invoice;
            $order->extra_field_1 = $row->extra_field_1;
            $order->extra_field_2 = $row->extra_field_2;
            $order->order_page = $row->order_page;
            $order->pick_state = $row->pick_state;
            $order->pack_state = $row->pack_state;
            $order->delivery_country = $row->delivery_country;
            $order->invoice_country = $row->invoice_country;
            $order->baselinker = $baselinker;
            $order->save();

            print $order->date_confirmed."\n";

            if (isset($row->products))
            {
                try {
                    foreach ($row->products as $position)
                    {
                        $orderPosition = new BL_orders_positions();

                        $orderPosition->order_id = $order->id;
                        $orderPosition->storage = $position->storage ?? '';
                        $orderPosition->storage_id = $position->storage_id ?? '';
                        $orderPosition->order_product_id = $position->order_product_id ?? null;
                        $orderPosition->product_id = $position->product_id ?? '';
                        $orderPosition->variant_id = (integer)$position->variant_id ?? null;
                        $orderPosition->name = $position->name ?? '';
                        $orderPosition->attributes = $position->attributes ?? '';
                        $orderPosition->sku = $position->sku ?? '';
                        $orderPosition->ean = $position->ean ?? null;
                        $orderPosition->location = $position->location ?? '';
                        $orderPosition->warehouse_id = $position->warehouse_id ?? null;
                        $orderPosition->auction_id = $position->auction_id ?? '';
                        $orderPosition->price_brutto = $position->price_brutto ?? null;
                        $orderPosition->tax_rate = $position->tax_rate ?? null;
                        $orderPosition->quantity = $position->quantity ?? null;
                        $orderPosition->weight = $position->weight ?? null;
                        $orderPosition->bundle_id = $position->bundle_id ?? null;
                        $orderPosition->save();
                    }
                } catch (Exception $e) {
                    dd($e);
                }
            }
        }


        return 0;
    }

    public function konwertuj_date_na_timestamp($data_wej)
    {
        // Przetwarzanie daty w formacie UTC
        $timestamp = $data_wej; // Zakładając, że $row->date_add to 1689318624
        $carbon_date = Carbon::createFromTimestamp($timestamp);
        //dd($data_wej, $carbon_date);
        return $carbon_date;
    }

    public function konwertuj_date_na_utc($data_wej)
    {
        $dateLocal = Carbon::createFromFormat('Y-m-d H:i:s', $data_wej, 'Europe/Warsaw');
        return $dateUtc = $dateLocal->timestamp;
    }

    public function pobierz_ostatni_wpis($baselinker)
    {
        $last = DB::connection('tableau')->table('bl_orders')
            ->where('baselinker', '=', $baselinker)
            ->orderBy('date_confirmed', 'DESC')
            ->first();

        return $last;
    }

    public function utc_ostatniego_wpisu($baselinker)
    {
        $last = self::pobierz_ostatni_wpis($baselinker);
        $utc_time = self::konwertuj_date_na_utc($last->date_confirmed);
        return $utc_time;
    }
}

<?php

namespace App\Http\Livewire;

use App\Models\db_tableau\Zestaw;
use App\Models\Zestawy\SPS;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class KalkulatorZlotowa extends Component
{

    public $kategorie;
    public $selectedCategory;
    public $kategoria_procent;
    public $cena_zakupu_netto;
    public $cena_zakupu_brutto;
    public $zmiana_pola;
    public $zakup_4pr;
    public $cena_sprzedazy_netto;
    public $cena_sprzedazy_brutto;
    public $prowizja_allegro;
    public $minimalna_sprzedazy_brutto;
    public $vat_sprzedazy_stawka = 23;
    public $etykiety_transportowe;
    public $etykieta_transportowa_koszt;
    public $etykieta_transportowa;
    public $obrazek;
    public $wyroznienie = false;
    public $oplata_wyroznienie = 0;
    public $allegro_smart = 0;

    public function updatedSelectedCategory($value)
    {
        $this->kategorie = Zestaw::get_kategorie_allegro();
        $rows = Zestaw::get_kategoria($value);

        $this->selectedCategory = $rows[0]->ProductsFeatures_FeatureKey;
        $this->kategoria_procent = $rows[0]->AllegroCommissions_Commission;
    }

    public function zmiana_pola()
    {
        $this->cena_zakupu_brutto = $this->cena_zakupu_netto * (1 + $this->vat_sprzedazy_stawka / 100) ;
        $this->cena_sprzedazy_netto = $this->cena_sprzedazy_brutto / ( 1 + $this->vat_sprzedazy_stawka / 100);
        $this->zakup_4pr = (($this->cena_zakupu_netto / 0.96) - $this->cena_zakupu_netto) * (1 + ($this->vat_sprzedazy_stawka / 100));
        $this->prowizja_allegro = $this->cena_sprzedazy_brutto * ($this->kategoria_procent / 100);
        $this->etykieta_transportowa_koszt = $this->etykieta_transportowa;

        if ($this->wyroznienie == true)
        {
            $this->oplata_wyroznienie = $this->prowizja_allegro * 0.75;
        } else {
            $this->oplata_wyroznienie = 0;
        }

        if ($this->cena_sprzedazy_brutto) {
            switch ($this->cena_sprzedazy_brutto) {
                case ($this->cena_sprzedazy_brutto < 45):
                    $this->allegro_smart = 0;
                    break;
                case ($this->cena_sprzedazy_brutto >= 45 && $this->cena_sprzedazy_brutto < 80):
                    $this->allegro_smart = 2.84 * 1.23;
                    break;
                case ($this->cena_sprzedazy_brutto >= 80 && $this->cena_sprzedazy_brutto < 120):
                    $this->allegro_smart = 4.14 * 1.23;
                    break;
                case ($this->cena_sprzedazy_brutto >= 120 && $this->cena_sprzedazy_brutto < 200):
                    $this->allegro_smart = 7.07 * 1.23;
                    break;
                case ($this->cena_sprzedazy_brutto >= 200):
                    $this->allegro_smart = 9.35 * 1.23;
                    break;
            }
        }

        $this->minimalna_sprzedazy_brutto = $this->cena_zakupu_brutto + $this->zakup_4pr + $this->prowizja_allegro + $this->etykieta_transportowa_koszt + $this->oplata_wyroznienie + $this->allegro_smart;

        if ($this->cena_sprzedazy_brutto > $this->minimalna_sprzedazy_brutto)
        {
            $this->obrazek = asset('assets/img/v.jpg');
        } else {
            $this->obrazek = asset('assets/img/x.jpg');
        }

    }
    public function render()
    {
        $this->kategorie = Zestaw::get_kategorie_allegro();
        $this->etykiety_transportowe = Zestaw::get_transports_costs();

        return view('livewire.kalkulator-zlotowa', [
            'kategorie' => $this->kategorie ?? null,
            'selectedCategory' => $this->selectedCategory ?? null,
            'kategoria_procent' => $this->kategoria_procent ?? null,
        ]);
    }
}

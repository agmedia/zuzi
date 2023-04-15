<?php

namespace App\Http\Livewire\Back\Settings;

use App\Helpers\Country;
use Livewire\Component;

class StatesAddition extends Component
{
    public $countries = [];

    public $states = [];


    public function mount()
    {
        $this->countries = Country::list();
        //dd($this->countries);
    }


    public function stateSelected(string $state)
    {
        array_push($this->states, $state);
    }


    public function deleteState(string $state)
    {
        $index = array_search($state, $this->states);

        unset($this->states[$index]);

        array_values($this->states);
    }

    public function render()
    {
        return view('livewire.back.settings.states-addition');
    }
}

<?php

namespace App\Helpers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class Chart
{
    
    /**
     * @var array
     */
    protected $request;

    /**
     * @var string[]
     */
    public $months = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'];

    /**
     * @var string[]
     */
    public $month_names = ['Sij', 'Velj', 'Ožu', 'Tra', 'Svi', 'Lip', 'Srp', 'Kol', 'Ruj', 'Lis', 'Stu', 'Pro'];
    
    
    /**
     * Set chart data query params.
     *
     * @return array
     */
    public function setQueryParams(bool $last = false): array
    {
        $from = now()->startOfYear();
        $to = now();

        if ($last) {
            $from = now()->subYear()->startOfYear();
            $to = now()->subYear()->endOfYear();
        }

        return [
            'from'     => $from,
            'to'       => $to,
            'iterator' => $this->months,
            'iterator_names' => $this->month_names,
            'group'    => 'm'
        ];
    }


    /**
     * @param Collection $data
     *
     * @return array
     */
    public function setDataByYear(Collection $data):array
    {
        $response = new Collection();

        foreach ($this->months as $key => $month) {
            if ( ! $data->has($month)) {
                $response->put($month, [
                    'title' => $this->month_names[$key],
                    'value' => 0
                ]);
            } else {
                $sum = $this->sumOvjere($data[$month]);

                $response->put($month, [
                    'title' => $this->month_names[$key],
                    'value' => $sum
                ]);
            }
        }

        return array_values($response->toArray());
    }


    /**
     * @param Collection $ovjere
     *
     * @return int
     */
    public function sumOvjere(Collection $items): int
    {
        $sum = 0;

        foreach ($items as $month => $item) {
            $sum += $item->total;
        }

        return $sum;
    }


    /**
     * @param array $data
     *
     * @return int|mixed
     */
    public function total(array $data)
    {
        $sum = 0;

        foreach ($data as $key => $month) {
            $sum += $month['value'];
        }

        return $sum;
    }
    
}

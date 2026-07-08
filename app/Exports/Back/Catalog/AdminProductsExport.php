<?php

namespace App\Exports\Back\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class AdminProductsExport implements FromQuery, WithColumnFormatting, WithCustomChunkSize, WithHeadings, WithMapping
{
    /**
     * @var Builder
     */
    private $query;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'Naziv',
            'Šifra',
            'Polica',
            'Cijena',
            'Količina',
            'ItemID',
            'ISBN',
            'Status',
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function map($product): array
    {
        return [
            $product->name,
            (string) $product->sku,
            (string) $product->polica,
            (float) $product->price,
            (int) $product->quantity,
            $product->itemid ? (string) $product->itemid : null,
            $product->isbn ? (string) $product->isbn : null,
            $product->status ? 'Aktivan' : 'Neaktivan',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_NUMBER_00,
            'F' => NumberFormat::FORMAT_TEXT,
            'G' => NumberFormat::FORMAT_TEXT,
        ];
    }
}

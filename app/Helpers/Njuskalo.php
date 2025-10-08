<?php

namespace App\Helpers;

use App\Models\Back\Catalog\Product\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Generira stavke za Njuškalo feed uz minimalan memory footprint.
 * Koristi lazyById() pa se proizvodi učitavaju i obrađuju streamano.
 */
class Njuskalo
{
    /**
     * Vraća Generator sa transformiranim stavkama.
     * Primjer upotrebe u kontroleru:
     *   return response()->view('front.layouts.partials.njuskalo', [
     *       'items' => (new Njuskalo())->items()
     *   ])->header('Content-Type', 'text/xml; charset=UTF-8');
     *
     * @param int $chunkSize Primarni ključ batch veličina za lazyById
     * @return \Generator<array<string,mixed>>
     */
    public function items(int $chunkSize = 500): \Generator
    {
        foreach ($this->baseQuery()->lazyById($chunkSize) as $product) {
            yield $this->transform($product);
        }
    }

    /**
     * Osnovni upit – ovdje držimo sve filtre i eager load.
     */
    private function baseQuery(): Builder
    {
        return Product::query()
            ->where('status', 1)
            ->where('price', '!=', 0)
            ->where('quantity', '!=', 0)
            ->select([
                'id', 'name', 'description', 'quantity', 'status', 'price',
                'image', 'pages', 'dimensions', 'origin', 'url', 'letter',
                'condition', 'binding', 'year',
            ])
            ->with(['categories:id,slug']);
    }

    /**
     * Transformira Eloquent model u polje prikladno za XML Blade view.
     *
     * @param \App\Models\Back\Catalog\Product\Product $product
     * @return array<string,mixed>
     */
    public function transform(Product $product): array
    {
        $categorySlug = optional($product->categories->first())->slug ?? 'ostala-literatura';

        return [
            'id'          => $product->id,
            'name'        => $product->name,
            'description' => $this->getDescription($product), // spremno za CDATA u Bladeu
            'group'       => config('settings.njuskalo.sync.' . $categorySlug),
            'price'       => $product->price,
            'slug'        => $product->url,
            'image'       => asset($product->image),
        ];
    }

    /**
     * Sastavlja opis; čisti kontrolne znakove i po želji limitira duljinu.
     */
    private function getDescription(Product $product): string
    {
        $parts = [];

        if (!empty($product->description)) {
            $desc = preg_replace('/[[:cntrl:]]/', '', (string) $product->description);
            // Po potrebi odkomentiraj limit:
            // $desc = Str::limit($desc, 2000, '…');
            $parts[] = $desc . '<br><br>';
        }

        $kv = [
            'Stranica'  => $product->pages,
            'Dimenzije' => $product->dimensions,
            'Jezik'     => $product->origin,
            'Pismo'     => $product->letter,
            'Stanje'    => $product->condition,
            'Uvez'      => $product->binding,
            'Godina'    => $product->year,
        ];

        foreach ($kv as $label => $value) {
            if (!empty($value)) {
                $parts[] = "{$label}: {$value}<br>";
            }
        }

        return implode('', $parts);
    }
}

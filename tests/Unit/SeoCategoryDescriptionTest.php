<?php

namespace Tests\Unit;

use App\Models\Front\Catalog\Category;
use App\Models\Seo;
use Tests\TestCase;

class SeoCategoryDescriptionTest extends TestCase
{
    public function test_category_data_uses_meta_description_when_available(): void
    {
        $category = new Category([
            'title' => 'Bookmarkeri',
            'meta_title' => 'Bookmarkeri',
            'meta_description' => 'Opis iz meta polja za kategoriju Bookmarkeri.',
            'description' => '<p>HTML opis kategorije koji ne treba imati prioritet.</p>',
        ]);

        $data = Seo::getCategoryData('kategorija-proizvoda', $category);

        $this->assertSame('Bookmarkeri | ZUZI Shop', $data['title']);
        $this->assertSame('Opis iz meta polja za kategoriju Bookmarkeri.', $data['description']);
    }

    public function test_category_data_falls_back_to_generic_copy_without_meta_description(): void
    {
        $category = new Category([
            'title' => 'Bookmarkeri',
            'description' => '<p>Postojeci opis kategorije koji vise ne treba biti fallback za SEO opis.</p>',
        ]);

        $data = Seo::getCategoryData('kategorija-proizvoda', $category);

        $this->assertSame(
            'Pregledajte knjige iz kategorije Bookmarkeri i izdvojite naslove koji vas zanimaju.',
            $data['description']
        );
    }
}

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'free_shipping' => 70,

    'pagination' => [
        'front' => 32,
        'back'  => 30
    ],

    'search_keyword'    => 'pojam',
    'author_path'       => 'autor',
    'publisher_path'    => 'nakladnik',
    'group_path'        => 'Kategorija proizvoda',

    'unknown_author'    => 3282,
    'unknown_publisher' => 376,
    'images_domain'     =>  env('APP_IMAGE_DOMAIN'),
    'default_tax_id'       => 1,
    'eur_divide_amount' => 0.13272280,

    'sorting_list' => [
        0 => [
            'title' => 'Najnovije',
            'value' => 'novi'
        ],
        1 => [
            'title' => 'Najmanja cijena',
            'value' => 'price_up'
        ],
        2 => [
            'title' => 'Najveća cijena',
            'value' => 'price_down'
        ],
        3 => [
            'title' => 'A - Ž',
            'value' => 'naziv_up'
        ],
        4 => [
            'title' => 'Ž - A',
            'value' => 'naziv_down'
        ],
    ],

    'actions_sorting_list' => [
        0 => [
            'title' => 'Sve akcije',
            'type' => 'all',
            'value' => 0
        ],
        1 => [
            'title' => 'Zaključane',
            'type' => 'lock',
            'value' => 'da'
        ],
        2 => [
            'title' => 'Otključane',
            'type' => 'lock',
            'value' => 'ne'
        ],
        3 => [
            'title' => 'Sa kuponom',
            'type' => 'coupon',
            'value' => 'da'
        ],
        4 => [
            'title' => 'Bez kupona',
            'type' => 'coupon',
            'value' => 'ne'
        ],
        5 => [
            'title' => 'Aktivne',
            'type' => 'status',
            'value' => 'da'
        ],
        6 => [
            'title' => 'Neaktivne',
            'type' => 'status',
            'value' => 'ne'
        ],
        7 => [
            'title' => 'Samo jedan artikl',
            'type' => 'group',
            'value' => 'single'
        ],
    ],

    'order' => [
        'made_text' => 'Narudžba napravljena.',
        'status'    => [
            'new'        => 1,
            'unfinished' => 8,
            'declined'   => 7,
            'canceled'   => 5,
            'paid'       => 3,
            'send'       => 4,
            'ready'      => 10,
        ],
        // Can be number or array.
        'new_status' => 1,
        'canceled_status' => [7, 5],
    ],

    'special_action' => [
        'title' => 'Količinski popust',
        'start' => null,
        'end' => null
    ],

    'payment' => [
        'providers' => [
            //'wspay'  => \App\Models\Front\Checkout\Payment\Wspay::class,
            //'payway' => \App\Models\Front\Checkout\Payment\Payway::class,
            'corvus' => \App\Models\Front\Checkout\Payment\Corvus::class,
            'cod'    => \App\Models\Front\Checkout\Payment\Cod::class,
            'bank'   => \App\Models\Front\Checkout\Payment\Bank::class,
            'pickup' => \App\Models\Front\Checkout\Payment\Pickup::class
        ]
    ],

    'sitemap' => [
        0 => 'pages',
        1 => 'categories',
        2 => 'products',
        3 => 'authors',
        4 => 'publishers'
    ],

    'njuskalo' => [
        'user_id' => '968815',
        'sync' => [
            'fantasy' => 15360, //*
            'djecje-knjige' => 15348,
            'beletristika' => 9751, //*
            'proza' => 15358, //* Poezija
            'sf' => 15369, //*
            'knjizevnost' => 9753, //*
            'outlet' => 9760,
            'duhovne-knjige' => 9754, //*
            'povijest' => 15387,
            'autobiografije-i-biografije' => 15363, //
            'kompleti' => 9760,
            'alternativne-knjige' => 9760,
            'savjetnici' => 9760, //*
            'psihologija' => 15389,
            'rijetke-knjige' => 9750,
            'erotske-knjige' => 9760,
            'nakladnici' => 9760,
            'knjige-na-stranom-jeziku' => 9760,
            'religija-i-mitologija' => 9754,
            'antikvarne-knjige' => 9750,
            'militarija' => 9760,
            'kuharice' => 13116,
            'publicistika' => 9760,
            'ostala-literatura' => 9760,
            'strucna-literatura' => 9756,
            'enciklopedije-i-leksikon' => 9756,
            'monografije' => 13118,
            'putopisi' => 9760,
            'rjecnici' => 13095,
            'filozofija' => 15380,
            'poezija' => 15358,
            'stripovi' => 12408,
            'wattpad-i-domaci-pisci' => 9760,
            'nekategorizirane' => 9760,
            'casopisi' => 15347,
            'bookmarkeri' => 9760,
            'fotografija' => 9760,
            'bojanke-za-odrasle' => 9760,
            'rokovnici' => 9760,
            'gift-program' => 9760,
            'karte' => 9760,
            'svezalice-pidzame-za-knjige' => 9760
        ]
    ]

];

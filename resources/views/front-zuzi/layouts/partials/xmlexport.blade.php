<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<ad_list>
    @foreach ($items as $item)
        <product_item>
            <product_id>{{ $item['id'] }}</product_id>
            <title>{{ $item['name'] }}</title>
            <author>{{ $item['author']['title'] }}</author>
            <author_id>{{ $item['author_id'] }}</author_id>
            <publisher>{{ isset($item['publisher']['title']) ? $item['publisher']['title'] : 'nije postavljeno' }}</publisher>
            <publisher_id>{{  $item['publisher_id'] }}</publisher_id>
            <price>{{ $item['price'] }}</price>
            <sku>{{ $item['sku'] }}</sku>
            <quantity>{{ $item['quantity'] }}</quantity>
            <polica>{{ $item['polica'] }}</polica>
            <tax_id>{{ $item['tax_id'] }}</tax_id>
            <meta_title>{{ $item['meta_title'] }}</meta_title>
            <meta_description>{{ $item['meta_description'] }}</meta_description>
            <pages>{{ $item['pages'] }}</pages>
            <dimensions>{{ $item['dimensions'] }}</dimensions>
            <origin>{{ $item['origin'] }}</origin>
            <letter>{{ $item['letter'] }}</letter>
            <condition>{{ $item['condition'] }}</condition>
            <binding>{{ $item['binding'] }}</binding>
            <year>{{ $item['year'] }}</year>
            <status>{{ $item['status'] }}</status>
            <description><![CDATA[{{ $item['description'] }}]]></description>
            <webshopLink>{{ url($item['slug']) }}</webshopLink>
            @foreach ($item['cat'] as $cat)
            <category>{{ $cat['title'] }}</category>
            <category_id>{{ $cat['id'] }}</category_id>
            @endforeach
            @foreach ($item['subcat'] as $subcat)
                <subcategory>{{ $subcat['title'] }}</subcategory>
                <subcategory_id>{{ $subcat['id'] }}</subcategory_id>
            @endforeach

            <image_list>
                <image>{{ $item['image'] }}</image>
            </image_list>
        </product_item>
    @endforeach
</ad_list>

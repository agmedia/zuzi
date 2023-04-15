<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
    @foreach ($items as $item)
        <url>
            <loc>{{ $item['loc'] }}</loc>
            @foreach ($item['images'] as $image)
                <image:image>
                    <image:loc>{{ $image['loc'] }}</image:loc>
                </image:image>
            @endforeach
        </url>
    @endforeach
</urlset>
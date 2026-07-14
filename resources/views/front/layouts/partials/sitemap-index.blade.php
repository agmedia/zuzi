<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach ($items as $item)
        @php
            $loc = is_array($item) ? ($item['loc'] ?? null) : route('sitemap', ['sitemap' => $item]);
            $lastmod = is_array($item) ? ($item['lastmod'] ?? null) : null;
        @endphp
        <sitemap>
            <loc>{{ $loc }}</loc>
            @if($lastmod)
                <lastmod>{{ $lastmod }}</lastmod>
            @endif
        </sitemap>
    @endforeach
</sitemapindex>

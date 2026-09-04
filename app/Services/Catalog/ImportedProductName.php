<?php

namespace App\Services\Catalog;

final class ImportedProductName
{
    /**
     * Build the catalogue name used for books created by supplier imports.
     */
    public static function format(?string $author, ?string $title): string
    {
        $author = self::clean($author);
        $title = self::clean($title);

        if ($author === '' || $title === '') {
            return $title;
        }

        $bareTitle = self::withoutAuthor($author, $title);

        return $bareTitle !== '' ? $author . ': ' . $bareTitle : $title;
    }

    /**
     * Return both catalogue names accepted while old unprefixed rows remain.
     *
     * @return array<int, string>
     */
    public static function variants(?string $author, ?string $title): array
    {
        $title = self::clean($title);

        return array_values(array_unique(array_filter([
            $title,
            self::format($author, $title),
        ], fn (string $value) => $value !== '')));
    }

    /**
     * Reduce a formatted catalogue name back to the supplier's bare title.
     */
    public static function withoutAuthor(?string $author, ?string $name): string
    {
        $author = self::clean($author);
        $name = self::clean($name);
        if ($author === '' || $name === '') {
            return $name;
        }

        $prefix = $author . ':';
        if (mb_strtolower(mb_substr($name, 0, mb_strlen($prefix))) !== mb_strtolower($prefix)) {
            return $name;
        }

        return ltrim(mb_substr($name, mb_strlen($prefix)));
    }

    private static function clean(?string $value): string
    {
        return AuthorResolver::normalizeName(html_entity_decode(
            (string) $value,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        ));
    }
}

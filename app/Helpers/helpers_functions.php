<?php

/**
 *
 */
if ( ! function_exists('group')) {
    /**
     * Function that returns category group based on
     * settings.php "group_path" key value. Returns it as is or
     * as a slug if the $slug parameter is true.
     *
     * @param bool $slug
     *
     * @return string
     */
    function group(bool $slug = false): string
    {
        if ($slug) {
            return \Illuminate\Support\Str::slug(config('settings.group_path'));
        }

        return config('settings.group_path');
    }
}

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

/**
 *
 */
if ( ! function_exists('main_currency_symbol')) {
    /**
     * Function that returns category group based on
     * settings.php "group_path" key value. Returns it as is or
     * as a slug if the $slug parameter is true.
     *
     * @param bool $slug
     *
     * @return string
     */
    function main_currency_symbol(): string
    {
        return \App\Helpers\Currency::main_symbol();
    }
}

/**
 *
 */
if ( ! function_exists('ag_log')) {
    /**
     * @param             $value
     * @param string      $level [debug, test, error]
     * @param string|null $title
     *
     * @return null
     */
    function ag_log($value, string $level = 'debug', string $title = null): void
    {
        if (in_array($level, ['d', 'debug'])) {
            if ($title) \Illuminate\Support\Facades\Log::channel('debug')->info($title);

            \Illuminate\Support\Facades\Log::channel('debug')->debug($value);
        }

        if (in_array($level, ['t', 'test'])) {
            if ($title) \Illuminate\Support\Facades\Log::channel('test')->info($title);

            \Illuminate\Support\Facades\Log::channel('test')->debug($value);
        }

        if (in_array($level, ['e', 'error'])) {
            if ($title) \Illuminate\Support\Facades\Log::channel('error')->info($title);

            \Illuminate\Support\Facades\Log::channel('error')->debug($value);
        }

        \Illuminate\Support\Facades\Log::info($value);
    }
}

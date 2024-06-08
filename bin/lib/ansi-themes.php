<?php

interface ThemeInterface {
    /**
     * @return array<int, string> The color codes to use for the terminal. (Ansi 30-37)
     */
    public static function colors(): array;

    /**
     * @return string The font family to use for the terminal. (CSS font-family)
     */
    public static function fontFamily(): string;

    /**
     * @return string The background color to use for the terminal. (HTML color code
     */
    public static function background(): string;
}

class ClassicTheme implements ThemeInterface
{
    public static function colors(): array
    {
        return [
            30 => '#000', // Black
            31 => '#f00', // Red
            32 => '#0f0', // Green
            33 => '#ff0', // Yellow
            34 => '#00f', // Blue
            35 => '#f0f', // Magenta
            36 => '#0ff', // Cyan
            37 => '#fff', // White
        ];
    }

    public static function fontFamily(): string
    {
        return 'monospace';
    }

    public static function background(): string
    {
        return '#000';
    }
}

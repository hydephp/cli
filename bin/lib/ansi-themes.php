<?php

interface ThemeInterface {
    /**
     * @return array<int, string> The color codes to use for the terminal. (Ansi 30-37)
     */
    public static function colors(): array;

    /**
     * @return string The background color to use for the terminal. (HTML color code)
     */
    public static function background(): string;

    /**
     * @return string The default text color to use for the terminal. (HTML color code)
     */
    public static function textColor(): string;

    /**
     * @return string The font family to use for the terminal. (CSS font-family)
     */
    public static function fontFamily(): string;
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

    public static function background(): string
    {
        return '#000';
    }

    public static function textColor(): string
    {
        return '#fff';
    }

    public static function fontFamily(): string
    {
        return 'monospace';
    }
}

class FiraTheme implements ThemeInterface
{
    public static function colors(): array
    {
        return [
            30 => '#000', // Black
            31 => '#f44336', // Red
            32 => '#4caf50', // Green
            33 => '#ffeb3b', // Yellow
            34 => '#2196f3', // Blue
            35 => '#9c27b0', // Magenta
            36 => '#00bcd4', // Cyan
            37 => '#fff', // White
        ];
    }

    public static function background(): string
    {
        return '#263238';
    }

    public static function textColor(): string
    {
        return '#fff';
    }

    public static function fontFamily(): string
    {
        return 'Fira Code, monospace';
    }
}

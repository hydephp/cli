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

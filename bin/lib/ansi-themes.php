<?php

interface ThemeInterface
{
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
    public static function foreground(): string;

    /**
     * @return string The font family to use for the terminal. (CSS font-family)
     */
    public static function fontFamily(): string;
}

enum Colors: int
{
    case Black = 30;
    case Red = 31;
    case Green = 32;
    case Yellow = 33;
    case Blue = 34;
    case Magenta = 35;
    case Cyan = 36;
    case White = 37;
}

abstract class BaseTheme implements ThemeInterface
{
    protected static string $background = '#000';
    protected static string $foreground = '#fff';
    protected static string $fontFamily = 'monospace';

    protected static array $colors = [
        'black' => 'black',
        'red' => 'red',
        'green' => 'green',
        'yellow' => 'yellow',
        'blue' => 'blue',
        'magenta' => 'magenta',
        'cyan' => 'cyan',
        'white' => 'white',
    ];

    public static function colors(): array
    {
        $keys = array_column(Colors::cases(), 'value');
        $values = array_values(static::$colors);

        return array_combine($keys, array_map('strtolower', $values));
    }

    public static function background(): string
    {
        return strtolower(static::$background);
    }

    public static function foreground(): string
    {
        return strtolower(static::$foreground);
    }

    public static function fontFamily(): string
    {
        return static::$fontFamily;
    }
}

class ClassicTheme extends BaseTheme
{
    protected static string $background = '#000000';
    protected static string $foreground = '#ffffff';
    protected static string $fontFamily = 'monospace';

    protected static array $colors = [
        'black' => '#000000',
        'red' => '#ff0000',
        'green' => '#00ff00',
        'yellow' => '#ffff00',
        'blue' => '#0000ff',
        'magenta' => '#ff00ff',
        'cyan' => '#00ffff',
        'white' => '#ffffff',
    ];
}

class FiraTheme extends BaseTheme
{
    protected static string $background = '#292d3e';
    protected static string $foreground = '#bfc7d5';
    protected static string $fontFamily = "'Fira Code', monospace";

    protected static array $colors = [
        'black' => '#000000',
        'red' => '#ff5572',
        'green' => '#c3e88d',
        'yellow' => '#ffcb6b',
        'blue' => '#82aaff',
        'magenta' => '#c792ea',
        'cyan' => '#89ddff',
        'white' => '#bec5d4',
    ];
}

class CampbellTheme extends BaseTheme
{
    protected static string $background = '#0c0c0c';
    protected static string $foreground = '#cccccc';
    protected static string $fontFamily = "'Cascadia Mono', monospace";

    protected static array $colors = [
        'black' => '#0c0c0c',
        'red' => '#c50f1f',
        'green' => '#13a10e',
        'yellow' => '#c19c00',
        'blue' => '#0037da',
        'magenta' => '#881798',
        'cyan' => '#3a96dd',
        'white' => '#cccccc',
    ];
}

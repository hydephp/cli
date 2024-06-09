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
    protected static array $colors = [
        'black' => '#000',
        'red' => '#f00',
        'green' => '#0f0',
        'yellow' => '#ff0',
        'blue' => '#00f',
        'magenta' => '#f0f',
        'cyan' => '#0ff',
        'white' => '#fff',
    ];

    public static function colors(): array
    {
        $keys = array_column(Colors::cases(), 'value');
        $values = array_values(static::$colors);

        return array_combine($keys, $values);
    }

    abstract public static function background(): string;

    abstract public static function foreground(): string;

    abstract public static function fontFamily(): string;
}

class ClassicTheme extends BaseTheme
{
    protected static array $colors = [
        'black' => '#000',
        'red' => '#f00',
        'green' => '#0f0',
        'yellow' => '#ff0',
        'blue' => '#00f',
        'magenta' => '#f0f',
        'cyan' => '#0ff',
        'white' => '#fff',
    ];

    public static function background(): string
    {
        return '#000';
    }

    public static function foreground(): string
    {
        return '#fff';
    }

    public static function fontFamily(): string
    {
        return 'monospace';
    }
}

class FiraTheme extends BaseTheme
{
    protected static array $colors = [
        'black' => '#000',
        'red' => '#ff5572',
        'green' => '#c3e88d',
        'yellow' => '#ffcb6b',
        'blue' => '#82aaff',
        'magenta' => '#c792ea',
        'cyan' => '#89ddff',
        'white' => '#bec5d4',
    ];

    public static function background(): string
    {
        return '#292d3e';
    }

    public static function foreground(): string
    {
        return '#bfc7d5';
    }

    public static function fontFamily(): string
    {
        return "'Fira Code', monospace";
    }
}

class CampbellTheme extends BaseTheme
{
    protected static array $colors = [
        'black' => '#0C0C0C',
        'red' => '#C50F1F',
        'green' => '#13A10E',
        'yellow' => '#C19C00',
        'blue' => '#0037DA',
        'magenta' => '#881798',
        'cyan' => '#3A96DD',
        'white' => '#CCCCCC',
    ];

    public static function background(): string
    {
        return '#0C0C0C';
    }

    public static function foreground(): string
    {
        return '#CCCCCC';
    }

    public static function fontFamily(): string
    {
        return "'Cascadia Mono', monospace";
    }
}

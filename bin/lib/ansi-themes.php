<?php

interface ThemeInterface {
    /**
     * @return array<int, string> The color codes to use for the terminal. (Ansi 30-37)
     */
    public function getColors(): array;

    /**
     * @return string The background color to use for the terminal. (HTML color code)
     */
    public function getBackground(): string;

    /**
     * @return string The default text color to use for the terminal. (HTML color code)
     */
    public function getTextColor(): string;

    /**
     * @return string The font family to use for the terminal. (CSS font-family)
     */
    public function getFontFamily(): string;
}

abstract class AbstractTheme implements ThemeInterface
{
    protected array $colors;
    protected string $background;
    protected string $textColor;
    protected string $fontFamily;

    public function __construct(array $colors, string $background, string $textColor, string $fontFamily)
    {
        $this->colors = $colors;
        $this->background = $background;
        $this->textColor = $textColor;
        $this->fontFamily = $fontFamily;
    }

    public function getColors(): array
    {
        return $this->colors;
    }

    public function getBackground(): string
    {
        return $this->background;
    }

    public function getTextColor(): string
    {
        return $this->textColor;
    }

    public function getFontFamily(): string
    {
        return $this->fontFamily;
    }
}

class ClassicTheme extends AbstractTheme
{
    public function __construct()
    {
        parent::__construct(
            [
                30 => '#000', // Black
                31 => '#f00', // Red
                32 => '#0f0', // Green
                33 => '#ff0', // Yellow
                34 => '#00f', // Blue
                35 => '#f0f', // Magenta
                36 => '#0ff', // Cyan
                37 => '#fff', // White
            ],
            '#000', // Background
            '#fff', // Text Color
            'monospace' // Font Family
        );
    }
}

class FiraTheme extends AbstractTheme
{
    public function __construct()
    {
        parent::__construct(
            [
                30 => '#000', // Black
                31 => '#f44336', // Red
                32 => '#4caf50', // Green
                33 => '#ffeb3b', // Yellow
                34 => '#2196f3', // Blue
                35 => '#9c27b0', // Magenta
                36 => '#00bcd4', // Cyan
                37 => '#fff', // White
            ],
            '#263238', // Background
            '#fff', // Text Color
            'Fira Code, monospace' // Font Family
        );
    }
}

class CampbellTheme extends AbstractTheme
{
    public function __construct()
    {
        parent::__construct(
            [
                30 => '#0C0C0C', // Black
                31 => '#C50F1F', // Red
                32 => '#13A10E', // Green
                33 => '#C19C00', // Yellow
                34 => '#0037DA', // Blue
                35 => '#881798', // Magenta
                36 => '#3A96DD', // Cyan
                37 => '#CCCCCC', // White
            ],
            '#0C0C0C', // Background
            '#CCCCCC', // Text Color
            'monospace' // Font Family
        );
    }
}

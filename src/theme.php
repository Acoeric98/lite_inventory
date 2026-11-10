<?php
declare(strict_types=1);

function theme_defaults(): array
{
    return ['background'=>'#f4f1e9', 'surface'=>'#fffdf8', 'text'=>'#17211b', 'muted'=>'#6c756f', 'accent'=>'#1d5138', 'border'=>'#dcd9d0'];
}

function validate_theme_colors(array $input): array
{
    $colors = [];
    foreach (theme_defaults() as $key => $default) {
        $value = $input[$key] ?? null;
        if (!is_string($value) || !preg_match('/^#[0-9a-fA-F]{6}$/D', $value)) {
            throw new InvalidArgumentException('Minden egyéni színhez érvényes HEX színkód szükséges.');
        }
        $colors[$key] = strtolower($value);
    }
    return $colors;
}

function saved_theme_colors(?string $json): array
{
    try {
        $colors = json_decode($json ?? '', true);
        return is_array($colors) ? validate_theme_colors($colors) : theme_defaults();
    } catch (InvalidArgumentException $error) {
        return theme_defaults();
    }
}

function theme_foreground(string $hex): string
{
    $channels = array_map(static function ($value) {
        $value = hexdec($value) / 255;
        return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }, str_split(substr($hex, 1), 2));
    $luminance = 0.2126*$channels[0] + 0.7152*$channels[1] + 0.0722*$channels[2];
    return $luminance > 0.179 ? '#000000' : '#ffffff';
}

function custom_theme_css(array $colors): string
{
    $colors = validate_theme_colors($colors);
    $mapping = ['background'=>'paper', 'surface'=>'card', 'text'=>'ink', 'muted'=>'muted', 'accent'=>'green', 'border'=>'line'];
    $css = '';
    foreach ($mapping as $key=>$variable) $css .= '--'.$variable.':'.$colors[$key].';';
    $css .= '--accent-ink:'.theme_foreground($colors['accent']).';';
    return '[data-theme="custom"]{color-scheme:'.(theme_foreground($colors['background']) === '#ffffff' ? 'dark' : 'light').';'.$css.'}';
}

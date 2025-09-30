<?php
function avatarPlaceholder(string $name, int $size = 120, string $bg = '#0B2A4A', string $fg = '#ffffff'): string
{
    $name = trim($name) !== '' ? $name : 'U';
    $initial = mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');

    $fontSize = (int) floor($size * 0.5);
    $half     = (int) floor($size / 2);
    $radius   = $half;

    $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="{$size}" height="{$size}" viewBox="0 0 {$size} {$size}">
            <defs>
                <clipPath id="round">
                    <circle cx="{$half}" cy="{$half}" r="{$radius}"/>
                </clipPath>
            </defs>
            <rect width="100%" height="100%" fill="{$bg}" clip-path="url(#round)"/>
            <text x="50%" y="50%" dy="0.35em" text-anchor="middle" font-family="Inter, Arial, Helvetica, sans-serif" 
                  font-size="{$fontSize}" font-weight="700" fill="{$fg}">{$initial}</text>
        </svg>
    SVG;

    return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
}

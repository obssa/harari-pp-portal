<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Photos of Harar shipped with the site (ADR-0065), from Wikimedia Commons
 * under open licences: each keeps its author, licence and source, which are
 * shown wherever it appears. Files: public/images/harar/{name}-{width}.{avif,webp,jpg}.
 */
final class HararPhotos
{
    /**
     * @var array<string, array{widths: list<int>, fallback: int, ratio: array{int, int}, author: string, licence: string, source: string}>
     */
    private const PHOTOS = [
        'gate' => ['widths' => [640, 1024, 1600], 'fallback' => 1024, 'ratio' => [1600, 1064], 'author' => 'Francisco Anzola', 'licence' => 'CC BY 3.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Harar_Gate_(211539305).jpeg'],
        'interior' => ['widths' => [640, 1024, 1400], 'fallback' => 1024, 'ratio' => [1400, 1012], 'author' => 'Sailko', 'licence' => 'CC BY 3.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Harar,_casa_tradizionale_(anisa)_00.jpg'],
        'panorama' => ['widths' => [640, 1024, 1600], 'fallback' => 1024, 'ratio' => [1600, 1200], 'author' => 'Giustino', 'licence' => 'CC BY 2.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Town_of_Harar_with_Citywall.jpg'],
        'alley' => ['widths' => [480, 748], 'fallback' => 748, 'ratio' => [748, 1024], 'author' => 'Rod Waddington', 'licence' => 'CC BY-SA 2.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Harar_Jugol,_Ethiopia_(14419898066).jpg'],
        'wall' => ['widths' => [640, 1024, 1600], 'fallback' => 1024, 'ratio' => [1600, 1067], 'author' => 'Sailko', 'licence' => 'CC BY 3.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Harar,_mura_presso_la_porta_di_buda_01.jpg'],
        'mosque' => ['widths' => [640, 1024], 'fallback' => 1024, 'ratio' => [1024, 681], 'author' => 'Elias Tesfaye (Shagiz Adonay)', 'licence' => 'CC BY-SA 4.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Grand_mosque_Jami_Mosque_or_Juma_Mesjid,_in_Harar_Built_in_12th_century.jpg'],
        'bureau' => ['widths' => [640, 1024, 1600], 'fallback' => 1024, 'ratio' => [1600, 884], 'author' => 'Bikilahussenadem', 'licence' => 'CC BY-SA 4.0', 'source' => 'https://commons.wikimedia.org/wiki/File:Harari_culture_and_Tourism_Buruea.jpg'],
    ];

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::PHOTOS);
    }

    /**
     * @return array{widths: list<int>, fallback: int, ratio: array{int, int}, author: string, licence: string, source: string}
     */
    public static function get(string $name): array
    {
        return self::PHOTOS[$name] ?? throw new InvalidArgumentException("No Harar photo called {$name}.");
    }

    public static function srcset(string $name, string $format): string
    {
        return implode(', ', array_map(
            fn (int $width): string => "/images/harar/{$name}-{$width}.{$format} {$width}w",
            self::get($name)['widths'],
        ));
    }

    public static function fallback(string $name): string
    {
        return "/images/harar/{$name}-".self::get($name)['fallback'].'.jpg';
    }

    /**
     * "Sailko, CC BY 3.0, Wikimedia Commons": the attribution the licences ask for.
     */
    public static function credit(string $name): string
    {
        $photo = self::get($name);

        return "{$photo['author']}, {$photo['licence']}, Wikimedia Commons";
    }
}

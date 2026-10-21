<?php

/*
| ADR-0061: under a long-running worker a static property outlives the
| request, so request state must never be kept in one. Filament classes
| use static properties for their configuration and are left out.
*/

/** Static properties allowed, with why. */
const ALLOWED_STATICS = [
    'app/Domain/Search/Support/SearchText.php' => '$map',            // a fixed transliteration table
    'app/Domain/Translation/Support/Locales.php' => '$defaultResolver', // set once at boot
];

it('keeps no request state in static properties', function () {
    $root = dirname(__DIR__, 2);
    $found = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app', FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        if ($file->getExtension() !== 'php' || str_starts_with($path, 'app/Filament/')) {
            continue;
        }
        preg_match_all('/^\s*(?:(?:public|protected|private|readonly)\s+)*static\s+(?:\??[\w\\\\|]+\s+)?(\$\w+)/m', (string) file_get_contents($file->getPathname()), $matches);
        foreach ($matches[1] as $property) {
            if ((ALLOWED_STATICS[$path] ?? null) !== $property) {
                $found[] = "{$path} {$property}";
            }
        }
    }

    expect($found)->toBe([]);
});

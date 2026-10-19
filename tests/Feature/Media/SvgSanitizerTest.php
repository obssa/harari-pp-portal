<?php

use App\Domain\Media\Exceptions\RejectedUpload;
use App\Domain\Media\Support\SvgSanitizer;

function cleanSvg(string $svg): string
{
    return app(SvgSanitizer::class)->sanitize($svg);
}

it('keeps ordinary drawing', function () {
    $clean = cleanSvg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><g fill="#3151ED"><rect width="5" height="5"/><path d="M0 0L10 10"/></g></svg>');

    expect($clean)->toContain('<rect', '<path', 'viewBox="0 0 10 10"', 'fill="#3151ED"', 'xmlns="http://www.w3.org/2000/svg"');
});

it('removes scripts, event handlers and javascript links', function () {
    $clean = cleanSvg(<<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" onload="alert(1)">
          <script>alert(2)</script>
          <a xlink:href="javascript:alert(3)"><rect width="5" height="5" onclick="alert(4)"/></a>
          <foreignObject><body xmlns="http://www.w3.org/1999/xhtml"><iframe src="https://evil.example"/></body></foreignObject>
          <use href="https://evil.example/sprite.svg#x"/>
          <image href="https://evil.example/track.png"/>
        </svg>
        SVG);

    expect($clean)->not->toContain('<script', 'onload', 'onclick', 'javascript:', 'foreignObject', '<use', 'evil.example')
        ->toContain('<rect');
});

it('strips a doctype before parsing', function () {
    $clean = cleanSvg(<<<'SVG'
        <?xml version="1.0"?>
        <!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
        <svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>
        SVG);

    expect($clean)->toContain('<rect')->not->toContain('DOCTYPE');
});

it('refuses SVGs that rely on entities (XML external entities)', function () {
    expect(fn () => cleanSvg(<<<'SVG'
        <?xml version="1.0"?>
        <!DOCTYPE svg [<!ENTITY secret SYSTEM "file:///etc/passwd">]>
        <svg xmlns="http://www.w3.org/2000/svg"><text>&secret;</text></svg>
        SVG))->toThrow(RejectedUpload::class);
});

it('adds the SVG namespace when it is missing', function () {
    expect(cleanSvg('<svg viewBox="0 0 1 1"><rect width="1" height="1"/></svg>'))
        ->toContain('xmlns="http://www.w3.org/2000/svg"');
});

it('refuses documents that are not an SVG', function (string $input) {
    expect(fn () => cleanSvg($input))->toThrow(RejectedUpload::class);
})->with([
    'html' => '<html><body><p>hi</p></body></html>',
    'garbage' => 'not xml at all',
    'oversized' => fn () => '<svg xmlns="http://www.w3.org/2000/svg">'.str_repeat('<g/>', 600_000).'</svg>',
]);

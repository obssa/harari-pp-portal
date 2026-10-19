<?php

use App\Domain\Content\Models\Category;
use App\Domain\Content\Support\Slugger;

it('writes Ethiopic titles in readable Latin letters', function (string $title, string $slug) {
    expect(app(Slugger::class)->slug($title))->toBe($slug);
})->with([
    ['አዲስ አበባ', 'adis-abeba'],
    ['ሐረር ጁጎል', 'harer-jugol'],
    ['ክልል', 'kilil'],
    ['የሴቶች ሊግ', 'yesetoch-lig'],
    ['እንኳን ደስ አላችሁ።', 'inikwan-des-alachihu'],
    ['ቋንቋ', 'qwaniqwa'],
    ['Paartii Badhaadhinaa Hararii', 'paartii-badhaadhinaa-hararii'],
    ['Town Hall Meeting!', 'town-hall-meeting'],
]);

it('reads Ethiopic numerals', function (string $title, string $slug) {
    expect(app(Slugger::class)->slug($title))->toBe($slug);
})->with([
    ['ጳጉሜን ፲፫', 'pagumen-13'],
    ['በ፳፻፲፱ ዓ.ም.', 'be-2019-am'],
    ['፻፳', '120'],
    ['፼', '10000'],
]);

it('limits slugs to a safe length', function () {
    expect(strlen(app(Slugger::class)->slug(str_repeat('ሀለሐመ ', 80))))->toBeLessThanOrEqual(Slugger::MAX_LENGTH);
});

it('makes slugs unique within one language only', function () {
    $first = new Category;
    $first->save();
    $first->putTranslation('en', ['name' => 'Youth']);
    $first->putTranslation('om', ['name' => 'Youth']);

    $second = new Category;
    $second->save();
    $second->putTranslation('en', ['name' => 'Youth']);

    expect($first->translation('en')?->slug)->toBe('youth')
        ->and($first->translation('om', fallback: false)?->slug)->toBe('youth')
        ->and($second->translation('en')?->slug)->toBe('youth-2');
});

it('uses a fallback slug when nothing readable is left', function () {
    expect(app(Slugger::class)->unique('!!!', 'category_translations', 'en', null, 'category'))->toBe('category');
});

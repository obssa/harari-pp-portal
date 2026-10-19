<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Models\Category;
use App\Domain\Content\Models\Tag;
use App\Domain\Sites\Models\Site;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

function category(array $names): Category
{
    $category = new Category;
    $category->save();
    foreach ($names as $locale => $name) {
        $category->putTranslation($locale, ['name' => $name]);
    }

    return $category->refresh();
}

it('follows the fallback chain: Harari to Amharic to English, Afaan Oromo to English', function () {
    $category = category(['en' => 'News', 'am' => 'ዜና']);

    expect($category->name('har'))->toBe('ዜና')
        ->and($category->name('om'))->toBe('News')
        ->and($category->name('am'))->toBe('ዜና')
        ->and($category->translation('har', fallback: false))->toBeNull()
        ->and($category->translatedLocales())->toBe(['en', 'am']);
});

it('uses the eager-loaded translations without extra queries', function () {
    category(['en' => 'News', 'am' => 'ዜና', 'om' => 'Oduu']);

    $loaded = Category::query()->withTranslation('om')->firstOrFail();
    DB::enableQueryLog();
    $name = $loaded->name('om');

    expect($name)->toBe('Oduu')
        ->and(DB::getQueryLog())->toBe([])
        ->and($loaded->translations->pluck('locale')->sort()->values()->all())->toBe(['en', 'om']);
});

it('finds records written in one exact language', function () {
    $amharic = category(['am' => 'ዜና']);
    category(['en' => 'Events']);

    expect(Category::query()->translatedIn('am')->pluck('id')->all())->toBe([$amharic->id]);
});

it('keeps the slug when the name changes, so links keep working', function () {
    $category = category(['en' => 'Youth League']);
    $category->putTranslation('en', ['name' => 'Youth and Students League']);

    expect($category->translation('en')?->slug)->toBe('youth-league');

    $category->putTranslation('en', ['slug' => 'youth-and-students']);
    expect($category->refresh()->translation('en')?->slug)->toBe('youth-and-students');
});

it('belongs to the current site and records who changed it', function () {
    $editor = staff(SystemRole::Editor);
    $this->actingAs($editor);

    $category = category(['en' => 'Statements']);
    $other = Site::query()->create(['key' => 'youth', 'name' => 'Youth League (sample)', 'domain' => 'youth.example.org']);

    expect($category->site_id)->toBe(Site::current()->id)
        ->and($category->created_by)->toBe($editor->id)
        ->and($category->updated_by)->toBe($editor->id);

    Site::setCurrent($other);
    expect(Category::query()->count())->toBe(0);
    Site::forgetCurrent();
    expect(Category::query()->count())->toBe(1);
});

it('audits names per language', function () {
    $tag = new Tag;
    $tag->save();
    $translation = $tag->putTranslation('am', ['name' => 'ምርጫ']);

    expect(Activity::query()->where('log_name', 'content')->where('subject_id', $translation->id)
        ->where('subject_type', $translation->getMorphClass())->exists())->toBeTrue();
});

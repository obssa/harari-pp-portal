<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Models\Category;
use App\Domain\Content\Models\Tag;
use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Filament\Resources\Tags\Pages\ManageTags;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;

use function Pest\Livewire\livewire;

it('lets editors open categories and tags, and keeps them from other staff', function (SystemRole $role, int $status) {
    $this->actingAs(staff($role));

    $this->get('/admin/categories')->assertStatus($status);
    $this->get('/admin/tags')->assertStatus($status);
})->with([
    'editor' => [SystemRole::Editor, 200],
    'publisher' => [SystemRole::Publisher, 200],
    'author (read only)' => [SystemRole::Author, 200],
    'membership officer' => [SystemRole::MembershipOfficer, 403],
]);

it('creates a category in several languages with Latin slugs', function () {
    $this->actingAs(staff(SystemRole::Editor));

    livewire(ManageCategories::class)
        ->callAction('create', data: [
            'sort' => 2,
            'translations' => [
                'en' => ['name' => 'Press releases'],
                'am' => ['name' => 'የፕሬስ መግለጫዎች', 'description' => 'ይፋዊ መግለጫዎች'],
                'om' => ['name' => '', 'slug' => ''],
            ],
        ])
        ->assertHasNoActionErrors();

    $category = Category::query()->with('translations')->firstOrFail();
    expect($category->translatedLocales())->toBe(['en', 'am'])
        ->and($category->translation('en')?->slug)->toBe('press-releases')
        ->and($category->translation('am')?->slug)->toBe('yepires-megilechawoch')
        ->and($category->sort)->toBe(2);
});

it('refuses a category without any name', function () {
    $this->actingAs(staff(SystemRole::Editor));

    livewire(ManageCategories::class)
        ->callAction('create', data: ['translations' => ['en' => ['name' => '  ']]])
        ->assertHasActionErrors(['translations.en.name']);

    expect(Category::query()->count())->toBe(0);
});

it('refuses a slug already used in the same language', function () {
    $this->actingAs(staff(SystemRole::Editor));
    $existing = new Category;
    $existing->save();
    $existing->putTranslation('en', ['name' => 'News']);

    livewire(ManageCategories::class)
        ->callAction('create', data: ['translations' => ['en' => ['name' => 'Latest', 'slug' => 'news']]])
        ->assertHasActionErrors(['translations.en.slug']);
});

it('edits names, removes an emptied language, and nests categories', function () {
    $this->actingAs(staff(SystemRole::Publisher));
    $parent = new Category;
    $parent->save();
    $parent->putTranslation('en', ['name' => 'News']);
    $child = new Category;
    $child->save();
    $child->putTranslation('en', ['name' => 'Regional']);
    $child->putTranslation('om', ['name' => 'Naannoo']);

    livewire(ManageCategories::class)
        ->callTableAction(EditAction::class, $child, data: [
            'parent_id' => $parent->id,
            'translations' => ['en' => ['name' => 'Regional news'], 'om' => ['name' => '']],
        ])
        ->assertHasNoTableActionErrors();

    $child->refresh();
    expect($child->parent_id)->toBe($parent->id)
        ->and($child->name('en'))->toBe('Regional news')
        ->and($child->translatedLocales())->toBe(['en']);
});

it('soft-deletes and restores tags', function () {
    $this->actingAs(staff(SystemRole::Editor));
    livewire(ManageTags::class)
        ->callAction('create', data: ['translations' => ['am' => ['name' => 'ምርጫ']]])
        ->assertHasNoActionErrors();
    $tag = Tag::query()->firstOrFail();

    livewire(ManageTags::class)->callTableAction(DeleteAction::class, $tag);
    expect(Tag::query()->count())->toBe(0)->and(Tag::withTrashed()->count())->toBe(1);
});

it('keeps authors from changing categories', function () {
    $author = staff(SystemRole::Author);

    expect($author->can('viewAny', Category::class))->toBeTrue()
        ->and($author->can('create', Category::class))->toBeFalse()
        ->and(staff(SystemRole::Editor)->can('create', Tag::class))->toBeTrue();
});

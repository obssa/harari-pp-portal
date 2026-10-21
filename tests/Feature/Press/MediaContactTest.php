<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Actions\SavePost;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Workflow\ContentWorkflow;
use App\Domain\Content\Workflow\Transition;
use App\Domain\Press\Actions\DistributePressRelease;
use App\Domain\Press\Actions\SaveMediaContact;
use App\Domain\Press\Mail\PressReleaseMail;
use App\Domain\Press\Models\MediaContact;
use App\Domain\Press\Models\PressDistribution;
use App\Domain\Privacy\Actions\ErasePersonalData;
use App\Domain\Privacy\Support\PersonalDataSearch;
use App\Filament\Resources\MediaContacts\MediaContactResource;
use App\Filament\Resources\MediaContacts\Pages\EditMediaContact;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Livewire\livewire;

function mediaContact(array $data = [], ?User $actor = null): MediaContact
{
    return app(SaveMediaContact::class)->handle(new MediaContact, [
        'name' => 'Sample Journalist', 'outlet' => 'Sample Radio', 'position' => 'Editor', 'email' => 'desk@example.org', 'languages' => ['am', 'en'],
        ...$data,
    ], $actor ?? staff(SystemRole::CommunicationsOfficer));
}

function pressRelease(array $publish = ['en', 'am'], string $type = 'press_release'): Post
{
    $publisher = staff(SystemRole::Publisher);
    $post = app(SavePost::class)->handle(new Post, ['type' => $type, 'translations' => [
        'en' => ['title' => 'Sample press release', 'excerpt' => 'Sample summary of the release.'],
        'am' => ['title' => 'የናሙና የፕሬስ መግለጫ', 'excerpt' => 'የናሙና ማጠቃለያ።'],
        'om' => ['title' => 'Ibsa miidiyaa fakkeenyaa', 'excerpt' => 'Cuunfaa fakkeenyaa.'],
    ]], $publisher);
    foreach ($publish as $locale) {
        app(ContentWorkflow::class)->apply($post, $locale, Transition::Publish, $publisher);
    }

    return $post->refresh();
}

beforeEach(fn () => Mail::fake());

it('keeps media contacts with their languages, one per email address', function () {
    $contact = mediaContact();
    expect($contact->email)->toBe('desk@example.org')->and($contact->token)->toHaveLength(24)
        ->and(json_encode((array) DB::table('media_contacts')->first()))->not->toContain('Sample Journalist')->not->toContain('desk@example.org');

    expect(fn () => mediaContact(['email' => 'DESK@example.org']))->toThrow(ValidationException::class, __('press.validation.duplicate'))
        ->and(fn () => mediaContact(['email' => 'other@example.org', 'languages' => []]))->toThrow(ValidationException::class)
        ->and(fn () => mediaContact(['email' => 'third@example.org'], staff(SystemRole::Editor)))->toThrow(HttpException::class);

    $this->actingAs(staff(SystemRole::CommunicationsOfficer));
    $this->get(MediaContactResource::getUrl('index'))->assertOk()->assertSee('Sample Radio')->assertSee('desk@example.org');
    livewire(EditMediaContact::class, ['record' => $contact->getRouteKey()])->assertSet('data.email', 'desk@example.org')
        ->fillForm(['outlet' => 'Sample Television'])->call('save')->assertHasNoFormErrors();
    expect($contact->refresh()->outlet)->toBe('Sample Television');
    $this->actingAs(staff(SystemRole::Editor))->get(MediaContactResource::getUrl('index'))->assertForbidden();
});

it('sends a published press release to each contact in the first of their languages it is published in', function () {
    $amharic = mediaContact();
    $oromo = mediaContact(['name' => 'Sample Reporter', 'email' => 'news@example.org', 'languages' => ['om']]);
    $gone = mediaContact(['email' => 'gone@example.org']);
    $gone->forceFill(['opted_out_at' => now()])->save();
    $post = pressRelease();

    $count = app(DistributePressRelease::class)->send($post, null, staff(SystemRole::CommunicationsOfficer));

    expect($count)->toBe(2)
        ->and(PressDistribution::query()->pluck('locale', 'media_contact_id')->all())->toEqual([$amharic->id => 'am', $oromo->id => 'en'])
        ->and(PressDistribution::query()->pluck('status')->unique()->all())->toBe([PressDistribution::SENT]);
    Mail::assertSent(PressReleaseMail::class, fn (PressReleaseMail $mail): bool => $mail->hasTo('desk@example.org') && $mail->translation->locale === 'am'
        && str_contains((string) $mail->headers()->text['List-Unsubscribe'], "/am/press/opt-out/{$amharic->token}"));
    Mail::assertSent(PressReleaseMail::class, 2);

    $mail = new PressReleaseMail($post->translations->firstWhere('locale', 'en'), $oromo);
    $mail->assertSeeInHtml('Sample summary of the release.')->assertSeeInHtml(route('news.show', ['locale' => 'en', 'slug' => 'sample-press-release']), false);

    expect(fn () => app(DistributePressRelease::class)->send(pressRelease([], 'press_release'), null, staff(SystemRole::CommunicationsOfficer)))->toThrow(ValidationException::class)
        ->and(DistributePressRelease::canSend(pressRelease(['en'], 'news')))->toBeFalse();
});

it('lets a journalist opt out by the link in the email', function () {
    $contact = mediaContact();
    $this->get("/en/press/opt-out/{$contact->token}")->assertOk()->assertSee(__('press.opt_out.ask'))->assertCookieMissing(config('session.cookie'));
    $this->post("/en/press/opt-out/{$contact->token}")->assertOk()->assertSee(__('press.opt_out.done'));
    expect($contact->refresh()->opted_out_at)->not->toBeNull();
    $this->get('/en/press/opt-out/'.str_repeat('a', 24))->assertNotFound();
});

it('sends from the post page and shows where the release went', function () {
    mediaContact();
    $post = pressRelease();
    $this->actingAs(staff(SystemRole::CommunicationsOfficer));

    livewire(EditPost::class, ['record' => $post->getRouteKey()])
        ->assertActionVisible('sendToMedia')
        ->callAction('sendToMedia', ['contacts' => []])->assertHasNoActionErrors();
    expect(PressDistribution::query()->count())->toBe(1);

    $this->actingAs(staff(SystemRole::Publisher));
    livewire(EditPost::class, ['record' => $post->getRouteKey()])->assertActionHidden('sendToMedia');
});

it('finds and removes media contacts in privacy requests', function () {
    mediaContact();
    $search = new PersonalDataSearch('desk@example.org', null);
    expect($search->counts())->toMatchArray(['media_contacts' => 1]);
    app(ErasePersonalData::class)->handle($search, staff(SystemRole::Admin));
    expect(MediaContact::query()->count())->toBe(0);
});

<?php

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Enums\SystemRole;
use App\Domain\Forms\Actions\HandleSubmission;
use App\Domain\Forms\Actions\SaveForm;
use App\Domain\Forms\Actions\SubmitForm;
use App\Domain\Forms\Enums\SubmissionStatus;
use App\Domain\Forms\Mail\NewSubmissionMail;
use App\Domain\Forms\Mail\SubmissionReceivedMail;
use App\Domain\Forms\Mail\SubmissionReplyMail;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Forms\Support\FormField;
use App\Domain\Forms\Support\WorkingDays;
use App\Domain\Organisation\Actions\SaveOffice;
use App\Domain\Organisation\Models\Office;
use App\Filament\Resources\Forms\Pages\CreateForm;
use App\Filament\Resources\Submissions\Pages\ViewSubmission;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Settings\SiteSettings;
use App\Support\SpamGuard;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\TestFiles;

use function Pest\Livewire\livewire;

/**
 * @param  array<string, mixed>  $data
 */
function makeForm(array $data = [], string $title = 'Sample feedback form'): Form
{
    return app(SaveForm::class)->handle(new Form, [
        'type' => 'feedback',
        'response_days' => 3,
        'translations' => ['en' => ['title' => $title, 'success_message' => 'Sample thanks', 'autoreply_subject' => 'Sample received', 'published' => true]],
        'fields' => [
            ['key' => 'topicfield', 'type' => FormField::SELECT, 'required' => true, 'label' => ['en' => 'Topic', 'am' => 'ርዕስ'], 'choices' => ['en' => "Roads\nWater", 'am' => "መንገድ\nውሃ"]],
            ['key' => 'messagefld', 'type' => FormField::TEXTAREA, 'required' => true, 'label' => ['en' => 'Your message']],
            ['key' => 'areasfield', 'type' => FormField::CHECKBOXES, 'label' => ['en' => 'Areas'], 'choices' => ['en' => "North\nSouth\nEast"]],
            ['key' => 'agreefield', 'type' => FormField::CONSENT, 'required' => true, 'label' => ['en' => 'I agree']],
        ],
        ...$data,
    ], staff(SystemRole::Publisher));
}

function sampleOffice(string $name, ?string $email = null): Office
{
    return app(SaveOffice::class)->handle(new Office, ['type' => 'woreda', 'email' => $email, 'translations' => ['en' => ['name' => $name, 'published' => true]]], staff(SystemRole::Publisher));
}

/**
 * @param  array<string, mixed>  $answers
 */
function submitSample(Form $form, array $answers = [], array $contact = []): FormSubmission
{
    return app(SubmitForm::class)->handle($form, [
        'name' => 'Sample Writer', 'email' => 'writer@example.org',
        'answers' => ['topicfield' => '1', 'messagefld' => 'Sample message', 'agreefield' => '1', ...$answers],
        ...$contact,
    ], [], 'en');
}

beforeEach(fn () => Mail::fake());

it('keeps each question with its key, labels and choices in every language', function () {
    $form = makeForm();
    $questions = $form->questions();

    expect($questions)->toHaveCount(4)
        ->and($questions[0]->key)->toBe('topicfield')
        ->and($questions[0]->labelFor('am'))->toBe('ርዕስ')
        ->and($questions[0]->labelFor('om'))->toBe('Topic')
        ->and($questions[0]->choicesFor('am'))->toBe(['መንገድ', 'ውሃ'])
        ->and($questions[2]->choicesFor('am'))->toBe(['North', 'South', 'East']);

    // A question without a usable key gets a new one.
    $other = makeForm(['fields' => [['key' => 'X', 'type' => 'text', 'label' => ['en' => 'Sample']]]], 'Sample other form');
    expect($other->questions()[0]->key)->toMatch('/^[a-z0-9]{10}$/');

    expect(fn () => makeForm(['fields' => [['type' => 'text', 'label' => []]]], 'Sample broken'))->toThrow(ValidationException::class)
        ->and(fn () => makeForm(['fields' => [['type' => 'select', 'label' => ['en' => 'Pick']]]], 'Sample broken'))->toThrow(ValidationException::class);
});

it('turns a sent form into an encrypted ticket with a random reference and an answer-by date', function () {
    $office = sampleOffice('Sample woreda office', 'woreda@example.org');
    $form = makeForm(['office_id' => $office->id]);

    $submission = submitSample($form, ['areasfield' => ['2', '0']]);

    expect($submission->reference)->toMatch('/^HPP-[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}$/')
        ->and($submission->office_id)->toBe($office->id)
        ->and($submission->status)->toBe(SubmissionStatus::New)
        ->and($submission->answers['topicfield'])->toBe(['index' => 1, 'text' => 'Water'])
        ->and($submission->answers['areasfield'])->toBe([['index' => 0, 'text' => 'North'], ['index' => 2, 'text' => 'East']])
        ->and($submission->due_at)->not->toBeNull();

    $raw = DB::table('form_submissions')->where('id', $submission->id)->first();
    expect($raw->answers)->not->toContain('Sample message')->and($raw->name)->not->toContain('Sample Writer');

    Mail::assertQueued(SubmissionReceivedMail::class, fn (SubmissionReceivedMail $mail): bool => $mail->hasTo('writer@example.org'));
    Mail::assertQueued(NewSubmissionMail::class, fn (NewSubmissionMail $mail): bool => $mail->hasTo('woreda@example.org'));

    expect(FormSubmission::normaliseReference(strtolower(str_replace('-', ' ', $submission->reference))))->toBe($submission->reference)
        ->and(FormSubmission::normaliseReference('nonsense'))->toBe('');
});

it('counts working days for the answer-by date', function () {
    // Friday afternoon in Addis Ababa plus 3 working days is Wednesday evening.
    $due = WorkingDays::after(CarbonImmutable::parse('2026-10-23 14:00', 'Africa/Addis_Ababa'), 3);

    expect($due->setTimezone('Africa/Addis_Ababa')->format('Y-m-d H:i'))->toBe('2026-10-28 23:59');
});

it('checks every answer against its question', function (array $answers, array $contact, string $field) {
    try {
        submitSample(makeForm(), $answers, $contact);
        $this->fail('No validation error');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }
})->with([
    'missing required' => [['messagefld' => ''], [], 'answers.messagefld'],
    'unknown choice' => [['topicfield' => '7'], [], 'answers.topicfield'],
    'bad checkbox' => [['areasfield' => ['9']], [], 'answers.areasfield'],
    'no consent' => [['agreefield' => ''], [], 'answers.agreefield'],
    'too long' => [['messagefld' => str_repeat('a', 5001)], [], 'answers.messagefld'],
    'no way to answer' => [[], ['email' => '', 'phone' => ''], 'email'],
    'bad phone' => [[], ['phone' => '12'], 'phone'],
]);

it('lets people write without their name when the form allows it', function () {
    $submission = submitSample(makeForm(['anonymous_allowed' => true]), [], ['name' => '', 'email' => '']);

    expect($submission->name)->toBeNull()->and($submission->email)->toBeNull();
    Mail::assertNotQueued(SubmissionReceivedMail::class);
});

it('keeps attachments on the private disk and refuses disguised files', function () {
    Storage::fake('local');
    $form = makeForm(['fields' => [['key' => 'cvfile1234', 'type' => FormField::FILE, 'required' => true, 'label' => ['en' => 'CV']]]], 'Sample vacancy form');

    $upload = new UploadedFile(TestFiles::pdf('cv'), 'my cv.pdf', 'application/pdf', null, true);
    $submission = app(SubmitForm::class)->handle($form, ['name' => 'Sample', 'email' => 'a@example.org', 'answers' => []], ['cvfile1234' => $upload], 'en');

    $file = $submission->files[0] ?? null;
    expect($file)->not->toBeNull()
        ->and($file['name'])->toBe('my cv.pdf')
        ->and($file['mime'])->toBe('application/pdf');
    Storage::disk('local')->assertExists($file['path']);

    $fake = new UploadedFile(TestFiles::text('<?php echo 1;', 'pdf'), 'cv.pdf', 'application/pdf', null, true);
    expect(fn () => app(SubmitForm::class)->handle($form, ['name' => 'Sample', 'email' => 'a@example.org'], ['cvfile1234' => $fake], 'en'))->toThrow(ValidationException::class);
});

it('takes the form on its page, turns robots away and shows the ticket status', function () {
    makeForm();

    $this->get('/en/forms/sample-feedback-form')->assertOk()
        ->assertSee('Your message')->assertSee('name="answers[topicfield]"', escape: false)
        ->assertSee('name="'.SpamGuard::HONEYPOT.'"', escape: false);

    $form = [
        'name' => 'Sample Visitor', 'email' => 'visitor@example.org',
        'answers' => ['topicfield' => '0', 'messagefld' => 'Sample road question', 'agreefield' => '1'],
        SpamGuard::STARTED => Crypt::encryptString((string) (time() - 10)),
    ];
    $this->post('/en/forms/sample-feedback-form', [...$form, SpamGuard::HONEYPOT => 'spam'])->assertSessionHasErrors('form');

    $response = $this->post('/en/forms/sample-feedback-form', $form);
    $submission = FormSubmission::query()->firstOrFail();
    $response->assertRedirect(route('tickets.show', ['locale' => 'en', 'reference' => $submission->reference]));

    $this->get(route('tickets.show', ['locale' => 'en', 'reference' => $submission->reference]))->assertOk()
        ->assertSee($submission->reference)
        ->assertSee(SubmissionStatus::New->label())
        ->assertDontSee('Sample road question')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    $this->get('/en/tickets?reference='.strtolower($submission->reference))
        ->assertRedirect(route('tickets.show', ['locale' => 'en', 'reference' => $submission->reference]));
    $this->get('/en/tickets?reference=HPP-2222-2222')->assertOk()->assertSee(__('forms.lookup_not_found', [], 'en'));
    $this->get('/en/tickets/HPP-2222-2222')->assertNotFound();
});

it('shows the contact page with its form, the hotline and the offices', function () {
    $settings = app(SiteSettings::class);
    $settings->hotline = '6016';
    $settings->save();
    sampleOffice('Sample contact woreda', 'contact-woreda@example.org');
    makeForm(['type' => 'contact', 'on_contact_page' => true], 'Sample contact form');

    $this->get('/en/contact')->assertOk()
        ->assertSee('Sample contact form')
        ->assertSee(route('forms.submit', ['locale' => 'en', 'slug' => 'sample-contact-form']), escape: false)
        ->assertSee('6016')
        ->assertSee('Sample contact woreda')->assertSee('contact-woreda@example.org');

    $this->get('/sitemaps/en.xml')->assertSee(url('/en/contact'), escape: false);
});

it('hides forms that are drafts or not taking answers', function () {
    makeForm(['translations' => ['en' => ['title' => 'Sample draft form']]], 'Sample draft form');
    makeForm(['active' => false], 'Sample closed form');

    $this->get('/en/forms/sample-draft-form')->assertNotFound();
    $this->get('/en/forms/sample-closed-form')->assertNotFound();
});

it('lets staff assign, answer, note and resolve tickets, timing the first answer', function () {
    $submission = submitSample(makeForm());
    $agent = staff(SystemRole::SupportAgent);
    $handle = app(HandleSubmission::class);

    $handle->assign($submission, $agent, staff(SystemRole::Publisher));
    expect($submission->refresh()->status)->toBe(SubmissionStatus::Assigned)->and($submission->assignee_id)->toBe($agent->id);

    $handle->note($submission, 'Sample internal note', $agent);
    expect($handle->reply($submission, 'Sample answer', $agent))->toBeTrue();
    Mail::assertQueued(SubmissionReplyMail::class, fn (SubmissionReplyMail $mail): bool => $mail->hasTo('writer@example.org') && $mail->text === 'Sample answer');

    $submission->refresh();
    expect($submission->status)->toBe(SubmissionStatus::InProgress)
        ->and($submission->first_response_at)->not->toBeNull()
        ->and($submission->isOverdue())->toBeFalse();

    $handle->status($submission, SubmissionStatus::Resolved, $agent);
    expect($submission->refresh()->resolved_at)->not->toBeNull()
        ->and($submission->notes()->pluck('kind')->all())->toBe(['status', 'reply', 'note', 'assign'])
        ->and(DB::table('submission_notes')->where('kind', 'note')->value('body'))->not->toContain('Sample internal note');

    $late = submitSample(makeForm([], 'Sample late form'));
    $late->forceFill(['due_at' => CarbonImmutable::now()->subDay()])->save();
    expect($late->isOverdue())->toBeTrue();
});

it('shows each office only its own tickets', function () {
    $north = sampleOffice('Sample north office');
    $south = sampleOffice('Sample south office');
    $northTicket = submitSample(makeForm(['office_id' => $north->id], 'Sample north form'));
    $southTicket = submitSample(makeForm(['office_id' => $south->id], 'Sample south form'));
    $branchTicket = submitSample(makeForm([], 'Sample branch form'));

    $agent = staff(SystemRole::SupportAgent);
    $agent->assignScopedRole(SystemRole::SupportAgent, ScopeType::Office, $north->id);
    $this->actingAs($agent->refresh());

    expect(SubmissionResource::getEloquentQuery()->pluck('id')->all())->toBe([$northTicket->id]);
    $this->get(SubmissionResource::getUrl('view', ['record' => $northTicket]))->assertOk()->assertSee('Sample message');
    $this->get(SubmissionResource::getUrl('view', ['record' => $southTicket]))->assertNotFound();
    expect(fn () => app(HandleSubmission::class)->reply($southTicket->refresh(), 'x', $agent))->toThrow(HttpException::class);

    $everywhere = staff(SystemRole::SupportAgent);
    $this->actingAs($everywhere);
    expect(SubmissionResource::getEloquentQuery()->count())->toBe(3);
    $this->get(SubmissionResource::getUrl('view', ['record' => $branchTicket]))->assertOk();
});

it('lets those who manage forms build them, and answers tickets from the inbox page', function () {
    $this->actingAs(staff(SystemRole::Author))->get('/admin/forms')->assertForbidden();
    $this->actingAs(staff(SystemRole::Author))->get('/admin/submissions')->assertForbidden();

    $this->actingAs(staff(SystemRole::Publisher));
    livewire(CreateForm::class)
        ->fillForm([
            'type' => 'petition',
            'response_days' => 5,
            'translations' => ['om' => ['title' => 'Iyyannoo fakkeenyaa', 'published' => true]],
            'fields' => [['key' => 'reasonfld1', 'type' => 'textarea', 'required' => true, 'label' => ['om' => 'Sababa']]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();
    $form = Form::query()->firstOrFail();
    expect($form->questions()[0]->labelFor('om'))->toBe('Sababa')->and($form->response_days)->toBe(5);

    $submission = app(SubmitForm::class)->handle($form, ['name' => 'Sample', 'email' => 'p@example.org', 'answers' => ['reasonfld1' => 'Sample reason']], [], 'om');
    livewire(ViewSubmission::class, ['record' => $submission->getRouteKey()])
        ->assertSee('Sample reason')
        ->callAction('reply', data: ['body' => 'Sample answer from the office', 'resolve' => true])
        ->assertHasNoActionErrors();

    expect($submission->refresh()->status)->toBe(SubmissionStatus::Resolved);
    Mail::assertQueued(SubmissionReplyMail::class);
});

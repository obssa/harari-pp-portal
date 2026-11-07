<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Membership\Actions\ApplyForMembership;
use App\Domain\Membership\Enums\ApplicationStatus;
use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Organisation\Actions\SaveOffice;
use App\Domain\Organisation\Models\Office;
use App\Services\Sms\SendSms;
use App\Support\SpamGuard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** The answers of the first step, as a person fills them in. */
function aboutAnswers(array $overrides = []): array
{
    return [
        'name' => 'Sample Applicant', 'sex' => 'female', 'birth_calendar' => 'gregorian',
        'birth_day' => '14', 'birth_month_gregorian' => '3', 'birth_year' => '1995',
        'id_number' => '1234 5678 9012 3456', 'education' => 'degree', 'occupation' => 'Sample teacher',
        SpamGuard::STARTED => Crypt::encryptString((string) (time() - 10)),
        ...$overrides,
    ];
}

function sampleWoreda(): Office
{
    return app(SaveOffice::class)->handle(new Office, ['type' => 'woreda', 'translations' => ['en' => ['name' => 'Sample Woreda 1', 'published' => true]]], staff(SystemRole::Publisher));
}

/** The code the last SMS carried. */
function lastCode(): string
{
    $code = '';
    Bus::assertDispatchedSync(SendSms::class, function (SendSms $sms) use (&$code): bool {
        if (preg_match('/\b(\d{6})\b/', $sms->text, $m) === 1) {
            $code = $m[1];
        }

        return true;
    });

    return $code;
}

beforeEach(fn () => Bus::fake([SendSms::class]));

it('takes an application step by step and submits it once the phone is confirmed', function () {
    $woreda = sampleWoreda();

    $this->get('/en/membership/apply')->assertOk()->assertSee(__('membership.step.about'))
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

    $this->post('/en/membership/apply/about', aboutAnswers())->assertRedirect(route('membership.apply', ['locale' => 'en', 'step' => 'place']));
    $this->post('/en/membership/apply/place', ['woreda_id' => $woreda->id, 'phone' => '0911 23 45 67', 'email' => 'Applicant@Example.org'])
        ->assertRedirect(route('membership.apply', ['locale' => 'en', 'step' => 'confirm']));
    $this->get('/en/membership/apply')->assertSee('Sample Applicant')->assertSee('+251911234567')->assertSee('Sample Woreda 1');

    $this->post('/en/membership/apply/code', ['consent' => '1'])->assertSessionHasNoErrors();
    $code = lastCode();
    expect($code)->toMatch('/^\d{6}$/');

    $this->post('/en/membership/apply/verify', ['code' => $code])->assertRedirect(route('membership.done', ['locale' => 'en']));
    $application = MembershipApplication::query()->sole();
    $this->get('/en/membership/done')->assertOk()->assertSee($application->reference);

    expect($application->status)->toBe(ApplicationStatus::Submitted)
        ->and($application->phone_verified_at)->not->toBeNull()
        ->and($application->birth_year)->toBe(1995)
        ->and($application->id_number)->toBe('1234567890123456')
        ->and($application->id_type)->toBe('fayda')
        ->and($application->email)->toBe('applicant@example.org')
        ->and($application->phone_hash)->toBe(MembershipApplication::hash('+251911234567'))
        ->and($application->code_hash)->toBeNull();

    $raw = (array) DB::table('membership_applications')->first();
    expect(json_encode($raw))->not->toContain('Sample Applicant')->not->toContain('1234567890123456')->not->toContain('251911234567');
    Bus::assertDispatched(SendSms::class, fn (SendSms $sms): bool => str_contains($sms->text, $application->reference));
});

it('accepts a birth date in the Ethiopian calendar and refuses applicants under 18', function () {
    $apply = app(ApplyForMembership::class);

    $application = $apply->about(null, aboutAnswers(['birth_calendar' => 'ethiopian', 'birth_day' => '1', 'birth_month_ethiopian' => '1', 'birth_year' => '1990']), 'am');
    expect($application->birth_date)->toBe('1997-09-11');
    // Older clients that send a single birth_month are still read.
    expect($apply->about(null, aboutAnswers(['birth_calendar' => 'ethiopian', 'birth_month_gregorian' => null, 'birth_day' => '1', 'birth_month' => '1', 'birth_year' => '1990']), 'am')->birth_date)->toBe('1997-09-11');

    $young = CarbonImmutable::now()->subYears(17);
    expect(fn () => $apply->about(null, aboutAnswers(['birth_day' => (string) $young->day, 'birth_month_gregorian' => (string) $young->month, 'birth_year' => (string) $young->year]), 'en'))
        ->toThrow(ValidationException::class, __('membership.validation.age', ['age' => 18]));
});

it('says exactly what is wrong with each answer of the first step (ADR-0076)', function (array $answers, string $field, string $message) {
    try {
        app(ApplyForMembership::class)->about(null, aboutAnswers($answers), 'en');
        $this->fail('The answers were accepted.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field)
            ->and($e->errors()[$field][0])->toBe($message);
    }
})->with([
    'empty name' => [['name' => '  '], 'name', 'Write your full name.'],
    'name with digits' => [['name' => 'Sample 2 Applicant'], 'name', 'Please enter a valid name without numbers or unsupported special characters.'],
    'name with signs' => [['name' => 'Sample @pplicant!'], 'name', 'Please enter a valid name without numbers or unsupported special characters.'],
    'empty Fayda' => [['id_number' => ''], 'id_number', 'Enter your 16-digit Fayda number.'],
    'Fayda with letters' => [['id_number' => '1234 5678 ABCD 3456'], 'id_number', 'The Fayda number has digits only: remove letters and other signs.'],
    'short Fayda' => [['id_number' => '1234 5678 9012'], 'id_number', 'Fayda ID must contain exactly 16 digits (you entered 12).'],
    'long Fayda' => [['id_number' => '1234 5678 9012 3456 7'], 'id_number', 'Fayda ID must contain exactly 16 digits (you entered 17).'],
    'no month' => [['birth_month_gregorian' => ''], 'birth_date', 'Choose the day, month and year of your birth.'],
    '31 April' => [['birth_day' => '31', 'birth_month_gregorian' => '4'], 'birth_date', 'Please select a valid date of birth for the chosen calendar: April 1995 has 30 days.'],
    '29 February 1995' => [['birth_day' => '29', 'birth_month_gregorian' => '2'], 'birth_date', 'Please select a valid date of birth for the chosen calendar: February 1995 has 28 days.'],
    'Pagume in the Gregorian calendar' => [['birth_day' => '3', 'birth_month_gregorian' => '13'], 'birth_date', 'Choose the day, month and year of your birth.'],
    'Pagume 6 in a common year' => [['birth_calendar' => 'ethiopian', 'birth_day' => '6', 'birth_month_ethiopian' => '13', 'birth_year' => '1980'], 'birth_date', 'Please select a valid date of birth for the chosen calendar: Pagume 1980 has 5 days.'],
    'a year far back' => [['birth_year' => '1850'], 'birth_date', 'Write the year in the Gregorian calendar, between '.((int) date('Y') - 120).' and '.date('Y').'.'],
    'a year to come' => [['birth_year' => (string) ((int) date('Y') + 1)], 'birth_date', 'Write the year in the Gregorian calendar, between '.((int) date('Y') - 120).' and '.date('Y').'.'],
]);

it('accepts names in every script, Pagume 6 in a leap year, and Fayda numbers as people group them', function () {
    $apply = app(ApplyForMembership::class);

    foreach (['ሳምፕል አመልካች', "Sample Gammachuu Dheeressa'a", 'Sample-Anne O’Neil', 'H.E Sample Person'] as $name) {
        expect($apply->about(null, aboutAnswers(['name' => $name]), 'en')->name)->toBe($name);
    }
    // 1979 E.C. is the year before the Gregorian leap year 1988.
    expect($apply->about(null, aboutAnswers(['birth_calendar' => 'ethiopian', 'birth_day' => '6', 'birth_month_ethiopian' => '13', 'birth_year' => '1979']), 'am')->birth_date)->toBe('1987-09-11')
        ->and($apply->about(null, aboutAnswers(['id_number' => '1234-5678-9012-3456']), 'en')->id_hash)->toBe(MembershipApplication::hash('fayda:1234567890123456'))
        // Whatever the visitor posts, the form registers a Fayda number only.
        ->and($apply->about(null, aboutAnswers(['id_type' => 'passport']), 'en')->id_type)->toBe('fayda');
});

it('says what is wrong with the mobile number (ADR-0076)', function (string $phone, string $message) {
    $application = app(ApplyForMembership::class)->about(null, aboutAnswers(), 'en');

    expect(fn () => app(ApplyForMembership::class)->place($application, ['address_note' => 'Sample street', 'phone' => $phone]))
        ->toThrow(ValidationException::class, $message);
})->with([
    'empty' => ['', 'Enter your mobile number.'],
    'letters' => ['0911 abc 567', 'Please enter numbers only in the mobile phone field.'],
    'signs' => ['0911-234-567', 'Please enter numbers only in the mobile phone field.'],
    'too short' => ['12', 'Enter a mobile number such as 0911 234 567 or +251 911 234 567.'],
]);

it('shows each calendar its own months and asks for the Fayda number only', function () {
    $html = (string) $this->get('/en/membership/apply')->assertOk()->getContent();

    // Pagume is offered once: in the Ethiopian months, never among the Gregorian ones.
    expect(substr_count($html, __('calendar.ethiopian.month.13')))->toBe(1)
        ->and($html)->toContain('name="birth_month_ethiopian"', 'name="birth_month_gregorian"', __('membership.field.fayda'), 'inputmode="numeric"')
        ->and($html)->not->toContain('name="id_type"', 'Meskerem / January');
});

it('needs a woreda or an address, and a mobile number', function () {
    $application = app(ApplyForMembership::class)->about(null, aboutAnswers(), 'en');

    expect(fn () => app(ApplyForMembership::class)->place($application, ['phone' => '0911234567']))->toThrow(ValidationException::class)
        ->and(fn () => app(ApplyForMembership::class)->place($application, ['address_note' => 'Sample street', 'phone' => '12']))->toThrow(ValidationException::class)
        ->and(app(ApplyForMembership::class)->place($application, ['address_note' => 'Sample street', 'phone' => '0911234567'])->phone)->toBe('+251911234567');
});

it('limits code attempts, resends and the life of a code', function () {
    $apply = app(ApplyForMembership::class);
    $application = $apply->place($apply->about(null, aboutAnswers(), 'en'), ['address_note' => 'Sample street', 'phone' => '0911234567']);

    expect(fn () => $apply->sendCode($application, false))->toThrow(ValidationException::class);
    $apply->sendCode($application, true);
    $code = lastCode();
    expect(fn () => $apply->sendCode($application->refresh(), true))->toThrow(ValidationException::class, __('membership.validation.wait', ['seconds' => 60]));

    foreach (range(1, ApplyForMembership::CODE_ATTEMPTS) as $attempt) {
        expect(fn () => $apply->verify($application->refresh(), '000000'))->toThrow(ValidationException::class);
    }
    expect(fn () => $apply->verify($application->refresh(), $code))->toThrow(ValidationException::class);

    $this->travel(2)->minutes();
    $apply->sendCode($application->refresh(), true);
    $fresh = lastCode();
    $this->travel(11)->minutes();
    expect(fn () => $apply->verify($application->refresh(), $fresh))->toThrow(ValidationException::class);
});

it('stops a robot on the first step', function () {
    $this->post('/en/membership/apply/about', aboutAnswers([SpamGuard::STARTED => Crypt::encryptString((string) time())]))
        ->assertSessionHasErrors('form');
    $this->post('/en/membership/apply/about', aboutAnswers([SpamGuard::HONEYPOT => 'http://spam.example']))->assertSessionHasErrors('form');

    expect(MembershipApplication::query()->count())->toBe(0);
});

it('shows the status to whoever has the reference and the phone number, and to no one else', function () {
    $apply = app(ApplyForMembership::class);
    $application = $apply->place($apply->about(null, aboutAnswers(), 'en'), ['address_note' => 'Sample street', 'phone' => '0911234567']);

    $this->post('/en/membership/status', ['reference' => $application->reference, 'phone' => '0911234567'])->assertOk()
        ->assertSee(__('membership.status.not_found'));

    $apply->sendCode($application, true);
    $apply->verify($application->refresh(), lastCode());

    $this->post('/en/membership/status', ['reference' => strtolower(str_replace('-', ' ', $application->reference)), 'phone' => '+251 911 234 567'])
        ->assertOk()->assertSee(__('membership.status.submitted'))
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    $this->post('/en/membership/status', ['reference' => $application->reference, 'phone' => '0922000000'])->assertSee(__('membership.status.not_found'));
});

it('forgets unfinished applications after a week and closed ones after the retention period', function () {
    $apply = app(ApplyForMembership::class);
    $unfinished = $apply->about(null, aboutAnswers(), 'en');
    $recent = $apply->about(null, aboutAnswers(), 'en');
    $rejected = $apply->about(null, aboutAnswers(), 'en');
    $approved = $apply->about(null, aboutAnswers(), 'en');
    DB::table('membership_applications')->where('id', $unfinished->id)->update(['updated_at' => now()->subDays(8)]);
    DB::table('membership_applications')->where('id', $rejected->id)->update(['status' => 'rejected', 'decided_at' => now()->subDays(200)]);
    DB::table('membership_applications')->where('id', $approved->id)->update(['status' => 'approved', 'decided_at' => now()->subDays(400)]);

    $this->artisan('membership:purge-applications')->assertSuccessful();

    expect(MembershipApplication::query()->pluck('id')->sort()->values()->all())->toBe(collect([$recent->id, $approved->id])->sort()->values()->all());
});

it('invites people to apply from the get involved page', function () {
    $this->get('/en/get-involved')->assertOk()
        ->assertSee(route('membership.apply', ['locale' => 'en']), escape: false)
        ->assertSee(route('membership.status', ['locale' => 'en']), escape: false);
});

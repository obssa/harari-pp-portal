<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Membership\Enums\ApplicationStatus;
use App\Domain\Membership\Mail\ApplicationCodeMail;
use App\Domain\Membership\Mail\ApplicationReceivedMail;
use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Organisation\Actions\SaveOffice;
use App\Domain\Organisation\Models\Office;
use App\Filament\Pages\ManageMembershipSettings;
use App\Services\Sms\SendSms;
use App\Settings\MembershipSettings;
use App\Support\SpamGuard;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

/** The first step's answers (as in ApplicationTest, kept apart so its tests do not run twice). */
function verifyAbout(): array
{
    return [
        'name' => 'Sample Applicant', 'sex' => 'female', 'birth_calendar' => 'gregorian',
        'birth_day' => '14', 'birth_month' => '3', 'birth_year' => '1995',
        'id_type' => 'fayda', 'id_number' => '1234 5678 9012', 'education' => 'degree', 'occupation' => 'Sample teacher',
        SpamGuard::STARTED => Crypt::encryptString((string) (time() - 10)),
    ];
}

function verifyWoreda(): Office
{
    return app(SaveOffice::class)->handle(new Office, ['type' => 'woreda', 'translations' => ['en' => ['name' => 'Sample Woreda 1', 'published' => true]]], staff(SystemRole::Publisher));
}

function useVerification(string $method): void
{
    $settings = app(MembershipSettings::class);
    $settings->verification = $method;
    $settings->save();
}

beforeEach(function () {
    Bus::fake([SendSms::class]);
    Mail::fake();
});

it('sends the code by email when applications are confirmed by email, with the phone optional', function () {
    useVerification(MembershipSettings::EMAIL);
    $woreda = verifyWoreda();

    $this->post('/en/membership/apply/about', verifyAbout());
    $this->post('/en/membership/apply/place', ['woreda_id' => $woreda->id, 'phone' => ''])->assertSessionHasErrors('email');
    $this->post('/en/membership/apply/place', ['woreda_id' => $woreda->id, 'phone' => '', 'email' => 'applicant@example.org'])
        ->assertSessionHasNoErrors();
    $this->get('/en/membership/apply')->assertSee(__('membership.help.send_code_email', ['email' => 'applicant@example.org']));

    $this->post('/en/membership/apply/code', ['consent' => '1'])->assertSessionHasNoErrors();
    $code = '';
    Mail::assertSent(ApplicationCodeMail::class, function (ApplicationCodeMail $mail) use (&$code): bool {
        $code = $mail->code;

        return $mail->hasTo('applicant@example.org');
    });
    Bus::assertNotDispatchedSync(SendSms::class);

    $this->post('/en/membership/apply/verify', ['code' => $code])->assertRedirect(route('membership.done', ['locale' => 'en']));
    $application = MembershipApplication::query()->sole();
    expect($application->status)->toBe(ApplicationStatus::Submitted)
        ->and($application->email_verified_at)->not->toBeNull()
        ->and($application->phone_verified_at)->toBeNull()
        ->and($application->phone)->toBeNull();
    Mail::assertQueued(ApplicationReceivedMail::class, fn (ApplicationReceivedMail $mail): bool => $mail->reference === $application->reference);

    // The status page finds it by the email address.
    $this->post('/en/membership/status', ['reference' => $application->reference, 'phone' => 'applicant@example.org'])
        ->assertSee(ApplicationStatus::Submitted->label());
});

it('submits without a code when none is asked for', function () {
    useVerification(MembershipSettings::NONE);
    $woreda = verifyWoreda();

    $this->post('/en/membership/apply/about', verifyAbout());
    $this->post('/en/membership/apply/place', ['woreda_id' => $woreda->id, 'phone' => '0911 23 45 67']);
    $this->get('/en/membership/apply')->assertSee(route('membership.submit', ['locale' => 'en']), escape: false)
        ->assertDontSee(__('membership.send_code'));

    $this->post('/en/membership/apply/code', ['consent' => '1'])->assertSessionHasErrors('form');
    $this->post('/en/membership/apply/submit', [])->assertSessionHasErrors('consent');
    $this->post('/en/membership/apply/submit', ['consent' => '1'])->assertRedirect(route('membership.done', ['locale' => 'en']));

    $application = MembershipApplication::query()->sole();
    expect($application->status)->toBe(ApplicationStatus::Submitted)
        ->and($application->phone_verified_at)->toBeNull()
        ->and($application->consented_at)->not->toBeNull();
    Bus::assertNotDispatchedSync(SendSms::class);
});

it('keeps the SMS code by default and refuses a direct submit then', function () {
    expect(MembershipSettings::verification())->toBe(MembershipSettings::SMS);
    $woreda = verifyWoreda();

    $this->post('/en/membership/apply/about', verifyAbout());
    $this->post('/en/membership/apply/place', ['woreda_id' => $woreda->id, 'phone' => ''])->assertSessionHasErrors('phone');
    $this->post('/en/membership/apply/place', ['woreda_id' => $woreda->id, 'phone' => '0911 23 45 67']);
    $this->post('/en/membership/apply/submit', ['consent' => '1'])->assertSessionHasErrors('code');
    expect(MembershipApplication::query()->sole()->status)->toBe(ApplicationStatus::Started);
});

it('lets a Super Admin choose the method, shows it to Admins and keeps it from others', function () {
    $this->actingAs(staff(SystemRole::Admin, mfa: false))->get('/admin/settings/membership')->assertOk();

    $this->actingAs(staff(SystemRole::SuperAdmin, mfa: false));
    livewire(ManageMembershipSettings::class)
        ->fillForm(['verification' => MembershipSettings::NONE])
        ->call('save')
        ->assertHasNoFormErrors();
    expect(MembershipSettings::verification())->toBe(MembershipSettings::NONE);

    $this->actingAs(staff(SystemRole::Author, mfa: false))->get('/admin/settings/membership')->assertForbidden();
});

<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Donations\Actions\RecordDonation;
use App\Domain\Donations\Enums\DonationStatus;
use App\Domain\Donations\Models\Donation;
use App\Domain\Privacy\Actions\ErasePersonalData;
use App\Domain\Privacy\Actions\ExportPersonalData;
use App\Domain\Privacy\Support\PersonalDataSearch;
use App\Filament\Pages\ManageDonations;
use App\Filament\Resources\Donations\Pages\ListDonations;
use App\Filament\Resources\Donations\Pages\ViewDonation;
use App\Services\Sms\SendSms;
use App\Settings\DonationSettings;
use App\Support\SpamGuard;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

function openDonations(): void
{
    $settings = app(DonationSettings::class);
    $settings->enabled = true;
    $settings->accounts = [['bank' => 'Sample Bank (test)', 'name' => 'Sample party account', 'number' => '1000000000000']];
    $settings->instructions = ['en' => 'Sample instructions: transfer within a week.'];
    $settings->save();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function pledge(array $overrides = []): array
{
    return [
        'name' => 'Sample Donor', 'phone' => '0911 55 66 77', 'email' => '', 'amount' => '500', 'purpose' => 'Sample purpose',
        'consent' => '1', SpamGuard::STARTED => Crypt::encryptString((string) (time() - 10)), ...$overrides,
    ];
}

beforeEach(fn () => Bus::fake([SendSms::class]));

it('has no donate page while donations are off', function () {
    expect(app(DonationSettings::class)->enabled)->toBeFalse();
    $this->get('/en/donate')->assertNotFound();
    $this->post('/en/donate', pledge())->assertNotFound();
    expect(Donation::query()->count())->toBe(0);
});

it('takes a pledge and shows the reference with the bank accounts', function () {
    openDonations();
    $this->get('/en/donate')->assertOk()->assertSee(__('donations.submit'));

    $this->post('/en/donate', pledge())->assertRedirect(route('donations.thanks', ['locale' => 'en']));
    $donation = Donation::query()->sole();

    $this->get('/en/donate/thanks')->assertOk()
        ->assertSee($donation->reference)
        ->assertSee('1000000000000')
        ->assertSee('Sample instructions: transfer within a week.');

    expect($donation->status)->toBe(DonationStatus::Pledged)
        ->and($donation->amount)->toBe('500.00')
        ->and($donation->currency)->toBe('ETB')
        ->and($donation->method)->toBe('bank_transfer')
        ->and($donation->reference)->toMatch('/^DON-[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}$/')
        ->and(DB::table('donations')->value('name'))->not->toContain('Sample');
    Bus::assertDispatched(SendSms::class, fn (SendSms $sms): bool => $sms->to === '+251911556677' && str_contains($sms->text, $donation->reference));
});

it('checks the pledge', function (array $overrides, string $field) {
    openDonations();
    $this->post('/en/donate', pledge($overrides))->assertSessionHasErrors($field);
    expect(Donation::query()->count())->toBe(0);
})->with([
    'below the minimum' => [['amount' => '5'], 'amount'],
    'no phone or email' => [['phone' => '', 'email' => ''], 'phone'],
    'a wrong phone' => [['phone' => '12'], 'phone'],
    'no consent' => [['consent' => ''], 'consent'],
    'no name' => [['name' => ''], 'name'],
]);

it('lets staff record the payment with a numbered receipt, or cancel', function () {
    openDonations();
    $this->post('/en/donate', pledge());
    $this->post('/en/donate', pledge(['name' => 'Sample Second Donor', 'phone' => '', 'email' => 'second@example.org']));
    [$first, $second] = Donation::query()->orderBy('created_at')->orderBy('id')->get()->all();
    $this->actingAs(staff(SystemRole::Admin));

    livewire(ListDonations::class)->assertOk()->assertCanSeeTableRecords([$first, $second])->assertDontSee('Sample Donor');

    livewire(ViewDonation::class, ['record' => $first->getRouteKey()])
        ->callAction('receive', data: ['received_amount' => '500', 'received_on' => now()->toDateString(), 'payment_reference' => 'FT-SAMPLE-1'])
        ->assertHasNoActionErrors()
        ->assertNotified(__('donations.admin.received', ['receipt' => 'RCPT-000001']));
    expect($first->refresh()->status)->toBe(DonationStatus::Received)
        ->and($first->receipt_no)->toBe('RCPT-000001')
        ->and($first->received_amount)->toBe('500.00');
    Bus::assertDispatched(SendSms::class, fn (SendSms $sms): bool => str_contains($sms->text, 'RCPT-000001'));

    livewire(ViewDonation::class, ['record' => $second->getRouteKey()])
        ->callAction('cancel', data: ['reason' => ''])->assertHasActionErrors(['reason']);
    livewire(ViewDonation::class, ['record' => $second->getRouteKey()])
        ->callAction('cancel', data: ['reason' => 'Sample: the donor changed their mind'])->assertHasNoActionErrors();
    expect($second->refresh()->status)->toBe(DonationStatus::Cancelled)->and($second->receipt_no)->toBeNull();

    livewire(ViewDonation::class, ['record' => $first->getRouteKey()])
        ->assertActionHidden('receive')->assertActionHidden('cancel')->assertActionVisible('receipt');
});

it('prints the receipt for staff who may see donations, in the donor\'s language', function () {
    openDonations();
    $this->post('/am/donate', pledge());
    $donation = Donation::query()->sole();

    $this->actingAs(staff(SystemRole::Admin));
    $this->get(route('donations.receipt', ['donation' => $donation]))->assertNotFound();

    app(RecordDonation::class)->receive($donation, '450', now()->toDateString(), null, staff(SystemRole::Admin));
    $receipt = $this->get(route('donations.receipt', ['donation' => $donation]))->assertOk()
        ->assertSee('RCPT-000001')
        ->assertSee('450.00 ETB')
        ->assertSee(__('donations.receipt.title', [], 'am'));
    expect((string) $receipt->headers->get('X-Robots-Tag'))->toContain('noindex')
        ->and((string) $receipt->headers->get('Cache-Control'))->toContain('no-store');

    $this->actingAs(staff(SystemRole::Editor))->get(route('donations.receipt', ['donation' => $donation]))->assertForbidden();
});

it('keeps donations and their settings to the right staff', function () {
    $this->actingAs(staff(SystemRole::Editor))->get('/admin/donations')->assertForbidden();
    $this->actingAs(staff(SystemRole::Admin))->get('/admin/donations')->assertOk();
    $this->actingAs(staff(SystemRole::Admin))->get(ManageDonations::getUrl())->assertForbidden();
    $this->actingAs(staff(SystemRole::SuperAdmin))->get(ManageDonations::getUrl())->assertOk();
});

it('finds, copies and erases a donor\'s details but keeps the amounts and receipts', function () {
    openDonations();
    $this->post('/en/donate', pledge());
    $donation = Donation::query()->sole();
    app(RecordDonation::class)->receive($donation, '500', now()->toDateString(), null, staff(SystemRole::Admin));

    $search = new PersonalDataSearch(null, '0911556677');
    expect($search->counts()['donations'])->toBe(1);
    $export = app(ExportPersonalData::class)->handle($search, staff(SystemRole::Admin));
    expect($export['donations'][0])->toMatchArray(['reference' => $donation->reference, 'name' => 'Sample Donor', 'receipt_no' => 'RCPT-000001']);

    app(ErasePersonalData::class)->handle($search, staff(SystemRole::Admin));
    $donation->refresh();
    expect($donation->name)->toBe(ErasePersonalData::ERASED)
        ->and([$donation->phone, $donation->phone_hash, $donation->email])->toBe([null, null, null])
        ->and($donation->erased_at)->not->toBeNull()
        ->and($donation->received_amount)->toBe('500.00')
        ->and($donation->receipt_no)->toBe('RCPT-000001')
        ->and((new PersonalDataSearch(null, '0911556677'))->counts()['donations'])->toBe(0);
});

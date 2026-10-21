<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\Post;
use App\Domain\Import\Actions\RunImport;
use App\Domain\Import\Models\ContentImport;
use App\Domain\Import\Models\ContentSource;
use App\Domain\Import\Support\MessageGroups;
use App\Domain\Import\Support\StoryText;
use App\Domain\Import\Support\TelegramChannelReader;
use App\Domain\Media\Models\Media;
use App\Filament\Resources\ContentSources\Pages\ManageContentSources;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

/**
 * A channel preview page in Telegram's markup with sample messages.
 *
 * @param  list<array{id: int, at: string, text?: string, photos?: list<string>, video?: bool, channel?: string}>  $messages
 */
function telegramPage(string $channel, array $messages): string
{
    $html = '<html><body><section class="tgme_channel_history">';
    foreach ($messages as $m) {
        $owner = $m['channel'] ?? $channel;
        $html .= '<div class="tgme_widget_message_wrap js-widget_message_wrap"><div class="tgme_widget_message text_not_supported_wrap js-widget_message" data-post="'.$owner.'/'.$m['id'].'">';
        $html .= '<div class="tgme_widget_message_bubble">';
        if (($m['photos'] ?? []) !== []) {
            $html .= '<div class="tgme_widget_message_grouped_wrap">';
            foreach ($m['photos'] as $url) {
                $html .= '<a class="tgme_widget_message_photo_wrap grouped_media_wrap" href="https://t.me/x" style="width:100px;background-image:url(\''.$url.'\')"></a>';
            }
            $html .= '</div>';
        }
        if ($m['video'] ?? false) {
            $html .= '<a class="tgme_widget_message_video_player" href="#"><i class="tgme_widget_message_video_thumb"></i></a>';
        }
        if (isset($m['text'])) {
            $html .= '<div class="tgme_widget_message_text js-message_text" dir="auto">'.str_replace("\n", '<br/>', e($m['text'])).'</div>';
        }
        $html .= '<div class="tgme_widget_message_footer"><a class="tgme_widget_message_date" href="#"><time datetime="'.$m['at'].'" class="time">10:00</time></a></div>';
        $html .= '</div></div></div>';
    }

    return $html.'</section></body></html>';
}

function sampleJpeg(): string
{
    $image = imagecreatetruecolor(320, 200);
    imagefill($image, 0, 0, (int) imagecolorallocate($image, 40, 80, 200));
    ob_start();
    imagejpeg($image, null, 85);

    return (string) ob_get_clean();
}

const BRANCH_STORY = "Sample council meeting reviews the first quarter\n\nThe sample council met today to review the plan of the first quarter and agreed next steps.\nSecond line of the same paragraph.\n\nA closing paragraph about the sample meeting.";

function fakeChannels(?string $branch = null, ?string $office = null): void
{
    Http::fake([
        't.me/s/hrprosperityparty*' => Http::response($branch ?? telegramPage('hrprosperityparty', [
            ['id' => 101, 'at' => '2026-10-15T10:00:00+00:00', 'text' => BRANCH_STORY],
            ['id' => 102, 'at' => '2026-10-15T10:00:08+00:00', 'photos' => ['https://cdn4.telesco.pe/file/one.jpg', 'https://cdn4.telesco.pe/file/two.jpg', 'https://evil.example.org/x.jpg']],
            ['id' => 103, 'at' => '2026-10-15T11:00:00+00:00', 'text' => "Waltajjiin hojii naannoo kanaa irratti geggeeffame\n\nKun fakkeenya qofa fi hojii bara kanaa ilaala."],
            ['id' => 104, 'at' => '2026-10-15T15:00:00+00:00', 'photos' => ['https://cdn4.telesco.pe/file/three.jpg']],
            ['id' => 999, 'at' => '2026-10-15T16:00:00+00:00', 'text' => 'Another channel', 'channel' => 'someoneelse'],
        ])),
        't.me/s/HarariGovernmentCommunication*' => Http::response($office ?? telegramPage('HarariGovernmentCommunication', [
            ['id' => 501, 'at' => '2026-10-15T12:00:00+00:00', 'text' => BRANCH_STORY],
            ['id' => 502, 'at' => '2026-10-15T13:00:00+00:00', 'text' => "Sample road works report from the office\n\nWork continues on the sample road.", 'video' => true],
        ])),
        'cdn4.telesco.pe/*' => Http::response(sampleJpeg(), 200, ['Content-Type' => 'image/jpeg']),
    ]);
}

it('reads messages, their text, photos and videos from the preview page', function () {
    fakeChannels();
    $messages = app(TelegramChannelReader::class)->page('hrprosperityparty');

    expect(array_map(fn ($m) => $m->id, $messages))->toBe([101, 102, 103, 104])
        ->and($messages[0]->text)->toBe(BRANCH_STORY)
        ->and($messages[1]->photos)->toBe(['https://cdn4.telesco.pe/file/one.jpg', 'https://cdn4.telesco.pe/file/two.jpg'])
        ->and($messages[0]->url())->toBe('https://t.me/hrprosperityparty/101');

    expect(fn () => app(TelegramChannelReader::class)->page('../etc'))->toThrow(RuntimeException::class);
});

it('joins albums to the story posted with them and leaves lone albums out', function () {
    fakeChannels();
    $groups = MessageGroups::build(app(TelegramChannelReader::class)->page('hrprosperityparty'));

    expect($groups['stories'])->toHaveCount(2)
        ->and($groups['stories'][0]['media'][0]->id)->toBe(102)
        ->and(array_map(fn ($m) => $m->id, $groups['leftovers']))->toBe([104]);
});

it('splits a message into title, summary and paragraphs and tells languages apart', function () {
    $story = StoryText::split("“Sample headline”\n\nFirst paragraph.\nWith a line.\n\nSecond.");

    expect($story['title'])->toBe('Sample headline')
        ->and($story['paragraphs'])->toBe(["First paragraph.\nWith a line.", 'Second.'])
        ->and($story['summary'])->toBe('First paragraph. With a line.')
        ->and(StoryText::language('የናሙና ዜና', 'am'))->toBe('am')
        ->and(StoryText::language('Hojii naannoo kanaa irratti fi bara kana', 'am'))->toBe('om')
        ->and(StoryText::language('A sample report in English', 'am'))->toBe('en')
        ->and(StoryText::similarity(StoryText::fingerprint(BRANCH_STORY), StoryText::fingerprint(BRANCH_STORY)))->toBe(1.0);
});

it('imports new stories with their source and photos, once; the branch publishes its own at once', function () {
    fakeChannels();
    $report = app(RunImport::class)->handle();

    expect($report['hrprosperityparty'])->toMatchArray(['imported' => 2, 'duplicates' => 0, 'skipped' => 1, 'error' => null])
        ->and($report['HarariGovernmentCommunication'])->toMatchArray(['imported' => 1, 'duplicates' => 1]);

    $post = Post::query()->where('source_url', 'https://t.me/hrprosperityparty/101')->firstOrFail();
    $translation = $post->translation('en', fallback: false);
    expect($translation?->title)->toBe('Sample council meeting reviews the first quarter')
        ->and($translation?->status)->toBe(ContentStatus::Published)
        ->and($translation?->published_at?->toIso8601String())->toBe('2026-10-15T10:00:00+00:00')
        ->and($post->source_name)->toBe('Prosperity Party Harari Region Branch')
        ->and($post->source_published_at?->toIso8601String())->toBe('2026-10-15T10:00:00+00:00')
        ->and($post->featuredImage?->credit)->toBe('Prosperity Party Harari Region Branch')
        ->and(collect($translation?->body['content'] ?? [])->firstWhere('type', 'customBlock')['attrs']['config']['media_ids'] ?? [])->toHaveCount(1)
        ->and(Media::query()->count())->toBe(2);

    expect(Post::query()->where('source_url', 'https://t.me/hrprosperityparty/103')->firstOrFail()->source_locale)->toBe('om')
        ->and(ContentImport::query()->where('external_id', 'HarariGovernmentCommunication/501')->value('status'))->toBe(ContentImport::DUPLICATE)
        ->and(ContentSource::query()->where('handle', 'hrprosperityparty')->value('cursor'))->toBe(104);

    // ADR-0063: the communication office's stories still wait for a person.
    $office = Post::query()->where('source_url', 'https://t.me/HarariGovernmentCommunication/502')->firstOrFail();
    expect($office->translations()->firstOrFail()->status)->toBe(ContentStatus::Draft);

    $again = app(RunImport::class)->handle();
    expect($again['hrprosperityparty']['imported'])->toBe(0)->and(Post::query()->count())->toBe(3);
});

it('skips a story the same channel posts again', function () {
    fakeChannels(office: telegramPage('HarariGovernmentCommunication', [
        ['id' => 601, 'at' => '2026-10-16T09:00:00+00:00', 'text' => "Sample health services story\n\nThe sample office reports progress on health services this year."],
        ['id' => 602, 'at' => '2026-10-16T13:00:00+00:00', 'text' => "Sample health services story\n\nThe sample office reports progress on health services this year."],
    ]));

    $report = app(RunImport::class)->handle();

    expect($report['HarariGovernmentCommunication'])->toMatchArray(['imported' => 1, 'duplicates' => 1])
        ->and(ContentImport::query()->where('external_id', 'HarariGovernmentCommunication/602')->value('status'))->toBe(ContentImport::DUPLICATE);
});

it('keeps the error of a failing source without stopping the others', function () {
    Http::fake([
        't.me/s/hrprosperityparty*' => Http::response('down', 502),
        't.me/s/HarariGovernmentCommunication*' => Http::response(telegramPage('HarariGovernmentCommunication', [
            ['id' => 7, 'at' => '2026-10-15T12:00:00+00:00', 'text' => 'Sample office note'],
        ])),
    ]);

    $report = app(RunImport::class)->handle();

    expect($report['hrprosperityparty']['error'])->toContain('502')
        ->and(ContentSource::query()->where('handle', 'hrprosperityparty')->value('last_error'))->toContain('502')
        ->and($report['HarariGovernmentCommunication']['imported'])->toBe(1);
});

it('runs from the command line and on a schedule', function () {
    fakeChannels();

    $this->artisan('content:import', ['--source' => 'hrprosperityparty'])->assertSuccessful();
    $this->artisan('content:import', ['--source' => 'unknown'])->assertFailed();
    $this->artisan('schedule:list')->expectsOutputToContain('content:import');

    expect(Post::query()->count())->toBe(2);
});

it('lets admins manage sources and import now, and keeps sources from publishers', function () {
    fakeChannels();
    $this->actingAs(staff(SystemRole::Publisher))->get('/admin/content-sources')->assertForbidden();

    $this->actingAs(staff(SystemRole::Admin));
    $this->get('/admin/content-sources')->assertOk()->assertSee('Harari Government Communication Affairs Office');

    $source = ContentSource::query()->where('handle', 'hrprosperityparty')->firstOrFail();
    livewire(ManageContentSources::class)->callTableAction('import', $source)->assertHasNoTableActionErrors();

    expect(Post::query()->count())->toBe(2);
});

it('shows the source under a published imported story', function () {
    fakeChannels();
    app(RunImport::class)->handle();
    $post = Post::query()->where('source_url', 'https://t.me/hrprosperityparty/101')->firstOrFail();

    $this->get('/en/news/'.$post->translation('en')?->slug)
        ->assertOk()
        ->assertSee('href="https://t.me/hrprosperityparty/101"', escape: false)
        ->assertSee('Prosperity Party Harari Region Branch');
});

it('recognises Harari text in Ethiopic script', function () {
    expect(StoryText::language('ናሙና ኪም ሐላ ዚቅቲቤ ዪነብራዩ', 'am'))->toBe('har')
        ->and(StoryText::language('ኢንጅነር ናሙና ዚያድ የመንገድ ስራዎችን ገመገሙ', 'am'))->toBe('am')
        ->and(StoryText::looksHarari('የናሙና ዜና ርዕስ'))->toBeFalse();
});

it('publishes imported drafts on request, moving Harari stories and leaving chosen ones as drafts', function () {
    staff(SystemRole::SuperAdmin);
    ContentSource::query()->update(['auto_publish' => false]);
    fakeChannels(branch: telegramPage('hrprosperityparty', [
        ['id' => 301, 'at' => '2026-10-15T10:00:00+00:00', 'text' => "የናሙና ዜና ርዕስ\n\nየናሙና ዜና ጽሑፍ።"],
        ['id' => 302, 'at' => '2026-10-15T10:00:05+00:00', 'photos' => ['https://cdn4.telesco.pe/file/one.jpg']],
        ['id' => 303, 'at' => '2026-10-15T11:00:00+00:00', 'text' => "ሌላ የናሙና ዜና\n\nየናሙና ጽሑፍ።"],
        ['id' => 304, 'at' => '2026-10-15T12:00:00+00:00', 'text' => "ሦስተኛ የናሙና ዜና\n\nየናሙና ጽሑፍ።"],
    ]), office: telegramPage('HarariGovernmentCommunication', []));
    app(RunImport::class)->handle();

    $harari = Post::query()->where('source_url', 'https://t.me/hrprosperityparty/301')->firstOrFail();
    $skipped = Post::query()->where('source_url', 'https://t.me/hrprosperityparty/304')->firstOrFail();

    $this->artisan('portal:publish-imported', ['--dry-run' => true])->expectsOutputToContain('would publish')->assertSuccessful();
    expect(Post::query()->whereHas('translations', fn ($q) => $q->where('status', 'published'))->count())->toBe(0);

    $this->artisan('portal:publish-imported', ['--harari' => [$harari->id], '--skip' => [$skipped->id]])->assertSuccessful();

    $harari->refresh();
    expect($harari->translation('har', fallback: false)?->status)->toBe(ContentStatus::Published)
        ->and($harari->translation('am', fallback: false))->toBeNull()
        ->and($harari->source_locale)->toBe('har')
        ->and($harari->featuredImage?->alt)->toHaveKey('har')
        ->and(Post::query()->where('source_url', 'https://t.me/hrprosperityparty/303')->firstOrFail()->translation('am', fallback: false)?->status)->toBe(ContentStatus::Published)
        ->and($skipped->translation('am', fallback: false)?->status)->toBe(ContentStatus::Draft);

    $this->get('/har/news/'.$harari->translation('har')?->slug)->assertOk()->assertSee('የናሙና ዜና ርዕስ');
});

it('refuses to publish without a Super Admin', function () {
    $this->artisan('portal:publish-imported')->assertFailed();
});

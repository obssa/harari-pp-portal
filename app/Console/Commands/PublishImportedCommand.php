<?php

namespace App\Console\Commands;

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Workflow\ContentWorkflow;
use App\Domain\Content\Workflow\Transition;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Publishes the stories imported from the channels that are still drafts
 * (ADR-0022), as a Super Admin, after a person has decided they may go
 * live. Stories written in Harari can be moved from the Amharic tab first;
 * others can be left as drafts. `--dry-run` shows what would happen.
 */
final class PublishImportedCommand extends Command
{
    protected $signature = 'portal:publish-imported
        {--dry-run : Only list what would be published}
        {--harari=* : Post ids written in Harari, moved from the Amharic tab before publishing}
        {--skip=* : Post ids to leave as drafts}
        {--as= : Email of the Super Admin who publishes (default: the first one)}';

    protected $description = 'Publish the imported stories that are still drafts';

    public function handle(ContentWorkflow $workflow): int
    {
        $actor = $this->actor();
        if ($actor === null) {
            $this->error('No Super Admin found to publish as.');

            return self::FAILURE;
        }
        Auth::setUser($actor);
        $dry = (bool) $this->option('dry-run');
        $skip = array_map('strval', (array) $this->option('skip'));

        foreach (array_map('strval', (array) $this->option('harari')) as $id) {
            $post = Post::query()->find($id);
            if ($post === null) {
                $this->warn("No post {$id}.");

                continue;
            }
            $this->line(($dry ? 'would move' : 'moved')." to Harari: {$id}");
            if (! $dry) {
                $this->moveToHarari($post);
            }
        }

        $failed = false;
        $posts = Post::query()->whereNotNull('source_url')->whereNotIn('id', $skip)->orderBy('source_published_at')->get();
        foreach ($posts as $post) {
            foreach ($post->translations()->where('status', ContentStatus::Draft->value)->get() as $translation) {
                $url = route('news.show', ['locale' => $translation->locale, 'slug' => $translation->slug]);
                if ($dry) {
                    $this->line("would publish {$url}");

                    continue;
                }
                try {
                    $workflow->apply($post->fresh() ?? $post, $translation->locale, Transition::Publish, $actor);
                    $this->line("published {$url}");
                } catch (Throwable $e) {
                    $failed = true;
                    $this->error("not published {$post->id} ({$translation->locale}): {$e->getMessage()}");
                }
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function actor(): ?User
    {
        $email = (string) $this->option('as');
        $query = User::role('super_admin')->orderBy('id');

        return $email !== '' ? $query->where('email', $email)->first() : $query->first();
    }

    private function moveToHarari(Post $post): void
    {
        $translation = $post->translations()->where('locale', 'am')->first();
        if ($translation === null || $post->translations()->where('locale', 'har')->exists()) {
            return;
        }

        $translation->locale = 'har';
        $translation->save();
        $post->source_locale = 'har';
        $post->save();

        $photo = $post->featuredImage;
        if ($photo !== null && isset($photo->alt['am'])) {
            $alt = $photo->alt;
            $alt['har'] = $alt['am'];
            unset($alt['am']);
            $photo->forceFill(['alt' => $alt])->save();
        }
    }
}

<?php

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class RecordsItsQueue implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function handle(): void
    {
        Cache::put('ran-on-'.$this->queue, true);
    }
}

it('drains every portal queue, media included, and then stops', function () {
    config(['queue.default' => 'database', 'cache.default' => 'array']);

    RecordsItsQueue::dispatch()->onQueue('media');
    RecordsItsQueue::dispatch()->onQueue('default');
    expect(DB::table('jobs')->count())->toBe(2);

    $this->artisan('portal:work-queues', ['--max-time' => 20, '--memory' => 4096])->assertSuccessful();

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(Cache::get('ran-on-media'))->toBeTrue()
        ->and(Cache::get('ran-on-default'))->toBeTrue();
});

it('stops at the memory limit without failing and leaves the rest for the next run', function () {
    config(['queue.default' => 'database', 'cache.default' => 'array']);

    RecordsItsQueue::dispatch()->onQueue('default');
    RecordsItsQueue::dispatch()->onQueue('default');

    $this->artisan('portal:work-queues', ['--max-time' => 20, '--memory' => 1])->assertSuccessful();

    expect(DB::table('jobs')->count())->toBe(1);
});

it('drains the queues listed in the configuration', function () {
    expect(config('portal.queues'))->toBe(['default', 'media'])
        ->and(config('media-library.queue_name'))->toBe('media');
});

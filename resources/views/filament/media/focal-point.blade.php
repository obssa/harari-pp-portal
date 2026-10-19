@php
    /** @var \App\Domain\Media\Models\Media|null $media */
    $media = $getRecord();
    $preview = $media?->adminPreviewUrl() ?? $media?->getUrl();
@endphp

@if ($preview)
    <div
        x-data="{
            x: $wire.$entangle('data.focal_x', true),
            y: $wire.$entangle('data.focal_y', true),
            pick(event) {
                const box = event.currentTarget.getBoundingClientRect();
                this.x = Math.min(100, Math.max(0, Math.round((event.clientX - box.left) / box.width * 100)));
                this.y = Math.min(100, Math.max(0, Math.round((event.clientY - box.top) / box.height * 100)));
            },
        }"
    >
        <div
            x-on:click="pick($event)"
            style="position: relative; display: inline-block; cursor: crosshair; max-width: 100%;"
        >
            <img
                src="{{ $preview }}"
                alt="{{ $media->altFor() }}"
                style="display: block; max-width: 100%; max-height: 22rem; border-radius: 0.5rem;"
            >
            <span
                aria-hidden="true"
                x-bind:style="`left: ${x}%; top: ${y}%`"
                style="position: absolute; width: 1.25rem; height: 1.25rem; margin: -0.625rem 0 0 -0.625rem; border: 2px solid #fff; border-radius: 9999px; background: rgb(49 81 237 / 0.75); box-shadow: 0 0 0 1px rgb(0 0 0 / 0.4); pointer-events: none;"
            ></span>
        </div>
        <p style="margin-top: 0.5rem; font-size: 0.875rem; opacity: 0.75;">
            {{ __('admin.media.focal.click') }}
        </p>
    </div>
@endif

@php($editing = $review->exists)

<x-admin-layout :title="$editing ? 'Edit review' : 'Add testimonial'">
    <x-admin.header :title="$editing ? 'Edit review' : 'Add testimonial'" :back="route('admin.reviews.index')" />

    <form method="POST" action="{{ $editing ? route('admin.reviews.update', $review) : route('admin.reviews.store') }}" class="admin-card space-y-5 p-5">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-3">
            <x-admin.input name="author_name" label="Name" :value="$review->author_name ?? $review->user?->name" :required="true" maxlength="120" />
            <x-admin.input name="country" label="Country" :value="$review->country" maxlength="80" />
            <x-admin.select name="rating" label="Rating" :options="[5 => '★★★★★ 5', 4 => '★★★★ 4', 3 => '★★★ 3', 2 => '★★ 2', 1 => '★ 1']" :value="$review->rating" :required="true" />
        </div>
        <x-admin.textarea name="comment" label="Comment" :value="$review->comment" rows="5" maxlength="2000" />
        <x-admin.checkbox name="is_approved" label="Approved (show on the site)" :checked="(bool) $review->is_approved" />
        @if ($review->trip_id)
            <p class="text-sm text-slate-600">Written for trip #{{ $review->trip_id }}.</p>
        @endif
        <x-admin.form-actions :cancel="route('admin.reviews.index')" />
    </form>
</x-admin-layout>

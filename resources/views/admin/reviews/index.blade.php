<x-admin-layout title="Reviews">
    <x-admin.header title="Reviews & testimonials" subtitle="Approved reviews can appear on the Home page testimonials carousel.">
        <a href="{{ route('admin.reviews.create') }}" class="btn btn-primary">Add testimonial</a>
    </x-admin.header>

    <x-admin.filters :action="route('admin.reviews.index')" placeholder="Name or comment">
        <x-admin.filter-select name="status" label="Status" :options="['pending' => 'Waiting for approval', 'approved' => 'Approved']" />
    </x-admin.filters>

    <div class="admin-card overflow-x-auto">
        <table class="admin-table">
            <thead><tr><th>Author</th><th>Rating</th><th>Comment</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($reviews as $review)
                    <tr>
                        <td><span class="font-semibold text-slate-900">{{ $review->author_name ?? $review->user?->name }}</span><div class="text-xs text-slate-500">{{ $review->country }} · {{ $review->created_at?->format('d M Y') }}</div></td>
                        <td class="whitespace-nowrap text-accent-600" aria-label="{{ $review->rating }} out of 5">{{ str_repeat('★', $review->rating) }}<span class="text-slate-300">{{ str_repeat('★', 5 - $review->rating) }}</span></td>
                        <td class="max-w-md text-xs">{{ Str::limit($review->comment, 160) }}</td>
                        <td><x-admin.status-badge :status="$review->is_approved ? 'approved' : 'pending'" /></td>
                        <td class="space-x-3 whitespace-nowrap text-end">
                            <form method="POST" action="{{ route('admin.reviews.approve', $review) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="text-sm font-medium text-primary-700 hover:underline">{{ $review->is_approved ? 'Hide' : 'Approve' }}</button>
                            </form>
                            <a href="{{ route('admin.reviews.edit', $review) }}" class="text-sm font-medium text-primary-700 hover:underline">Edit</a>
                            <x-admin.delete-form :action="route('admin.reviews.destroy', $review)" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-admin.empty message="No reviews yet. Travellers can review completed trips (phase 10)." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $reviews->links() }}</div>
</x-admin-layout>

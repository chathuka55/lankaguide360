@php($editing = $faq->exists)

<x-admin-layout :title="$editing ? 'Edit FAQ' : 'Add FAQ'">
    <x-admin.header :title="$editing ? 'Edit FAQ' : 'Add FAQ'" :back="route('admin.faqs.index')" />

    <form method="POST" action="{{ $editing ? route('admin.faqs.update', $faq) : route('admin.faqs.store') }}" class="admin-card space-y-5 p-5">
        @csrf
        @if ($editing) @method('PUT') @endif
        <x-admin.input name="question" label="Question" :value="$faq->question" :required="true" maxlength="255" />
        <x-admin.textarea name="answer" label="Answer" :value="$faq->answer" :required="true" rows="5" maxlength="3000" />
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2"><x-admin.input name="keywords" label="Keywords" :value="$faq->keywords" maxlength="255" help="Extra words people might use, e.g. visa eta permit." /></div>
            <x-admin.input name="sort_order" label="Order" type="number" min="0" :value="$faq->sort_order" />
        </div>
        <x-admin.checkbox name="is_active" label="Active" :checked="(bool) $faq->is_active" />
        <x-admin.form-actions :cancel="route('admin.faqs.index')" />
    </form>
</x-admin-layout>

{{-- Credit, license and rights fields for admin photo uploads / URL imports. $prefix keeps ids unique. --}}
<div class="grid gap-3 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="{{ $prefix }}-alt" class="form-label">Alt text (describe the photo) <span class="text-red-600">*</span></label>
        <input id="{{ $prefix }}-alt" name="alt" value="{{ old('alt') }}" required maxlength="250" class="form-control">
        @error('alt')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="{{ $prefix }}-author" class="form-label">Owner / credit <span class="text-red-600">*</span></label>
        <input id="{{ $prefix }}-author" name="author" value="{{ old('author') }}" required maxlength="250" placeholder="Photographer or hotel name" class="form-control">
        @error('author')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="{{ $prefix }}-license" class="form-label">License <span class="text-red-600">*</span></label>
        <select id="{{ $prefix }}-license" name="license" required class="form-control">
            @foreach ($licenses as $license)
                <option value="{{ $license }}" @selected(old('license') === $license)>{{ $license }}</option>
            @endforeach
        </select>
        @error('license')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="{{ $prefix }}-license-url" class="form-label">License or permission link</label>
        <input id="{{ $prefix }}-license-url" type="url" name="license_url" value="{{ old('license_url') }}" placeholder="https://…" class="form-control">
        @error('license_url')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="{{ $prefix }}-caption" class="form-label">Caption</label>
        <input id="{{ $prefix }}-caption" name="caption" value="{{ old('caption') }}" maxlength="500" class="form-control">
    </div>
    <div class="sm:col-span-2">
        <label class="inline-flex items-start gap-2 text-sm text-slate-700">
            <input type="checkbox" name="rights" value="1" required class="form-check mt-0.5">
            <span>I have the right to use this image on the LankaGuide360 website.</span>
        </label>
        @error('rights')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
</div>

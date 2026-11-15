@if (session('status'))
    <div role="status" class="alert alert-success flex items-start gap-3 mb-6">
        <x-glyph name="check" class="mt-1 text-success" />
        <p>{{ session('status') }}</p>
    </div>
@endif

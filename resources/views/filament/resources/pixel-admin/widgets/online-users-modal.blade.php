<div class="space-y-4">
    @if ($users->isEmpty())
        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-6 text-sm text-gray-600">
            Nenhum usuário online nos últimos 5 minutos.
        </div>
    @else
        <div class="space-y-3">
            @foreach ($users as $user)
                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
                    <div class="text-sm font-semibold text-gray-900">{{ $user->name }}</div>
                    <div class="text-sm text-gray-600">{{ $user->email }}</div>
                </div>
            @endforeach
        </div>

        <div class="pt-2">
            {{ $users->links() }}
        </div>
    @endif
</div>

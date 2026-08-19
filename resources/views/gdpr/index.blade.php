<x-app-layout>
    <div class="max-w-3xl mx-auto py-8 px-4 space-y-8">
        <div>
            <h1 class="text-2xl font-bold text-[var(--stone)]">Privacy &amp; Data</h1>
            <p class="text-sm text-[var(--sand)] mt-1">
                Request a copy of the personal data this platform stores about you,
                or permanently delete your account.
            </p>
        </div>

        @if (session('status') === 'gdpr-export-requested')
            <div class="rounded-lg border border-green-700/40 bg-green-900/20 px-4 py-3 text-sm text-green-300">
                Export requested. You will receive an email when it is ready to download.
            </div>
        @elseif (session('status') === 'gdpr-export-pending')
            <div class="rounded-lg border border-yellow-700/40 bg-yellow-900/20 px-4 py-3 text-sm text-yellow-300">
                An export request is already in progress.
            </div>
        @elseif (session('status') === 'gdpr-export-expired')
            <div class="rounded-lg border border-yellow-700/40 bg-yellow-900/20 px-4 py-3 text-sm text-yellow-300">
                That download link has expired. Request a new export below.
            </div>
        @endif

        <!-- Export -->
        <div class="rounded-xl border border-white/10 bg-white/5 p-6">
            <h2 class="text-lg font-semibold text-[var(--stone)]">Export my data</h2>
            <p class="text-sm text-[var(--sand)] mt-1 mb-4">
                Generates a JSON file with your profile, team memberships, operator
                fatigue history, and activity trail. Download links expire after 7 days.
            </p>
            <form method="POST" action="{{ route('gdpr.export') }}">
                @csrf
                <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium">
                    Request data export
                </button>
            </form>
        </div>

        <!-- Request history -->
        <div class="rounded-xl border border-white/10 bg-white/5 p-6">
            <h2 class="text-lg font-semibold text-[var(--stone)] mb-4">Request history</h2>
            @if ($requests->isEmpty())
                <p class="text-sm text-[var(--sand)]">No privacy requests yet.</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wider text-[var(--sand)]">
                            <th class="py-2 pr-4">Type</th>
                            <th class="py-2 pr-4">Status</th>
                            <th class="py-2 pr-4">Requested</th>
                            <th class="py-2">Download</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $gdprRequest)
                            <tr class="border-t border-white/10 text-[var(--stone)]">
                                <td class="py-2 pr-4">{{ ucfirst($gdprRequest->type) }}</td>
                                <td class="py-2 pr-4">{{ ucfirst($gdprRequest->status) }}</td>
                                <td class="py-2 pr-4">{{ $gdprRequest->created_at->format('Y-m-d H:i') }}</td>
                                <td class="py-2">
                                    @if ($gdprRequest->type === 'export' && $gdprRequest->status === 'completed' && $gdprRequest->download_token && ! $gdprRequest->isExpired())
                                        <a href="{{ route('gdpr.download', $gdprRequest->download_token) }}" class="text-blue-400 hover:underline">Download</a>
                                    @else
                                        <span class="text-[var(--sand)]">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <!-- Delete account -->
        <div class="rounded-xl border border-red-700/40 bg-red-900/10 p-6">
            <h2 class="text-lg font-semibold text-red-300">Delete my account</h2>
            <p class="text-sm text-[var(--sand)] mt-1 mb-4">
                Permanently deletes your account, teams you own, and your personal data.
                This cannot be undone. Type <span class="font-mono font-semibold">DELETE</span> to confirm.
            </p>
            <form method="POST" action="{{ route('gdpr.delete') }}" class="flex items-center gap-3">
                @csrf
                <input type="text" name="confirmation" placeholder="DELETE"
                       class="rounded-lg bg-transparent border border-white/20 px-3 py-2 text-sm text-[var(--stone)] placeholder-[var(--sand)]/50" />
                <button type="submit" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-medium">
                    Delete my account
                </button>
            </form>
            @error('confirmation')
                <p class="text-sm text-red-400 mt-2">Type DELETE (exactly) to confirm account deletion.</p>
            @enderror
        </div>
    </div>
</x-app-layout>

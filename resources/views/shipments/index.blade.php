<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('出荷指示一覧') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('success'))
                <div class="rounded-md bg-green-50 border border-green-200 text-green-800 px-4 py-3" role="status">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div class="rounded-md bg-red-50 border border-red-200 text-red-800 px-4 py-3" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            {{-- 検索フォーム --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4">
                    <form method="GET" action="{{ route('shipments.index') }}" class="flex items-center gap-3">
                        <label for="q" class="text-sm font-medium text-gray-700">{{ __('検索') }}</label>
                        <input type="text" name="q" id="q" value="{{ request('q') }}"
                               placeholder="受注番号・顧客名"
                               class="border-gray-300 rounded-md shadow-sm text-sm w-64">
                        <x-primary-button type="submit">{{ __('検索') }}</x-primary-button>
                        @if (request('q'))
                            <a href="{{ route('shipments.index') }}" class="text-sm text-gray-500 underline">{{ __('クリア') }}</a>
                        @endif
                    </form>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('受注番号') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('顧客名') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('受注確定日') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('操作') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($orders as $order)
                                    <tr>
                                        <td class="px-4 py-2 whitespace-nowrap font-mono text-sm">{{ $order->order_number }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $order->customer?->company_name }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $order->confirmed_at?->format('Y/m/d') }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <form method="POST" action="{{ route('shipments.complete', $order) }}"
                                                  onsubmit="return confirm('受注 {{ $order->order_number }} の出荷完了を登録しますか？在庫数が減算されます。')">
                                                @csrf
                                                <x-primary-button type="submit" class="text-xs">
                                                    {{ __('出荷完了登録') }}
                                                </x-primary-button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-6 text-center text-gray-400 text-sm">
                                            {{ __('出荷待ちの受注はありません。') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($orders->hasPages())
                        <div class="mt-4">
                            {{ $orders->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('受注一覧') }}
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

            {{-- ステータスフィルタ --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4">
                    <form method="GET" action="{{ route('orders.index') }}" class="flex items-center gap-3">
                        <label for="status" class="text-sm font-medium text-gray-700">{{ __('ステータス') }}</label>
                        <select name="status" id="status" class="border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">{{ __('すべて') }}</option>
                            @foreach (\App\Enums\OrderStatus::cases() as $s)
                                <option value="{{ $s->value }}" @selected($currentStatus === $s->value)>{{ $s->label() }}</option>
                            @endforeach
                        </select>
                        <x-primary-button type="submit">{{ __('絞り込み') }}</x-primary-button>
                        @if ($currentStatus !== null)
                            <a href="{{ route('orders.index') }}" class="text-sm text-gray-500 underline">{{ __('クリア') }}</a>
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
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('受注番号') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('顧客名') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ステータス') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('受注確定日') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('操作') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($orders as $order)
                                    <tr>
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $order->order_number }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $order->customer?->company_name }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $order->status->label() }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $order->confirmed_at?->format('Y/m/d') }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <a href="{{ route('orders.show', $order) }}" class="text-indigo-600 hover:text-indigo-900 text-sm">{{ __('詳細') }}</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-4 text-center text-gray-500 text-sm">{{ __('受注データがありません。') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- ページネーション --}}
                    <div class="mt-4">
                        {{ $orders->appends(request()->query())->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('在庫変動履歴') }}: {{ $product->product_name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            {{-- フィルタフォーム --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4">
                    <form method="GET" action="{{ route('inventory.movements', $product) }}" class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <label for="reason" class="text-sm font-medium text-gray-700">{{ __('変動理由') }}</label>
                            <select name="reason" id="reason" class="border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="">{{ __('すべて') }}</option>
                                @foreach ($reasons as $reason)
                                    <option value="{{ $reason->value }}" @selected(request('reason') == $reason->value)>
                                        {{ $reason->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-center gap-2">
                            <label for="from" class="text-sm font-medium text-gray-700">{{ __('期間') }}</label>
                            <input type="date" name="from" id="from" value="{{ request('from') }}"
                                   class="border-gray-300 rounded-md shadow-sm text-sm">
                            <span class="text-gray-500">〜</span>
                            <input type="date" name="to" id="to" value="{{ request('to') }}"
                                   class="border-gray-300 rounded-md shadow-sm text-sm">
                        </div>
                        <x-primary-button type="submit">{{ __('絞り込み') }}</x-primary-button>
                        @if (request('reason') || request('from') || request('to'))
                            <a href="{{ route('inventory.movements', $product) }}" class="text-sm text-gray-500 underline">{{ __('クリア') }}</a>
                        @endif
                    </form>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="mb-4 flex justify-between items-center">
                        <a href="{{ route('inventory.index') }}" class="text-sm text-indigo-600 hover:underline">
                            ← {{ __('在庫一覧に戻る') }}
                        </a>
                        <div class="text-sm text-gray-500">
                            現在庫: <strong>{{ number_format($product->stock_quantity) }}</strong>
                            / 引当中: <strong>{{ number_format($product->reserved_quantity) }}</strong>
                            / 利用可能: <strong>{{ number_format($product->stock_quantity - $product->reserved_quantity) }}</strong>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('日時') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('変動理由') }}</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('変動数量') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('関連受注') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('操作者') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('メモ') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($movements as $movement)
                                    <tr>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm">
                                            {{ $movement->created_at?->format('Y/m/d H:i') }}
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm">
                                            {{ $movement->reason->name }}
                                        </td>
                                        <td class="px-4 py-2 text-right whitespace-nowrap text-sm font-mono
                                            {{ $movement->quantity_change > 0 ? 'text-green-700' : 'text-red-700' }}">
                                            {{ $movement->quantity_change > 0 ? '+' : '' }}{{ number_format($movement->quantity_change) }}
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm">
                                            {{ $movement->relatedOrder?->order_number ?? '-' }}
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm">
                                            {{ $movement->operator?->name ?? '-' }}
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-500 max-w-xs truncate">
                                            {{ $movement->memo ?? '-' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-6 text-center text-gray-400 text-sm">
                                            {{ __('変動履歴がありません。') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($movements->hasPages())
                        <div class="mt-4">
                            {{ $movements->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

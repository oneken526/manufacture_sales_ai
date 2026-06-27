<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('在庫一覧') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if ($alertCount > 0)
                <div class="rounded-md bg-yellow-50 border border-yellow-300 text-yellow-800 px-4 py-3" role="alert">
                    <strong>在庫アラート:</strong> {{ $alertCount }}件の製品が在庫アラート閾値以下です。
                </div>
            @endif

            {{-- 検索フォーム --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4">
                    <form method="GET" action="{{ route('inventory.index') }}" class="flex items-center gap-3">
                        <label for="q" class="text-sm font-medium text-gray-700">{{ __('検索') }}</label>
                        <input type="text" name="q" id="q" value="{{ request('q') }}"
                               placeholder="品番・製品名"
                               class="border-gray-300 rounded-md shadow-sm text-sm w-64">
                        <x-primary-button type="submit">{{ __('検索') }}</x-primary-button>
                        @if (request('q'))
                            <a href="{{ route('inventory.index') }}" class="text-sm text-gray-500 underline">{{ __('クリア') }}</a>
                        @endif
                    </form>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('品番') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('製品名') }}</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('現在庫数') }}</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('引当中') }}</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('利用可能数') }}</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('アラート閾値') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('操作') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($products as $product)
                                    @php
                                        $isAlert = $product->stock_quantity <= $product->alert_threshold;
                                        $available = $product->stock_quantity - $product->reserved_quantity;
                                    @endphp
                                    <tr class="{{ $isAlert ? 'bg-yellow-50' : '' }}">
                                        <td class="px-4 py-2 whitespace-nowrap font-mono text-sm">{{ $product->product_code }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            {{ $product->product_name }}
                                            @if ($isAlert)
                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                                    在庫不足
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-right whitespace-nowrap {{ $isAlert ? 'text-red-700 font-semibold' : '' }}">
                                            {{ number_format($product->stock_quantity) }}
                                        </td>
                                        <td class="px-4 py-2 text-right whitespace-nowrap text-orange-600">
                                            {{ number_format($product->reserved_quantity) }}
                                        </td>
                                        <td class="px-4 py-2 text-right whitespace-nowrap font-semibold">
                                            {{ number_format($available) }}
                                        </td>
                                        <td class="px-4 py-2 text-right whitespace-nowrap text-gray-500">
                                            {{ number_format($product->alert_threshold) }}
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <a href="{{ route('inventory.movements', $product) }}"
                                               class="text-indigo-600 hover:text-indigo-900 text-sm">
                                                {{ __('変動履歴') }}
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-6 text-center text-gray-400 text-sm">
                                            {{ __('製品が登録されていません。') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($products->hasPages())
                        <div class="mt-4">
                            {{ $products->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('製品一覧') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 text-green-800 px-4 py-3" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-md bg-red-50 border border-red-200 text-red-800 px-4 py-3" role="alert" aria-live="assertive">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <form method="GET" action="{{ route('products.index') }}" class="flex items-center gap-2" role="search">
                            <label for="product-search" class="sr-only">{{ __('製品検索') }}</label>
                            <input
                                type="text"
                                id="product-search"
                                name="q"
                                value="{{ $keyword }}"
                                placeholder="{{ __('品番・製品名で検索') }}"
                                class="border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 w-72"
                            >
                            <x-secondary-button type="submit">{{ __('検索') }}</x-secondary-button>
                        </form>

                        @if (Auth::user()->role === \App\Enums\UserRole::ADMIN)
                            <a href="{{ route('products.create') }}">
                                <x-primary-button type="button">{{ __('新規製品登録') }}</x-primary-button>
                            </a>
                        @endif
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200" id="product-table">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('品番') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('製品名') }}</th>
                                    <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('単価') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('単位') }}</th>
                                    <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('在庫数') }}</th>
                                    <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('引当中') }}</th>
                                    <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('利用可能') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('在庫状況') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('操作') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($products as $product)
                                    @php $isLowStock = $product->stock_quantity < $product->alert_threshold; @endphp
                                    <tr class="{{ $isLowStock ? 'bg-red-50' : '' }}">
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $product->product_code }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $product->product_name }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-right">{{ number_format($product->unit_price) }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $product->unit }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-right">{{ number_format($product->stock_quantity) }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-right">{{ number_format($product->reserved_quantity) }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-right">{{ number_format($product->stock_quantity - $product->reserved_quantity) }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            @if ($product->stock_quantity === 0)
                                                <span class="inline-flex items-center rounded-full bg-red-600 px-2.5 py-0.5 text-xs font-semibold text-white">{{ __('在庫切れ') }}</span>
                                            @elseif ($isLowStock)
                                                <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-800">{{ __('在庫不足') }}</span>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap space-x-2">
                                            @if (Auth::user()->role === \App\Enums\UserRole::ADMIN)
                                                <a href="{{ route('products.edit', $product) }}" class="text-indigo-600 hover:underline">{{ __('編集') }}</a>
                                            @endif
                                            @if (in_array(Auth::user()->role, [\App\Enums\UserRole::WAREHOUSE, \App\Enums\UserRole::ADMIN], true))
                                                <a href="{{ route('products.adjust-stock.form', $product) }}" class="text-indigo-600 hover:underline">{{ __('在庫調整') }}</a>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-4 py-6 text-center text-gray-500">{{ __('該当する製品が見つかりません') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div>
                        {{ $products->appends(['q' => $keyword])->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

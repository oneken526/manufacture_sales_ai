<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('受注詳細') }}: {{ $order->order_number }}
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

            {{-- 受注基本情報 --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 space-y-4">
                    <div class="flex justify-between items-start">
                        <h3 class="text-lg font-semibold text-gray-900">{{ __('受注情報') }}</h3>
                        <div class="flex gap-2">
                            @can('update', $order)
                                <a href="{{ route('orders.edit', $order) }}">
                                    <x-secondary-button type="button">{{ __('編集') }}</x-secondary-button>
                                </a>
                            @endcan
                            @if (in_array($order->status, [\App\Enums\OrderStatus::CONFIRMED, \App\Enums\OrderStatus::SHIPPING_INSTRUCTED]))
                                <form method="POST" action="{{ route('orders.cancel', $order) }}"
                                    onsubmit="return confirm('この受注をキャンセルしますか？引き当てられた在庫は解除されます。')">
                                    @csrf
                                    <x-danger-button type="submit">{{ __('キャンセル') }}</x-danger-button>
                                </form>
                            @endif
                            @if ($order->status === \App\Enums\OrderStatus::CONFIRMED)
                                <form method="POST" action="{{ route('orders.shipping_instruction', $order) }}">
                                    @csrf
                                    <x-primary-button type="submit">{{ __('出荷指示発行') }}</x-primary-button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <dl class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('受注番号') }}</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $order->order_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('ステータス') }}</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $order->status->label() }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('顧客名') }}</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $order->customer?->company_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('受注確定日') }}</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $order->confirmed_at?->format('Y/m/d H:i') ?? '-' }}</dd>
                        </div>
                        @if ($order->cancelled_at)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('キャンセル日時') }}</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $order->cancelled_at->format('Y/m/d H:i') }}</dd>
                            </div>
                        @endif
                        @if ($order->quotation)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">{{ __('元の見積') }}</dt>
                                <dd class="mt-1 text-sm">
                                    <a href="{{ route('quotations.show', $order->quotation) }}"
                                        class="text-indigo-600 hover:text-indigo-900">
                                        {{ $order->quotation->quotation_number }}
                                    </a>
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- 受注明細 --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('受注明細') }}</h3>
                    @if ($order->items->isEmpty())
                        <p class="text-gray-500 text-sm">{{ __('明細がありません。') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('製品名') }}</th>
                                        <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('数量') }}</th>
                                        <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('単価') }}</th>
                                        <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('小計') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach ($order->items as $item)
                                        <tr>
                                            <td class="px-4 py-2">{{ $item->product?->product_name }}</td>
                                            <td class="px-4 py-2 text-right">{{ number_format($item->quantity) }}</td>
                                            <td class="px-4 py-2 text-right">¥{{ number_format($item->unit_price) }}</td>
                                            <td class="px-4 py-2 text-right">¥{{ number_format($item->quantity * $item->unit_price) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="bg-gray-50">
                                        <td colspan="3" class="px-4 py-2 text-right font-semibold text-gray-700">{{ __('合計') }}</td>
                                        <td class="px-4 py-2 text-right font-semibold text-gray-900">
                                            ¥{{ number_format($order->items->sum(fn ($i) => $i->quantity * $i->unit_price)) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div>
                <a href="{{ route('orders.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm">
                    ← {{ __('受注一覧に戻る') }}
                </a>
            </div>
        </div>
    </div>
</x-app-layout>

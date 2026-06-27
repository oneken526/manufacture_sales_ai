<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('見積詳細') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 text-green-800 px-4 py-3" role="status">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('success'))
                <div class="rounded-md bg-green-50 border border-green-200 text-green-800 px-4 py-3" role="status">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('warning'))
                <div class="rounded-md bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3" role="alert" aria-live="assertive">
                    {{ session('warning') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold">{{ $quotation->quotation_number }}</h3>
                        <a href="{{ route('quotations.pdf', $quotation) }}">
                            <x-secondary-button type="button">{{ __('PDFプレビュー・ダウンロード') }}</x-secondary-button>
                        </a>
                    </div>

                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('顧客') }}</dt>
                            <dd class="mt-1 text-gray-900">{{ $quotation->customer->company_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('ステータス') }}</dt>
                            <dd class="mt-1 text-gray-900">{{ $quotation->status->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('有効期限') }}</dt>
                            <dd class="mt-1 text-gray-900">{{ optional($quotation->expires_at)->format('Y-m-d') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('備考') }}</dt>
                            <dd class="mt-1 text-gray-900">{{ $quotation->remarks ?? '—' }}</dd>
                        </div>
                    </dl>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('製品名') }}</th>
                                    <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('数量') }}</th>
                                    <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('単価') }}</th>
                                    <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('金額') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($quotation->items as $item)
                                    <tr>
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $item->product->product_name }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-right">{{ $item->quantity }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-right">{{ $item->unit_price }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-right">{{ $item->quantity * $item->unit_price }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-6 text-center text-gray-500">{{ __('明細が登録されていません') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="px-4 py-2 text-right font-medium">{{ __('合計金額') }}</td>
                                    <td class="px-4 py-2 text-right font-medium">{{ $quotation->items->sum(fn ($item) => $item->quantity * $item->unit_price) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="space-y-2">
                        @if ($quotation->items->isEmpty())
                            <button type="button" class="opacity-50 cursor-not-allowed inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest" disabled>
                                {{ __('受注確定') }}
                            </button>
                            <p class="text-sm text-gray-500">{{ __('明細が登録されていないため受注確定できません') }}</p>
                        @else
                            <form method="POST" action="{{ route('quotations.confirm', $quotation) }}" onsubmit="return confirm('{{ __('この見積を受注確定しますか？') }}');">
                                @csrf
                                <x-primary-button type="submit">{{ __('受注確定') }}</x-primary-button>
                            </form>
                        @endif
                    </div>

                    <a href="{{ route('quotations.index') }}" class="text-sm text-gray-600 hover:underline">{{ __('一覧に戻る') }}</a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

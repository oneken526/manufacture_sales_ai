<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('請求書一覧') }}
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

            {{-- フィルタ --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4">
                    <form method="GET" action="{{ route('invoices.index') }}" class="flex items-center gap-3">
                        <label for="payment_status" class="text-sm font-medium text-gray-700">{{ __('入金ステータス') }}</label>
                        <select name="payment_status" id="payment_status" class="border-gray-300 rounded-md shadow-sm text-sm">
                            <option value="">{{ __('すべて') }}</option>
                            @foreach ($paymentStatuses as $s)
                                <option value="{{ $s->value }}" @selected(request('payment_status') == $s->value)>
                                    {{ $s->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-primary-button type="submit">{{ __('絞り込み') }}</x-primary-button>
                        @if (request('payment_status'))
                            <a href="{{ route('invoices.index') }}" class="text-sm text-gray-500 underline">{{ __('クリア') }}</a>
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
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('請求書番号') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('顧客名') }}</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ __('金額') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('入金ステータス') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('発行日') }}</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('操作') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($invoices as $invoice)
                                    <tr>
                                        <td class="px-4 py-2 whitespace-nowrap font-mono text-sm">{{ $invoice->invoice_number }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $invoice->salesOrder?->customer?->company_name }}</td>
                                        <td class="px-4 py-2 text-right whitespace-nowrap">¥{{ number_format($invoice->total_amount) }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            @php
                                                $badgeClass = match ($invoice->payment_status) {
                                                    \App\Enums\PaymentStatus::UNPAID => 'bg-red-100 text-red-800',
                                                    \App\Enums\PaymentStatus::PARTIALLY_PAID => 'bg-yellow-100 text-yellow-800',
                                                    \App\Enums\PaymentStatus::PAID => 'bg-green-100 text-green-800',
                                                };
                                            @endphp
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $badgeClass }}">
                                                {{ $invoice->payment_status->name }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm">{{ $invoice->issued_at?->format('Y/m/d') }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap space-x-2">
                                            {{-- 入金ステータス更新フォーム --}}
                                            <form method="POST" action="{{ route('invoices.payment_status', $invoice) }}" class="inline">
                                                @csrf
                                                @method('PUT')
                                                <select name="payment_status" class="border-gray-300 rounded text-xs">
                                                    @foreach ($paymentStatuses as $s)
                                                        <option value="{{ $s->value }}" @selected($invoice->payment_status === $s)>
                                                            {{ $s->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="text-xs text-indigo-600 hover:underline ml-1">{{ __('更新') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-6 text-center text-gray-400 text-sm">
                                            {{ __('請求書がありません。') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($invoices->hasPages())
                        <div class="mt-4">
                            {{ $invoices->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

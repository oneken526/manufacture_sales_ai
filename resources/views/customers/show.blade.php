<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('顧客詳細') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
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
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold">{{ $customer->company_name }}</h3>
                        <div class="space-x-2">
                            @if (in_array(Auth::user()->role, [\App\Enums\UserRole::SALES, \App\Enums\UserRole::ADMIN], true))
                                <a href="{{ route('customers.edit', $customer) }}">
                                    <x-secondary-button type="button">{{ __('編集') }}</x-secondary-button>
                                </a>
                            @endif
                            @if (Auth::user()->role === \App\Enums\UserRole::ADMIN)
                                <form method="POST" action="{{ route('customers.destroy', $customer) }}" class="inline" onsubmit="return confirm('{{ __('この顧客を削除しますか？') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <x-danger-button type="submit">{{ __('削除') }}</x-danger-button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('担当者名') }}</dt>
                            <dd class="mt-1 text-gray-900">{{ $customer->contact_name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('電話番号') }}</dt>
                            <dd class="mt-1 text-gray-900">{{ $customer->phone ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('メールアドレス') }}</dt>
                            <dd class="mt-1 text-gray-900">{{ $customer->email ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">{{ __('与信枠') }}</dt>
                            <dd class="mt-1 text-gray-900">{{ number_format($customer->credit_limit) }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">{{ __('住所') }}</dt>
                            <dd class="mt-1 text-gray-900">{{ $customer->address ?? '—' }}</dd>
                        </div>
                    </dl>

                    <a href="{{ route('customers.index') }}" class="text-sm text-gray-600 hover:underline">{{ __('一覧に戻る') }}</a>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    <h3 class="text-lg font-semibold">{{ __('受注履歴') }}</h3>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('受注番号') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('受注日') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ステータス') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('操作') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($customer->salesOrders as $order)
                                    <tr>
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $order->order_number }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">{{ optional($order->confirmed_at)->format('Y-m-d') ?? $order->created_at->format('Y-m-d') }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $order->status->label() }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            {{-- 受注詳細画面はOrderController管轄（別タスクで実装予定） --}}
                                            <span class="text-gray-400">{{ $order->order_number }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-6 text-center text-gray-500">{{ __('受注履歴はありません') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

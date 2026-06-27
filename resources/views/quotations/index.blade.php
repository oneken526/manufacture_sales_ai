<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('見積一覧') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 border border-green-200 text-green-800 px-4 py-3" role="status">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    <div class="flex items-center justify-end">
                        <a href="{{ route('quotations.create') }}">
                            <x-primary-button type="button">{{ __('見積新規作成') }}</x-primary-button>
                        </a>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('見積番号') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('顧客名') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('ステータス') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('有効期限') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('操作') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse ($quotations as $quotation)
                                    <tr>
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $quotation->quotation_number }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $quotation->customer->company_name }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">{{ $quotation->status->name }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">{{ optional($quotation->expires_at)->format('Y-m-d') ?? '—' }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap">
                                            <a href="{{ route('quotations.show', $quotation) }}" class="text-indigo-600 hover:underline">{{ __('詳細') }}</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">{{ __('登録されている見積がありません') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div>
                        {{ $quotations->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('振込データCSVインポート') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 border border-red-200 text-red-800 px-4 py-3">
                    <ul class="list-disc list-inside text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 space-y-4">
                    <p class="text-sm text-gray-600">
                        全銀協フォーマットのCSVファイルをアップロードしてください。<br>
                        CSVフォーマット: <code class="bg-gray-100 px-1 rounded text-xs">paid_at,transfer_name,amount,description</code>
                    </p>

                    <form method="POST" action="{{ route('payments.import.store') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <label for="csv_file" class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('CSVファイル') }} <span class="text-red-600">*</span>
                                </label>
                                <input type="file" name="csv_file" id="csv_file" accept=".csv,.txt"
                                       class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                            </div>
                            <x-primary-button type="submit">{{ __('インポート実行') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

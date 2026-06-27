@php
    $old = fn (string $key, $default = null) => old($key, $user?->{$key} ?? $default);
    $roleDescriptions = [
        'admin' => 'システム管理者：すべての機能（マスタ管理、ユーザー管理、運用機能）を利用できます。',
        'sales' => '営業担当者：顧客・受注・見積・請求書の作成や編集ができます。',
        'warehouse' => '在庫・出荷担当者：在庫管理・出荷処理ができます。請求書操作はできません。',
        'accounting' => '管理職・経理担当：請求書発行や経営状況の確認ができます。',
    ];
@endphp

<div>
    <x-input-label for="name" :value="__('名前')" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="$old('name')" required autofocus />
    <x-input-error class="mt-2" :messages="$errors->get('name')" />
</div>

<div>
    <x-input-label for="email" :value="__('メールアドレス')" />
    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="$old('email')" required />
    <x-input-error class="mt-2" :messages="$errors->get('email')" />
</div>

<div>
    <x-input-label for="role" :value="__('役割')" />
    <select id="role" name="role" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
        <option value="">{{ __('選択してください') }}</option>
        @foreach ($roles as $role)
            <option value="{{ $role->routeKey() }}" @selected($old('role', $user?->role?->routeKey()) === $role->routeKey())>{{ $role->label() }}</option>
        @endforeach
    </select>
    <x-input-error class="mt-2" :messages="$errors->get('role')" />
    <p id="role-description" class="mt-2 text-sm text-gray-500"></p>
</div>

<script>
    $(function () {
        var descriptions = @json($roleDescriptions);

        function updateRoleDescription() {
            var selected = $('#role').val();
            $('#role-description').text(descriptions[selected] || '');
        }

        $('#role').on('change', updateRoleDescription);
        updateRoleDescription();
    });
</script>

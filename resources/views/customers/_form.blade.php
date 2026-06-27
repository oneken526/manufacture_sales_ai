@php
    $old = fn (string $key, $default = null) => old($key, $customer?->{$key} ?? $default);
@endphp

<div>
    <x-input-label for="company_name" :value="__('会社名')" />
    <x-text-input id="company_name" name="company_name" type="text" class="mt-1 block w-full" :value="$old('company_name')" required autofocus />
    <x-input-error class="mt-2" :messages="$errors->get('company_name')" />
</div>

<div>
    <x-input-label for="contact_name" :value="__('担当者名')" />
    <x-text-input id="contact_name" name="contact_name" type="text" class="mt-1 block w-full" :value="$old('contact_name')" />
    <x-input-error class="mt-2" :messages="$errors->get('contact_name')" />
</div>

<div>
    <x-input-label for="address" :value="__('住所')" />
    <x-text-input id="address" name="address" type="text" class="mt-1 block w-full" :value="$old('address')" />
    <x-input-error class="mt-2" :messages="$errors->get('address')" />
</div>

<div>
    <x-input-label for="phone" :value="__('電話番号')" />
    <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="$old('phone')" />
    <x-input-error class="mt-2" :messages="$errors->get('phone')" />
</div>

<div>
    <x-input-label for="email" :value="__('メールアドレス')" />
    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="$old('email')" />
    <x-input-error class="mt-2" :messages="$errors->get('email')" />
</div>

<div>
    <x-input-label for="credit_limit" :value="__('与信枠')" />
    <x-text-input id="credit_limit" name="credit_limit" type="number" min="0" step="1" class="mt-1 block w-full" :value="$old('credit_limit', 0)" required />
    <x-input-error class="mt-2" :messages="$errors->get('credit_limit')" />
</div>

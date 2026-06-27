@php
    $old = fn (string $key, $default = null) => old($key, $product?->{$key} ?? $default);
@endphp

<div>
    <x-input-label for="product_code" :value="__('品番')" />
    <x-text-input id="product_code" name="product_code" type="text" class="mt-1 block w-full" :value="$old('product_code')" required autofocus />
    <x-input-error class="mt-2" :messages="$errors->get('product_code')" />
</div>

<div>
    <x-input-label for="product_name" :value="__('製品名')" />
    <x-text-input id="product_name" name="product_name" type="text" class="mt-1 block w-full" :value="$old('product_name')" required />
    <x-input-error class="mt-2" :messages="$errors->get('product_name')" />
</div>

<div>
    <x-input-label for="unit_price" :value="__('単価')" />
    <x-text-input id="unit_price" name="unit_price" type="number" min="0" step="1" class="mt-1 block w-full" :value="$old('unit_price', 0)" required />
    <x-input-error class="mt-2" :messages="$errors->get('unit_price')" />
</div>

<div>
    <x-input-label for="unit" :value="__('単位')" />
    <x-text-input id="unit" name="unit" type="text" class="mt-1 block w-full" :value="$old('unit', '個')" required />
    <x-input-error class="mt-2" :messages="$errors->get('unit')" />
</div>

<div>
    <x-input-label for="stock_quantity" :value="__('在庫数')" />
    <x-text-input id="stock_quantity" name="stock_quantity" type="number" min="0" step="1" class="mt-1 block w-full" :value="$old('stock_quantity', 0)" required />
    <x-input-error class="mt-2" :messages="$errors->get('stock_quantity')" />
</div>

<div>
    <x-input-label for="alert_threshold" :value="__('在庫アラート閾値')" />
    <x-text-input id="alert_threshold" name="alert_threshold" type="number" min="0" step="1" class="mt-1 block w-full" :value="$old('alert_threshold', 0)" required />
    <x-input-error class="mt-2" :messages="$errors->get('alert_threshold')" />
</div>

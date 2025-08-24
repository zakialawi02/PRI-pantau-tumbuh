<textarea {{ $attributes->merge([
    'class' => 'focus:ring-primary focus:border-primar block w-full rounded-lg border border-gray-300 bg-gray-50 px-2.5 py-2 text-gray-900 ',
]) }}>{{ $slot }}</textarea>

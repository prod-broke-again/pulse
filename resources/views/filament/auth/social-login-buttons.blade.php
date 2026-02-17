<div class="mt-4 flex flex-col gap-2">
    <a href="{{ route('auth.social.redirect', ['provider' => 'vkontakte']) }}"
       class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
        Login with VK
    </a>
    <a href="{{ route('auth.social.redirect', ['provider' => 'telegram']) }}"
       class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">
        Login with Telegram
    </a>
</div>

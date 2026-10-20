<div class="flex shrink-0 border border-white/20 text-xs font-bold">
    <a href="{{ request()->url() }}?lang=id" class="px-3 py-2 {{ $language === 'id' ? 'bg-white text-slate-950' : 'text-slate-300' }}">ID</a>
    <a href="{{ request()->url() }}?lang=en" class="px-3 py-2 {{ $language === 'en' ? 'bg-white text-slate-950' : 'text-slate-300' }}">EN</a>
</div>

<nav class="fixed top-0 right-0 left-0 lg:left-64 h-16 bg-white border-b border-gray-200 z-20 flex items-center justify-between px-6">
    <button type="button" @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 text-black hover:bg-gray-100 transition" aria-label="Menu">
        <i class="fas fa-bars text-lg"></i>
    </button>
    <div class="flex-1"></div>
    <div class="flex items-center gap-4">
        <span class="text-sm text-gray-600 hidden sm:inline flex items-center gap-2">
            <i class="fas fa-user-circle text-gray-400"></i>
            {{ auth()->user()->email }}
        </span>
        <span class="hidden sm:block w-px h-6 bg-gray-200"></span>
        <form method="POST" action="{{ route('logout') }}" class="inline">
            @csrf
            <button type="submit" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:text-black transition border border-gray-300 hover:border-black">
                <i class="fas fa-sign-out-alt text-xs"></i>
                Log out
            </button>
        </form>
    </div>
</nav>
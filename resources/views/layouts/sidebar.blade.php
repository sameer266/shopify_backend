<aside class="fixed inset-y-0 left-0 z-30 w-64 bg-black text-white transform transition-transform duration-200 ease-out lg:translate-x-0 flex flex-col border-r border-gray-800"
     :class="{ '-translate-x-full': !sidebarOpen }"
     x-show="true"
     x-transition
     @click.away="sidebarOpen = false">
    <div class="flex items-center h-16 px-6 border-b border-gray-800">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 font-light text-white tracking-wide">
            <span class="w-8 h-8 border border-white flex items-center justify-center">
                <i class="fas fa-store text-xs"></i>
            </span>
            <span class="text-sm uppercase">Admin</span>
        </a>
    </div>
    <nav class="flex-1 overflow-y-auto py-6" x-data="{ open: 'dashboard' }">
        <ul class="space-y-1 px-4">
            <li>
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-4 py-3 text-gray-300 hover:bg-gray-900 hover:text-white transition {{ request()->routeIs('dashboard') ? 'bg-gray-900 text-white border-l-2 border-white' : '' }}">
                    <i class="fas fa-chart-line w-4 text-center text-xs"></i>
                    <span class="text-sm">Dashboard</span>
                </a>
            </li>
            <li x-data="{ expanded: {{ request()->routeIs('orders*') ? 'true' : 'false' }} }">
                <button type="button" @click="expanded = !expanded" class="w-full flex items-center justify-between px-4 py-3 text-gray-300 hover:bg-gray-900 hover:text-white transition">
                    <span class="flex items-center gap-3">
                        <i class="fas fa-shopping-cart w-4 text-center text-xs"></i>
                        <span class="text-sm">Orders</span>
                    </span>
                    <i class="fas fa-chevron-right transition-transform text-xs" :class="{ 'rotate-90': expanded }"></i>
                </button>
                <ul x-show="expanded" x-transition class="ml-4 mt-1 space-y-1 border-l border-gray-700 pl-3">
                    <li>
                        <a href="{{ route('orders.index') }}" class="flex items-center gap-2 block px-4 py-2 text-sm text-gray-400 hover:bg-gray-900 hover:text-white transition {{ request()->routeIs('orders.index') ? 'text-white' : '' }}">
                            <i class="fas fa-list text-xs"></i>
                            All Orders
                        </a>
                    </li>
                </ul>
            </li>
            <li>
                <a href="{{ route('reports.index') }}" class="flex items-center gap-3 px-4 py-3 text-gray-300 hover:bg-gray-900 hover:text-white transition {{ request()->routeIs('reports.index') ? 'bg-gray-900 text-white border-l-2 border-white' : '' }}">
                    <i class="fas fa-chart-pie w-4 text-center text-xs"></i>
                    <span class="text-sm">Reports</span>
                </a>
            </li>
            <li>
                <a href="{{ route('dashboard') }}#sync" class="flex items-center gap-3 px-4 py-3 text-gray-300 hover:bg-gray-900 hover:text-white transition">
                    <i class="fas fa-sync-alt w-4 text-center text-xs"></i>
                    <span class="text-sm">Sync</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>
<div x-show="sidebarOpen" x-transition class="fixed inset-0 bg-black/70 z-20 lg:hidden" @click="sidebarOpen = false" style="display: none;"></div>
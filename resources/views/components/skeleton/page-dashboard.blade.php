{{-- Skeleton: Vista tipo dashboard/métricas --}}
@props([
    'statCards' => 4,
    'sections' => 2,
])

<div class="py-6">
    <div class="w-full px-4 sm:px-6 lg:px-8">
        {{-- Header hero --}}
        <div class="mb-6 bg-gradient-to-r from-gray-200 to-gray-300 dark:from-gray-700 dark:to-gray-600 rounded-xl shadow-lg p-6 animate-pulse">
            <div class="flex items-center gap-4">
                <div class="h-12 w-12 bg-white/30 rounded-lg"></div>
                <div>
                    <div class="h-7 w-56 bg-white/30 rounded mb-2"></div>
                    <div class="h-4 w-80 bg-white/20 rounded"></div>
                </div>
            </div>
        </div>

        {{-- Stat cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-{{ min($statCards, 4) }} gap-4 mb-6">
            @for($i = 0; $i < $statCards; $i++)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 bg-gray-200 dark:bg-gray-700 rounded-lg animate-pulse"></div>
                        <div class="flex-1">
                            <div class="h-3 w-16 bg-gray-200 dark:bg-gray-700 rounded animate-pulse mb-2"></div>
                            <div class="h-6 w-24 bg-gray-300 dark:bg-gray-600 rounded animate-pulse"></div>
                        </div>
                    </div>
                </div>
            @endfor
        </div>

        {{-- Content sections --}}
        @for($s = 0; $s < $sections; $s++)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 mb-4 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                    <div class="h-5 w-40 bg-gray-300 dark:bg-gray-600 rounded animate-pulse"></div>
                </div>
                <div class="p-4 space-y-3">
                    @for($i = 0; $i < 4; $i++)
                        <div class="flex justify-between items-center">
                            <div class="h-4 bg-gray-100 dark:bg-gray-700/50 rounded animate-pulse" style="width: {{ 30 + ($i * 15) % 40 }}%"></div>
                            <div class="h-4 w-20 bg-gray-100 dark:bg-gray-700/50 rounded animate-pulse"></div>
                        </div>
                    @endfor
                </div>
            </div>
        @endfor
    </div>
</div>

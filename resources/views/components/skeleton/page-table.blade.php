{{-- Skeleton: Vista tipo tabla/listado (patrón más común) --}}
@props([
    'statCards' => 0,
    'filterCount' => 3,
    'columns' => 6,
    'rows' => 8,
])

<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-4 sm:mb-6">
            <div class="flex justify-between items-start gap-3 sm:gap-4">
                <div class="flex-1">
                    <div class="h-7 w-48 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
                    <div class="mt-2 h-4 w-72 bg-gray-100 dark:bg-gray-700/50 rounded animate-pulse"></div>
                </div>
                <div class="h-9 w-32 bg-indigo-200 dark:bg-indigo-800 rounded-md animate-pulse"></div>
            </div>
        </div>

        {{-- Stat cards (opcional) --}}
        @if($statCards > 0)
            <div class="grid grid-cols-2 sm:grid-cols-{{ min($statCards, 4) }} gap-3 sm:gap-4 mb-4 sm:mb-6">
                @for($i = 0; $i < $statCards; $i++)
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border border-gray-200 dark:border-gray-700">
                        <div class="h-3 w-20 bg-gray-200 dark:bg-gray-700 rounded animate-pulse mb-2"></div>
                        <div class="h-6 w-24 bg-gray-300 dark:bg-gray-600 rounded animate-pulse"></div>
                    </div>
                @endfor
            </div>
        @endif

        {{-- Filtros --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-3 sm:p-4 mb-4">
            <div class="flex flex-wrap gap-3">
                @for($i = 0; $i < $filterCount; $i++)
                    <div class="flex-1 min-w-[140px] space-y-1">
                        <div class="h-3 w-16 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
                        <div class="h-9 bg-gray-200 dark:bg-gray-700 rounded-md animate-pulse"></div>
                    </div>
                @endfor
            </div>
        </div>

        {{-- Tabla --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 overflow-hidden">
            {{-- Header tabla --}}
            <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <div class="grid grid-cols-{{ $columns }} gap-3">
                    @for($i = 0; $i < $columns; $i++)
                        <div class="h-3 bg-gray-300 dark:bg-gray-600 rounded animate-pulse" style="width: {{ 50 + ($i * 13) % 40 }}%"></div>
                    @endfor
                </div>
            </div>
            {{-- Filas --}}
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @for($i = 0; $i < $rows; $i++)
                    <div class="px-4 py-3">
                        <div class="grid grid-cols-{{ $columns }} gap-3 items-center">
                            @for($j = 0; $j < $columns; $j++)
                                <div class="h-4 bg-gray-100 dark:bg-gray-700/50 rounded animate-pulse" style="width: {{ 40 + (($i + $j) * 17) % 50 }}%"></div>
                            @endfor
                        </div>
                    </div>
                @endfor
            </div>
        </div>
    </div>
</div>

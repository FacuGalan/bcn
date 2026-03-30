{{-- Skeleton: Vista tipo formulario/wizard/configuración --}}
@props([
    'tabs' => 0,
    'fields' => 6,
    'hasBackButton' => false,
])

<div class="py-4">
    <div class="px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-6">
            <div class="flex items-center gap-3">
                @if($hasBackButton)
                    <div class="h-9 w-9 bg-gray-200 dark:bg-gray-700 rounded-md animate-pulse"></div>
                @endif
                <div>
                    <div class="h-7 w-56 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
                    <div class="mt-2 h-4 w-80 bg-gray-100 dark:bg-gray-700/50 rounded animate-pulse"></div>
                </div>
            </div>
        </div>

        {{-- Tabs (opcional) --}}
        @if($tabs > 0)
            <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
                <div class="flex space-x-4">
                    @for($i = 0; $i < $tabs; $i++)
                        <div class="pb-3 px-1">
                            <div class="h-4 w-20 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
                        </div>
                    @endfor
                </div>
            </div>
        @endif

        {{-- Contenido formulario --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700 p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @for($i = 0; $i < $fields; $i++)
                    <div class="{{ $i === 0 ? 'sm:col-span-2' : '' }} space-y-1">
                        <div class="h-3 w-24 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
                        <div class="h-10 bg-gray-200 dark:bg-gray-700 rounded-md animate-pulse"></div>
                    </div>
                @endfor
            </div>

            {{-- Botones footer --}}
            <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="h-9 w-24 bg-gray-200 dark:bg-gray-700 rounded-md animate-pulse"></div>
                <div class="h-9 w-32 bg-indigo-200 dark:bg-indigo-800 rounded-md animate-pulse"></div>
            </div>
        </div>
    </div>
</div>

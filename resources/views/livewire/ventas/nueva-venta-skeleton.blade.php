{{-- Skeleton del POS - Se muestra mientras carga el componente NuevaVenta --}}
<div class="h-[calc(100vh-5.5rem)] flex flex-col py-2 overflow-hidden">
    <div class="flex-1 px-3 sm:px-4 lg:px-6 min-h-0">
        <div class="h-full bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden flex flex-col">
            <div class="flex-1 px-4 py-3 min-h-0 overflow-hidden">
                <div class="h-full grid grid-cols-1 lg:grid-cols-4 gap-4">

                    {{-- Columna izquierda (75%) --}}
                    <div class="lg:col-span-3 flex flex-col space-y-3 min-h-0">
                        {{-- Barra de busqueda --}}
                        <div class="flex gap-2 items-end">
                            <div class="flex-1 space-y-1">
                                <div class="h-3 w-24 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
                                <div class="h-10 bg-gray-200 dark:bg-gray-700 rounded-md animate-pulse"></div>
                            </div>
                            <div class="w-36 space-y-1">
                                <div class="h-3 w-16 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
                                <div class="h-10 bg-gray-200 dark:bg-gray-700 rounded-md animate-pulse"></div>
                            </div>
                            <div class="space-y-1">
                                <div class="h-3 w-14 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
                                <div class="h-10 w-28 bg-gray-200 dark:bg-gray-700 rounded-md animate-pulse"></div>
                            </div>
                            <div class="space-y-1">
                                <div class="h-3 w-16 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
                                <div class="flex gap-1">
                                    <div class="h-10 w-10 bg-gray-200 dark:bg-gray-700 rounded-md animate-pulse"></div>
                                    <div class="h-10 w-10 bg-gray-200 dark:bg-gray-700 rounded-md animate-pulse"></div>
                                    <div class="h-10 w-10 bg-gray-200 dark:bg-gray-700 rounded-md animate-pulse"></div>
                                </div>
                            </div>
                        </div>

                        {{-- Tabla de items --}}
                        <div class="flex-1 min-h-0 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                            {{-- Header de tabla --}}
                            <div class="bg-gray-50 dark:bg-gray-700 px-3 py-2 border-b border-gray-200 dark:border-gray-700">
                                <div class="grid grid-cols-12 gap-2">
                                    <div class="col-span-1 h-3 bg-gray-300 dark:bg-gray-600 rounded animate-pulse"></div>
                                    <div class="col-span-4 h-3 bg-gray-300 dark:bg-gray-600 rounded animate-pulse"></div>
                                    <div class="col-span-1 h-3 bg-gray-300 dark:bg-gray-600 rounded animate-pulse"></div>
                                    <div class="col-span-2 h-3 bg-gray-300 dark:bg-gray-600 rounded animate-pulse"></div>
                                    <div class="col-span-2 h-3 bg-gray-300 dark:bg-gray-600 rounded animate-pulse"></div>
                                    <div class="col-span-2 h-3 bg-gray-300 dark:bg-gray-600 rounded animate-pulse"></div>
                                </div>
                            </div>
                            {{-- Filas vacias simuladas --}}
                            <div class="p-3 space-y-3">
                                @for($i = 0; $i < 6; $i++)
                                    <div class="grid grid-cols-12 gap-2 items-center" style="animation-delay: {{ $i * 100 }}ms">
                                        <div class="col-span-1 h-4 bg-gray-100 dark:bg-gray-700/50 rounded animate-pulse"></div>
                                        <div class="col-span-4 h-4 bg-gray-100 dark:bg-gray-700/50 rounded animate-pulse" style="width: {{ 60 + ($i * 7) % 30 }}%"></div>
                                        <div class="col-span-1 h-4 bg-gray-100 dark:bg-gray-700/50 rounded animate-pulse"></div>
                                        <div class="col-span-2 h-4 bg-gray-100 dark:bg-gray-700/50 rounded animate-pulse"></div>
                                        <div class="col-span-2 h-4 bg-gray-100 dark:bg-gray-700/50 rounded animate-pulse"></div>
                                        <div class="col-span-2 h-4 bg-gray-100 dark:bg-gray-700/50 rounded animate-pulse"></div>
                                    </div>
                                @endfor
                            </div>
                        </div>
                    </div>

                    {{-- Columna derecha (25%) --}}
                    <div class="flex flex-col space-y-2 min-h-0">
                        {{-- Selector cliente --}}
                        <div class="space-y-1">
                            <div class="h-3 w-16 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
                            <div class="h-10 bg-gray-200 dark:bg-gray-700 rounded-md animate-pulse"></div>
                        </div>

                        {{-- Selectores (lista precio, forma venta, forma pago) --}}
                        @for($i = 0; $i < 3; $i++)
                            <div class="space-y-1">
                                <div class="h-3 w-24 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
                                <div class="h-9 bg-gray-200 dark:bg-gray-700 rounded-md animate-pulse"></div>
                            </div>
                        @endfor

                        {{-- Resumen --}}
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden mt-auto">
                            <div class="bg-gray-50 dark:bg-gray-700 px-3 py-1.5 border-b border-gray-200 dark:border-gray-700">
                                <div class="h-3 w-16 bg-gray-300 dark:bg-gray-600 rounded animate-pulse"></div>
                            </div>
                            <div class="px-3 py-2 space-y-2">
                                @for($i = 0; $i < 3; $i++)
                                    <div class="flex justify-between">
                                        <div class="h-3 w-20 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
                                        <div class="h-3 w-16 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
                                    </div>
                                @endfor
                                {{-- Total destacado --}}
                                <div class="flex justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
                                    <div class="h-5 w-14 bg-gray-300 dark:bg-gray-600 rounded animate-pulse"></div>
                                    <div class="h-5 w-20 bg-indigo-200 dark:bg-indigo-800 rounded animate-pulse"></div>
                                </div>
                            </div>
                        </div>

                        {{-- Botones de accion --}}
                        <div class="flex gap-2 pt-2">
                            <div class="flex-1 h-10 bg-gray-200 dark:bg-gray-700 rounded-md animate-pulse"></div>
                            <div class="flex-1 h-10 bg-indigo-200 dark:bg-indigo-800 rounded-md animate-pulse"></div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

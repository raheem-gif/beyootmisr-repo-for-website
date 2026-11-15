<x-filament-panels::page>
    <x-filament::section>
        <table class="w-full text-sm">
            <tbody>
                @foreach ($checks as $c)
                    <tr class="border-b border-gray-100 dark:border-white/10">
                        <td class="py-2 pe-4">{{ $c['label'] }}</td>
                        <td class="py-2 pe-4 w-20 whitespace-nowrap font-semibold" style="color: {{ match ($c['status']) { 'pass' => '#0e8345', 'fail' => '#de1135', default => '#9f6402' } }}">{{ strtoupper($c['status']) }}</td>
                        <td class="py-2 font-mono text-xs break-all" dir="ltr">{{ $c['detail'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>

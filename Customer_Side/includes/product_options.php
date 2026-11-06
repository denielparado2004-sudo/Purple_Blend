<?php

// Return the rice choices and prices supported by selected meal names.
function riceOptionsForProduct(string $productName): array
{
    $name = strtolower($productName);
    $supportsRiceOptions =
        str_contains($name, 'chicken pastil') ||
        str_contains($name, 'adobo baboy') ||
        str_contains($name, 'pork adobo');

    if (!$supportsRiceOptions) {
        return [];
    }

    return [
        'with' => [
            'label' => 'With Rice',
            'price' => 55.00
        ],
        'without' => [
            'label' => 'Without Rice',
            'price' => 40.00
        ]
    ];
}

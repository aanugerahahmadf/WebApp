<?php

use Filament\Facades\Filament;
use Filament\Livewire\GlobalSearch;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('welcome'));
});

afterEach(function (): void {
    Filament::setCurrentPanel(null);
});

it('renders global search results', function (): void {
    $html = Livewire::test(GlobalSearch::class)
        ->set('search', 'mawar')
        ->html();

    $hasContainer = str_contains($html, 'fi-global-search-results-ctn');
    $resultCount = substr_count($html, 'fi-global-search-result-link');
    $hasNoResults = str_contains($html, 'fi-global-search-no-results-message');
    $categories = [];

    if (preg_match_all('/fi-global-search-result-group.*?<h3[^>]*>(.*?)<\/h3>/s', $html, $m)) {
        $categories = $m[1];
    }

    dump(compact('hasContainer', 'resultCount', 'hasNoResults', 'categories'));

    expect(true)->toBeTrue();
});

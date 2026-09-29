<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /**
     * @param  string|null  $image  absolute URL for the Open Graph preview image
     */
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $image = null,
        public ?string $ogType = null,
    ) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.app');
    }
}

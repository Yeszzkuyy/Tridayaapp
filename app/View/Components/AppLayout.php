<?php

namespace App\View\Components;

use App\Support\PageContext;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        if (PageContext::isEmbed(request())) {
            return view('layouts.embed');
        }

        return view('layouts.app');
    }
}

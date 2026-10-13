<?php

namespace App\Http\Controllers;

use App\Models\ApprovalDelegation;
use App\Models\ApprovalWorkflow;
use App\Models\RoutingRule;
use App\Models\ServiceLevelTarget;
use App\Models\TicketCategory;
use App\Models\TicketForm;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use Illuminate\View\View;

class ConfigurationDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('configuration.dashboard', [
            'metrics' => [
                'categories' => TicketCategory::where('is_active', true)->count(),
                'forms' => TicketForm::where('is_active', true)->count(),
                'workflows' => ApprovalWorkflow::where('is_active', true)->count(),
                'routes' => RoutingRule::where('is_active', true)->count(),
                'statuses' => TicketStatus::where('is_active', true)->count(),
                'priorities' => TicketPriority::where('is_active', true)->count(),
            ],
            'forms' => TicketForm::with(['category', 'subcategory'])->withCount('fields')->latest()->limit(5)->get(),
            'workflows' => ApprovalWorkflow::with(['category', 'subcategory'])->withCount('levels')->latest()->limit(4)->get(),
            'slaCount' => ServiceLevelTarget::where('is_active', true)->count(),
            'activeDelegations' => ApprovalDelegation::where('is_active', true)
                ->where('starts_at', '<=', now())->where('ends_at', '>=', now())->count(),
        ]);
    }
}

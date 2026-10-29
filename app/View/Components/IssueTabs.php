<?php

namespace App\View\Components;

use App\Models\IssueImport;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class IssueTabs extends Component
{
    public ?IssueImport $resumeDocument = null;

    public string $resumeStep = 'match';

    public string $importUrl;

    public function __construct()
    {
        $this->importUrl = route('issue-imports.index');
        $request = request();
        $pointer = $request->session()->get('issue_import_navigation');
        if (! $request->user()?->can('operate-inventory') || ! is_array($pointer)
            || ($pointer['user_id'] ?? null) !== $request->user()->id) {
            return;
        }
        $this->resumeDocument = IssueImport::with('location:id,name')->select(['id', 'location_id'])
            ->whereKey((int) ($pointer['id'] ?? 0))->where('status', 'draft')->whereNotNull('payload')
            ->whereNull('posted_at')->where('created_at', '>=', now()->subDays(7))->first();
        if (! $this->resumeDocument) {
            $request->session()->forget('issue_import_navigation');

            return;
        }
        $this->resumeStep = in_array($pointer['step'] ?? null, ['match', 'review'], true) ? $pointer['step'] : 'match';
        if ($request->routeIs('preview.issues')) {
            $this->importUrl = route('issue-imports.show', [$this->resumeDocument, 'step' => $this->resumeStep]);
        }
    }

    public function render(): View
    {
        return view('components.issue-tabs');
    }
}

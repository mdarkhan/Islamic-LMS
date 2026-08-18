<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NoticeRequest;
use App\Models\Notice;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NoticeController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.notices.index', [
            'notices' => Notice::query()->orderByDesc('priority')->orderByDesc('id')->paginate(20),
        ]);
    }

    public function store(NoticeRequest $request): RedirectResponse
    {
        $notice = Notice::query()->create($request->validated() + [
            'is_active' => $request->boolean('is_active'),
            'created_by' => $request->user()->getKey(),
        ]);
        $this->audit->log('notice.created', $notice, after: ['audience' => $notice->audience, 'priority' => $notice->priority]);

        return back()->with('success', __('notices.saved'));
    }

    public function update(NoticeRequest $request, Notice $notice): RedirectResponse
    {
        $notice->update($request->validated() + ['is_active' => $request->boolean('is_active')]);
        $this->audit->log('notice.updated', $notice, after: ['audience' => $notice->audience, 'is_active' => $notice->is_active]);

        return back()->with('success', __('notices.saved'));
    }

    public function toggle(Notice $notice): RedirectResponse
    {
        $notice->update(['is_active' => ! $notice->is_active]);
        $this->audit->log('notice.updated', $notice, after: ['is_active' => $notice->is_active]);

        return back()->with('success', __('notices.saved'));
    }

    public function destroy(Notice $notice): RedirectResponse
    {
        $this->audit->log('notice.deleted', $notice, before: ['body' => $notice->body]);
        $notice->delete();

        return back()->with('success', __('notices.deleted'));
    }
}

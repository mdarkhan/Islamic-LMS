<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FaqRequest;
use App\Models\Faq;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.faqs.index', [
            'faqs' => Faq::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(FaqRequest $request): RedirectResponse
    {
        $faq = Faq::query()->create($request->validated() + [
            'sort_order' => $request->input('sort_order') ?? ((int) Faq::query()->max('sort_order') + 1),
            'is_published' => $request->boolean('is_published'),
        ]);
        $this->audit->log('faq.created', $faq, after: ['question' => $faq->question, 'is_published' => $faq->is_published]);

        return back()->with('success', __('faqs.saved'));
    }

    public function update(FaqRequest $request, Faq $faq): RedirectResponse
    {
        $faq->update($request->validated() + ['is_published' => $request->boolean('is_published')]);
        $this->audit->log('faq.updated', $faq, after: ['question' => $faq->question, 'is_published' => $faq->is_published]);

        return back()->with('success', __('faqs.saved'));
    }

    public function togglePublish(Faq $faq): RedirectResponse
    {
        $faq->update(['is_published' => ! $faq->is_published]);
        $this->audit->log('faq.updated', $faq, after: ['is_published' => $faq->is_published]);

        return back()->with('success', __('faqs.saved'));
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $this->audit->log('faq.deleted', $faq, before: ['question' => $faq->question]);
        $faq->delete();

        return back()->with('success', __('faqs.deleted'));
    }
}

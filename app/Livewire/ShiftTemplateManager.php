<?php

namespace App\Livewire;

use App\Models\FeedPost;
use App\Models\ShiftTemplate;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ShiftTemplateManager extends Component
{
    public string $activeCategory = 'all';

    // ── Form state ──────────────────────────────────────────────────────────
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $formCategory = 'breakdown';

    public string $formTitle = '';

    public string $formBody = '';

    /** @var array<string, mixed> */
    public array $formRequired = [];

    /**
     * @return array<mixed>
     */
    protected function rules(): array
    {
        return [
            'formCategory' => 'required|in:'.implode(',', FeedPost::CATEGORIES),
            'formTitle' => 'required|string|max:255',
            'formBody' => 'required|string|max:5000',
            'formRequired' => 'nullable|array',
            'formRequired.*' => 'string|max:100',
        ];
    }

    public function mount(): void
    {
        abort_unless(
            Auth::user()?->hasRole(['admin', 'supervisor', 'manager', 'safety_officer']),
            403
        );
    }

    public function render(): View
    {
        $query = ShiftTemplate::with('creator:id,name')->latest();

        if ($this->activeCategory !== 'all') {
            $query->where('category', $this->activeCategory);
        }

        return view('livewire.shift-template-manager', [
            'templates' => $query->get(),
            'categories' => array_merge(['all'], FeedPost::CATEGORIES),
        ]);
    }

    public function setCategory(string $category): void
    {
        $this->activeCategory = $category;
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $template = ShiftTemplate::findOrFail($id);
        $this->editingId = $template->id;
        $this->formCategory = $template->category;
        $this->formTitle = $template->title;
        $this->formBody = $template->template_body;
        $this->formRequired = $template->required_fields ?? [];
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        $user = Auth::user();

        if ($this->editingId) {
            $template = ShiftTemplate::findOrFail($this->editingId);
            $this->authorize('update', $template);
            $template->update([
                'category' => $this->formCategory,
                'title' => $this->formTitle,
                'template_body' => $this->formBody,
                'required_fields' => $this->formRequired ?: null,
            ]);
        } else {
            $this->authorize('create', ShiftTemplate::class);
            ShiftTemplate::create([
                'team_id' => $user->current_team_id,
                'category' => $this->formCategory,
                'title' => $this->formTitle,
                'template_body' => $this->formBody,
                'required_fields' => $this->formRequired ?: null,
                'created_by' => $user->id,
            ]);
        }

        $this->resetForm();
        $this->dispatch('notify', type: 'success', message: 'Template saved.');
    }

    public function delete(int $id): void
    {
        $template = ShiftTemplate::findOrFail($id);
        $this->authorize('delete', $template);
        $template->delete();
        $this->dispatch('notify', type: 'success', message: 'Template deleted.');
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->formCategory = 'breakdown';
        $this->formTitle = '';
        $this->formBody = '';
        $this->formRequired = [];
        $this->resetValidation();
    }
}

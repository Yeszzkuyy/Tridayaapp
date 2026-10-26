<?php

namespace App\Livewire;

use App\Models\ProjectStatus;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class ProjectStatusList extends Component
{
    use WithPagination;

    public $name = '';
    public $color = '';
    public $is_default = false;
    public $editId = null;
    public $editName = '';
    public $editColor = '';
    public $editIsDefault = false;

    protected $rules = [
        'name' => 'required|string|max:255',
        'color' => 'nullable|string|max:50',
        'is_default' => 'boolean',
    ];

    public function render()
    {
        $statuses = ProjectStatus::latest()->paginate(10);
        return view('livewire.project-status-list', compact('statuses'));
    }

    /**
     * Otorisasi wajib diperiksa SETIAP aksi mutasi dijalankan.
     * Middleware route (permission:manage-monitoring) hanya menutup GET halaman;
     * request /livewire/update tidak menjalankan ulang middleware tersebut,
     * jadi pemeriksaan method-level ini adalah lapisan utamanya.
     */
    private function authorizeManage(): void
    {
        Gate::authorize('manage', ProjectStatus::class);
    }

    public function save()
    {
        $this->authorizeManage();
        $this->validate();

        ProjectStatus::create([
            'name' => $this->name,
            'color' => $this->color ?? '#3b82f6',
            'is_default' => $this->is_default ? true : false,
        ]);

        $this->reset(['name', 'color', 'is_default']);
        session()->flash('message', __('Status added successfully!'));
    }

    public function edit($id)
    {
        $status = ProjectStatus::findOrFail($id);
        $this->editId = $status->id;
        $this->editName = $status->name;
        $this->editColor = $status->color;
        $this->editIsDefault = $status->is_default;
    }

    public function update()
    {
        $this->authorizeManage();
        $this->validate([
            'editName' => 'required|string|max:255',
            'editColor' => 'nullable|string|max:50',
            'editIsDefault' => 'boolean',
        ]);

        $status = ProjectStatus::findOrFail($this->editId);
        $status->update([
            'name' => $this->editName,
            'color' => $this->editColor,
            'is_default' => $this->editIsDefault ? true : false,
        ]);

        $this->reset(['editId', 'editName', 'editColor', 'editIsDefault']);
        session()->flash('message', __('Status updated successfully!'));
    }

    public function delete($id)
    {
        $this->authorizeManage();
        $status = ProjectStatus::findOrFail($id);
        $status->delete();
        session()->flash('message', __('Status deleted successfully!'));
    }

    public function cancelEdit()
    {
        $this->reset(['editId', 'editName', 'editColor', 'editIsDefault']);
    }
}

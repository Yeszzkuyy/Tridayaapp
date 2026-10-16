<?php

namespace App\Livewire;

use App\Models\ProjectStatus;
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
    ];

    public function render()
    {
        $statuses = ProjectStatus::latest()->paginate(10);
        return view('livewire.project-status-list', compact('statuses'));
    }

    public function save()
    {
        $this->validate();

        ProjectStatus::create([
            'name' => $this->name,
            'color' => $this->color ?? '#3b82f6',
            'is_default' => $this->is_default ? true : false,
        ]);

        $this->reset(['name', 'color', 'is_default']);
        session()->flash('message', __('Status berhasil ditambahkan!'));
    }

    public function edit($id)
    {
        $status = ProjectStatus::find($id);
        $this->editId = $id;
        $this->editName = $status->name;
        $this->editColor = $status->color;
        $this->editIsDefault = $status->is_default;
    }

    public function update()
    {
        $this->validate([
            'editName' => 'required|string|max:255',
            'editColor' => 'nullable|string|max:50',
        ]);

        $status = ProjectStatus::find($this->editId);
        $status->update([
            'name' => $this->editName,
            'color' => $this->editColor,
            'is_default' => $this->editIsDefault ? true : false,
        ]);

        $this->reset(['editId', 'editName', 'editColor', 'editIsDefault']);
        session()->flash('message', __('Status berhasil diupdate!'));
    }

    public function delete($id)
    {
        $status = ProjectStatus::find($id);
        $status->delete();
        session()->flash('message', __('Status berhasil dihapus!'));
    }

    public function cancelEdit()
    {
        $this->reset(['editId', 'editName', 'editColor', 'editIsDefault']);
    }
}
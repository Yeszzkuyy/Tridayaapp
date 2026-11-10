<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rapikan data lama agar identitas engineer memakai akun user.
     *
     * Aturan aman: hanya mengisi bila nama engineer cocok PERSIS (tanpa beda
     * huruf besar/kecil) dengan tepat satu akun ber-role teknisi. Nama ganda
     * dibiarkan kosong supaya tidak salah orang, dan bisa dirapikan lewat UI.
     */
    public function up(): void
    {
        $techIds = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereIn('roles.name', ['technician', 'lead-technician', 'prakerin-technician'])
            ->whereIn('model_has_roles.model_type', ['App\\Models\\User', 'App\\User'])
            ->pluck('model_has_roles.model_id')
            ->unique()
            ->values();

        if ($techIds->isEmpty()) {
            return;
        }

        $users = DB::table('users')
            ->whereIn('id', $techIds)
            ->get(['id', 'name']);

        $byName = [];
        foreach ($users as $user) {
            $key = $this->normalize($user->name);
            if ($key !== '') {
                $byName[$key][] = (int) $user->id;
            }
        }

        $matchOne = function (?string $name) use ($byName): ?int {
            $key = $this->normalize($name);
            $ids = $byName[$key] ?? [];

            return count($ids) === 1 ? $ids[0] : null;
        };

        // Project: PIC + engineer pendukung.
        DB::table('projects')->orderBy('id')->chunkById(100, function ($projects) use ($matchOne) {
            foreach ($projects as $project) {
                $updates = [];

                if (blank($project->teknisi_user_id)) {
                    $picId = $matchOne($project->pic_engineer);
                    if ($picId) {
                        $updates['teknisi_user_id'] = $picId;
                    }
                }

                if ($updates) {
                    DB::table('projects')->where('id', $project->id)->update($updates);
                }

                foreach ($this->splitNames($project->support_technicians) as $name) {
                    $supportId = $matchOne($name);
                    if (! $supportId) {
                        continue;
                    }

                    $exists = DB::table('project_supports')
                        ->where('project_id', $project->id)
                        ->where('user_id', $supportId)
                        ->exists();

                    if (! $exists) {
                        DB::table('project_supports')->insert([
                            'project_id' => $project->id,
                            'user_id' => $supportId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        });

        // Survey: PIC engineer.
        DB::table('surveys')->orderBy('id')->chunkById(100, function ($surveys) use ($matchOne) {
            foreach ($surveys as $survey) {
                if (! blank($survey->technician_user_id)) {
                    continue;
                }

                $picId = $matchOne($survey->pic);
                if ($picId) {
                    DB::table('surveys')->where('id', $survey->id)->update(['technician_user_id' => $picId]);
                }
            }
        });

        // Instalasi: PIC, customer, dan sales dari project.
        DB::table('instalasis')->orderBy('id')->chunkById(100, function ($instalasis) use ($matchOne) {
            foreach ($instalasis as $instalasi) {
                $updates = [];

                if (blank($instalasi->technician_user_id)) {
                    $picId = $matchOne($instalasi->technician_pic);
                    if ($picId) {
                        $updates['technician_user_id'] = $picId;
                    }
                }

                if ($instalasi->project_id) {
                    $project = DB::table('projects')->find($instalasi->project_id);
                    if ($project) {
                        if (blank($instalasi->customer_id) && $project->customer_id) {
                            $updates['customer_id'] = $project->customer_id;
                        }
                        if (blank($instalasi->sales_user_id) && $project->sales_user_id) {
                            $updates['sales_user_id'] = $project->sales_user_id;
                        }
                    }
                }

                if ($updates) {
                    DB::table('instalasis')->where('id', $instalasi->id)->update($updates);
                }
            }
        });

        // Technical request: tautkan ke project bila jelas.
        DB::table('technical_requests')
            ->whereNull('project_id')
            ->orderBy('id')
            ->chunkById(100, function ($requests) {
                foreach ($requests as $request) {
                    $projectId = DB::table('surveys')
                        ->where('technical_request_id', $request->id)
                        ->whereNotNull('project_id')
                        ->value('project_id');

                    if (! $projectId && $request->lead_id) {
                        $projectIds = DB::table('proposals')
                            ->join('projects', 'projects.proposal_id', '=', 'proposals.id')
                            ->where('proposals.lead_id', $request->lead_id)
                            ->pluck('projects.id')
                            ->unique()
                            ->values();

                        if ($projectIds->count() === 1) {
                            $projectId = $projectIds->first();
                        }
                    }

                    if ($projectId) {
                        DB::table('technical_requests')->where('id', $request->id)->update([
                            'project_id' => $projectId,
                        ]);
                    }
                }
            });

        // Price request: tautkan project dari proposal.
        DB::table('price_requests')
            ->whereNull('project_id')
            ->whereNotNull('proposal_id')
            ->orderBy('id')
            ->chunkById(100, function ($requests) {
                foreach ($requests as $request) {
                    $projectId = DB::table('projects')
                        ->where('proposal_id', $request->proposal_id)
                        ->value('id');

                    if ($projectId) {
                        DB::table('price_requests')->where('id', $request->id)->update([
                            'project_id' => $projectId,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Sengaja tidak menghapus tautan hasil backfill: datanya tetap valid
        // walau kolom baru di-drop oleh migrasi skema.
    }

    private function normalize(?string $name): string
    {
        return mb_strtolower(trim((string) $name));
    }

    /** @return array<int, string> */
    private function splitNames(?string $value): array
    {
        if (blank($value)) {
            return [];
        }

        return collect(explode(',', (string) $value))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
};

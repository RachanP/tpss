<?php

namespace App\Console\Commands;

use App\Jobs\ConflictRecomputeJob;
use App\Models\AcademicYear;
use App\Models\ScheduleConflictRun;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RecomputeConflictsCommand extends Command
{
    protected $signature = 'conflicts:recompute
        {--academic-year= : Academic year id to recompute}
        {--all : Recompute every academic year}
        {--active-or-scheduling : Recompute active or scheduling academic years}
        {--queue : Queue jobs instead of running inline}
        {--sync : Run immediately in the current process}';

    protected $description = 'Queue or run schedule conflict recomputation for async read models.';

    public function handle(): int
    {
        $academicYearOption = $this->option('academic-year');
        $all = (bool) $this->option('all');
        $activeOrScheduling = (bool) $this->option('active-or-scheduling');
        $selectedModes = collect([$academicYearOption, $all, $activeOrScheduling])->filter()->count();

        if ($selectedModes !== 1) {
            $this->error('Specify exactly one of --academic-year=ID, --all, or --active-or-scheduling.');

            return self::FAILURE;
        }

        if ($this->option('sync') && $this->option('queue')) {
            $this->error('Choose only one of --sync or --queue.');

            return self::FAILURE;
        }

        $academicYears = match (true) {
            $all => AcademicYear::query()->select(['id', 'name', 'semester'])->orderBy('id')->get(),
            $activeOrScheduling => AcademicYear::query()
                ->select(['id', 'name', 'semester'])
                ->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->orWhere('phase', 'scheduling'))
                ->orderBy('id')
                ->get(),
            default => $this->academicYearFromOption((int) $academicYearOption),
        };

        if ($academicYears->isEmpty()) {
            if ($academicYearOption) {
                return self::FAILURE;
            }

            $this->warn('No academic years found.');

            return self::SUCCESS;
        }

        foreach ($academicYears as $academicYear) {
            $run = $this->createRun((int) $academicYear->id);
            $job = new ConflictRecomputeJob(
                academicYearId: (int) $academicYear->id,
                runId: (int) $run->id,
                generation: (int) $run->generation,
                source: $this->runSource()
            );

            if ($this->option('sync')) {
                app()->call([$job, 'handle']);
                $this->info("Recomputed academic year {$academicYear->id} synchronously in run {$run->id}.");
            } else {
                ConflictRecomputeJob::dispatch((int) $academicYear->id, (int) $run->id, (int) $run->generation, $this->runSource());
                $this->info("Queued recompute for academic year {$academicYear->id} in run {$run->id}.");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, AcademicYear>
     */
    private function academicYearFromOption(int $academicYearId): Collection
    {
        $academicYear = AcademicYear::query()
            ->select(['id', 'name', 'semester'])
            ->find($academicYearId);

        if (! $academicYear) {
            $this->error("Academic year {$academicYearId} was not found.");

            return collect();
        }

        return collect([$academicYear]);
    }

    private function createRun(int $academicYearId): ScheduleConflictRun
    {
        return DB::transaction(function () use ($academicYearId): ScheduleConflictRun {
            $latestGeneration = (int) ScheduleConflictRun::query()
                ->where('academic_year_id', $academicYearId)
                ->lockForUpdate()
                ->max('generation');

            return ScheduleConflictRun::query()->create([
                'academic_year_id' => $academicYearId,
                'status' => 'pending',
                'generation' => $latestGeneration + 1,
                'source' => $this->runSource(),
                'requested_at' => now(),
                'result_count' => 0,
            ]);
        });
    }

    private function runSource(): string
    {
        return $this->option('active-or-scheduling') ? 'scheduled' : 'manual';
    }
}

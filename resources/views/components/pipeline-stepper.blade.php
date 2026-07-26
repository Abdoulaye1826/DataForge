@php
    $steps = \App\Enums\PipelineStage::ordered();
    $progress = $project->pipelineProgress();
    $lastDoneIndex = -1;
    foreach ($steps as $index => $step) {
        if ($progress[$step->value]) {
            $lastDoneIndex = $index;
        }
    }
    $activeIndex = min($lastDoneIndex + 1, count($steps) - 1);
@endphp

<div class="df-pipeline">
    @foreach ($steps as $index => $step)
        @php
            $done = $progress[$step->value];
            $isActive = ! $done && $index === $activeIndex;
        @endphp
        <div class="df-pipe-step {{ $done ? 'done' : '' }} {{ $isActive ? 'active' : '' }}">
            <div class="df-pipe-dot">{{ $done ? '✓' : $index + 1 }}</div>
            <div class="df-pipe-label">{{ $step->label() }}</div>
        </div>
    @endforeach
</div>

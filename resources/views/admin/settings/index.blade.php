@extends('layouts.app')
@section('title', 'Admin - Settings')
@section('content')
<h1 class="mb-4">Settings</h1>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <strong>Could not save:</strong>
        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<p class="text-muted">
    Intervals, caps, and ladder lengths that govern the escalation sweep.
    Values must be whole numbers of at least 1 — a smaller value would stall
    the sweep. By default a change applies to new cases only.
</p>

<form method="POST" action="{{ route('admin.settings.update') }}">
    @csrf
    @method('PUT')

    @foreach($settings as $setting)
        <div class="mb-3 row">
            <label for="{{ $setting['field'] }}" class="col-sm-5 col-form-label">{{ $setting['label'] }}</label>
            <div class="col-sm-4">
                @if($setting['type'] === 'flag')
                    <select class="form-select" id="{{ $setting['field'] }}" name="{{ $setting['field'] }}">
                        <option value="0" @selected(old($setting['field'], $setting['value']) === '0')>No</option>
                        <option value="1" @selected(old($setting['field'], $setting['value']) === '1')>Yes</option>
                    </select>
                @else
                    <input type="number" min="1" step="1" class="form-control"
                           id="{{ $setting['field'] }}" name="{{ $setting['field'] }}"
                           value="{{ old($setting['field'], $setting['value']) }}" required>
                @endif
                @if(!empty($setting['help']))
                    <div class="form-text">{{ $setting['help'] }}</div>
                @endif
                <div class="form-text"><code>{{ $setting['key'] }}</code></div>
            </div>
        </div>
    @endforeach

    <button type="submit" class="btn btn-primary">Save settings</button>
</form>
@endsection

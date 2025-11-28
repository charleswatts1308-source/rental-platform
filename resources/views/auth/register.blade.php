@extends('layouts.app')

@section('title', 'Register')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Create Your Account</h4>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('register') }}">
                    @csrf

                    <!-- Name -->
                    <div class="floating-label mb-3">
                        <input id="name" type="text" class="form-control @error('name') is-invalid @enderror"
                               name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
                        <label for="name">Name</label>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Email Address -->
                    <div class="floating-label mb-3">
                        <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                               name="email" value="{{ old('email') }}" required autocomplete="username">
                        <label for="email">Email</label>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div class="floating-label mb-3">
                        <input id="password" type="password" class="form-control @error('password') is-invalid @enderror"
                               name="password" required autocomplete="new-password">
                        <label for="password">Password</label>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Confirm Password -->
                    <div class="floating-label mb-3">
                        <input id="password_confirmation" type="password" class="form-control @error('password_confirmation') is-invalid @enderror"
                               name="password_confirmation" required autocomplete="new-password">
                        <label for="password_confirmation">Confirm Password</label>
                        @error('password_confirmation')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <a href="{{ route('login') }}" class="text-decoration-none">
                            Already registered?
                        </a>
                        <button type="submit" class="btn btn-primary">
                            Register
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .floating-label {
        position: relative;
        margin-bottom: 10px;
    }

    .floating-label input {
        width: 100%;
        padding: 1.2rem 0.75rem 0.3rem 0.75rem;
        border: 2px solid #ced4da;
        border-radius: 6px;
        font-size: 0.875rem;
        background: white;
        color: #495057;
        transition: all 0.2s ease-in-out;
        outline: none;
    }

    .floating-label input::placeholder {
        opacity: 0;
    }

    .floating-label input:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.15);
    }

    .floating-label input.is-invalid {
        border-color: #dc3545;
    }

    .floating-label input.is-invalid:focus {
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.15);
    }

    .floating-label label {
        position: absolute;
        top: 1rem;
        left: 0.75rem;
        font-size: 0.875rem;
        color: #6c757d;
        pointer-events: none;
        transition: all 0.2s ease-in-out;
        background: white;
        padding: 0 6px;
        z-index: 2;
        transform-origin: left top;
    }

    .floating-label input:focus + label,
    .floating-label input.has-value + label {
        top: -0.5rem;
        left: 0.5rem;
        font-size: 0.75rem;
        color: #007bff;
        font-weight: 500;
    }

    .floating-label input.is-invalid + label {
        color: #dc3545;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const floatingInputs = document.querySelectorAll('.floating-label input');

        function updateLabelState(input) {
            const hasValue = input.value && input.value.trim() !== '';
            if (hasValue) {
                input.classList.add('has-value');
            } else {
                input.classList.remove('has-value');
            }
        }

        floatingInputs.forEach(function(input) {
            setTimeout(() => updateLabelState(input), 10);

            input.addEventListener('input', function() {
                updateLabelState(this);
            });

            input.addEventListener('blur', function() {
                updateLabelState(this);
            });
        });
    });
</script>
@endsection

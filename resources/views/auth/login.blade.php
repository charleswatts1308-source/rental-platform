@extends('layouts.app')

@section('title', 'Login')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Login to Your Account</h4>
            </div>
            <div class="card-body p-4">
                <!-- Session Status -->
                @if (session('status'))
                    <div class="alert alert-success mb-3" role="alert">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <!-- Email Address -->
                    <div class="floating-label mb-3">
                        <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                               name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                        <label for="email">Email</label>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div class="floating-label mb-3">
                        <input id="password" type="password" class="form-control @error('password') is-invalid @enderror"
                               name="password" required autocomplete="current-password">
                        <label for="password">Password</label>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Remember Me -->
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember_me" name="remember">
                        <label class="form-check-label" for="remember_me">
                            Remember me
                        </label>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4">
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-decoration-none">
                                Forgot your password?
                            </a>
                        @endif

                        <button type="submit" class="btn btn-primary">
                            Log in
                        </button>
                    </div>

                    <div class="text-center mt-3">
                        <p class="mb-0">Don't have an account? <a href="{{ route('register') }}" class="text-decoration-none">Register here</a></p>
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

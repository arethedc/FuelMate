@extends('layouts.app')

@section('title', 'FuelMate - Fuel and Trip Cost Predictor')

@section('content')
@php
    $formData = [
        'distance' => old('distance', $result['input']['distance'] ?? ''),
        'speed' => old('speed', $result['input']['speed'] ?? ''),
        'traffic' => old('traffic', $result['input']['traffic'] ?? ''),
        'fuel_price' => old('fuel_price', $result['input']['fuel_price'] ?? ''),
        'vehicle_type' => old('vehicle_type', $result['input']['vehicle_type'] ?? ''),
    ];
@endphp

<div class="hero-section py-5">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <h1 class="display-5 fw-bold mb-3">FuelMate</h1>
                <p class="lead mb-0">
                    Predict fuel usage and trip cost for motorcycles and cars using a real, beginner-friendly
                    multiple linear regression model with interaction-aware smarter predictions.
                </p>
            </div>
            <div class="col-lg-5">
                <div class="stat-card p-4">
                    <h2 class="h5 mb-3">Quick Overview</h2>
                    <ul class="mb-0 ps-3">
                        <li>No database required</li>
                        <li>Hardcoded sample dataset</li>
                        <li>Visible regression math in PHP</li>
                        <li>Confidence range + model quality metrics</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container pb-5">
    <section class="content-card p-4 p-md-5 mb-4">
        <h2 class="h4 mb-3">About the System</h2>
        <p class="mb-0">
            FuelMate is an educational predictor that estimates how many liters you may consume on a trip and how much
            it may cost in pesos. It uses distance, average speed, traffic level, and vehicle type to generate a
            prediction through linear regression.
        </p>
    </section>

    <section class="content-card p-4 p-md-5 mb-4">
        <h2 class="h4 mb-3">Predictor Form</h2>

        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Please fix the following:</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('predict') }}" method="POST" class="row g-3">
            @csrf
            <div class="col-md-6">
                <label for="distance" class="form-label">Distance Traveled (km)</label>
                <input
                    type="number"
                    step="0.01"
                    class="form-control @error('distance') is-invalid @enderror"
                    id="distance"
                    name="distance"
                    value="{{ $formData['distance'] }}"
                    placeholder="e.g. 45"
                    required
                >
                @error('distance')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label for="speed" class="form-label">Average Speed (km/h)</label>
                <input
                    type="number"
                    step="0.01"
                    class="form-control @error('speed') is-invalid @enderror"
                    id="speed"
                    name="speed"
                    value="{{ $formData['speed'] }}"
                    placeholder="e.g. 40"
                    required
                >
                @error('speed')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label for="traffic" class="form-label">Traffic Level</label>
                <select class="form-select @error('traffic') is-invalid @enderror" id="traffic" name="traffic" required>
                    <option value="">Choose level</option>
                    <option value="1" {{ (string) $formData['traffic'] === '1' ? 'selected' : '' }}>1 - Light</option>
                    <option value="2" {{ (string) $formData['traffic'] === '2' ? 'selected' : '' }}>2 - Moderate</option>
                    <option value="3" {{ (string) $formData['traffic'] === '3' ? 'selected' : '' }}>3 - Heavy</option>
                </select>
                @error('traffic')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label for="fuel_price" class="form-label">Fuel Price per Liter (PHP)</label>
                <input
                    type="number"
                    step="0.01"
                    class="form-control @error('fuel_price') is-invalid @enderror"
                    id="fuel_price"
                    name="fuel_price"
                    value="{{ $formData['fuel_price'] }}"
                    placeholder="e.g. 68"
                    required
                >
                @error('fuel_price')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label for="vehicle_type" class="form-label">Vehicle Type</label>
                <select class="form-select @error('vehicle_type') is-invalid @enderror" id="vehicle_type" name="vehicle_type" required>
                    <option value="">Choose vehicle</option>
                    <option value="motorcycle" {{ $formData['vehicle_type'] === 'motorcycle' ? 'selected' : '' }}>Motorcycle</option>
                    <option value="car" {{ $formData['vehicle_type'] === 'car' ? 'selected' : '' }}>Car</option>
                </select>
                @error('vehicle_type')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary px-4">Predict Trip</button>
            </div>
        </form>
    </section>

    @if (!empty($result))
        <section class="content-card p-4 p-md-5 mb-4 result-card">
            <h2 class="h4 mb-4">Result</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="metric-box p-3">
                        <p class="text-muted mb-1">Predicted Fuel Used</p>
                        <p class="h3 mb-0">{{ number_format($result['fuel_liters'], 2) }} L</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="metric-box p-3">
                        <p class="text-muted mb-1">Estimated Trip Cost</p>
                        <p class="h3 mb-0">PHP {{ number_format($result['trip_cost'], 2) }}</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="metric-box p-3">
                        <p class="text-muted mb-1">{{ $result['fuel_range']['confidence'] }}% Likely Fuel Range</p>
                        <p class="h5 mb-0">
                            {{ number_format($result['fuel_range']['min'], 2) }} L
                            to
                            {{ number_format($result['fuel_range']['max'], 2) }} L
                        </p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="metric-box p-3">
                        <p class="text-muted mb-1">Estimated Efficiency</p>
                        <p class="h5 mb-0">{{ number_format($result['efficiency_kmpl'], 2) }} km/L</p>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <h3 class="h6 text-uppercase text-muted">Interpretation</h3>
                <p class="mb-0">{{ $result['interpretation'] }}</p>
            </div>
            @if (!empty($result['tips']))
                <div class="mt-4">
                    <h3 class="h6 text-uppercase text-muted">Smart Tips</h3>
                    <ul class="mb-0 ps-3">
                        @foreach ($result['tips'] as $tip)
                            <li>{{ $tip }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>
    @endif

    <section class="content-card p-4 p-md-5 mb-4">
        <h2 class="h4 mb-3">Model Quality</h2>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="coefficient-box">
                    <strong>R²:</strong> {{ number_format($metrics['r2'] * 100, 2) }}%
                </div>
            </div>
            <div class="col-md-4">
                <div class="coefficient-box">
                    <strong>RMSE:</strong> {{ number_format($metrics['rmse'], 3) }} L
                </div>
            </div>
            <div class="col-md-4">
                <div class="coefficient-box">
                    <strong>MAE:</strong> {{ number_format($metrics['mae'], 3) }} L
                </div>
            </div>
        </div>
        <p class="text-muted mt-3 mb-0">
            Higher R² and lower RMSE / MAE generally indicate better fit on the sample dataset.
        </p>
    </section>

    <section class="content-card p-4 p-md-5 mb-4">
        <h2 class="h4 mb-3">Sample Dataset (Hardcoded in PHP)</h2>
        <p class="text-muted">
            This dataset is used by the regression model for training. Fuel liters is the target value.
        </p>
        <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Distance (km)</th>
                        <th>Speed (km/h)</th>
                        <th>Traffic</th>
                        <th>Vehicle</th>
                        <th>Fuel Liters (Actual)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($dataset as $row)
                        <tr>
                            <td>{{ $row['distance'] }}</td>
                            <td>{{ $row['speed'] }}</td>
                            <td>{{ $row['traffic'] }}</td>
                            <td>{{ ucfirst($row['vehicle_type']) }}</td>
                            <td>{{ number_format($row['fuel_liters'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="content-card p-4 p-md-5">
        <h2 class="h4 mb-3">How Linear Regression Works</h2>
        <p>
            FuelMate uses multiple linear regression with engineered features:
        </p>
        <p class="formula-box">
            Fuel = b0 + b1(distance) + b2(speed) + b3(traffic) + b4(vehicle_flag)
            + b5(distance×traffic) + b6(speed×traffic) + b7(distance×vehicle_flag) + b8(traffic×vehicle_flag)
        </p>
        <p>
            The coefficients are computed in PHP using matrix operations and the normal equation:
            <code>beta = (X^T X)^-1 X^T y</code>.
            Here, <code>vehicle_flag</code> is <code>0</code> for motorcycle and <code>1</code> for car.
        </p>

        <div class="row g-3 mt-1">
            @foreach ($featureLabels as $index => $label)
                <div class="col-md-6 col-lg-4">
                    <div class="coefficient-box">
                        <strong>b{{ $index }} ({{ $label }}):</strong> {{ number_format($coefficients[$index], 6) }}
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</div>

<footer class="footer py-4 mt-4">
    <div class="container text-center">
        <p class="mb-0">FuelMate | Educational linear regression demo using Laravel Blade and PHP</p>
    </div>
</footer>
@endsection

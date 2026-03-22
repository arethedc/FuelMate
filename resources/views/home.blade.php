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

    $activeVehicle = $result['input']['vehicle_type'] ?? ($modelInfo['vehicle_type'] ?? 'motorcycle');
@endphp

<div class="hero-section py-5">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <h1 class="display-5 fw-bold mb-3">FuelMate</h1>
                <p class="lead mb-0">
                    Estimate fuel usage and trip cost in a simple, practical way using linear regression.
                </p>
            </div>
            <div class="col-lg-5">
                <div class="stat-card p-4">
                    <h2 class="h5 mb-3">What You Get</h2>
                    <ul class="mb-0 ps-3">
                        <li>Fuel estimate in liters</li>
                        <li>Total trip cost in pesos</li>
                        <li>Live trip history after each prediction</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container pb-5">
    <section class="content-card p-4 p-md-5 mb-4">
        <h2 class="h4 mb-3">About FuelMate</h2>
        <p class="mb-0">
            FuelMate uses distance, speed, traffic, and vehicle type to estimate fuel consumption. The model is built with
            linear regression so each input has a measurable effect on the final prediction.
        </p>
    </section>

    <section class="content-card p-4 p-md-5 mb-4">
        <h2 class="h4 mb-3">How Linear Regression Is Used</h2>
        @php
            $consistencyScore = $modelInfo['metrics']['r2'] * 100;
            $typicalError = $modelInfo['metrics']['rmse'];
            $consistencyLabel = $consistencyScore >= 90 ? 'High' : ($consistencyScore >= 75 ? 'Good' : 'Moderate');
        @endphp

        <p class="mb-3">
            FuelMate looks at past trip patterns, then estimates your fuel use based on your distance, speed, and traffic level.
            It does not guess randomly. It follows a consistent formula and updates the estimate from your inputs.
        </p>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="coefficient-box h-100">
                    <strong>Step 1</strong>
                    <p class="mb-0 mt-2">Past trip records teach the model how fuel changes in real driving.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="coefficient-box h-100">
                    <strong>Step 2</strong>
                    <p class="mb-0 mt-2">It learns how much each factor (distance, speed, traffic) affects fuel use.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="coefficient-box h-100">
                    <strong>Step 3</strong>
                    <p class="mb-0 mt-2">Your trip values are applied to that pattern to produce your estimate.</p>
                </div>
            </div>
        </div>

        <p class="formula-box mb-3">
            Estimated Fuel = Base + (Distance Effect) + (Speed Effect) + (Traffic Effect)
        </p>

        <p class="mb-2">
            Current vehicle model: <strong>{{ ucfirst($activeVehicle) }}</strong>
        </p>
        <p class="mb-2">
            Prediction consistency: <strong>{{ $consistencyLabel }}</strong>
            ({{ number_format($consistencyScore, 1) }}%)
        </p>
        <p class="mb-0 text-muted">
            Typical difference from real trips: about <strong>{{ number_format($typicalError, 2) }} liters</strong>.
        </p>

        <details class="mt-3">
            <summary class="text-primary fw-semibold">Show technical details</summary>
            <div class="row g-3 mt-1">
                @foreach ($modelInfo['feature_labels'] as $index => $label)
                    <div class="col-md-6 col-lg-3">
                        <div class="coefficient-box">
                            <strong>{{ $label }}:</strong><br>
                            {{ number_format($modelInfo['coefficients'][$index], 6) }}
                        </div>
                    </div>
                @endforeach
                <div class="col-md-4">
                    <div class="coefficient-box"><strong>R2:</strong> {{ number_format($modelInfo['metrics']['r2'] * 100, 2) }}%</div>
                </div>
                <div class="col-md-4">
                    <div class="coefficient-box"><strong>RMSE:</strong> {{ number_format($modelInfo['metrics']['rmse'], 3) }} L</div>
                </div>
                <div class="col-md-4">
                    <div class="coefficient-box"><strong>MAE:</strong> {{ number_format($modelInfo['metrics']['mae'], 3) }} L</div>
                </div>
            </div>
        </details>
    </section>

    <section class="content-card p-4 p-md-5 mb-4">
        <h2 class="h4 mb-3">Trip Predictor</h2>

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
            <h2 class="h4 mb-4">Prediction Result</h2>
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
                        <p class="text-muted mb-1">Likely Fuel Range ({{ $result['fuel_range']['confidence'] }}%)</p>
                        <p class="h5 mb-0">{{ number_format($result['fuel_range']['min'], 2) }} L to {{ number_format($result['fuel_range']['max'], 2) }} L</p>
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
                <h3 class="h6 text-uppercase text-muted">Simple Explanation</h3>
                <p class="mb-0">{{ $result['interpretation'] }}</p>
            </div>
            @if (!empty($result['tips']))
                <div class="mt-4">
                    <h3 class="h6 text-uppercase text-muted">Practical Tips</h3>
                    <ul class="mb-0 ps-3">
                        @foreach ($result['tips'] as $tip)
                            <li>{{ $tip }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>
    @endif

    <section class="content-card p-4 p-md-5">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h2 class="h4 mb-0">Trip History</h2>
            <small class="text-muted">Every prediction is saved here for this browser session.</small>
        </div>

        @if (empty($tripHistory))
            <div class="empty-state p-4">
                <p class="mb-0">No trips yet. Submit your first prediction to start building your trip history.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-striped table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Vehicle</th>
                            <th>Distance</th>
                            <th>Speed</th>
                            <th>Traffic</th>
                            <th>Fuel Price</th>
                            <th>Fuel Used</th>
                            <th>Trip Cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tripHistory as $row)
                            <tr>
                                <td>{{ $row['date'] }}</td>
                                <td>{{ ucfirst($row['vehicle_type']) }}</td>
                                <td>{{ number_format($row['distance'], 1) }} km</td>
                                <td>{{ number_format($row['speed'], 1) }} km/h</td>
                                <td>{{ $row['traffic'] }}</td>
                                <td>PHP {{ number_format($row['fuel_price'], 2) }}</td>
                                <td>{{ number_format($row['fuel_liters'], 2) }} L</td>
                                <td>PHP {{ number_format($row['trip_cost'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>

<footer class="footer py-4 mt-4">
    <div class="container text-center">
        <p class="mb-0">FuelMate | Clean fuel and trip estimate tool with easy-to-read regression logic</p>
    </div>
</footer>
@endsection

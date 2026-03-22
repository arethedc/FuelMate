<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class FuelPredictorController extends Controller
{
    private const MIN_FUEL_LITERS = 0.1;
    private const HISTORY_SESSION_KEY = 'fuelmate_trip_history';
    private const MAX_HISTORY_ITEMS = 30;

    public function index(): View
    {
        $defaultVehicle = 'motorcycle';
        $model = $this->trainModel($defaultVehicle);

        return view('home', [
            'result' => null,
            'tripHistory' => session()->get(self::HISTORY_SESSION_KEY, []),
            'modelInfo' => [
                'vehicle_type' => $defaultVehicle,
                'coefficients' => $model['coefficients'],
                'metrics' => $model['metrics'],
                'feature_labels' => $this->getFeatureLabels(),
            ],
        ]);
    }

    public function predict(Request $request): View
    {
        $validated = $request->validate(
            [
                'distance' => ['required', 'numeric', 'min:1', 'max:3000'],
                'speed' => ['required', 'numeric', 'min:10', 'max:220'],
                'traffic' => ['required', 'integer', 'in:1,2,3'],
                'fuel_price' => ['required', 'numeric', 'min:1', 'max:200'],
                'vehicle_type' => ['required', 'in:motorcycle,car'],
            ],
            [
                'distance.required' => 'Please enter the travel distance in kilometers.',
                'distance.numeric' => 'Distance must be a valid number.',
                'distance.min' => 'Distance should be at least 1 km.',
                'distance.max' => 'Distance should not exceed 3000 km.',
                'speed.required' => 'Please enter the average speed.',
                'speed.numeric' => 'Average speed must be a number.',
                'speed.min' => 'Average speed should be at least 10 km/h.',
                'speed.max' => 'Average speed should be at most 220 km/h.',
                'traffic.required' => 'Please choose a traffic level.',
                'traffic.in' => 'Traffic level must be 1 (light), 2 (moderate), or 3 (heavy).',
                'fuel_price.required' => 'Please enter the fuel price per liter.',
                'fuel_price.numeric' => 'Fuel price must be a valid number.',
                'fuel_price.min' => 'Fuel price should be at least 1 peso per liter.',
                'fuel_price.max' => 'Fuel price should be at most 200 pesos per liter.',
                'vehicle_type.required' => 'Please select the vehicle type.',
                'vehicle_type.in' => 'Vehicle type must be motorcycle or car.',
            ]
        );

        $vehicleType = $validated['vehicle_type'];
        $distance = (float) $validated['distance'];
        $speed = (float) $validated['speed'];
        $traffic = (int) $validated['traffic'];
        $fuelPrice = (float) $validated['fuel_price'];

        $model = $this->trainModel($vehicleType);
        $coefficients = $model['coefficients'];
        $features = $this->buildFeatureVector($distance, $speed, $traffic);

        $predictedFuel = max(self::MIN_FUEL_LITERS, $this->predictFuel($coefficients, $features));
        $predictedCost = $predictedFuel * $fuelPrice;
        $efficiency = $distance / max($predictedFuel, self::MIN_FUEL_LITERS);
        $fuelRange = $this->buildPredictionRange($predictedFuel, $model['metrics']['rmse'], $traffic);

        $history = session()->get(self::HISTORY_SESSION_KEY, []);
        array_unshift($history, [
            'date' => now()->format('Y-m-d H:i'),
            'vehicle_type' => $vehicleType,
            'distance' => $distance,
            'speed' => $speed,
            'traffic' => $traffic,
            'fuel_price' => $fuelPrice,
            'fuel_liters' => $predictedFuel,
            'trip_cost' => $predictedCost,
        ]);

        $history = array_slice($history, 0, self::MAX_HISTORY_ITEMS);
        session()->put(self::HISTORY_SESSION_KEY, $history);

        return view('home', [
            'tripHistory' => $history,
            'modelInfo' => [
                'vehicle_type' => $vehicleType,
                'coefficients' => $coefficients,
                'metrics' => $model['metrics'],
                'feature_labels' => $this->getFeatureLabels(),
            ],
            'result' => [
                'fuel_liters' => $predictedFuel,
                'trip_cost' => $predictedCost,
                'fuel_range' => $fuelRange,
                'efficiency_kmpl' => $efficiency,
                'input' => $validated,
                'interpretation' => $this->buildInterpretation(
                    $predictedFuel,
                    $distance,
                    $traffic,
                    $vehicleType,
                    $model['metrics']
                ),
                'tips' => $this->buildEfficiencyTips($speed, $traffic, $vehicleType, $efficiency),
            ],
        ]);
    }

    /**
     * Internal training records used by linear regression.
     * These are not shown in the table. The visible table is prediction history.
     */
    private function getReferenceTrips(): array
    {
        return [
            ['vehicle_type' => 'motorcycle', 'distance' => 8, 'speed' => 25, 'traffic' => 3, 'fuel_liters' => 0.42],
            ['vehicle_type' => 'motorcycle', 'distance' => 12, 'speed' => 35, 'traffic' => 2, 'fuel_liters' => 0.43],
            ['vehicle_type' => 'motorcycle', 'distance' => 18, 'speed' => 40, 'traffic' => 2, 'fuel_liters' => 0.63],
            ['vehicle_type' => 'motorcycle', 'distance' => 22, 'speed' => 50, 'traffic' => 1, 'fuel_liters' => 0.69],
            ['vehicle_type' => 'motorcycle', 'distance' => 30, 'speed' => 55, 'traffic' => 1, 'fuel_liters' => 0.87],
            ['vehicle_type' => 'motorcycle', 'distance' => 35, 'speed' => 45, 'traffic' => 2, 'fuel_liters' => 1.08],
            ['vehicle_type' => 'motorcycle', 'distance' => 42, 'speed' => 38, 'traffic' => 3, 'fuel_liters' => 1.52],
            ['vehicle_type' => 'motorcycle', 'distance' => 50, 'speed' => 48, 'traffic' => 2, 'fuel_liters' => 1.51],
            ['vehicle_type' => 'motorcycle', 'distance' => 65, 'speed' => 60, 'traffic' => 1, 'fuel_liters' => 1.78],
            ['vehicle_type' => 'motorcycle', 'distance' => 75, 'speed' => 52, 'traffic' => 2, 'fuel_liters' => 2.21],
            ['vehicle_type' => 'motorcycle', 'distance' => 85, 'speed' => 40, 'traffic' => 3, 'fuel_liters' => 2.92],
            ['vehicle_type' => 'motorcycle', 'distance' => 95, 'speed' => 58, 'traffic' => 1, 'fuel_liters' => 2.58],
            ['vehicle_type' => 'car', 'distance' => 8, 'speed' => 25, 'traffic' => 3, 'fuel_liters' => 0.95],
            ['vehicle_type' => 'car', 'distance' => 12, 'speed' => 35, 'traffic' => 2, 'fuel_liters' => 1.01],
            ['vehicle_type' => 'car', 'distance' => 18, 'speed' => 40, 'traffic' => 2, 'fuel_liters' => 1.39],
            ['vehicle_type' => 'car', 'distance' => 22, 'speed' => 50, 'traffic' => 1, 'fuel_liters' => 1.50],
            ['vehicle_type' => 'car', 'distance' => 30, 'speed' => 55, 'traffic' => 1, 'fuel_liters' => 1.98],
            ['vehicle_type' => 'car', 'distance' => 35, 'speed' => 45, 'traffic' => 2, 'fuel_liters' => 2.50],
            ['vehicle_type' => 'car', 'distance' => 42, 'speed' => 38, 'traffic' => 3, 'fuel_liters' => 3.17],
            ['vehicle_type' => 'car', 'distance' => 50, 'speed' => 48, 'traffic' => 2, 'fuel_liters' => 3.34],
            ['vehicle_type' => 'car', 'distance' => 65, 'speed' => 60, 'traffic' => 1, 'fuel_liters' => 4.11],
            ['vehicle_type' => 'car', 'distance' => 75, 'speed' => 52, 'traffic' => 2, 'fuel_liters' => 5.12],
            ['vehicle_type' => 'car', 'distance' => 85, 'speed' => 40, 'traffic' => 3, 'fuel_liters' => 6.36],
            ['vehicle_type' => 'car', 'distance' => 95, 'speed' => 58, 'traffic' => 1, 'fuel_liters' => 5.80],
        ];
    }

    /**
     * Trains one model per vehicle type using linear regression:
     * beta = (X^T X)^-1 X^T y
     */
    private function trainModel(string $vehicleType): array
    {
        $rows = array_values(array_filter(
            $this->getReferenceTrips(),
            static fn (array $row) => $row['vehicle_type'] === $vehicleType
        ));

        $xRows = [];
        $yValues = [];

        foreach ($rows as $row) {
            $xRows[] = $this->buildFeatureVector(
                (float) $row['distance'],
                (float) $row['speed'],
                (int) $row['traffic']
            );
            $yValues[] = (float) $row['fuel_liters'];
        }

        $coefficients = $this->computeRegressionCoefficients($xRows, $yValues);
        $predicted = [];

        foreach ($xRows as $features) {
            $predicted[] = $this->predictFuel($coefficients, $features);
        }

        return [
            'coefficients' => $coefficients,
            'metrics' => $this->computeModelMetrics($yValues, $predicted),
        ];
    }

    /**
     * Simple and stable feature vector used for prediction.
     */
    private function buildFeatureVector(float $distance, float $speed, int $traffic): array
    {
        return [
            1.0,              // intercept
            $distance,        // distance
            $speed,           // average speed
            (float) $traffic, // traffic level
        ];
    }

    private function getFeatureLabels(): array
    {
        return ['Intercept', 'Distance', 'Speed', 'Traffic'];
    }

    private function computeRegressionCoefficients(array $xRows, array $yValues): array
    {
        $y = array_map(static fn (float $value) => [$value], $yValues);
        $xTranspose = $this->transpose($xRows);
        $xTx = $this->multiplyMatrices($xTranspose, $xRows);

        // Light regularization stabilizes inversion on smaller datasets.
        $lambda = 0.001;
        for ($i = 0; $i < count($xTx); $i++) {
            $xTx[$i][$i] += $lambda;
        }

        $xTy = $this->multiplyMatrices($xTranspose, $y);
        $inverse = $this->inverseMatrix($xTx);
        $betaMatrix = $this->multiplyMatrices($inverse, $xTy);

        return array_map(static fn ($row) => (float) $row[0], $betaMatrix);
    }

    private function predictFuel(array $coefficients, array $features): float
    {
        $sum = 0.0;

        foreach ($features as $index => $featureValue) {
            $sum += ($coefficients[$index] ?? 0.0) * $featureValue;
        }

        return $sum;
    }

    private function computeModelMetrics(array $actual, array $predicted): array
    {
        $count = count($actual);
        if ($count === 0) {
            return ['r2' => 0.0, 'rmse' => 0.0, 'mae' => 0.0];
        }

        $mean = array_sum($actual) / $count;
        $sse = 0.0;
        $sst = 0.0;
        $maeSum = 0.0;

        foreach ($actual as $i => $actualValue) {
            $error = $actualValue - ($predicted[$i] ?? 0.0);
            $sse += $error ** 2;
            $sst += ($actualValue - $mean) ** 2;
            $maeSum += abs($error);
        }

        $rmse = sqrt($sse / $count);
        $mae = $maeSum / $count;
        $r2 = $sst > 0.0 ? max(0.0, min(1.0, 1 - ($sse / $sst))) : 1.0;

        return [
            'r2' => $r2,
            'rmse' => $rmse,
            'mae' => $mae,
        ];
    }

    private function buildPredictionRange(float $prediction, float $rmse, int $traffic): array
    {
        $trafficUncertainty = ($traffic - 1) * 0.06;
        $margin = max(0.08, (1.65 * $rmse) + $trafficUncertainty);

        $min = max(self::MIN_FUEL_LITERS, $prediction - $margin);
        $max = max($min, $prediction + $margin);

        return [
            'min' => $min,
            'max' => $max,
            'confidence' => 90,
        ];
    }

    private function buildInterpretation(
        float $predictedFuel,
        float $distance,
        int $traffic,
        string $vehicleType,
        array $metrics
    ): string {
        $efficiency = $distance / max($predictedFuel, self::MIN_FUEL_LITERS);
        $vehicleLabel = $vehicleType === 'car' ? 'car' : 'motorcycle';

        return 'For a ' . number_format($distance, 0) . ' km ' . $vehicleLabel . ' trip in '
            . $this->trafficLabel($traffic)
            . ', the estimate is ' . number_format($predictedFuel, 2)
            . ' liters (about ' . number_format($efficiency, 2)
            . ' km/L). Model fit on reference trips: R2 '
            . number_format($metrics['r2'] * 100, 1)
            . '%, average error around ' . number_format($metrics['rmse'], 2)
            . ' liters.';
    }

    private function buildEfficiencyTips(
        float $speed,
        int $traffic,
        string $vehicleType,
        float $efficiency
    ): array {
        $tips = [];

        if ($traffic === 3) {
            $tips[] = 'Heavy traffic usually increases fuel use. If possible, avoid rush hour.';
        }

        if ($speed < 35) {
            $tips[] = 'Very low average speed often means stop-and-go driving, which burns more fuel.';
        } elseif ($speed > 90) {
            $tips[] = 'Fuel burn rises at high speeds. A steadier moderate speed is usually cheaper.';
        }

        if ($vehicleType === 'car') {
            $tips[] = 'For cars, smooth acceleration and proper tire pressure help reduce fuel cost.';
        } else {
            $tips[] = 'For motorcycles, gentler throttle control helps improve km/L in city driving.';
        }

        if ($efficiency < 10) {
            $tips[] = 'This trip is fuel-intensive. A less congested route can lower your cost.';
        }

        return $tips;
    }

    private function trafficLabel(int $traffic): string
    {
        return match ($traffic) {
            1 => 'light traffic',
            2 => 'moderate traffic',
            default => 'heavy traffic',
        };
    }

    private function transpose(array $matrix): array
    {
        $result = [];
        $rowCount = count($matrix);
        $colCount = count($matrix[0]);

        for ($col = 0; $col < $colCount; $col++) {
            $result[$col] = [];
            for ($row = 0; $row < $rowCount; $row++) {
                $result[$col][$row] = $matrix[$row][$col];
            }
        }

        return $result;
    }

    private function multiplyMatrices(array $a, array $b): array
    {
        $aRows = count($a);
        $aCols = count($a[0]);
        $bRows = count($b);
        $bCols = count($b[0]);

        if ($aCols !== $bRows) {
            throw new RuntimeException('Matrix dimensions are incompatible for multiplication.');
        }

        $result = array_fill(0, $aRows, array_fill(0, $bCols, 0.0));

        for ($i = 0; $i < $aRows; $i++) {
            for ($j = 0; $j < $bCols; $j++) {
                $sum = 0.0;
                for ($k = 0; $k < $aCols; $k++) {
                    $sum += $a[$i][$k] * $b[$k][$j];
                }
                $result[$i][$j] = $sum;
            }
        }

        return $result;
    }

    /**
     * Gauss-Jordan elimination for matrix inversion.
     */
    private function inverseMatrix(array $matrix): array
    {
        $n = count($matrix);
        $augmented = [];

        for ($i = 0; $i < $n; $i++) {
            $identityRow = array_fill(0, $n, 0.0);
            $identityRow[$i] = 1.0;
            $augmented[$i] = array_merge($matrix[$i], $identityRow);
        }

        for ($i = 0; $i < $n; $i++) {
            $pivotRow = $i;
            for ($row = $i + 1; $row < $n; $row++) {
                if (abs($augmented[$row][$i]) > abs($augmented[$pivotRow][$i])) {
                    $pivotRow = $row;
                }
            }

            if ($pivotRow !== $i) {
                [$augmented[$i], $augmented[$pivotRow]] = [$augmented[$pivotRow], $augmented[$i]];
            }

            $pivot = $augmented[$i][$i];
            if (abs($pivot) < 1e-12) {
                throw new RuntimeException('Matrix is singular and cannot be inverted.');
            }

            for ($col = 0; $col < 2 * $n; $col++) {
                $augmented[$i][$col] /= $pivot;
            }

            for ($row = 0; $row < $n; $row++) {
                if ($row === $i) {
                    continue;
                }

                $factor = $augmented[$row][$i];
                for ($col = 0; $col < 2 * $n; $col++) {
                    $augmented[$row][$col] -= $factor * $augmented[$i][$col];
                }
            }
        }

        $inverse = [];
        for ($i = 0; $i < $n; $i++) {
            $inverse[$i] = array_slice($augmented[$i], $n);
        }

        return $inverse;
    }
}

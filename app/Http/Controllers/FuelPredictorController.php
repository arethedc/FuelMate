<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class FuelPredictorController extends Controller
{
    private const MIN_FUEL_LITERS = 0.1;

    public function index(): View
    {
        $dataset = $this->getTrainingDataset();
        $model = $this->trainModel($dataset);

        return view('home', [
            'dataset' => $dataset,
            'coefficients' => $model['coefficients'],
            'featureLabels' => $this->getFeatureLabels(),
            'metrics' => $model['metrics'],
            'result' => null,
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

        $dataset = $this->getTrainingDataset();
        $model = $this->trainModel($dataset);
        $coefficients = $model['coefficients'];
        $features = $this->buildFeatureVector(
            (float) $validated['distance'],
            (float) $validated['speed'],
            (int) $validated['traffic'],
            $validated['vehicle_type']
        );

        $predictedFuel = max(self::MIN_FUEL_LITERS, $this->predictFuel($coefficients, $features));
        $predictedCost = $predictedFuel * (float) $validated['fuel_price'];
        $noveltyScore = $this->calculateNoveltyScore(
            [
                'distance' => (float) $validated['distance'],
                'speed' => (float) $validated['speed'],
                'traffic' => (float) $validated['traffic'],
                'vehicle_flag' => $validated['vehicle_type'] === 'car' ? 1.0 : 0.0,
            ],
            $model['featureStats']
        );
        $fuelRange = $this->buildConfidenceInterval($predictedFuel, $model['metrics']['rmse'], $noveltyScore);
        $efficiency = (float) $validated['distance'] / max($predictedFuel, self::MIN_FUEL_LITERS);

        return view('home', [
            'dataset' => $dataset,
            'coefficients' => $coefficients,
            'featureLabels' => $this->getFeatureLabels(),
            'metrics' => $model['metrics'],
            'result' => [
                'fuel_liters' => $predictedFuel,
                'trip_cost' => $predictedCost,
                'fuel_range' => $fuelRange,
                'efficiency_kmpl' => $efficiency,
                'input' => $validated,
                'interpretation' => $this->buildInterpretation(
                    $predictedFuel,
                    (float) $validated['distance'],
                    (int) $validated['traffic'],
                    $validated['vehicle_type'],
                    $model['metrics']
                ),
                'tips' => $this->buildEfficiencyTips(
                    (float) $validated['speed'],
                    (int) $validated['traffic'],
                    $validated['vehicle_type'],
                    $efficiency
                ),
            ],
        ]);
    }

    /**
     * Hardcoded educational sample records.
     * Features: distance, speed, traffic, vehicle_type
     * Target: fuel_liters
     */
    private function getTrainingDataset(): array
    {
        return [
            ['distance' => 18, 'speed' => 45, 'traffic' => 1, 'vehicle_type' => 'motorcycle', 'fuel_liters' => 0.62],
            ['distance' => 22, 'speed' => 38, 'traffic' => 2, 'vehicle_type' => 'motorcycle', 'fuel_liters' => 0.89],
            ['distance' => 15, 'speed' => 30, 'traffic' => 3, 'vehicle_type' => 'motorcycle', 'fuel_liters' => 0.88],
            ['distance' => 42, 'speed' => 60, 'traffic' => 1, 'vehicle_type' => 'motorcycle', 'fuel_liters' => 1.30],
            ['distance' => 55, 'speed' => 50, 'traffic' => 2, 'vehicle_type' => 'motorcycle', 'fuel_liters' => 1.95],
            ['distance' => 35, 'speed' => 28, 'traffic' => 3, 'vehicle_type' => 'motorcycle', 'fuel_liters' => 1.85],
            ['distance' => 25, 'speed' => 42, 'traffic' => 1, 'vehicle_type' => 'car', 'fuel_liters' => 2.25],
            ['distance' => 30, 'speed' => 35, 'traffic' => 2, 'vehicle_type' => 'car', 'fuel_liters' => 3.20],
            ['distance' => 28, 'speed' => 26, 'traffic' => 3, 'vehicle_type' => 'car', 'fuel_liters' => 3.55],
            ['distance' => 70, 'speed' => 65, 'traffic' => 1, 'vehicle_type' => 'car', 'fuel_liters' => 5.35],
            ['distance' => 80, 'speed' => 52, 'traffic' => 2, 'vehicle_type' => 'car', 'fuel_liters' => 6.55],
            ['distance' => 65, 'speed' => 33, 'traffic' => 3, 'vehicle_type' => 'car', 'fuel_liters' => 7.00],
            ['distance' => 90, 'speed' => 62, 'traffic' => 1, 'vehicle_type' => 'car', 'fuel_liters' => 6.70],
            ['distance' => 48, 'speed' => 46, 'traffic' => 2, 'vehicle_type' => 'car', 'fuel_liters' => 4.20],
            ['distance' => 40, 'speed' => 40, 'traffic' => 2, 'vehicle_type' => 'motorcycle', 'fuel_liters' => 1.42],
            ['distance' => 52, 'speed' => 34, 'traffic' => 3, 'vehicle_type' => 'motorcycle', 'fuel_liters' => 2.45],
        ];
    }

    /**
     * Trains a smarter model using base and interaction terms.
     * Multiple linear regression via normal equation:
     * beta = (X^T X)^-1 X^T y
     */
    private function trainModel(array $dataset): array
    {
        $xRows = [];
        $yValues = [];

        foreach ($dataset as $row) {
            $xRows[] = $this->buildFeatureVector(
                (float) $row['distance'],
                (float) $row['speed'],
                (int) $row['traffic'],
                $row['vehicle_type']
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
            'featureStats' => $this->computeFeatureStats($dataset),
        ];
    }

    /**
     * Feature vector with interaction terms for better real-world behavior.
     */
    private function buildFeatureVector(
        float $distance,
        float $speed,
        int $traffic,
        string $vehicleType
    ): array {
        $vehicleFlag = $vehicleType === 'car' ? 1.0 : 0.0;
        $trafficValue = (float) $traffic;

        return [
            1.0,                          // b0
            $distance,                    // b1
            $speed,                       // b2
            $trafficValue,                // b3
            $vehicleFlag,                 // b4
            $distance * $trafficValue,    // b5 (distance x traffic)
            $speed * $trafficValue,       // b6 (speed x traffic)
            $distance * $vehicleFlag,     // b7 (distance x vehicle)
            $trafficValue * $vehicleFlag, // b8 (traffic x vehicle)
        ];
    }

    private function getFeatureLabels(): array
    {
        return [
            'Intercept',
            'Distance',
            'Speed',
            'Traffic',
            'Vehicle Flag',
            'Distance x Traffic',
            'Speed x Traffic',
            'Distance x Vehicle',
            'Traffic x Vehicle',
        ];
    }

    private function computeRegressionCoefficients(array $xRows, array $yValues): array
    {
        $y = array_map(static fn (float $value) => [$value], $yValues);
        $xTranspose = $this->transpose($xRows);
        $xTx = $this->multiplyMatrices($xTranspose, $xRows);

        // Light regularization stabilizes inversion on a small educational dataset.
        $lambda = 0.0001;
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

    private function computeFeatureStats(array $dataset): array
    {
        $distance = [];
        $speed = [];
        $traffic = [];
        $vehicleFlag = [];

        foreach ($dataset as $row) {
            $distance[] = (float) $row['distance'];
            $speed[] = (float) $row['speed'];
            $traffic[] = (float) $row['traffic'];
            $vehicleFlag[] = $row['vehicle_type'] === 'car' ? 1.0 : 0.0;
        }

        return [
            'distance' => $this->calculateMeanAndStd($distance),
            'speed' => $this->calculateMeanAndStd($speed),
            'traffic' => $this->calculateMeanAndStd($traffic),
            'vehicle_flag' => $this->calculateMeanAndStd($vehicleFlag),
        ];
    }

    private function calculateMeanAndStd(array $values): array
    {
        $n = count($values);
        if ($n === 0) {
            return ['mean' => 0.0, 'std' => 1.0];
        }

        $mean = array_sum($values) / $n;
        $varianceSum = 0.0;
        foreach ($values as $value) {
            $varianceSum += ($value - $mean) ** 2;
        }
        $std = sqrt($varianceSum / max(1, $n - 1));

        return [
            'mean' => $mean,
            'std' => max($std, 1e-6),
        ];
    }

    private function calculateNoveltyScore(array $input, array $featureStats): float
    {
        $zScores = [];

        foreach ($input as $feature => $value) {
            $stats = $featureStats[$feature] ?? ['mean' => 0.0, 'std' => 1.0];
            $zScores[] = abs(($value - $stats['mean']) / $stats['std']);
        }

        if (empty($zScores)) {
            return 0.0;
        }

        return array_sum($zScores) / count($zScores);
    }

    private function buildConfidenceInterval(float $prediction, float $rmse, float $noveltyScore): array
    {
        $uncertaintyBoost = 1 + min(1.5, $noveltyScore / 3);
        $margin = 1.96 * max($rmse, 0.05) * $uncertaintyBoost;

        $min = max(self::MIN_FUEL_LITERS, $prediction - $margin);
        $max = max($min, $prediction + $margin);

        return [
            'min' => $min,
            'max' => $max,
            'confidence' => 95,
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

        $trafficText = match ($traffic) {
            1 => 'light traffic',
            2 => 'moderate traffic',
            default => 'heavy traffic',
        };

        $vehicleLabel = $vehicleType === 'car' ? 'car' : 'motorcycle';

        if ($efficiency >= 20) {
            $efficiencyMessage = 'high fuel efficiency for this trip.';
        } elseif ($efficiency >= 12) {
            $efficiencyMessage = 'average fuel efficiency for this trip.';
        } else {
            $efficiencyMessage = 'lower fuel efficiency, likely due to slower speed or heavier traffic.';
        }

        return 'For this ' . $vehicleLabel . ' trip under ' . $trafficText . ', the model estimates about '
            . number_format($predictedFuel, 2)
            . ' L of fuel use (' . number_format($efficiency, 2) . ' km/L), which indicates '
            . $efficiencyMessage
            . ' Model fit on sample data: R² '
            . number_format($metrics['r2'] * 100, 1)
            . '% and RMSE '
            . number_format($metrics['rmse'], 2)
            . ' L.';
    }

    private function buildEfficiencyTips(
        float $speed,
        int $traffic,
        string $vehicleType,
        float $efficiency
    ): array {
        $tips = [];

        if ($traffic === 3) {
            $tips[] = 'Heavy traffic raises fuel use. If possible, travel during off-peak hours.';
        }

        if ($speed < 35) {
            $tips[] = 'Very low average speed can increase fuel consumption due to stop-and-go patterns.';
        } elseif ($speed > 90) {
            $tips[] = 'High speed increases drag and fuel burn. A steadier moderate speed can reduce cost.';
        }

        if ($vehicleType === 'car') {
            $tips[] = 'For cars, smooth acceleration and proper tire pressure can noticeably improve efficiency.';
        } else {
            $tips[] = 'For motorcycles, avoid aggressive throttle changes to improve km/L on city trips.';
        }

        if ($efficiency < 10) {
            $tips[] = 'This trip is currently fuel-intensive. Consider route optimization or less congested roads.';
        }

        return $tips;
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

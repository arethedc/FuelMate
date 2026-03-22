<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class FuelPredictorController extends Controller
{
    public function index(): View
    {
        $dataset = $this->getTrainingDataset();
        $coefficients = $this->computeRegressionCoefficients($dataset);

        return view('home', [
            'dataset' => $dataset,
            'coefficients' => $coefficients,
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
        $coefficients = $this->computeRegressionCoefficients($dataset);

        $vehicleFlag = $validated['vehicle_type'] === 'car' ? 1.0 : 0.0;
        $features = [
            1.0,
            (float) $validated['distance'],
            (float) $validated['speed'],
            (float) $validated['traffic'],
            $vehicleFlag,
        ];

        $predictedFuel = max(0.1, $this->predictFuel($coefficients, $features));
        $predictedCost = $predictedFuel * (float) $validated['fuel_price'];

        return view('home', [
            'dataset' => $dataset,
            'coefficients' => $coefficients,
            'result' => [
                'fuel_liters' => $predictedFuel,
                'trip_cost' => $predictedCost,
                'input' => $validated,
                'interpretation' => $this->buildInterpretation(
                    $predictedFuel,
                    (float) $validated['distance'],
                    (int) $validated['traffic'],
                    $validated['vehicle_type']
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
     * Multiple linear regression using the normal equation:
     * beta = (X^T X)^-1 X^T y
     */
    private function computeRegressionCoefficients(array $dataset): array
    {
        $x = [];
        $y = [];

        foreach ($dataset as $row) {
            $vehicleFlag = $row['vehicle_type'] === 'car' ? 1.0 : 0.0;

            $x[] = [
                1.0,
                (float) $row['distance'],
                (float) $row['speed'],
                (float) $row['traffic'],
                $vehicleFlag,
            ];

            $y[] = [(float) $row['fuel_liters']];
        }

        $xTranspose = $this->transpose($x);
        $xTx = $this->multiplyMatrices($xTranspose, $x);

        // Light regularization to avoid singular matrix errors on small datasets.
        $lambda = 0.00001;
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

    private function buildInterpretation(
        float $predictedFuel,
        float $distance,
        int $traffic,
        string $vehicleType
    ): string {
        $efficiency = $distance / max($predictedFuel, 0.1);

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
            . $efficiencyMessage;
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

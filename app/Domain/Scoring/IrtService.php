<?php

namespace App\Domain\Scoring;

use InvalidArgumentException;

class IrtService
{
    public const THETA_MIN = -3.0;

    public const THETA_MAX = 3.0;

    public const DEFAULT_A = 1.0;

    public const DEFAULT_B = 0.0;

    public const DEFAULT_C = 0.25;

    private const MAX_ITERATIONS = 100;

    private const TOLERANCE = 1e-7;

    private const EPSILON = 1e-12;

    /**
     * Probabilitas menjawab benar menurut model 3PL:
     * P(θ) = c + (1 - c) / (1 + e^(-a(θ - b)))
     */
    public function probability(float $theta, float $a, float $b, float $c): float
    {
        $logistic = 1.0 / (1.0 + exp(-$a * ($theta - $b)));

        return $c + (1.0 - $c) * $logistic;
    }

    public function clampTheta(float $theta): float
    {
        return max(self::THETA_MIN, min(self::THETA_MAX, $theta));
    }

    /**
     * Estimasi theta dengan MLE (Newton-Raphson / Fisher scoring).
     * Pola monotone (semua benar / semua salah) diberi nilai ekstrem ±3.0 (edge 6.3).
     *
     * @param  array<int, array{a?: float|int, b?: float|int, c?: float|int, response: int|bool}>  $items
     */
    public function estimateMle(array $items): float
    {
        $items = $this->normalize($items);

        if ($items === []) {
            return 0.0;
        }

        $responses = array_column($items, 'response');
        $allCorrect = ! in_array(0, $responses, true);
        $allWrong = ! in_array(1, $responses, true);

        if ($allCorrect || $allWrong) {
            return $allCorrect ? self::THETA_MAX : self::THETA_MIN;
        }

        return $this->iterate($items, 0.0, null);
    }

    /**
     * Estimasi theta dengan prior normal (weighted likelihood) untuk data sedikit / per-KD.
     *
     * @param  array<int, array{a?: float|int, b?: float|int, c?: float|int, response: int|bool}>  $items
     * @param  array{mean?: float, sd?: float}  $prior
     */
    public function estimateWithPrior(
        array $items,
        array $prior = ['mean' => 0.0, 'sd' => 1.0]
    ): float {
        $items = $this->normalize($items);

        if ($items === []) {
            return $prior['mean'] ?? 0.0;
        }

        return $this->iterate($items, $prior['mean'] ?? 0.0, $prior);
    }

    /**
     * Standard error theta dari test information: SE = 1/sqrt(Σ I_i(θ)).
     *
     * @param  array<int, array{a?: float|int, b?: float|int, c?: float|int, response: int|bool}>  $items
     */
    public function standardError(float $theta, array $items): ?float
    {
        $items = $this->normalize($items);
        $info = $this->information($theta, $items);

        if ($info <= self::EPSILON) {
            return null;
        }

        return 1.0 / sqrt($info);
    }

    /**
     * Konversi theta ke skala pelaporan (dengan clamping, edge 6.8).
     * SD/SMP: 0-100 dengan rumus 50 + 10θ. SMA/SMK: 200-700 dengan rumus 450 + 100θ.
     */
    public function convertToScale(float $theta, ?string $tingkat): int
    {
        if (in_array($tingkat, ['SD', 'SMP'], true)) {
            return (int) round(max(0.0, min(100.0, 50.0 + 10.0 * $theta)));
        }

        return (int) round(max(200.0, min(700.0, 450.0 + 100.0 * $theta)));
    }

    /**
     * Iterasi Newton mengoptimalkan log-likelihood (+ log prior bila diberikan).
     *
     * @param  array<int, array{a: float, b: float, c: float, response: int}>  $items
     * @param  array{mean?: float, sd?: float}|null  $prior
     */
    private function iterate(array $items, float $start, ?array $prior): float
    {
        $theta = $start;

        $priorMean = $prior['mean'] ?? 0.0;
        $priorInfo = $prior !== null ? 1.0 / (($prior['sd'] ?? 1.0) ** 2) : 0.0;
        $usePrior = $prior !== null && $priorInfo > self::EPSILON;

        for ($iteration = 0; $iteration < self::MAX_ITERATIONS; $iteration++) {
            $score = 0.0;
            $info = 0.0;

            foreach ($items as $item) {
                [$probability, $derivative, $variance] = $this->components($theta, $item);

                if ($variance <= self::EPSILON) {
                    continue;
                }

                $score += $derivative * ($item['response'] - $probability) / $variance;
                $info += $derivative ** 2 / $variance;
            }

            if ($usePrior) {
                $score -= ($theta - $priorMean) * $priorInfo;
                $info += $priorInfo;
            }

            if ($info <= self::EPSILON) {
                return $usePrior ? $priorMean : 0.0;
            }

            $step = $score / $info;
            $theta = $this->clampTheta($theta + $step);

            if (abs($step) <= self::TOLERANCE) {
                break;
            }
        }

        return $this->clampTheta($theta);
    }

    /**
     * Menghitung komponen P, dP/dθ, dan P(1-P) pada theta.
     *
     * @param  array{a: float, b: float, c: float, response: int}  $item
     * @return array{0: float, 1: float, 2: float}
     */
    private function components(float $theta, array $item): array
    {
        $a = $item['a'];
        $b = $item['b'];
        $c = $item['c'];

        $logistic = 1.0 / (1.0 + exp(-$a * ($theta - $b)));
        $probability = $c + (1.0 - $c) * $logistic;

        // dP/dθ = a(1-c)σ(1-σ)
        $derivative = $a * (1.0 - $c) * $logistic * (1.0 - $logistic);

        $variance = $probability * (1.0 - $probability);

        return [$probability, $derivative, $variance];
    }

    /**
     * Informasi tes (sum informasi item) pada theta.
     *
     * @param  array<int, array{a: float, b: float, c: float, response: int}>  $items
     */
    private function information(float $theta, array $items): float
    {
        $info = 0.0;

        foreach ($items as $item) {
            [, $derivative, $variance] = $this->components($theta, $item);

            if ($variance <= self::EPSILON) {
                continue;
            }

            $info += $derivative ** 2 / $variance;
        }

        return $info;
    }

    /**
     * @param  array<int, array{a?: float|int, b?: float|int, c?: float|int, response: int|bool}>  $items
     * @return array<int, array{a: float, b: float, c: float, response: int}>
     */
    private function normalize(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if (! array_key_exists('response', $item)) {
                throw new InvalidArgumentException('Setiap item harus memiliki response.');
            }

            $normalized[] = [
                'a' => (float) ($item['a'] ?? self::DEFAULT_A),
                'b' => (float) ($item['b'] ?? self::DEFAULT_B),
                'c' => (float) ($item['c'] ?? self::DEFAULT_C),
                'response' => (int) $item['response'],
            ];
        }

        return $normalized;
    }
}

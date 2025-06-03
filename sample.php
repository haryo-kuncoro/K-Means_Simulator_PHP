<?php

class KMeans {
    private $data;
    private $k;
    private $centroids;
    private $clusters;
    private $maxIterations;
    private $dataAssignments; // Tambahkan properti ini untuk menyimpan penugasan

    public function __construct(array $data, int $k, int $maxIterations = 100) {
        if ($k <= 0) {
            throw new InvalidArgumentException("Jumlah cluster (k) harus lebih besar dari 0.");
        }
        if (empty($data)) {
            throw new InvalidArgumentException("Data tidak boleh kosong.");
        }
        if ($k > count($data)) {
            throw new InvalidArgumentException("Jumlah cluster (k) tidak boleh lebih dari jumlah data.");
        }
        $this->data = $data;
        $this->k = $k;
        $this->maxIterations = $maxIterations;
        $this->centroids = [];
        $this->clusters = [];
        $this->dataAssignments = array_fill(0, count($this->data), -1); // Inisialisasi
    }

    private function calculateDistance(array $point1, array $point2): float {
        $sumOfSquares = 0;
        $dimensions = count($point1);
        for ($i = 0; $i < $dimensions; $i++) {
            $sumOfSquares += pow($point1[$i] - $point2[$i], 2);
        }
        return sqrt($sumOfSquares);
    }

    private function initializeCentroidsRCE() {
        $firstCentroidIndex = array_rand($this->data);
        $this->centroids[0] = $this->data[$firstCentroidIndex];

        for ($i = 1; $i < $this->k; $i++) {
            $maxDistance = -1;
            $nextCentroidIndex = -1;

            foreach ($this->data as $dataIndex => $point) {
                $minDistanceFromExistingCentroids = INF;
                foreach ($this->centroids as $existingCentroid) {
                    $distance = $this->calculateDistance($point, $existingCentroid);
                    if ($distance < $minDistanceFromExistingCentroids) {
                        $minDistanceFromExistingCentroids = $distance;
                    }
                }

                if ($minDistanceFromExistingCentroids > $maxDistance) {
                    $maxDistance = $minDistanceFromExistingCentroids;
                    $nextCentroidIndex = $dataIndex;
                }
            }
            $this->centroids[$i] = $this->data[$nextCentroidIndex];
        }
    }

    private function assignDataToClusters(): bool {
        $newClusters = array_fill(0, $this->k, []);
        $changed = false;

        foreach ($this->data as $dataIndex => $point) {
            $minDistance = INF;
            $closestCentroidId = -1;

            foreach ($this->centroids as $centroidId => $centroid) {
                $distance = $this->calculateDistance($point, $centroid);
                if ($distance < $minDistance) {
                    $minDistance = $distance;
                    $closestCentroidId = $centroidId;
                }
            }

            if ($this->dataAssignments[$dataIndex] !== $closestCentroidId) {
                $changed = true;
            }

            $newClusters[$closestCentroidId][] = $dataIndex;
            $this->dataAssignments[$dataIndex] = $closestCentroidId;
        }

        $this->clusters = $newClusters;
        return $changed;
    }

    private function updateCentroids() {
        $newCentroids = [];
        foreach ($this->clusters as $clusterId => $dataIndices) {
            if (empty($dataIndices)) {
                $newCentroids[$clusterId] = $this->centroids[$clusterId];
                continue;
            }

            $numDimensions = count($this->data[array_values($dataIndices)[0]]);
            $sumDimensions = array_fill(0, $numDimensions, 0);

            foreach ($dataIndices as $dataIndex) {
                foreach ($this->data[$dataIndex] as $dim => $value) {
                    $sumDimensions[$dim] += $value;
                }
            }

            $newCentroid = [];
            foreach ($sumDimensions as $dimSum) {
                $newCentroid[] = $dimSum / count($dataIndices);
            }
            $newCentroids[$clusterId] = $newCentroid;
        }
        $this->centroids = $newCentroids;
    }

    public function run(): array {
        $this->initializeCentroidsRCE();

        for ($i = 0; $i < $this->maxIterations; $i++) {
            $hasChanged = $this->assignDataToClusters();
            $this->updateCentroids();

            if (!$hasChanged && $i > 0) {
                break;
            }
        }

        return [
            'clusters_by_index' => $this->clusters, // Ini adalah indeks data di dalam cluster
            'data_assignments' => $this->dataAssignments, // Ini adalah cluster ID untuk setiap data point
            'centroids' => $this->centroids
        ];
    }

    // Fungsi baru untuk mendapatkan data dengan informasi cluster
    public function getClusteredData(): array {
        $clusteredData = [];
        foreach ($this->data as $dataIndex => $point) {
            $clusteredData[] = [
                'x' => $point[0], // Asumsi dimensi pertama adalah X
                'y' => $point[1], // Asumsi dimensi kedua adalah Y
                'cluster' => $this->dataAssignments[$dataIndex]
            ];
        }
        return $clusteredData;
    }
}

// --- Data Contoh ---
$sampleData = [
    [25, 4000], [30, 4500], [20, 3500], [40, 6000], [45, 6500],
    [50, 7000], [22, 3800], [35, 5500], [28, 4200], [55, 7200],
    [18, 3200], [60, 8000], [23, 4100], [15, 3000], [62, 8100]
];

$k = 3; // Jumlah cluster

try {
    $kmeans = new KMeans($sampleData, $k);
    $result = $kmeans->run();

    $clusteredPoints = $kmeans->getClusteredData();
    $centroids = $result['centroids'];

    // Encode data ke JSON untuk dikirim ke JavaScript
    $jsonDataPoints = json_encode($clusteredPoints);
    $jsonCentroids = json_encode($centroids);

} catch (InvalidArgumentException $e) {
    // Tangani error
    $jsonDataPoints = "[]";
    $jsonCentroids = "[]";
    echo "Error: " . $e->getMessage() . "\n";
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>K-Means Clustering Visualization</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: sans-serif; display: flex; flex-direction: column; align-items: center; }
        #kmeansChart { max-width: 800px; max-height: 600px; margin-top: 20px; }
        h1 { text-align: center; }
    </style>
</head>
<body>
    <h1>Visualisasi K-Means Clustering</h1>
    <canvas id="kmeansChart"></canvas>

    <script>
        // Data dari PHP
        const dataPoints = <?php echo $jsonDataPoints; ?>;
        const centroids = <?php echo $jsonCentroids; ?>;
        const k = <?php echo $k; ?>;

        // Warna untuk setiap cluster (sesuaikan jika k lebih besar)
        const colors = [
            'rgba(255, 99, 132, 0.6)',  // Merah
            'rgba(54, 162, 235, 0.6)',  // Biru
            'rgba(255, 206, 86, 0.6)',  // Kuning
            'rgba(75, 192, 192, 0.6)',  // Hijau
            'rgba(153, 102, 255, 0.6)', // Ungu
            'rgba(255, 159, 64, 0.6)'   // Oranye
        ];

        // Buat dataset untuk setiap cluster
        const datasets = [];
        for (let i = 0; i < k; i++) {
            datasets.push({
                label: `Cluster ${i}`,
                data: [], // Akan diisi di bawah
                backgroundColor: colors[i % colors.length], // Gunakan modulo untuk mengulang warna
                borderColor: colors[i % colors.length].replace('0.6', '1'),
                borderWidth: 1,
                pointRadius: 5,
                pointHoverRadius: 7
            });
        }

        // Tambahkan data ke dataset cluster yang sesuai
        dataPoints.forEach(point => {
            if (point.cluster !== null && datasets[point.cluster]) {
                datasets[point.cluster].data.push({ x: point.x, y: point.y });
            }
        });

        // Tambahkan centroid sebagai dataset terpisah
        const centroidData = centroids.map(c => ({ x: c[0], y: c[1] }));
        datasets.push({
            label: 'Centroids',
            data: centroidData,
            backgroundColor: 'black', // Centroid biasanya hitam
            borderColor: 'black',
            pointRadius: 8,
            pointHoverRadius: 10,
            pointStyle: 'crossRot', // Bentuk salib atau bintang
            borderWidth: 2
        });

        const ctx = document.getElementById('kmeansChart').getContext('2d');
        new Chart(ctx, {
            type: 'scatter', // Tipe grafik scatter plot
            data: {
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, // Penting agar bisa diatur ukurannya
                scales: {
                    x: {
                        type: 'linear',
                        position: 'bottom',
                        title: {
                            display: true,
                            text: 'Dimensi 1 (Contoh: Usia)' // Sesuaikan label
                        }
                    },
                    y: {
                        type: 'linear',
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Dimensi 2 (Contoh: Pendapatan)' // Sesuaikan label
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.x !== null && context.parsed.y !== null) {
                                    label += `(${context.parsed.x}, ${context.parsed.y})`;
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
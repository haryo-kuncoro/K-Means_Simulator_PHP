<?php

// Pastikan autoload Composer dimuat
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

class KMeans {
    private $data;
    private $k;
    private $centroids;
    private $clusters; // Ini berisi clusterId => array of data_indices
    private $maxIterations;
    private $dataAssignments; // Ini berisi data_index => cluster_id
    private $iterationClusterCounts; // NEW: Untuk menyimpan jumlah anggota cluster per iterasi

    public function __construct(array $data, int $k, int $maxIterations = 100) {
        if ($k <= 0) {
            throw new InvalidArgumentException("Jumlah cluster (k) harus lebih besar dari 0.");
        }
        if (empty($data)) {
            throw new InvalidArgumentException("Data tidak boleh kosong.");
        }
        // Validasi tambahan: Pastikan semua data memiliki dimensi yang sama
        $firstPointDims = count($data[0]);
        foreach ($data as $point) {
            if (count($point) !== $firstPointDims) {
                throw new InvalidArgumentException("Semua titik data harus memiliki jumlah dimensi yang sama.");
            }
        }

        if ($k > count($data)) {
            throw new InvalidArgumentException("Jumlah cluster (k) tidak boleh lebih dari jumlah data.");
        }
        $this->data = $data;
        $this->k = $k;
        $this->maxIterations = $maxIterations;
        $this->centroids = [];
        $this->clusters = [];
        $this->dataAssignments = array_fill(0, count($this->data), -1);
        $this->iterationClusterCounts = []; // NEW: Inisialisasi properti baru
    }

    /**
     * Menghitung jarak Euclidean antara dua titik.
     * Dapat menangani dimensi N.
     */
    private function calculateDistance(array $point1, array $point2): float {
        $sumOfSquares = 0;
        $dimensions = count($point1);
        for ($i = 0; $i < $dimensions; $i++) {
            $sumOfSquares += pow($point1[$i] - $point2[$i], 2);
        }
        return sqrt($sumOfSquares);
    }

    /**
     * Inisialisasi centroid dengan memilih K titik data secara acak.
     * Ini adalah metode inisialisasi K-Means yang paling dasar (tanpa optimasi).
     */
    private function initializeCentroidsRandom() {
        // Ambil indeks unik secara acak dari data points
        // Pastikan jumlah data cukup untuk K cluster
        if (count($this->data) < $this->k) {
             throw new InvalidArgumentException("Jumlah data (" . count($this->data) . ") kurang dari jumlah cluster (k=" . $this->k . "). Tidak dapat menginisialisasi centroid.");
        }

        $randomIndices = array_rand($this->data, $this->k);

        // Jika hanya 1 centroid yang diminta, array_rand mengembalikan integer, bukan array
        if ($this->k === 1) {
            $randomIndices = [$randomIndices];
        }

        // Tetapkan titik data yang dipilih secara acak sebagai centroid awal
        $this->centroids = [];
        foreach ($randomIndices as $index) {
            $this->centroids[] = $this->data[$index];
        }
    }


    /**
     * Menetapkan setiap titik data ke cluster terdekat berdasarkan centroid saat ini.
     * Mengembalikan true jika ada perubahan penugasan, false jika tidak ada.
     */
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

    /**
     * Memperbarui posisi centroid berdasarkan rata-rata titik data dalam cluster.
     */
    private function updateCentroids() {
        $newCentroids = [];
        for ($i = 0; $i < $this->k; $i++) {
            if (empty($this->clusters[$i])) {
                // Jika cluster kosong, centroidnya tetap pada posisi sebelumnya
                // Ini penting untuk stabilitas, terutama dengan inisialisasi acak
                $newCentroids[$i] = $this->centroids[$i];
                continue;
            }

            $numDimensions = count($this->data[array_values($this->clusters[$i])[0]]);
            $sumDimensions = array_fill(0, $numDimensions, 0);

            foreach ($this->clusters[$i] as $dataIndex) {
                foreach ($this->data[$dataIndex] as $dim => $value) {
                    $sumDimensions[$dim] += $value;
                }
            }

            $newCentroid = [];
            foreach ($sumDimensions as $dimSum) {
                $newCentroid[] = $dimSum / count($this->clusters[$i]);
            }
            $newCentroids[$i] = $newCentroid;
        }
        $this->centroids = $newCentroids;
    }

    /**
     * Menjalankan algoritma K-Means.
     *
     * @return array Hasil clustering (cluster_by_index, data_assignments, centroids, iteration_cluster_counts)
     */
    public function run(): array {
        // Panggil metode inisialisasi centroid acak
        $this->initializeCentroidsRandom();

        for ($i = 0; $i < $this->maxIterations; $i++) {
            $hasChanged = $this->assignDataToClusters();
            $this->updateCentroids();

            // NEW: Simpan jumlah anggota cluster untuk iterasi saat ini
            $this->iterationClusterCounts[$i] = $this->getClusterCounts();

            // Kita akan tetap menjalankan hingga maxIterations untuk tujuan visualisasi iterasi.
            // Jika Anda ingin menghentikan jika konvergen, uncomment baris di bawah ini:
            // if (!$hasChanged && $i > 0) {
            //     break;
            // }
        }

        return [
            'clusters_by_index' => $this->clusters,
            'data_assignments' => $this->dataAssignments,
            'centroids' => $this->centroids,
            'iteration_cluster_counts' => $this->iterationClusterCounts // NEW: Tambahkan data ini ke hasil
        ];
    }

    /**
     * Mengembalikan data asli dengan tambahan informasi cluster untuk visualisasi.
     * Mengembalikan seluruh titik data (array multidimensi) beserta penugasan cluster.
     */
    public function getClusteredData(): array {
        $clusteredData = [];
        foreach ($this->data as $dataIndex => $point) {
            $clusteredData[] = [
                'point' => $point, // Seluruh array dimensi dari titik data
                'cluster' => $this->dataAssignments[$dataIndex]
            ];
        }
        return $clusteredData;
    }

    /**
     * Mengembalikan jumlah data point di setiap cluster.
     * @return array Array asosiatif clusterId => count
     */
    public function getClusterCounts(): array {
        $counts = [];
        for ($i = 0; $i < $this->k; $i++) {
            $counts[$i] = isset($this->clusters[$i]) ? count($this->clusters[$i]) : 0;
        }
        return $counts;
    }

    /**
     * Menghitung Silhouette Score untuk hasil clustering.
     * @return float Silhouette Score rata-rata
     */
    public function calculateSilhouetteScore(): float {
        if (empty($this->data) || count($this->data) < 2 || $this->k < 2) {
            // Silhouette Score tidak dapat dihitung untuk kurang dari 2 data point atau kurang dari 2 cluster
            return 0.0;
        }

        $silhouetteScores = [];
        $numDataPoints = count($this->data);

        foreach ($this->data as $dataIndex => $point) {
            $assignedClusterId = $this->dataAssignments[$dataIndex];

            // Jika cluster hanya memiliki satu anggota, a(i) = 0
            if ($assignedClusterId === -1 || !isset($this->clusters[$assignedClusterId]) || count($this->clusters[$assignedClusterId]) <= 1) {
                $a_i = 0.0;
            } else {
                $a_i = 0.0;
                $sameClusterPointsCount = 0;
                foreach ($this->clusters[$assignedClusterId] as $otherDataIndex) {
                    if ($otherDataIndex !== $dataIndex) {
                        $a_i += $this->calculateDistance($point, $this->data[$otherDataIndex]);
                        $sameClusterPointsCount++;
                    }
                }
                $a_i = ($sameClusterPointsCount > 0) ? $a_i / $sameClusterPointsCount : 0.0;
            }

            // Hitung b(i) - jarak rata-rata terdekat ke cluster lain
            $b_i = INF;
            $hasOtherClusters = false;
            foreach ($this->clusters as $otherClusterId => $otherClusterPointsIndices) {
                if ($otherClusterId !== $assignedClusterId) {
                    $hasOtherClusters = true;
                    if (!empty($otherClusterPointsIndices)) {
                        $sumDistancesToOtherCluster = 0.0;
                        foreach ($otherClusterPointsIndices as $otherPointIndex) {
                            $sumDistancesToOtherCluster += $this->calculateDistance($point, $this->data[$otherPointIndex]);
                        }
                        $avgDistanceToOtherCluster = $sumDistancesToOtherCluster / count($otherClusterPointsIndices);
                        $b_i = min($b_i, $avgDistanceToOtherCluster);
                    }
                }
            }
            
            // Handle cases where b_i might still be INF (e.g., only one cluster)
            if ($b_i === INF || !$hasOtherClusters) {
                $s_i = 0.0; 
            } else {
                $s_i = ($b_i - $a_i) / max($a_i, $b_i);
            }
            
            $silhouetteScores[] = $s_i;
        }

        return empty($silhouetteScores) ? 0.0 : array_sum($silhouetteScores) / count($silhouetteScores);
    }
}

// --- FUNGSI NORMALISASI ---
function normalizeMinMax(array $data): array {
    if (empty($data)) {
        return [];
    }

    $numDimensions = count($data[0]);
    $minValues = array_fill(0, $numDimensions, INF);
    $maxValues = array_fill(0, $numDimensions, -INF);

    foreach ($data as $point) {
        if (count($point) !== $numDimensions) {
            throw new InvalidArgumentException("Semua titik data harus memiliki jumlah dimensi yang sama untuk normalisasi.");
        }
        foreach ($point as $dim => $value) {
            if (!is_numeric($value)) {
                throw new InvalidArgumentException("Data harus berupa angka untuk normalisasi.");
            }
            $minValues[$dim] = min($minValues[$dim], $value);
            $maxValues[$dim] = max($maxValues[$dim], $value);
        }
    }

    $normalizedData = [];
    foreach ($data as $point) {
        $normalizedPoint = [];
        foreach ($point as $dim => $value) {
            $range = $maxValues[$dim] - $minValues[$dim];
            if ($range == 0) {
                $normalizedPoint[] = 0.5;
            } else {
                $normalizedPoint[] = ($value - $minValues[$dim]) / $range;
            }
        }
        $normalizedData[] = $normalizedPoint;
    }

    return $normalizedData;
}


// --- BAGIAN UTAMA: MEMBACA DATA DARI EXCEL, MENORMALISASI, MENJALANKAN K-MEANS, DAN MENAMPILKAN ---

$inputFileName = 'data_kmeans.xlsx'; // Nama file Excel Anda
$reader = IOFactory::createReaderForFile($inputFileName);

$jsonDataPoints = "[]";
$jsonCentroids = "[]";
$jsonClusterCounts = "[]";
$jsonSilhouetteScore = "0.0";
$jsonIterationClusterCounts = "[]"; // NEW: Inisialisasi variabel untuk data iterasi
$k = 3; // Jumlah cluster default
$numIndicatorColumns = 0;
$maxIterations = 4; // NEW: Atur jumlah iterasi yang diinginkan

try {
    $spreadsheet = $reader->load($inputFileName);
    $sheet = $spreadsheet->getActiveSheet();
    $excelData = $sheet->toArray();

    $rawData = [];
    $headerSkipped = false;

    $numIndicatorColumns = 8; // <<< Sesuaikan ini dengan jumlah kolom indikator Anda

    foreach ($excelData as $row) {
        if (!$headerSkipped) {
            $headerSkipped = true;
            continue;
        }

        $currentRowData = [];
        $isValidRow = true;

        for ($i = 0; $i < $numIndicatorColumns; $i++) {
            if (isset($row[$i]) && is_numeric($row[$i])) {
                $currentRowData[] = (float)$row[$i];
            } else {
                $isValidRow = false;
                break;
            }
        }

        if ($isValidRow && !empty($currentRowData) && count($currentRowData) === $numIndicatorColumns) {
            $rawData[] = $currentRowData;
        }
    }

    if (empty($rawData)) {
        throw new InvalidArgumentException("Tidak ada data valid yang ditemukan di file Excel atau file kosong setelah filter kolom.");
    }

    // --- PILIH SALAH SATU: NORMALISASI ATAU LANGSUNG GUNAKAN DATA ---
    // Jika data Anda SUDAH dinormalisasi di Excel, gunakan baris ini:
    // $finalDataForKMeans = $rawData;

    // Jika data Anda BELUM dinormalisasi dan ingin dinormalisasi di PHP, gunakan baris ini:
    $finalDataForKMeans = normalizeMinMax($rawData);


    // --- JALANKAN ALGORITMA K-MEANS DENGAN MAX ITERATIONS YANG DITENTUKAN ---
    $kmeans = new KMeans($finalDataForKMeans, $k, $maxIterations); // NEW: Kirim maxIterations
    $result = $kmeans->run();

    $clusteredPoints = $kmeans->getClusteredData();
    $centroids = $result['centroids'];
    $clusterCounts = $kmeans->getClusterCounts(); // Ini akan menjadi hitungan akhir setelah iterasi selesai
    $silhouetteScore = $kmeans->calculateSilhouetteScore();
    $iterationClusterCounts = $result['iteration_cluster_counts']; // NEW: Ambil data iterasi

    $jsonDataPoints = json_encode($clusteredPoints);
    $jsonCentroids = json_encode($centroids);
    $jsonClusterCounts = json_encode(array_values($clusterCounts));
    $jsonSilhouetteScore = json_encode(round($silhouetteScore, 4));
    $jsonIterationClusterCounts = json_encode($iterationClusterCounts); // NEW: Encode data iterasi

} catch (InvalidArgumentException $e) {
    echo "<p style='color:red;'>Error Argumen: " . $e->getMessage() . "</p>\n";
} catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
    echo "<p style='color:red;'>Error Membaca File Excel: Pastikan file '$inputFileName' ada dan valid, serta ekstensi 'zip' PHP diaktifkan. Detail: " . $e->getMessage() . "</p>\n";
} catch (Exception $e) {
    echo "<p style='color:red;'>Terjadi Kesalahan Tak Terduga: " . $e->getMessage() . "</p>\n";
}

// Buat array nama kolom untuk dropdown
$columnNames = [];
for ($i = 0; $i < $numIndicatorColumns; $i++) {
    $columnNames[] = 'Kolom ' . chr(65 + $i);
}
$jsonColumnNames = json_encode($columnNames);

?>
<!DOCTYPE html>
<html>
<head>
    <title>K-Means Clustering Visualization (Multi-Dim Data)</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: sans-serif; display: flex; flex-direction: column; align-items: center; margin: 20px; background-color: #f4f4f4; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); width: 100%; max-width: 900px; }
        #kmeansChart, #clusterCountChart, #iterationClusterChart { max-width: 100%; max-height: 550px; margin-top: 20px; }
        h1, h2 { text-align: center; color: #333; }
        p { text-align: center; color: #555; }
        pre { background-color: #eee; padding: 10px; border-radius: 5px; overflow-x: auto; }
        .info { margin-top: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background-color: #f9f9f9; }
        .controls { display: flex; justify-content: center; gap: 20px; margin-bottom: 20px; }
        .controls label { font-weight: bold; }
        .controls select { padding: 8px; border-radius: 4px; border: 1px solid #ccc; }
        .score-info { text-align: center; margin-top: 20px; }
        #clusteringConclusion { font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Visualisasi K-Means Clustering</h1>
        <h1>Dengan Centroid Acak Dasar K-Means</h1>
        <p>Data bersumber dari file Excel (<code>data_kmeans.xlsx</code>).</p>
        <p class="info">
            Pilih dua dimensi yang ingin Anda visualisasikan pada grafik scatter plot di bawah ini.<br>
            Algoritma K-Means telah memproses semua **<?php echo $numIndicatorColumns; ?> dimensi** data Anda.
        </p>

        <div class="controls">
            <div>
                <label for="xAxisSelect">Sumbu X:</label>
                <select id="xAxisSelect"></select>
            </div>
            <div>
                <label for="yAxisSelect">Sumbu Y:</label>
                <select id="yAxisSelect"></select>
            </div>
        </div>

        <canvas id="kmeansChart"></canvas>

        <h2>Jumlah Anggota Cluster Tiap Iterasi</h2>
        <canvas id="iterationClusterChart"></canvas>

        <h2>Jumlah Anggota Setiap Cluster (Hasil Akhir)</h2>
        <canvas id="clusterCountChart"></canvas>

        <h2>Kualitas Clustering</h2>
        <p class="score-info">
            <strong>Silhouette Score:</strong> <span id="silhouetteScoreDisplay">Calculating...</span>
            <br>
            <small>(Nilai mendekati 1 menunjukkan clustering yang baik; mendekati -1 menunjukkan penugasan yang salah)</small>
            <br>
            <span id="clusteringConclusion" style="font-weight: bold;"></span>
        </p>

        <h2>Detail Clustering</h2>
        <?php if (!empty($clusteredPoints)): ?>
            <h3>Centroid Akhir (Dinormalisasi):</h3>
            <pre><?php echo htmlspecialchars(json_encode($centroids, JSON_PRETTY_PRINT)); ?></pre>

            <h3>Penugasan Cluster untuk Semua Data:</h3>
            <pre><?php
                foreach ($clusteredPoints as $idx => $point) {
                    $dataValues = [];
                    if (isset($point['point']) && is_array($point['point'])) {
                         // Ambil semua nilai dimensi dari titik data
                        $dataValues = array_map(function($val) { return round($val, 4); }, $point['point']);
                    }
                   
                    echo "Data ke-" . ($idx + 1) . ": [" . implode(", ", $dataValues) . "] => Cluster " . $point['cluster'] . "\n";
                }
            ?></pre>

        <?php else: ?>
            <p>Tidak ada hasil clustering untuk ditampilkan (kemungkinan ada kesalahan dalam membaca data).</p>
        <?php endif; ?>
    </div>

    <script>
        // Data dari PHP
        const dataPoints = <?php echo $jsonDataPoints; ?>;
        const centroids = <?php echo $jsonCentroids; ?>;
        const clusterCounts = <?php echo $jsonClusterCounts; ?>;
        const silhouetteScore = <?php echo $jsonSilhouetteScore; ?>;
        const iterationClusterCounts = <?php echo $jsonIterationClusterCounts; ?>; // NEW: Ambil data iterasi
        const k = <?php echo $k; ?>;
        const columnNames = <?php echo $jsonColumnNames; ?>;
        const numIndicatorColumns = <?php echo $numIndicatorColumns; ?>;
        const maxIterations = <?php echo $maxIterations; ?>; // NEW: Ambil maxIterations

        // Warna untuk setiap cluster (digunakan juga untuk bar chart)
        const colors = [
            'rgba(255, 99, 132, 0.6)',
            'rgba(54, 162, 235, 0.6)',
            'rgba(255, 206, 86, 0.6)',
            'rgba(75, 192, 192, 0.6)',
            'rgba(153, 102, 255, 0.6)',
            'rgba(255, 159, 64, 0.6)',
            'rgba(199, 199, 199, 0.6)',
            'rgba(83, 102, 255, 0.6)'
        ];

        // Warna solid untuk border bar chart
        const borderColors = [
            'rgba(255, 99, 132, 1)',
            'rgba(54, 162, 235, 1)',
            'rgba(255, 206, 86, 1)',
            'rgba(75, 192, 192, 1)',
            'rgba(153, 102, 255, 1)',
            'rgba(255, 159, 64, 1)',
            'rgba(199, 199, 199, 1)',
            'rgba(83, 102, 255, 1)'
        ];


        let kmeansChart;
        let clusterCountBarChart;
        let iterationBarChart; // NEW: Deklarasikan variabel untuk grafik iterasi

        const xAxisSelect = document.getElementById('xAxisSelect');
        const yAxisSelect = document.getElementById('yAxisSelect');
        const silhouetteScoreDisplay = document.getElementById('silhouetteScoreDisplay');
        const clusteringConclusion = document.getElementById('clusteringConclusion');

        // Isi dropdown dengan nama kolom
        columnNames.forEach((name, index) => {
            const optionX = document.createElement('option');
            optionX.value = index;
            optionX.textContent = name;
            xAxisSelect.appendChild(optionX);

            const optionY = document.createElement('option');
            optionY.value = index;
            optionY.textContent = name;
            yAxisSelect.appendChild(optionY);
        });

        // Set default pilihan (misal: Kolom A untuk X, Kolom B untuk Y)
        xAxisSelect.value = 0;
        yAxisSelect.value = 1;

        // Fungsi untuk memperbarui grafik scatter
        function updateScatterChart() {
            const xAxisIndex = parseInt(xAxisSelect.value);
            const yAxisIndex = parseInt(yAxisSelect.value);

            const datasets = [];
            for (let i = 0; i < k; i++) {
                datasets.push({
                    label: `Cluster ${i}`,
                    data: [],
                    backgroundColor: colors[i % colors.length],
                    borderColor: colors[i % colors.length].replace('0.6', '1'),
                    borderWidth: 1,
                    pointRadius: 5,
                    pointHoverRadius: 7
                });
            }

            dataPoints.forEach(item => {
                const point = item.point;
                if (item.cluster !== null && datasets[item.cluster]) {
                    if (point[xAxisIndex] !== undefined && point[yAxisIndex] !== undefined) {
                        datasets[item.cluster].data.push({
                            x: point[xAxisIndex],
                            y: point[yAxisIndex]
                        });
                    }
                }
            });

            const centroidChartData = centroids.map(c => ({
                x: c[xAxisIndex] !== undefined ? c[xAxisIndex] : 0,
                y: c[yAxisIndex] !== undefined ? c[yAxisIndex] : 0
            }));
            datasets.push({
                label: 'Centroids',
                data: centroidChartData,
                backgroundColor: 'black',
                borderColor: 'black',
                pointRadius: 8,
                pointHoverRadius: 10,
                pointStyle: 'crossRot',
                borderWidth: 2
            });

            const chartConfig = {
                type: 'scatter',
                data: {
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            type: 'linear',
                            position: 'bottom',
                            title: {
                                display: true,
                                text: columnNames[xAxisIndex] + ' (Normalisasi 0-1)'
                            },
                            min: 0,
                            max: 1
                        },
                        y: {
                            type: 'linear',
                            position: 'left',
                            title: {
                                display: true,
                                text: columnNames[yAxisIndex] + ' (Normalisasi 0-1)'
                            },
                            min: 0,
                            max: 1
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
                                        label += `(${context.parsed.x.toFixed(4)}, ${context.parsed.y.toFixed(4)})`;
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            };

            if (kmeansChart) {
                kmeansChart.destroy();
            }
            const ctx = document.getElementById('kmeansChart').getContext('2d');
            kmeansChart = new Chart(ctx, chartConfig);
        }

        // Fungsi untuk membuat/memperbarui grafik bar jumlah anggota cluster (Akhir)
        function createClusterCountBarChart() {
            const labels = [];
            for (let i = 0; i < k; i++) {
                labels.push(`Cluster ${i}`);
            }

            const barConfig = {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Jumlah Anggota',
                        data: clusterCounts,
                        backgroundColor: colors.slice(0, k),
                        borderColor: borderColors.slice(0, k),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Jumlah Data Point'
                            },
                            ticks: {
                                precision: 0
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Cluster'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `${context.dataset.label}: ${context.parsed.y}`;
                                }
                            }
                        },
                        legend: {
                            display: false
                        }
                    }
                }
            };

            if (clusterCountBarChart) {
                clusterCountBarChart.destroy();
            }
            const ctxBar = document.getElementById('clusterCountChart').getContext('2d');
            clusterCountBarChart = new Chart(ctxBar, barConfig);
        }

        // NEW FUNCTION: Untuk membuat/memperbarui grafik bar jumlah anggota cluster per iterasi
        function createIterationClusterBarChart() {
            const labels = [];
            for (let i = 0; i < k; i++) {
                labels.push(`Cluster ${i}`);
            }

            const datasets = [];
            // Iterasi melalui setiap iterasi yang disimpan
            // Gunakan Object.values(iterationClusterCounts) untuk mendapatkan array dari objek-objek hitungan cluster
            // atau langsung iterationClusterCounts karena PHP mengencode array numerik sebagai array JS
            for (let i = 0; i < maxIterations; i++) {
                if (iterationClusterCounts[i]) { // Pastikan data untuk iterasi ini ada
                    datasets.push({
                        label: `Iterasi ${i + 1}`,
                        data: Object.values(iterationClusterCounts[i]), // Ambil nilai hitungan cluster
                        backgroundColor: colors[i % colors.length].replace('0.6', '0.7'), // Warna sedikit berbeda
                        borderColor: borderColors[i % borderColors.length],
                        borderWidth: 1,
                        // Untuk membuat bar yang dikelompokkan
                        barPercentage: 0.9,
                        categoryPercentage: 0.8
                    });
                }
            }


            const barConfig = {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Jumlah Data Point'
                            },
                            ticks: {
                                precision: 0
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Cluster'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            mode: 'index', // Tampilkan tooltip untuk semua bar pada label yang sama
                            intersect: false, // Jangan mengharuskan mouse berada di atas bar
                            callbacks: {
                                label: function(context) {
                                    return `${context.dataset.label}: ${context.parsed.y}`;
                                }
                            }
                        },
                        legend: {
                            display: true, // Tampilkan legend untuk setiap iterasi
                            position: 'top'
                        }
                    }
                }
            };

            if (iterationBarChart) {
                iterationBarChart.destroy();
            }
            const ctxIteration = document.getElementById('iterationClusterChart').getContext('2d');
            iterationBarChart = new Chart(ctxIteration, barConfig);
        }


        // Tampilkan Silhouette Score dan kesimpulan
        silhouetteScoreDisplay.textContent = silhouetteScore;

        let conclusionText = "";
        let conclusionColor = "";

        // Logika untuk menentukan kesimpulan berdasarkan Silhouette Score
        if (silhouetteScore >= 0.7) {
            conclusionText = "Sangat Baik: Struktur clustering sangat kuat dan terpisah dengan jelas.";
            conclusionColor = "#28a745"; // Hijau
        } else if (silhouetteScore >= 0.5) {
            conclusionText = "Baik: Struktur clustering cukup kuat dan terpisah dengan baik.";
            conclusionColor = "#17a2b8"; // Biru kehijauan
        } else if (silhouetteScore >= 0.25) {
            conclusionText = "Cukup: Struktur clustering ada, tetapi mungkin ada tumpang tindih atau kurang optimal.";
            conclusionColor = "#ffc107"; // Kuning
        } else if (silhouetteScore >= 0) {
            conclusionText = "Buruk: Struktur clustering lemah, mungkin ada tumpang tindih signifikan atau clustering tidak sesuai.";
            conclusionColor = "#dc3545"; // Merah
        } else { // silhouetteScore < 0
            conclusionText = "Sangat Buruk: Data mungkin salah dikelompokkan, atau jumlah cluster (K) terlalu banyak.";
            conclusionColor = "#6c757d"; // Abu-abu
        }
        clusteringConclusion.textContent = `Kesimpulan: ${conclusionText}`;
        clusteringConclusion.style.color = conclusionColor;


        // Event listener untuk perubahan dropdown
        xAxisSelect.addEventListener('change', updateScatterChart);
        yAxisSelect.addEventListener('change', updateScatterChart);

        // Panggil semua fungsi chart saat halaman dimuat
        document.addEventListener('DOMContentLoaded', () => {
            updateScatterChart();
            createClusterCountBarChart();
            createIterationClusterBarChart(); // Panggil fungsi grafik iterasi
        });
    </script>
</body>
</html>
<?php

// Pastikan autoload Composer dimuat
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

class KMeans {
    private $data;
    private $k;
    private $centroids;
    private $clusters;
    private $maxIterations;
    private $dataAssignments;

    public function __construct(array $data, int $k, int $maxIterations = 100) {
        if ($k <= 0) {
            throw new InvalidArgumentException("Jumlah cluster (k) harus lebih besar dari 0.");
        }
        if (empty($data)) {
            throw new InvalidArgumentException("Data tidak boleh kosong.");
        }
        // Validasi tambahan: Pastikan semua data memiliki dimensi yang sama
        // Ambil jumlah dimensi dari titik data pertama
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
     * Inisialisasi centroid menggunakan metode Rapid Centroid Estimation (RCE).
     * Pendekatan: pilih centroid pertama secara acak, lalu centroid berikutnya
     * adalah titik data yang paling jauh dari centroid yang sudah terpilih.
     */
    private function initializeCentroidsRCE() {
        // 1. Pilih centroid pertama secara acak dari data
        $firstCentroidIndex = array_rand($this->data);
        $this->centroids[0] = $this->data[$firstCentroidIndex];

        // 2. Pilih centroid berikutnya hingga mencapai k
        for ($i = 1; $i < $this->k; $i++) {
            $maxDistance = -1;
            $nextCentroidIndex = -1;

            // Untuk setiap titik data
            foreach ($this->data as $dataIndex => $point) {
                // Hitung jarak terdekat dari titik ini ke centroid yang sudah ada
                $minDistanceFromExistingCentroids = INF;
                foreach ($this->centroids as $existingCentroid) {
                    $distance = $this->calculateDistance($point, $existingCentroid);
                    if ($distance < $minDistanceFromExistingCentroids) {
                        $minDistanceFromExistingCentroids = $distance;
                    }
                }

                // Pilih titik yang memiliki jarak minimum terbesar ke centroid yang ada
                // Ini berarti kita memilih titik yang paling "jauh" dari semua centroid yang sudah terpilih
                if ($minDistanceFromExistingCentroids > $maxDistance) {
                    $maxDistance = $minDistanceFromExistingCentroids;
                    $nextCentroidIndex = $dataIndex;
                }
            }
            // Tambahkan titik data yang paling jauh sebagai centroid baru
            $this->centroids[$i] = $this->data[$nextCentroidIndex];
        }
    }

    /**
     * Menetapkan setiap titik data ke cluster terdekat berdasarkan centroid saat ini.
     * Mengembalikan true jika ada perubahan penugasan, false jika tidak ada.
     */
    private function assignDataToClusters(): bool {
        $newClusters = array_fill(0, $this->k, []);
        $changed = false; // Flag untuk melacak apakah ada penugasan cluster yang berubah

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

            // Periksa apakah penugasan cluster titik data ini berubah
            if ($this->dataAssignments[$dataIndex] !== $closestCentroidId) {
                $changed = true;
            }

            // Tetapkan data point ke cluster baru
            $newClusters[$closestCentroidId][] = $dataIndex;
            $this->dataAssignments[$dataIndex] = $closestCentroidId; // Update penugasan
        }

        $this->clusters = $newClusters; // Update struktur cluster utama
        return $changed;
    }

    /**
     * Memperbarui posisi centroid berdasarkan rata-rata titik data dalam cluster.
     */
    private function updateCentroids() {
        $newCentroids = [];
        foreach ($this->clusters as $clusterId => $dataIndices) {
            if (empty($dataIndices)) {
                // Jika cluster kosong (tidak ada data yang ditugaskan), biarkan centroidnya sama
                // Ini mencegah 'death cluster' jika k terlalu besar
                $newCentroids[$clusterId] = $this->centroids[$clusterId];
                continue;
            }

            // Asumsi semua data memiliki jumlah dimensi yang sama
            $numDimensions = count($this->data[array_values($dataIndices)[0]]);
            $sumDimensions = array_fill(0, $numDimensions, 0);

            // Jumlahkan nilai untuk setiap dimensi
            foreach ($dataIndices as $dataIndex) {
                foreach ($this->data[$dataIndex] as $dim => $value) {
                    $sumDimensions[$dim] += $value;
                }
            }

            // Hitung rata-rata untuk setiap dimensi
            $newCentroid = [];
            foreach ($sumDimensions as $dimSum) {
                $newCentroid[] = $dimSum / count($dataIndices);
            }
            $newCentroids[$clusterId] = $newCentroid;
        }
        $this->centroids = $newCentroids;
    }

    /**
     * Menjalankan algoritma K-Means.
     *
     * @return array Hasil clustering (cluster_by_index, data_assignments, centroids)
     */
    public function run(): array {
        $this->initializeCentroidsRCE(); // Menggunakan RCE untuk inisialisasi

        // Variabel untuk melacak penugasan data, diinisialisasi di konstruktor
        // Ini perlu untuk memeriksa konvergensi secara lebih akurat

        for ($i = 0; $i < $this->maxIterations; $i++) {
            $hasChanged = $this->assignDataToClusters(); // Tetapkan data ke cluster
            $this->updateCentroids(); // Perbarui centroid

            // Jika tidak ada data yang berpindah cluster, algoritma konvergen
            if (!$hasChanged && $i > 0) { // i > 0 memastikan setidaknya satu iterasi telah berjalan
                break;
            }
        }

        // Mengembalikan hasil clustering
        return [
            'clusters_by_index' => $this->clusters,      // array yang berisi cluster_id => array of data_indices
            'data_assignments' => $this->dataAssignments, // array yang berisi data_index => cluster_id
            'centroids' => $this->centroids              // array yang berisi cluster_id => array of centroid_coordinates
        ];
    }

    /**
     * Mengembalikan data asli dengan tambahan informasi cluster untuk visualisasi.
     * Secara default, mengambil dimensi pertama (indeks 0) sebagai X dan dimensi kedua (indeks 1) sebagai Y.
     * Jika Anda ingin memvisualisasikan dimensi lain, ubah indeks $point[0] dan $point[1] di sini.
     */
    public function getClusteredData(): array {
        $clusteredData = [];
        foreach ($this->data as $dataIndex => $point) {
            // Pastikan ada setidaknya 2 dimensi untuk visualisasi X dan Y
            $x_val = isset($point[0]) ? $point[0] : 0; // Default ke 0 jika tidak ada
            $y_val = isset($point[1]) ? $point[1] : 0; // Default ke 0 jika tidak ada

            $clusteredData[] = [
                'x' => $x_val,
                'y' => $y_val,
                'cluster' => $this->dataAssignments[$dataIndex]
            ];
        }
        return $clusteredData;
    }
}

// --- FUNGSI NORMALISASI ---

/**
 * Normalisasi data menggunakan Min-Max Scaling ke rentang [0, 1].
 * Rumus: X_normalized = (X - X_min) / (X_max - X_min)
 *
 * @param array $data Array data numerik 2D (misal: [[x1_dim1, x1_dim2, ...], [x2_dim1, x2_dim2, ...], ...])
 * @return array Data yang sudah dinormalisasi
 * @throws InvalidArgumentException Jika data tidak valid untuk normalisasi
 */
function normalizeMinMax(array $data): array {
    if (empty($data)) {
        return [];
    }

    // Tentukan jumlah dimensi dari data pertama
    $numDimensions = count($data[0]);

    // Inisialisasi min dan max untuk setiap dimensi
    $minValues = array_fill(0, $numDimensions, INF);
    $maxValues = array_fill(0, $numDimensions, -INF);

    // Cari nilai min dan max untuk setiap dimensi di seluruh dataset
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
                // Jika min == max untuk dimensi ini, semua nilai sama.
                // Set ke 0.5 (tengah rentang 0-1) atau 0/1. 0.5 lebih umum untuk menjaga centroid di tengah.
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

// Variabel untuk menampung data yang akan dikirim ke JavaScript
$jsonDataPoints = "[]";
$jsonCentroids = "[]";
$k = 3; // Jumlah cluster default

try {
    // Memuat spreadsheet
    $spreadsheet = $reader->load($inputFileName);
    $sheet = $spreadsheet->getActiveSheet();
    $excelData = $sheet->toArray(); // Mengubah sheet menjadi array PHP

    $rawData = [];
    $headerSkipped = false;

    // Tentukan jumlah kolom indikator yang ingin Anda ambil dari Excel
    // Contoh: Jika Anda punya 8 kolom indikator (A sampai H), set ini ke 8.
    // Jika Anda punya 3 kolom (A, B, C), set ini ke 3.
    $numIndicatorColumns = 8; // <<< Sesuaikan ini dengan jumlah kolom indikator Anda

    foreach ($excelData as $row) {
        if (!$headerSkipped) {
            $headerSkipped = true; // Lewati baris pertama (header)
            continue;
        }

        $currentRowData = [];
        $isValidRow = true; // Flag untuk memeriksa validitas baris

        // Ambil data dari setiap kolom indikator yang relevan
        for ($i = 0; $i < $numIndicatorColumns; $i++) {
            // Periksa apakah kolom ada dan nilainya numerik
            if (isset($row[$i]) && is_numeric($row[$i])) {
                $currentRowData[] = (float)$row[$i]; // Konversi ke float
            } else {
                // Jika ada kolom yang tidak numerik atau tidak ada, baris ini tidak valid
                $isValidRow = false;
                break; // Keluar dari loop kolom karena baris ini rusak
            }
        }

        // Tambahkan baris data hanya jika valid dan tidak kosong
        if ($isValidRow && !empty($currentRowData) && count($currentRowData) === $numIndicatorColumns) {
            $rawData[] = $currentRowData;
        }
    }

    // Periksa apakah ada data valid yang ditemukan setelah pembacaan
    if (empty($rawData)) {
        throw new InvalidArgumentException("Tidak ada data valid yang ditemukan di file Excel atau file kosong setelah filter kolom.");
    }

    // --- NORMALISASI DATA ---
    $normalizedSampleData = normalizeMinMax($rawData);

    // --- JALANKAN ALGORITMA K-MEANS ---
    $kmeans = new KMeans($normalizedSampleData, $k); // Gunakan data yang sudah dinormalisasi
    $result = $kmeans->run();

    // Dapatkan data cluster untuk visualisasi
    $clusteredPoints = $kmeans->getClusteredData();
    $centroids = $result['centroids'];

    // Encode data ke JSON untuk dikirim ke JavaScript
    $jsonDataPoints = json_encode($clusteredPoints);
    $jsonCentroids = json_encode($centroids);

} catch (InvalidArgumentException $e) {
    echo "<p style='color:red;'>Error Argumen: " . $e->getMessage() . "</p>\n";
} catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
    echo "<p style='color:red;'>Error Membaca File Excel: Pastikan file '$inputFileName' ada dan valid, serta ekstensi 'zip' PHP diaktifkan. Detail: " . $e->getMessage() . "</p>\n";
} catch (Exception $e) {
    echo "<p style='color:red;'>Terjadi Kesalahan Tak Terduga: " . $e->getMessage() . "</p>\n";
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>K-Means Clustering Visualization (Normalized Multi-Dim Data)</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: sans-serif; display: flex; flex-direction: column; align-items: center; margin: 20px; background-color: #f4f4f4; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); width: 100%; max-width: 900px; }
        #kmeansChart { max-width: 100%; max-height: 550px; margin-top: 20px; }
        h1, h2 { text-align: center; color: #333; }
        p { text-align: center; color: #555; }
        pre { background-color: #eee; padding: 10px; border-radius: 5px; overflow-x: auto; }
        .info { margin-top: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background-color: #f9f9f9; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Visualisasi K-Means Clustering</h1>
        <p>Data bersumber dari file Excel (<code>data_kmeans.xlsx</code>) yang telah dinormalisasi menggunakan Min-Max Scaling.</p>
        <p class="info">
            <strong>Catatan Penting:</strong> Grafik ini hanya menampilkan **Dimensi 1 (Kolom A)** dan **Dimensi 2 (Kolom B)** dari data yang sudah dinormalisasi.<br>
            Meskipun algoritma K-Means memproses semua <?php echo isset($numIndicatorColumns) ? $numIndicatorColumns : 'n'; ?> dimensi, visualisasi 2D hanya dapat menampilkan dua.<br>
            Untuk memvisualisasikan kombinasi dimensi lain, Anda bisa mengubah indeks di fungsi <code>getClusteredData()</code> pada kode PHP (misal: <code>$point[2], $point[3]</code> untuk Kolom C dan D).<br>
            Untuk visualisasi data dimensi tinggi yang lebih canggih, pertimbangkan menggunakan teknik reduksi dimensi seperti **Principal Component Analysis (PCA)**.
        </p>
        <canvas id="kmeansChart"></canvas>

        <h2>Detail Clustering</h2>
        <?php if (!empty($clusteredPoints)): ?>
            <h3>Centroid Akhir (Dinormalisasi):</h3>
            <pre><?php echo htmlspecialchars(json_encode($centroids, JSON_PRETTY_PRINT)); ?></pre>

            <h3>Contoh Penugasan Data (Beberapa data pertama):</h3>
            <pre><?php
                $displayCount = 10; // Jumlah data yang akan ditampilkan
                $sampleDisplay = array_slice($clusteredPoints, 0, $displayCount);
                foreach ($sampleDisplay as $idx => $point) {
                    echo "Data ke-" . ($idx + 1) . ": [" . implode(", ", array_map(function($val) { return round($val, 4); }, array_slice($point, 0, 2))) . "] (Normalisasi) => Cluster " . $point['cluster'] . "\n";
                }
                if (count($clusteredPoints) > $displayCount) {
                    echo "[... dan " . (count($clusteredPoints) - $displayCount) . " data lainnya]\n";
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
        const k = <?php echo $k; ?>;

        // Warna untuk setiap cluster (bisa diperluas jika k lebih besar)
        const colors = [
            'rgba(255, 99, 132, 0.6)',  // Merah
            'rgba(54, 162, 235, 0.6)',  // Biru
            'rgba(255, 206, 86, 0.6)',  // Kuning
            'rgba(75, 192, 192, 0.6)',  // Hijau
            'rgba(153, 102, 255, 0.6)', // Ungu
            'rgba(255, 159, 64, 0.6)',  // Oranye
            'rgba(199, 199, 199, 0.6)', // Abu-abu
            'rgba(83, 102, 255, 0.6)'   // Biru muda
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
        // Perhatikan: centroid juga diambil dari dimensi 0 dan 1 untuk visualisasi
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
                            text: 'Dimensi 1 (Normalisasi 0-1)' // Label sumbu X
                        },
                        min: 0, // Batas bawah sumbu X
                        max: 1  // Batas atas sumbu X
                    },
                    y: {
                        type: 'linear',
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Dimensi 2 (Normalisasi 0-1)' // Label sumbu Y
                        },
                        min: 0, // Batas bawah sumbu Y
                        max: 1  // Batas atas sumbu Y
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
                                    // Tampilkan nilai yang sudah dinormalisasi di tooltip
                                    label += `(${context.parsed.x.toFixed(4)}, ${context.parsed.y.toFixed(4)})`;
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
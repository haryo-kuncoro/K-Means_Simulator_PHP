<?php
function loadCSV($filename, &$headers) {
    $data = [];
    if (($handle = fopen($filename, "r")) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ","); // Ambil header
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $data[] = array_map('floatval', $row);
        }
        fclose($handle);
    }
    return $data;
}


function initializeCentroids($data, $k) {
    shuffle($data);
    return array_slice($data, 0, $k);
}

function euclideanDistance($a, $b) {
    $sum = 0;
    for ($i = 0; $i < count($a); $i++) {
        $sum += pow($a[$i] - $b[$i], 2);
    }
    return sqrt($sum);
}

function assignClusters($data, $centroids) {
    $clusters = [];
    foreach ($data as $point) {
        $minDist = PHP_INT_MAX;
        $clusterIndex = 0;
        foreach ($centroids as $i => $centroid) {
            $dist = euclideanDistance($point, $centroid);
            if ($dist < $minDist) {
                $minDist = $dist;
                $clusterIndex = $i;
            }
        }
        $clusters[$clusterIndex][] = $point;
    }
    return $clusters;
}

function computeCentroidRCE($cluster) {
    $transpose = [];
    foreach ($cluster as $row) {
        foreach ($row as $i => $val) {
            $transpose[$i][] = $val;
        }
    }

    $centroid = [];
    foreach ($transpose as $values) {
        sort($values);
        $mid = floor(count($values) / 2);
        $centroid[] = count($values) % 2 === 0
            ? ($values[$mid - 1] + $values[$mid]) / 2
            : $values[$mid];
    }
    return $centroid;
}

function updateCentroidsRCE($clusters) {
    $newCentroids = [];
    foreach ($clusters as $cluster) {
        $newCentroids[] = computeCentroidRCE($cluster);
    }
    return $newCentroids;
}

function kMeansRCE($data, $k, $maxIter = 100) {
    $centroids = initializeCentroids($data, $k);
    for ($i = 0; $i < $maxIter; $i++) {
        $clusters = assignClusters($data, $centroids);
        $newCentroids = updateCentroidsRCE($clusters);
        if ($centroids == $newCentroids) break; // convergence
        $centroids = $newCentroids;
    }
    return [$clusters, $centroids];
}

$headers = [];
$data = loadCSV('data_normalisasi.csv', $headers);
$k = 3; // Jumlah cluster
list($clusters, $centroids) = kMeansRCE($data, $k);

// Siapkan data untuk grafik (gunakan hanya 2 dimensi: X = kolom 0, Y = kolom 1)
$chartData = [];
foreach ($clusters as $clusterIndex => $cluster) {
    $points = [];
    foreach ($cluster as $point) {
        $points[] = ["x" => $point[0], "y" => $point[1]];
    }
    $chartData[] = [
        "label" => "Cluster " . ($clusterIndex + 1),
        "data" => $points,
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Visualisasi K-Means + RCE</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <h2>Hasil Clustering K-Means + RCE</h2>
    <canvas id="clusterChart" width="800" height="500"></canvas>
    <script>
        const xLabel = <?php echo json_encode($headers[0]); ?>;
        const yLabel = <?php echo json_encode($headers[1]); ?>;
    </script>
    <script>
        const ctx = document.getElementById('clusterChart').getContext('2d');

        const colors = ['rgba(255,99,132,0.6)', 'rgba(54,162,235,0.6)', 'rgba(255,206,86,0.6)', 'rgba(75,192,192,0.6)'];
        const borderColors = ['rgba(255,99,132,1)', 'rgba(54,162,235,1)', 'rgba(255,206,86,1)', 'rgba(75,192,192,1)'];

        const chartData = <?php echo json_encode($chartData); ?>;

        chartData.forEach((cluster, index) => {
            cluster.backgroundColor = colors[index % colors.length];
            cluster.borderColor = borderColors[index % borderColors.length];
            cluster.pointRadius = 5;
            cluster.pointHoverRadius = 7;
            cluster.showLine = false;
        });

        new Chart(ctx, {
            type: 'scatter',
            data: {
                datasets: chartData
            },
            options: {
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: xLabel
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: yLabel
                        }
                    }

                }
            }
        });
    </script>
</body>
</html>

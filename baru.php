<?php
// AUTOLOAD COMPOSER
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use Phpml\Clustering\KMeans;
use Phpml\Preprocessing\LabelEncoder;

// ========== 1. BACA EXCEL ==========
$spreadsheet = IOFactory::load('data.xlsx');
$sheet = $spreadsheet->getActiveSheet();
$data = $sheet->toArray(null, true, true, true);
$headers = array_shift($data); // baris pertama sebagai header

// ========== 2. PERSIAPAN DATA ==========
$records = [];
foreach ($data as $row) {
    if (!is_array($row)) continue; // Lewati baris kosong

    $record = [];
    foreach ($headers as $key => $colName) {
        if (trim($colName) !== 'NAMA') {
            $record[$colName] = isset($row[$key]) ? $row[$key] : null;
        }
    }
    $records[] = $record;
}


// ========== 3. LABEL ENCODING ==========
$features = [];
$encoders = [];
foreach (array_keys($records[0]) as $column) {
    $columnValues = array_column($records, $column);
    $encoder = new LabelEncoder();
    $encoder->fit($columnValues);
    $encoders[$column] = $encoder;

    foreach ($records as $i => $record) {
        $tmp = [$record[$column]];                   // ← fix: tidak langsung masukkan array literal
        $encoded = $encoder->transform($tmp);        // ← transform harus dapat array sebagai variabel
        $features[$i][] = $encoded[0];
    }
}

// ========== 4. K-MEANS CLUSTERING ==========
$k = 3;
$kmeans = new KMeans($k);
$clusters = $kmeans->cluster($features);

// ========== 5. GABUNGKAN HASIL ==========
$clusteredData = [];
foreach ($clusters as $clusterId => $group) {
    foreach ($group as $point) {
        $clusteredData[] = [
            'cluster' => $clusterId,
            'features' => $point
        ];
    }
}

// ========== 6. HITUNG RATA-RATA PER CLUSTER ==========
$clusterMeans = [];
foreach ($clusteredData as $entry) {
    foreach ($entry['features'] as $i => $value) {
        $clusterMeans[$i][$entry['cluster']][] = $value;
    }
}

$averageProfile = [];
foreach ($clusterMeans as $i => $clusterValues) {
    $featureName = array_keys($records[0])[$i];
    $row = [$featureName];
    for ($c = 0; $c < $k; $c++) {
        $row[] = isset($clusterValues[$c]) ? array_sum($clusterValues[$c]) / count($clusterValues[$c]) : 0;
    }
    $averageProfile[] = $row;
}
?>

<!-- ========== 7. TAMPILKAN CHART ========== -->
<!DOCTYPE html>
<html>
<head>
    <title>K-Means Clustering Native PHP</title>
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script>
    google.charts.load('current', {'packages':['corechart']});
    google.charts.setOnLoadCallback(drawChart);
    function drawChart() {
        var data = new google.visualization.DataTable();
        data.addColumn('string', 'Fitur');
        <?php for ($i = 0; $i < $k; $i++): ?>
            data.addColumn('number', 'Cluster <?= $i ?>');
        <?php endfor; ?>

        data.addRows([
            <?php foreach ($averageProfile as $row): ?>
                ['<?= $row[0] ?>', <?= implode(", ", array_slice($row, 1)) ?>],
            <?php endforeach; ?>
        ]);

        var options = {
            title: 'Profil Rata-rata Tiap Cluster',
            curveType: 'function',
            legend: { position: 'bottom' },
            hAxis: { title: 'Fitur' },
            vAxis: { title: 'Nilai Rata-rata' }
        };

        var chart = new google.visualization.LineChart(document.getElementById('chart_div'));
        chart.draw(data, options);
    }
    </script>
</head>
<body>
    <h2>Visualisasi Clustering K-Means (Native PHP)</h2>
    <div id="chart_div" style="width: 900px; height: 500px;"></div>
</body>
</html>

<?php

require 'vendor/autoload.php'; // Sesuaikan path jika berbeda

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Nama file CSV yang akan dibaca
$csvFilePath = 'data_normalisasi.csv';

// Nama file Excel yang akan dibuat
$excelFilePath = 'output_data.xlsx';

// Buat objek Spreadsheet baru
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// --- Bagian 1: Baca File CSV dan Tulis ke Spreadsheet ---

// Buka file CSV
if (($handle = fopen($csvFilePath, "r")) !== FALSE) {
    $row = 1;
    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        // Tulis setiap kolom dari baris CSV ke sel-sel di Spreadsheet
        $col = 'A';
        foreach ($data as $cellData) {
            $sheet->setCellValue($col . $row, $cellData);
            $col++;
        }
        $row++;
    }
    fclose($handle);
    echo "Data dari CSV berhasil dibaca dan ditulis ke memori Spreadsheet.\n";
} else {
    die("Tidak dapat membuka file CSV: " . $csvFilePath);
}

// --- Bagian 2: Menyimpan Spreadsheet ke File Excel ---

// Buat objek Writer untuk format XLSX
$writer = new Xlsx($spreadsheet);

try {
    // Simpan file Excel
    $writer->save($excelFilePath);
    echo "Data berhasil disalin ke file Excel: " . $excelFilePath . "\n";
} catch (Exception $e) {
    die("Terjadi kesalahan saat menyimpan file Excel: " . $e->getMessage());
}

?>
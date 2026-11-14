<?php
// 1. Koneksi database phpMyAdmin
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "point_yogyakarta"; // Pastikan nama database ini SAMA dengan yang di phpMyAdmin

// Buat koneksi
$conn = @new mysqli($servername, $username, $password, $dbname);

// Cek jika koneksi gagal
if ($conn->connect_error) {
    die("<h2 style='color:red; font-family:sans-serif; text-align:center; margin-top:50px;'>Koneksi Database Gagal!</h2>
        <p style='font-family:sans-serif; text-align:center;'>Pesan Error: " . $conn->connect_error . "<br><br>
        <i>Pastikan MySQL di XAMPP sudah ON dan nama database '<b>$dbname</b>' sudah benar di phpMyAdmin.</i></p>");
}

// 2. Query data sebaran SD
$sql = "SELECT * FROM sebaran_sd";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>WebGIS Sebaran SD Kota Yogyakarta</title>

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.5.1/dist/leaflet.css">

    <!-- PolylineMeasure CSS -->
    <link rel="stylesheet" href="https://ppete2.github.io/Leaflet.PolylineMeasure/Leaflet.PolylineMeasure.css" />

    <style>
        html, body {
            height: 100%;
            width: 100%;
            margin: 0;
            font-family: sans-serif;
            overflow: hidden;
        }

        #map {
            height: 100%;
            width: 100%;
        }

        /* Panel Tabel Data */
        #tabel_data {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 40%;
            background-color: white;
            z-index: 1000;
            border-top: 2px solid #ccc;
            transition: 0.3s;
            display: flex;
            flex-direction: column;
        }

        #tabel_data.sembunyi {
            transform: translateY(100%);
        }

        .header-tabel {
            background-color: #333;
            color: white;
            padding: 8px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-tabel h4 {
            margin: 0;
        }

        .header-tabel button {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 4px 8px;
            cursor: pointer;
            border-radius: 3px;
        }

        .isi-tabel {
            overflow-y: auto;
            padding: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table, th, td {
            border: 1px solid #ddd;
            padding: 6px 10px;
            font-size: 14px;
        }

        th {
            background-color: #f4f4f4;
            text-align: left;
        }

        /* Tombol buka tabel */
        #btn_tabel {
            position: absolute;
            bottom: 35px;
            right: 15px;
            z-index: 999;
            background: #333;
            color: #fff;
            border: none;
            padding: 8px 12px;
            cursor: pointer;
            border-radius: 4px;
        }
    </style>
</head>
<body>

    <div id="map"></div>

    <button id="btn_tabel" onclick="tampilTabel()">Lihat Data Tabel</button>

    <div id="tabel_data" class="sembunyi">
        <div class="header-tabel">
            <h4>Tabel Data Sebaran SD</h4>
            <button onclick="tampilTabel()">X Tutup</button>
        </div>
        <div class="isi-tabel">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama SD</th>
                        <th>Status</th>
                        <th>Kecamatan</th>
                        <th>Kelurahan</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result && $result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            echo "<tr>
                                    <td>".(isset($row['id']) ? $row['id'] : '-')."</td>
                                    <td>".(isset($row['Nama_SD']) ? $row['Nama_SD'] : '-')."</td>
                                    <td>".(isset($row['Status']) ? $row['Status'] : '-')."</td>
                                    <td>".(isset($row['kecamatan']) ? $row['kecamatan'] : '-')."</td>
                                    <td>".(isset($row['kelurahan']) ? $row['kelurahan'] : '-')."</td>
                                    <td>".(isset($row['latitude']) ? $row['latitude'] : '-')."</td>
                                    <td>".(isset($row['longitude']) ? $row['longitude'] : '-')."</td>
                                </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7'>Data tidak ditemukan di database</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Leaflet JS & Plugin -->
    <script src="https://unpkg.com/leaflet@1.5.1/dist/leaflet.js"></script>
    <script src="https://ppete2.github.io/Leaflet.PolylineMeasure/Leaflet.PolylineMeasure.js"></script>

    <script>
        // Init map Yogyakarta
        var map = L.map('map').setView([-7.773, 110.377], 13);

        // Basemaps
        var basemap1 = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Esri'
        }).addTo(map);

        var basemap2 = L.tileLayer('https://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
            maxZoom: 20,
            subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
            attribution: 'Google'
        });

        // Layer overlay untuk titik SD
        var overlayMaps = {};

        <?php
        if ($result && $result->num_rows > 0) {
            $result->data_seek(0);
            $i = 1;

            while($row = $result->fetch_assoc()) {
                $lat   = isset($row["latitude"]) ? floatval($row["latitude"]) : 0;
                $long  = isset($row["longitude"]) ? floatval($row["longitude"]) : 0;
                $nama  = isset($row["Nama_SD"]) ? addslashes($row["Nama_SD"]) : 'SD';
                $status= isset($row["Status"]) ? addslashes($row["Status"]) : '-';
                $kec   = isset($row["kecamatan"]) ? addslashes($row["kecamatan"]) : '-';
                $kel   = isset($row["kelurahan"]) ? addslashes($row["kelurahan"]) : '-';

                if($lat != 0 && $long != 0) {
                    echo "var marker_$i = L.marker([$lat, $long]).bindPopup('<b>$nama</b><br>Status: $status<br>Kecamatan: $kec<br>Kelurahan: $kel').addTo(map);\n";
                    echo "overlayMaps['$nama'] = marker_$i;\n";
                    $i++;
                }
            }
        }
        if (isset($conn)) { $conn->close(); }
        ?>

        // Basemap & Layer Control
        var baseMaps = {
            "Esri Imagery": basemap1,
            "Google Street": basemap2
        };

        L.control.layers(baseMaps, overlayMaps, {collapsed: false}).addTo(map);

        // Skala di kiri bawah
        L.control.scale({ maxWidth: 150, position: 'bottomleft' }).addTo(map);

        // Tool ukur jarak di kiri atas
        L.control.polylineMeasure({
            position: 'topleft',
            unit: 'metres'
        }).addTo(map);

        // Fungsi buka/tutup tabel
        function tampilTabel() {
            var t = document.getElementById("tabel_data");
            t.classList.toggle("sembunyi");
        }
    </script>
</body>
</html>
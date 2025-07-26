<?php
require_once 'config.php';
require_once 'functions.php';

$conn = db_connect();

// Fetch all drop-off locations from the database
$locations = [];
$sql = "SELECT name, address, latitude, longitude FROM drop_off_locations WHERE latitude IS NOT NULL AND longitude IS NOT NULL";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $locations[] = $row;
    }
}

$conn->close();

$page_title = 'Our Drop-off Locations';
require_once 'templates/header.php';
?>
<style>
    #map {
        height: 70vh;
        width: 100%;
    }
</style>

    <div class="container">
        <h2 class="page-title">Our Drop-off Locations</h2>
        <p style="text-align: center;">Find the VuaToFua drop-off point nearest to you.</p>
        <div id="map"></div>
    </div>

    <!-- IMPORTANT: Replace YOUR_API_KEY with your actual Google Maps API key -->
    <script>
        function initMap() {
            // Centered on Nairobi
            const map = new google.maps.Map(document.getElementById('map'), {
                zoom: 12,
                center: { lat: -1.286389, lng: 36.817223 }
            });

            const locations = <?php echo json_encode($locations); ?>;

            locations.forEach(location => {
                const marker = new google.maps.Marker({
                    position: { lat: parseFloat(location.latitude), lng: parseFloat(location.longitude) },
                    map: map,
                    title: location.name
                });

                const infowindow = new google.maps.InfoWindow({
                    content: `<strong>${location.name}</strong><br>${location.address}`
                });

                marker.addListener('click', () => {
                    infowindow.open(map, marker);
                });
            });
        }
    </script>
    <script async defer src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&callback=initMap"></script>

    <?php require_once 'templates/footer.php'; ?>

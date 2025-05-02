<?php
// tour_app/tour_details.php
$pageTitle = "Tour Details"; // Default title
$loadLeaflet = true; // Flag for header/footer to load Leaflet library

define('DB_HOST', 'localhost'); define('DB_USER', 'root'); define('DB_PASS', ''); define('DB_NAME', 'tour_booking_db');
$tour = null; $errorMsg = null; $availableDates = [];

// --- Fetch Tour Data and Available Dates (Keep this logic the same) ---
if (isset($_GET['id']) && is_numeric($_GET['id']) && $_GET['id'] > 0) { $tourId = (int)$_GET['id']; $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); if ($conn->connect_error) { $errorMsg = "Connection failed: " . $conn->connect_error; } else { $sql = "SELECT id, name, location_city, description, price, latitude, longitude, capacity FROM tours WHERE id = ?"; $stmt = $conn->prepare($sql); if ($stmt) { $stmt->bind_param("i", $tourId); $stmt->execute(); $result = $stmt->get_result(); if ($result && $result->num_rows === 1) { $tour = $result->fetch_assoc(); $pageTitle = htmlspecialchars($tour['name']); $sql_dates = "SELECT tour_date FROM tour_dates WHERE tour_id = ? AND tour_date >= CURDATE() ORDER BY tour_date ASC"; $stmt_dates = $conn->prepare($sql_dates); if ($stmt_dates) { $stmt_dates->bind_param("i", $tourId); $stmt_dates->execute(); $result_dates = $stmt_dates->get_result(); while ($row_date = $result_dates->fetch_assoc()) { $dateObj = date_create($row_date['tour_date']); $availableDates[] = ['value' => $row_date['tour_date'], 'display' => date_format($dateObj, 'D, M j, Y')]; } $stmt_dates->close(); } else { error_log("Error preparing dates query: " . $conn->error); } } elseif ($result && $result->num_rows === 0) { $errorMsg = "Tour not found."; } else { $errorMsg = "Error fetching details: " . $stmt->error; } $stmt->close(); } else { $errorMsg = "Error preparing query: " . $conn->error; } $conn->close(); } } else { $errorMsg = "Invalid or missing Tour ID."; }

// --- Include Header ---
require_once 'includes/header.php'; // Include Header AFTER setting $pageTitle and $loadLeaflet
?>

    <?php // --- Page Content --- ?>
    <div class="content-box details-page">

        <?php if ($errorMsg !== null): ?>
            <p class="error"><?php echo htmlspecialchars($errorMsg); ?></p>
            <p style="text-align: center;"><a href="index.php">Back to Tour List</a></p>
        <?php elseif ($tour !== null): ?>
            <?php // --- Tour Details Section --- ?>
            <div id="tour-details-content">
                <h1><?php echo htmlspecialchars($tour['name']); ?></h1>
                <div class="tour-main-details-layout">
                    <div class="tour-meta">
                        <p><span class="label">Location:</span> <?php echo htmlspecialchars($tour['location_city']); ?></p>
                        <p><span class="label">Price:</span> <span class="price">KES <?php echo htmlspecialchars(number_format($tour['price'], 2)); ?></span></p>
                        <div class="available-dates">
                            <span class="label">Available Dates:</span>
                            <?php if (!empty($availableDates)): ?>
                                <ul><?php foreach($availableDates as $dateInfo): ?><li><?php echo htmlspecialchars($dateInfo['display']); ?></li><?php endforeach; ?></ul>
                                <p><small>(Select date on booking form)</small></p>
                            <?php else: ?><p><i>No upcoming dates scheduled.</i></p><?php endif; ?>
                        </div>
                        <?php if (!empty($availableDates)): ?>
                             <div class="book-button-area"><a href="booking.php?tour_id=<?php echo htmlspecialchars($tour['id']); ?>" class="book-now-button">Request Booking</a></div>
                        <?php else: ?><div class="fully-booked-notice">Booking currently unavailable.</div><?php endif; ?>
                    </div>
                    <div class="tour-description">
                         <h3 class="description-heading">Description</h3>
                         <?php echo nl2br(htmlspecialchars($tour['description'])); ?>
                    </div>
                </div>
            </div>
            <hr>
            <?php // --- Weather Info Container --- ?>
            <div id="weather-info" data-location="<?php echo htmlspecialchars($tour['location_city']); ?>">
                <h3>Weather Information</h3>
                <div class="loader-container"><div class="loader"></div><p>Loading weather...</p></div>
            </div>
            <hr>
            <?php // --- Map Section --- ?>
            <div id="map-info">
                <h3>Location Map</h3>
                <?php if (isset($tour['latitude']) && $tour['latitude'] !== null && isset($tour['longitude']) && $tour['longitude'] !== null): ?>
                    <div id="map"></div>
                <?php else: ?>
                    <p class="error" style="text-align:left; background:none; border:none; color:#6c757d; padding:0;">Map coordinates not available for this location.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="error">Could not display tour details.</p>
        <?php endif; ?>
        <p class="back-link"><a href="index.php">&laquo; Back to Tour List</a></p>
    </div> <?php // End content-box ?>


    <?php // --- Page-Specific JavaScript Block --- ?>
    <?php if ($tour !== null): // Only add script if tour data is loaded ?>
    <script>
        // Helper function first (can be moved to script.js later if needed elsewhere)
        function escapeHTML(str) {
             if (typeof str !== 'string') return str; const div = document.createElement('div'); div.textContent = str; return div.innerHTML;
        }

        // Function to display weather and forecast data inside the weather div
        function displayWeatherAndForecast(weather, targetDiv) {
            const tempUnit = weather.units === 'metric' ? 'C' : 'F'; const speedUnit = weather.units === 'metric' ? 'm/s' : 'mph'; const currentIconUrl = weather.iconCode ? `https://openweathermap.org/img/wn/${weather.iconCode}@2x.png` : null;
            let html = `<h3>Weather in ${escapeHTML(weather.cityName)}</h3>`; html += `<div class="weather-current">`; if (currentIconUrl) { html += `<img class="weather-icon-current" src="${currentIconUrl}" alt="${escapeHTML(weather.description)}">`; } else { html += `<div style="width: 80px; height: 80px; margin-right: 20px; flex-shrink: 0;"></div>`; } html += `<div class="weather-text-details">`; html += `<p><strong>Temperature:</strong> ${escapeHTML(weather.temperature)}&deg;${tempUnit}</p>`; html += `<p><strong>Conditions:</strong> ${escapeHTML(weather.description)}</p>`; html += `<p><strong>Humidity:</strong> ${escapeHTML(weather.humidity)}%</p>`; html += `<p><strong>Wind Speed:</strong> ${escapeHTML(weather.windSpeed)} ${speedUnit}</p>`; html += `</div>`; html += `</div>`; if (weather.forecast && Array.isArray(weather.forecast) && weather.forecast.length > 0) { html += '<h4>Forecast</h4>'; html += '<div class="forecast-container">'; weather.forecast.forEach(dayForecast => { const forecastIconUrl = dayForecast.icon ? `https://openweathermap.org/img/wn/${dayForecast.icon}@2x.png` : null; html += '<div class="forecast-item">'; html += `<strong>${escapeHTML(dayForecast.day)}</strong>`; if (forecastIconUrl) { html += `<img src="${forecastIconUrl}" alt="${escapeHTML(dayForecast.description ?? '')}">`; } else { html += '<div style="height: 50px;"></div>'; } html += `${escapeHTML(dayForecast.temp)}&deg;${tempUnit}`; html += '</div>'; }); html += '</div>'; } else if (weather.forecast_error) { html += `<p class="error">Could not load forecast: ${escapeHTML(weather.forecast_error)}</p>`; } targetDiv.innerHTML = html;
        }

        // Function to display error messages inside the weather div
        function displayWeatherError(message) {
            const weatherInfoDiv = document.getElementById('weather-info');
             if (weatherInfoDiv) { weatherInfoDiv.innerHTML = `<h3>Weather Information</h3><p class="error">${escapeHTML(message)}</p>`; }
        }

        // Run scripts after DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // --- Weather Fetching Logic ---
            const weatherInfoDiv = document.getElementById('weather-info');
            const location = weatherInfoDiv ? weatherInfoDiv.dataset.location : null;
            if (location && weatherInfoDiv) {
                 // Add cache busting timestamp
                 const fetchUrl = `weather_api.php?city=${encodeURIComponent(location)}&timestamp=${Date.now()}`;
                fetch(fetchUrl)
                    .then(response => {
                        if (!response.ok){ return response.json().then(err => {throw new Error(err.message||'HTTP Error ' + response.status)}).catch(()=>{throw new Error('HTTP Error ' + response.status)}); } return response.json();
                    })
                    .then(data => { if (data.success) { displayWeatherAndForecast(data.data, weatherInfoDiv); } else { displayWeatherError(data.message || 'Unknown API error'); } })
                    .catch(error => { console.error('Weather Fetch Error:', error); displayWeatherError(`Failed to load weather: ${error.message}`); });
            } else { console.error('Missing location/weather div'); if(weatherInfoDiv && !location) displayWeatherError('Location missing.');}

            // --- Map Initialization Logic ---
            const mapDiv = document.getElementById('map');
            // Get coordinates from PHP (ensure $tour is available in the scope above)
             const lat = <?php echo (isset($tour['latitude']) && is_numeric($tour['latitude'])) ? json_encode((float)$tour['latitude']) : 'null'; ?>;
             const lon = <?php echo (isset($tour['longitude']) && is_numeric($tour['longitude'])) ? json_encode((float)$tour['longitude']) : 'null'; ?>;
             const tourName = <?php echo json_encode($tour['name']); ?>;

             if (typeof L !== 'undefined' && mapDiv && typeof lat === 'number' && typeof lon === 'number') {
                 try {
                     const map = L.map('map').setView([lat, lon], 13);
                     L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors' }).addTo(map);
                     L.marker([lat, lon]).addTo(map).bindPopup(`<b>${escapeHTML(tourName)}</b>`).openPopup();
                 } catch (e) { console.error('Map Error:', e); mapDiv.innerHTML = `<p class='error'>Could not load map.</p>`; }
             } else if (mapDiv && (typeof lat !== 'number' || typeof lon !== 'number')) { console.warn('Map coordinates invalid/missing.'); }
               else if (typeof L === 'undefined' && mapDiv && typeof lat === 'number' && typeof lon === 'number'){ console.error('Leaflet (L) not loaded before map init.'); mapDiv.innerHTML = `<p class='error'>Map library failed.</p>`; }

        }); // End DOMContentLoaded
    </script>
    <?php endif; // End check for valid tour ?>

<?php
require_once 'includes/footer.php'; // Include the footer
?>
<?php
// tour_app/weather_api.php

// --- Configuration ---
// IMPORTANT: Replace with your actual OpenWeatherMap API key!
$apiKey = "63c278f1bae9cb5809aff394c6c305bc"; // Use your key
$units = "metric"; // Use "metric" for Celsius, "imperial" for Fahrenheit

// --- Initialize variables ---
$currentWeatherData = null; // To store successful current weather data
$forecastData = null;       // To store processed daily forecast data
$errorMsg = null;           // To store any error messages encountered
$cityName = '';             // To store the requested city name
$responseData = [];         // Array to hold the final JSON response

// --- Set JSON Header ---
// We always output JSON from this script
header('Content-Type: application/json');

// --- Get City from Request Parameter ---
if (isset($_GET['city'])) {
    $cityName = trim($_GET['city']);

    if (empty($cityName)) {
        $errorMsg = "City parameter cannot be empty.";
    }
} else {
    $errorMsg = "City parameter is required.";
}

// --- Proceed only if city name is provided ---
if ($errorMsg === null) {

    // --- Construct the API URLs ---
    $currentWeatherUrl = sprintf(
        "https://api.openweathermap.org/data/2.5/weather?q=%s&appid=%s&units=%s",
        urlencode($cityName), $apiKey, $units
    );
    $forecastUrl = sprintf(
        "https://api.openweathermap.org/data/2.5/forecast?q=%s&appid=%s&units=%s",
        urlencode($cityName), $apiKey, $units
    );

    // --- Use cURL for Current Weather ---
    $ch_current = curl_init();
    curl_setopt($ch_current, CURLOPT_URL, $currentWeatherUrl);
    curl_setopt($ch_current, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_current, CURLOPT_TIMEOUT, 10); // Set timeout
    $currentResponse = curl_exec($ch_current);
    $currentHttpCode = curl_getinfo($ch_current, CURLINFO_HTTP_CODE);
    $currentCurlError = curl_errno($ch_current) ? 'cURL error (Current): ' . curl_error($ch_current) : null;
    curl_close($ch_current);

    // --- Use cURL for Forecast ---
    $ch_forecast = curl_init();
    curl_setopt($ch_forecast, CURLOPT_URL, $forecastUrl);
    curl_setopt($ch_forecast, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_forecast, CURLOPT_TIMEOUT, 10); // Set timeout
    $forecastResponse = curl_exec($ch_forecast);
    $forecastHttpCode = curl_getinfo($ch_forecast, CURLINFO_HTTP_CODE);
    $forecastCurlError = curl_errno($ch_forecast) ? 'cURL error (Forecast): ' . curl_error($ch_forecast) : null;
    curl_close($ch_forecast);

    // --- Process Current Weather Response ---
    if ($currentCurlError) {
        $errorMsg = $currentCurlError; // Prioritize curl errors
    } elseif ($currentHttpCode === 200) {
        $currentData = json_decode($currentResponse, true);
        if (isset($currentData['cod']) && $currentData['cod'] != '200') {
            $errorMsg = "API Error (Current): " . ($currentData['message'] ?? 'Unknown error');
        } elseif ($currentData === null) {
            $errorMsg = "Error decoding current weather JSON response.";
        } else {
            $currentWeatherData = $currentData; // Store successful current weather data
            $cityName = $currentWeatherData['name'] ?? $cityName; // Update city name from response if available
        }
    } else {
        $errorMsg = "Failed to fetch current weather (HTTP Code: " . $currentHttpCode . ")";
        // Try to get more specific error from API response body
        $apiErrorDetails = json_decode($currentResponse, true);
        if (isset($apiErrorDetails['message'])) {
            $errorMsg .= " - " . $apiErrorDetails['message'];
        }
    }

    // --- Process Forecast Response ---
    // We attempt this even if current weather failed, maybe forecast works, but append errors
    $forecastError = null; // Temporary variable for forecast-specific errors
    if ($forecastCurlError) {
        $forecastError = $forecastCurlError;
    } elseif ($forecastHttpCode === 200) {
        $forecastRawData = json_decode($forecastResponse, true);
        if (isset($forecastRawData['cod']) && $forecastRawData['cod'] != '200') {
            $forecastError = "API Error (Forecast): " . ($forecastRawData['message'] ?? 'Unknown error');
        } elseif ($forecastRawData === null) {
            $forecastError = "Error decoding forecast JSON response.";
        } else {
            // --- Process Raw Forecast Data into Daily Summaries ---
            $dailyForecasts = [];
            $processedDates = []; // Keep track of dates we've added
            if (isset($forecastRawData['list'])) {
                foreach ($forecastRawData['list'] as $forecastItem) {
                    $timestamp = $forecastItem['dt'];
                    $date = date('Y-m-d', $timestamp);
                    $hour = date('H', $timestamp);

                    // Only add one forecast per day, prefer midday (12 or 15)
                    if (!in_array($date, $processedDates) && ($hour >= '12' && $hour <= '15')) {
                        $dailyForecasts[] = [
                            'day' => date('l', $timestamp), // e.g., 'Monday'
                            'date' => $date,
                            'temp' => $forecastItem['main']['temp'] ?? 'N/A',
                            'icon' => $forecastItem['weather'][0]['icon'] ?? null,
                            'description' => $forecastItem['weather'][0]['description'] ?? 'N/A'
                        ];
                        $processedDates[] = $date; // Mark date as processed
                    }
                    // Limit to 5 days max
                    if (count($dailyForecasts) >= 5) break;
                }
                // If we didn't get enough midday forecasts, try filling with first available per day
                if (count($dailyForecasts) < 5) {
                     $processedDates = array_column($dailyForecasts, 'date'); // Update processed dates
                     foreach ($forecastRawData['list'] as $forecastItem) {
                          $timestamp = $forecastItem['dt']; $date = date('Y-m-d', $timestamp);
                          if (!in_array($date, $processedDates)) {
                               $dailyForecasts[] = [
                                    'day' => date('l', $timestamp), 'date' => $date,
                                    'temp' => $forecastItem['main']['temp'] ?? 'N/A',
                                    'icon' => $forecastItem['weather'][0]['icon'] ?? null,
                                    'description' => $forecastItem['weather'][0]['description'] ?? 'N/A'
                               ];
                               $processedDates[] = $date;
                          }
                          if (count($dailyForecasts) >= 5) break;
                     }
                }
                $forecastData = $dailyForecasts; // Store processed forecast data
            }
        }
    } else { // Non-200 HTTP code for forecast
        $forecastError = "Failed to fetch forecast (HTTP Code: " . $forecastHttpCode . ")";
        $apiErrorDetails = json_decode($forecastResponse, true);
        if (isset($apiErrorDetails['message'])) {
            $forecastError .= " - " . $apiErrorDetails['message'];
        }
    }

    // Append forecast error to main error message if it exists
    if ($forecastError !== null) {
        if ($errorMsg === null) {
            $errorMsg = $forecastError; // If no previous error, this becomes the main error
        } else {
            $errorMsg .= "; " . $forecastError; // Append if there was already an error
        }
    }
} // End of city check block

// --- Prepare Final JSON Response ---
if ($errorMsg !== null && $currentWeatherData === null) {
    // If there's an error and NO current data, send error status
    $responseData = ['success' => false, 'message' => $errorMsg];
} elseif ($currentWeatherData !== null) {
    // If we have current weather data (even if forecast failed), report success
    $responseData = [
        'success' => true,
        'data' => [
            'cityName' => $currentWeatherData['name'] ?? $cityName,
            'temperature' => $currentWeatherData['main']['temp'] ?? 'N/A',
            'description' => isset($currentWeatherData['weather'][0]['description']) ? ucfirst($currentWeatherData['weather'][0]['description']) : 'N/A',
            'humidity' => $currentWeatherData['main']['humidity'] ?? 'N/A',
            'windSpeed' => $currentWeatherData['wind']['speed'] ?? 'N/A',
            'iconCode' => $currentWeatherData['weather'][0]['icon'] ?? null,
            'units' => $units,
            'forecast' => $forecastData, // Will be null if forecast failed
            // Include forecast error message only if forecast specifically failed
            'forecast_error' => ($forecastData === null && $forecastError !== null) ? $forecastError : null
        ]
    ];
} else {
     // Fallback if no error but also no data (e.g., API returned unexpected empty success)
     $responseData = ['success' => false, 'message' => $errorMsg ?? 'Unknown error occurred or no data found.'];
}

// --- Output JSON and Exit ---
echo json_encode($responseData);
exit; // Stop script execution

?>
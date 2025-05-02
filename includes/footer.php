<?php // tour_app/includes/footer.php ?>

    <?php // Common footer content could go here ?>
    <footer style="text-align: center; margin-top: 40px; padding: 20px; font-size: 0.9em; color: #6c757d;">
        <p>&copy; <?php echo date('Y'); ?> Tour Booking App. All rights reserved.</p>
    </footer>

    <?php // Conditionally include Leaflet JS (only needed on tour_details.php) ?>
    <?php if (isset($loadLeaflet) && $loadLeaflet === true): ?>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <?php endif; ?>

    <?php // Link main JavaScript file ?>
    <script src="script.js" defer></script>

    <?php // Conditionally include page-specific inline scripts if needed ?>
     <?php if (isset($inlineScripts) && !empty($inlineScripts)): ?>
        <script><?php echo $inlineScripts; ?></script>
     <?php endif; ?>

</body>
</html>
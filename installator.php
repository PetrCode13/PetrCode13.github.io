<?php
// 1. SETTINGS: Direct URL address to your ZIP file
$vzdalenaUrl = "https://updates.peceme.org/install/scr1400.zip";

// Other configurations
set_time_limit(300);
ini_set('memory_limit', '256M');
$docasnyZip = __DIR__ . "/docasny_archiv.zip";
$cilovyRoot = __DIR__;

// Helper function to output a visual step into the HTML structure
function vypisKrok($text, $stav = 'loading') {
    $ikona = '⏳';
    $trida = 'krok-loading';
    if ($stav === 'success') { $ikona = '✅'; $trida = 'krok-success'; }
    if ($stav === 'error') { $ikona = '❌'; $trida = 'krok-error'; }
    
    echo "<div class='krok $trida'><span class='ikona'>$ikona</span> <span class='text'>$text</span></div>";
    flush(); // Immediate output to the browser
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web Installer Pro</title>
    <style>
        :root {
            --bg-color: #f4f6f9;
            --card-bg: #ffffff;
            --primary: #4f46e5;
            --text-main: #1f2937;
            --text-muted: #4b5563;
            --success: #10b981;
            --error: #ef4444;
            --border: #e5e7eb;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        /* Main Card Container */
        .instalator-karta {
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 480px;
            padding: 32px;
            border: 1px solid var(--border);
            animation: fadeIn 0.4s ease-out;
        }

        /* Card Header */
        .karta-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .karta-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        .karta-header p {
            font-size: 14px;
            color: var(--text-muted);
        }

        /* Progress Bar Container */
        .progress-container {
            width: 100%;
            height: 6px;
            background-color: var(--border);
            border-radius: 3px;
            margin-bottom: 24px;
            overflow: hidden;
            position: relative;
        }

        /* Animated Progress Bar (3 seconds) */
        .progress-bar {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--primary), #818cf8);
            border-radius: 3px;
            animation: progressRun 3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        /* Steps Wrapper */
        .kroky-wrapper {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 24px;
        }

        .krok {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 500;
            border: 1px solid var(--border);
            transition: all 0.2s ease;
        }

        .krok-loading {
            background-color: #f9fafb;
            color: var(--text-main);
        }

        .krok-success {
            background-color: #ecfdf5;
            border-color: #a7f3d0;
            color: #065f46;
        }

        .krok-error {
            background-color: #fef2f2;
            border-color: #fca5a5;
            color: #991b1b;
        }

        .ikona {
            font-size: 18px;
            flex-shrink: 0;
        }

        /* Final Result Box */
        .vysledek-box {
            text-align: center;
            padding: 20px;
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            margin-top: 16px;
            animation: scaleIn 0.3s ease-out;
        }

        .vysledek-box h2 {
            color: #1e40af;
            font-size: 18px;
            margin-bottom: 8px;
        }

        .vysledek-box p {
            font-size: 14px;
            color: #1e3a8a;
            line-height: 1.5;
        }

        .vysledek-box code {
            background: #dbeafe;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
            font-weight: bold;
        }

        /* Keyframes Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.96); }
            to { opacity: 1; transform: scale(1); }
        }

        @keyframes progressRun {
            0% { width: 0%; }
            20% { width: 15%; }
            40% { width: 55%; }
            70% { width: 85%; }
            100% { width: 100%; }
        }
    </style>
</head>
<body>

<div class="instalator-karta">
    <div class="karta-header">
        <h1>Automatic Installation</h1>
        <p>Downloading and extracting the package to root</p>
    </div>

    <!-- Progress Bar -->
    <div class="progress-container">
        <div class="progress-bar"></div>
    </div>

    <div class="kroky-wrapper">
    <?php
    // --- PROCESS START ---

    // STEP 1: Downloading the file
    vypisKrok("Downloading ZIP file from the remote server...");

    if (function_exists('curl_init')) {
        $fp = fopen($docasnyZip, 'w+');
        $ch = curl_init($vzdalenaUrl);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        fclose($fp);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            @unlink($docasnyZip);
            vypisKrok("Error: Remote server returned HTTP code $httpCode.", "error");
            echo "</div></div></body></html>";
            exit;
        }
    } else {
        $data = @file_get_contents($vzdalenaUrl);
        if ($data === false) {
            vypisKrok("Error: Failed to download file (hosting blocks file_get_contents).", "error");
            echo "</div></div></body></html>";
            exit;
        }
        file_put_contents($docasnyZip, $data);
    }

    if (!file_exists($docasnyZip) || filesize($docasnyZip) === 0) {
        vypisKrok("Error: Downloaded file is empty or could not be saved.", "error");
        echo "</div></div></body></html>";
        exit;
    }

    vypisKrok("ZIP file downloaded successfully.", "success");


    // STEP 2: Extracting the file
    vypisKrok("Extracting archive directly to the root directory...");

    $zip = new ZipArchive;
    if ($zip->open($docasnyZip) === TRUE) {
        if ($zip->extractTo($cilovyRoot)) {
            $zip->close();
            @unlink($docasnyZip);
            
            vypisKrok("Archive extracted successfully.", "success");
            
            // Final success view inside the card
            echo "</div>"; // Close .kroky-wrapper
            echo "<div class='vysledek-box'>";
            echo "<h2>🎉 Success!</h2>";
            echo "<p>All files are deployed.<br>Please <b>delete</b> the <code>installator.php</code> file from your FTP immediately!</p>";
            echo "</div>";
        } else {
            $zip->close();
            @unlink($docasnyZip);
            vypisKrok("Error occurred during file extraction.", "error");
            echo "</div>";
        }
    } else {
        @unlink($docasnyZip);
        vypisKrok("Error: Cannot open file as ZIP (ZipArchive extension missing?).", "error");
        echo "</div>";
    }
    ?>
</div>

</body>
</html>

